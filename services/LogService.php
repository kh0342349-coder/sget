<?php
/**
 * services/LogService.php
 * -----------------------------------------------------------------------------
 * MÓDULO: AUDITORÍA
 * -----------------------------------------------------------------------------
 * Toda la consulta de `sget_logs_auditoria` vive aquí. La página
 * (Admin/logs.php) solo dibuja: si mañana se cambia el esquema de auditoría,
 * se toca este archivo y nada más.
 *
 * Write path: helpers/Logger.php (registra).
 * Read path:  este servicio (consulta, filtra, pagina, agrega y exporta).
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class LogService
{
    /* ================================================================== */
    /* Definición de filtros                                               */
    /* ================================================================== */

    /**
     * Normaliza los filtros que llegan por GET.
     * @return array{accion:string, usuario:int, desde:string, hasta:string, q:string}
     */
    public static function normalizarFiltros(array $entrada): array
    {
        $desde = trim((string)($entrada['desde'] ?? ''));
        $hasta = trim((string)($entrada['hasta'] ?? ''));

        // Se aceptan fechas sueltas o un rango "2026-09-01,2026-09-30"
        if (str_contains($desde, ',')) {
            [$desde, $hasta] = array_pad(explode(',', $desde, 2), 2, '');
        }

        $seguro = static function (string $v): string {
            $ts = strtotime($v);
            return $ts === false ? '' : date(Fecha::FMT_FECHA, $ts);
        };

        return [
            'accion'  => mb_substr(trim((string)($entrada['accion'] ?? '')), 0, 50),
            'usuario' => max(0, (int)($entrada['usuario'] ?? 0)),
            'desde'   => $seguro($desde),
            'hasta'   => $seguro($hasta),
            'q'       => mb_substr(trim((string)($entrada['q'] ?? '')), 0, 100),
        ];
    }

    /** Traduce los filtros a un fragmento SQL + parámetros. */
    private static function where(array $f): array
    {
        $w = [];
        $p = [];

        if ($f['accion'] !== '') { $w[] = 'l.accion = ?';            $p[] = $f['accion']; }
        if ($f['usuario'] > 0)   { $w[] = 'l.id_usu = ?';            $p[] = $f['usuario']; }
        if ($f['desde'] !== '')   { $w[] = 'l.fec_log >= ?';         $p[] = $f['desde'] . ' 00:00:00'; }
        if ($f['hasta'] !== '')   { $w[] = 'l.fec_log <= ?';         $p[] = $f['hasta'] . ' 23:59:59'; }

        if ($f['q'] !== '') {
            $w[] = '(l.descripcion LIKE ? OR l.nom_usu_log LIKE ? OR l.accion LIKE ? OR l.ip_origen LIKE ? OR l.user_agent LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($p, $like, $like, $like, $like, $like);
        }

        return [($w ? 'WHERE ' : '') . implode(' AND ', $w), $p];
    }

    /* ================================================================== */
    /* Consulta principal                                                  */
    /* ================================================================== */

    public static function listar(array $filtros, int $pagina = 1, int $porPagina = 30): array
    {
        [$sqlWhere, $params] = self::where($filtros);

        $porPagina = max(5, min(200, $porPagina));
        $total     = (int) Database::scalar("SELECT COUNT(*) FROM sget_logs_auditoria l {$sqlWhere}", $params);
        $paginas   = max(1, (int)ceil($total / $porPagina));
        $pagina    = max(1, min($pagina, $paginas));
        $offset    = ($pagina - 1) * $porPagina;

        $filas = Database::all(
            "SELECT l.*, u.num_doc_usu
               FROM sget_logs_auditoria l
               LEFT JOIN usuario u ON u.id_usu = l.id_usu
               {$sqlWhere}
              ORDER BY l.id_log DESC
              LIMIT {$porPagina} OFFSET {$offset}",
            $params
        );

        return [
            'filas'     => $filas,
            'total'     => $total,
            'pagina'    => $pagina,
            'paginas'   => $paginas,
            'porPagina' => $porPagina,
        ];
    }

    /** Todos los registros que cumplen los filtros (para exportar). */
    public static function todos(array $filtros, int $tope = 50000): array
    {
        [$sqlWhere, $params] = self::where($filtros);

        return Database::all(
            "SELECT l.*, u.num_doc_usu
               FROM sget_logs_auditoria l
               LEFT JOIN usuario u ON u.id_usu = l.id_usu
               {$sqlWhere}
              ORDER BY l.fec_log DESC, l.id_log DESC
              LIMIT " . (int)$tope,
            $params
        );
    }

    public static function porId(int $id): ?array
    {
        return Database::one(
            "SELECT l.*, u.num_doc_usu
               FROM sget_logs_auditoria l
               LEFT JOIN usuario u ON u.id_usu = l.id_usu
              WHERE l.id_log = ?",
            [$id]
        );
    }

    /* ================================================================== */
    /* Catálogos y estadísticas                                            */
    /* ================================================================== */

    /** Tipos de acción con su número de eventos. */
    public static function acciones(): array
    {
        return Database::all(
            "SELECT accion, COUNT(*) n
               FROM sget_logs_auditoria
              GROUP BY accion
              ORDER BY n DESC, accion ASC"
        );
    }

    /** Usuarios que han generado actividad. */
    public static function usuarios(): array
    {
        return Database::all(
            "SELECT DISTINCT l.id_usu, l.nom_usu_log
               FROM sget_logs_auditoria l
              WHERE l.id_usu IS NOT NULL AND l.nom_usu_log IS NOT NULL
              ORDER BY l.nom_usu_log ASC"
        );
    }

    /**
     * Resumen para las tarjetas de KPIs y el gráfico de barras.
     * @return array{total:int, hoy:int, semana:int, criticos:int, usuarios:int,
     *               por_accion:array, por_dia:array, por_hora:array}
     */
    public static function resumen(array $filtros = []): array
    {
        [$sqlWhere, $params] = self::where($filtros);

        $total     = (int) Database::scalar("SELECT COUNT(*) FROM sget_logs_auditoria l {$sqlWhere}", $params);
        $hoy       = (int) Database::scalar("SELECT COUNT(*) FROM sget_logs_auditoria WHERE DATE(fec_log) = CURDATE()");
        $semana    = (int) Database::scalar("SELECT COUNT(*) FROM sget_logs_auditoria WHERE fec_log >= (NOW() - INTERVAL 7 DAY)");
        $criticos  = (int) Database::scalar(
            "SELECT COUNT(*) FROM sget_logs_auditoria
              WHERE accion LIKE '%ELIMINAR%' OR accion LIKE '%CANCELAR%'
                 OR accion LIKE '%FALLIDO%' OR accion LIKE '%SUSPENDER%'"
        );
        $usuarios  = (int) Database::scalar("SELECT COUNT(DISTINCT id_usu) FROM sget_logs_auditoria WHERE id_usu IS NOT NULL");

        $porAccion = Database::all(
            "SELECT accion, COUNT(*) n
               FROM sget_logs_auditoria
              GROUP BY accion
              ORDER BY n DESC
              LIMIT 8"
        );

        $porDia = Database::all(
            "SELECT DATE(fec_log) dia, COUNT(*) n
               FROM sget_logs_auditoria
              WHERE fec_log >= (CURDATE() - INTERVAL 13 DAY)
              GROUP BY DATE(fec_log)
              ORDER BY dia ASC"
        );

        $porHora = array_fill(0, 24, 0);
        foreach (Database::all(
            "SELECT HOUR(fec_log) h, COUNT(*) n FROM sget_logs_auditoria GROUP BY HOUR(fec_log)"
        ) as $f) {
            $porHora[(int)$f['h']] = (int)$f['n'];
        }

        return [
            'total'     => $total,
            'hoy'       => $hoy,
            'semana'    => $semana,
            'criticos'  => $criticos,
            'usuarios'  => $usuarios,
            'por_accion'=> $porAccion,
            'por_dia'   => $porDia,
            'por_hora'  => $porHora,
        ];
    }

    /* ================================================================== */
    /* Exportación                                                        */
    /* ================================================================== */

    /** Genera un CSV y lo envía al navegador con cabeceras de descarga. */
    public static function exportarCsv(array $filtros): int
    {
        $filas = self::todos($filtros);

        $nombre = 'sget-auditoria-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Cache-Control: no-store');

        $salida = fopen('php://output', 'w');
        // BOM para que Excel respete UTF-8 (acentos y ñ)
        fwrite($salida, "\xEF\xBB\xBF");

        fputcsv($salida, ['ID', 'Fecha y hora', 'Acción', 'Usuario', 'Documento', 'Rol', 'Descripción', 'IP', 'Navegador'], ';');

        foreach ($filas as $f) {
            fputcsv($salida, [
                (int)$f['id_log'],
                date('Y-m-d H:i:s', strtotime((string)$f['fec_log'])),
                (string)$f['accion'],
                (string)($f['nom_usu_log'] ?? ''),
                (string)($f['num_doc_usu'] ?? ''),
                (string)($f['nom_rol_log'] ?? ''),
                (string)$f['descripcion'],
                (string)($f['ip_origen'] ?? ''),
                mb_substr((string)($f['user_agent'] ?? ''), 0, 300),
            ], ';');
        }

        fclose($salida);
        Logger::registrar(Database::pdo(), 'EXPORTAR_LOGS',
            "Se exportaron " . count($filas) . " evento(s) de auditoría a CSV por " . Auth::nombre() . '.');

        return count($filas);
    }

    /* ================================================================== */
    /* Presentación                                                        */
    /* ================================================================== */

    public static function claseAccion(string $accion): string
    {
        $a = strtoupper($accion);
        if (str_contains($a, 'ELIMINAR'))                                return 'sget-badge--error';
        if (str_contains($a, 'CANCELAR'))                                return 'sget-badge--error';
        if (str_contains($a, 'SUSPENDER'))                               return 'sget-badge--aviso';
        if (str_contains($a, 'FALLIDO'))                                 return 'sget-badge--aviso';
        if (str_contains($a, 'LOGOUT') || str_contains($a, 'CERR'))       return 'sget-badge--neutro';
        if (str_contains($a, 'LOGIN') || str_contains($a, 'DESBLOQUEO'))  return 'sget-badge--exito';
        if (str_contains($a, 'CREAR') || str_contains($a, 'REGISTRAR'))   return 'sget-badge--exito';
        if (str_contains($a, 'EDITAR') || str_contains($a, 'ACTUALIZAR')) return 'sget-badge--info';
        if (str_contains($a, 'GUARDAR') || str_contains($a, 'FINALIZAR')
            || str_contains($a, 'EXPORTAR') || str_contains($a, 'ESTADO')) return 'sget-badge--info';
        return 'sget-badge--neutro';
    }

    public static function iconoAccion(string $accion): string
    {
        $a = strtoupper($accion);
        if (str_contains($a, 'ELIMINAR'))            return 'fa-trash';
        if (str_contains($a, 'CANCELAR'))            return 'fa-ban';
        if (str_contains($a, 'SUSPENDER'))           return 'fa-user-slash';
        if (str_contains($a, 'FALLIDO'))             return 'fa-triangle-exclamation';
        if (str_contains($a, 'LOGIN'))               return 'fa-right-to-bracket';
        if (str_contains($a, 'LOGOUT'))              return 'fa-arrow-right-from-bracket';
        if (str_contains($a, 'DESBLOQUEO'))          return 'fa-lock-open';
        if (str_contains($a, 'CREAR') || str_contains($a, 'REGISTRAR')) return 'fa-circle-plus';
        if (str_contains($a, 'EDITAR') || str_contains($a, 'ACTUALIZAR')) return 'fa-pen';
        if (str_contains($a, 'FINALIZAR'))           return 'fa-flag-checkered';
        if (str_contains($a, 'EXPORTAR'))            return 'fa-file-csv';
        if (str_contains($a, 'ESTADO'))              return 'fa-toggle-on';
        if (str_contains($a, 'GUARDAR'))             return 'fa-floppy-disk';
        return 'fa-circle-info';
    }

    /** Agrupa el listado por día para mostrar separadores. */
    public static function agruparPorDia(array $filas): array
    {
        $dias = [];
        foreach ($filas as $f) {
            $dias[date('Y-m-d', strtotime((string)$f['fec_log']))][] = $f;
        }
        return $dias;
    }

    public static function etiquetaDia(string $fechaIso): string
    {
        $ts = strtotime($fechaIso);
        if ($ts === false) return $fechaIso;

        $hoy = date('Y-m-d');
        if ($fechaIso === $hoy)                             return 'Hoy';
        if ($fechaIso === date('Y-m-d', strtotime('-1 day'))) return 'Ayer';

        return ucfirst((string)strftime('%A %d de %B', $ts)) . ', ' . date('H:i', $ts);
    }
}

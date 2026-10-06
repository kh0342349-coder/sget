<?php
/**
 * services/InformacionService.php
 * -----------------------------------------------------------------------------
 * MÓDULO: PANEL DE INFORMACIÓN DEL ADMINISTRADOR
 * -----------------------------------------------------------------------------
 * SUSTITUYE al antiguo "ReporteService" (Admin/reportes.php), que solo servía
 * para descargar archivos CSV. Este módulo NO exporta nada: es un tablero de
 * consulta con cinco secciones:
 *
 *   1. general    → información general del sistema (composición, estados,
 *                   operación de hoy, auditoría reciente)
 *   2. viajes     → historial completo de viajes con filtros y paginación
 *   3. usuarios   → historial de usuarios (rol, reservas, viajes, gasto)
 *   4. rutas      → historial y rendimiento de cada ruta
 *   5. ganancias  → ingresos reales, comparados con el período anterior
 *
 * REGLA DE ORO — "Ganancias" NO es el precio del pasaje.
 * El reporte anterior sumaba `SUM(viaje.val_via)`: el precio nominal de todos
 * los viajes programados, que no es dinero cobrado (un viaje con 0 pasajeros
 * y uno con 30 cupos contaban igual, y los cancelados también sumaban).
 * El dinero real es la suma de `reserva.valor_pagado` de las reservas
 * CONFIRMADAS.
 *
 * TODO EL SQL vive aquí. La página solo dibuja.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class InformacionService
{
    /* ================================================================== */
    /* Rango de fechas                                                      */
    /* ================================================================== */

    public static function rango(string $desde, string $hasta): array
    {
        $d = self::fecha($desde) ?: date('Y-m-d', strtotime('-29 days'));
        $h = self::fecha($hasta) ?: date('Y-m-d');

        if (strtotime($d) > strtotime($h)) {
            [$d, $h] = [$h, $d];
        }
        return [$d, $h];
    }

    private static function fecha(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') return null;
        $ts = strtotime($v);
        return $ts === false ? null : date(Fecha::FMT_FECHA, $ts);
    }

    /** Etiquetas de los rangos rápidos. */
    public static function rangosRapidos(): array
    {
        return [
            '7'    => 'Últimos 7 días',
            '30'   => 'Últimos 30 días',
            '90'   => 'Últimos 90 días',
            'mes'  => 'Mes en curso',
            'anio' => 'Año en curso',
            'todo' => 'Todo el histórico',
            ''     => 'Personalizado',
        ];
    }

    /** Traduce el parámetro ?rango= a un par de fechas. */
    public static function fechasDesdeParametro(string $rango, string $desde, string $hasta): array
    {
        switch ($rango) {
            case '7':    return [date('Y-m-d', strtotime('-6 days')),  date('Y-m-d')];
            case '30':   return [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')];
            case '90':   return [date('Y-m-d', strtotime('-89 days')), date('Y-m-d')];
            case 'mes':  return [date('Y-m-01'), date('Y-m-d')];
            case 'anio': return [date('Y-01-01'), date('Y-m-d')];
            case 'todo': return ['1970-01-01', date('Y-m-d')];
            default:     return self::rango($desde, $hasta);
        }
    }

    /** Período inmediatamente anterior, de la misma longitud (para comparar). */
    public static function periodoAnterior(string $desde, string $hasta): array
    {
        $dias = max(1, (int)ceil((strtotime($hasta) - strtotime($desde)) / 86400) + 1);
        $fin  = date('Y-m-d', strtotime($desde . ' -1 day'));
        $ini  = date('Y-m-d', strtotime($fin . ' -' . ($dias - 1) . ' days'));
        return [$ini, $fin];
    }

    /* ================================================================== */
    /* 1. INFORMACIÓN GENERAL DEL SISTEMA                                  */
    /* ================================================================== */

    /**
     * Fotografía global del sistema: totales históricos, mes en curso y
     * los indicadores que el resto de módulos usan como verdad.
     */
    public static function panelGeneral(): array
    {
        $mes  = date('Y-m-01');
        $hoy  = date('Y-m-d');

        $conteo = static function (string $sql, array $p = []) {
            return (int) Database::scalar($sql, $p);
        };

        // --- Personas ---
        $usuarios      = $conteo("SELECT COUNT(*) FROM usuario");
        $usuariosAct   = $conteo("SELECT COUNT(*) FROM usuario WHERE estado = ?", [Config::USU_ACTIVO]);
        $admins        = $conteo("SELECT COUNT(*) FROM usuario WHERE id_rol_usu = ?", [Config::ROL_ADMIN]);
        $conductores   = $conteo("SELECT COUNT(*) FROM usuario WHERE id_rol_usu = ?", [Config::ROL_CONDUCTOR]);
        $pasajeros     = $conteo("SELECT COUNT(*) FROM usuario WHERE id_rol_usu = ?", [Config::ROL_PASAJERO]);
        $conductoresLibres = $conteo(
            "SELECT COUNT(*) FROM usuario WHERE id_rol_usu = ? AND est_con_usu = ? AND estado = ?",
            [Config::ROL_CONDUCTOR, Config::CON_DISPONIBLE, Config::USU_ACTIVO]);
        $cuentasGoogle = $conteo("SELECT COUNT(*) FROM usuario WHERE google_id IS NOT NULL AND google_id <> ''");

        // --- Flota y catálogo ---
        $vehiculos   = $conteo("SELECT COUNT(*) FROM vehiculo");
        $vehLibres   = $conteo("SELECT COUNT(*) FROM vehiculo WHERE est_veh = ?", [Config::VEH_DISPONIBLE]);
        $rutas       = $conteo("SELECT COUNT(*) FROM rutas");
        $rutasActivas = $conteo("SELECT COUNT(*) FROM rutas WHERE estado = ?", [Config::USU_ACTIVO]);

        // --- Operación ---
        $viajes      = $conteo("SELECT COUNT(*) FROM viaje");
        $viajesMes   = $conteo("SELECT COUNT(*) FROM viaje WHERE fec_via >= ?", [$mes]);
        $viajesHoy   = $conteo("SELECT COUNT(*) FROM viaje WHERE fec_via = ?", [$hoy]);
        $enCurso     = $conteo("SELECT COUNT(*) FROM viaje WHERE est_via = ?", [Config::VIA_EN_CURSO]);
        $programados = $conteo("SELECT COUNT(*) FROM viaje WHERE est_via = ? AND fec_via >= ?",
                               [Config::VIA_PROGRAMADO, $hoy]);

        $reservas      = $conteo("SELECT COUNT(*) FROM reserva");
        $reservasMes   = $conteo("SELECT COUNT(*) FROM reserva WHERE fech_res >= ?", [$mes]);
        $reservasPend  = $conteo("SELECT COUNT(*) FROM reserva WHERE estado_pago = ?", [Config::RES_PENDIENTE]);

        // --- Dinero (histórico y mes) ---
        $ingresos    = (float) Database::scalar(
            "SELECT COALESCE(SUM(valor_pagado), 0) FROM reserva WHERE estado_pago = ?", [Config::RES_CONFIRMADA]);
        $ingresosMes = (float) Database::scalar(
            "SELECT COALESCE(SUM(valor_pagado), 0) FROM reserva WHERE estado_pago = ? AND fecha_pago >= ?",
            [Config::RES_CONFIRMADA, $mes]);
        $porCobrar   = (float) Database::scalar(
            "SELECT COALESCE(SUM(valor_pagado), 0) FROM reserva WHERE estado_pago <> ?", [Config::RES_CANCELADA]);

        // --- Salud de la operación ---
        $cancelados   = $conteo("SELECT COUNT(*) FROM viaje WHERE est_via = ?", [Config::VIA_CANCELADO]);
        $finalizados  = $conteo("SELECT COUNT(*) FROM viaje WHERE est_via = ?", [Config::VIA_FINALIZADO]);
        $tasaCancel   = $viajes > 0 ? round(($cancelados / $viajes) * 100, 1) : 0.0;
        $tasaCierre   = $viajes > 0 ? round(($finalizados / $viajes) * 100, 1) : 0.0;
        $ocupacion    = self::ocupacionGlobal();
        $ticketMedio  = $reservas > 0 ? $ingresos / $reservas : 0.0;

        return [
            'usuarios'            => $usuarios,
            'usuarios_activos'    => $usuariosAct,
            'usuarios_suspendidos'=> $usuarios - $usuariosAct,
            'admins'              => $admins,
            'conductores'         => $conductores,
            'pasajeros'           => $pasajeros,
            'conductores_libres'  => $conductoresLibres,
            'cuentas_google'      => $cuentasGoogle,
            'vehiculos'           => $vehiculos,
            'vehiculos_libres'    => $vehLibres,
            'rutas'               => $rutas,
            'rutas_activas'       => $rutasActivas,
            'viajes'              => $viajes,
            'viajes_mes'          => $viajesMes,
            'viajes_hoy'          => $viajesHoy,
            'viajes_en_curso'     => $enCurso,
            'viajes_programados'  => $programados,
            'viajes_finalizados'  => $finalizados,
            'viajes_cancelados'   => $cancelados,
            'tasa_cancelacion'    => $tasaCancel,
            'tasa_cierre'         => $tasaCierre,
            'reservas'            => $reservas,
            'reservas_mes'        => $reservasMes,
            'reservas_pendientes' => $reservasPend,
            'ingresos'            => $ingresos,
            'ingresos_mes'        => $ingresosMes,
            'por_cobrar'          => $porCobrar,
            'ticket_promedio'     => $ticketMedio,
            'ocupacion'           => $ocupacion,
        ];
    }

    /** Cupos ocupados sobre cupos ofertados, en todo el histórico. */
    public static function ocupacionGlobal(): float
    {
        $cupos = (int) Database::scalar("SELECT COALESCE(SUM(cup_tot), 0) FROM viaje");
        if ($cupos <= 0) return 0.0;
        $vendidos = (int) Database::scalar(
            "SELECT COUNT(*) FROM reserva WHERE estado_pago = ?", [Config::RES_CONFIRMADA]);
        return min(100, round(($vendidos / $cupos) * 100, 1));
    }

    /** Usuarios agrupados por rol (para las barras de composición). */
    public static function composicionUsuarios(): array
    {
        return Database::all(
            "SELECT r.id_rol, r.nom_rol, COUNT(u.id_usu) total,
                    SUM(CASE WHEN u.estado = ? THEN 1 ELSE 0 END) activos
               FROM rol r
               LEFT JOIN usuario u ON u.id_rol_usu = r.id_rol
              GROUP BY r.id_rol, r.nom_rol
              ORDER BY total DESC",
            [Config::USU_ACTIVO]
        );
    }

    /** Vehículos agrupados por estado. */
    public static function composicionFlota(): array
    {
        return Database::all(
            "SELECT est_veh, COUNT(*) total, COALESCE(SUM(cap_veh), 0) capacidad
               FROM vehiculo GROUP BY est_veh"
        );
    }

    /** Viajes agrupados por estado (histórico completo). */
    public static function estadosDeViajes(): array
    {
        $filas = Database::all("SELECT est_via, COUNT(*) total FROM viaje GROUP BY est_via");
        $mapa  = [];
        foreach ($filas as $f) $mapa[(string)$f['est_via']] = (int)$f['total'];

        $out = [];
        foreach (Config::VIA_ESTADOS as $estado) {
            $out[] = ['estado' => $estado, 'total' => $mapa[$estado] ?? 0];
        }
        return $out;
    }

    /** Próximas salidas programadas (lo que está por salir). */
    public static function proximosViajes(int $limite = 6): array
    {
        return Database::all(
            "SELECT v.id_via, v.fec_via, v.hor_sal_via, v.est_via, v.cup_tot,
                    r.nom_rut, r.ori_rut, r.des_rut,
                    u.nom_usu AS conductor, veh.pla_veh,
                    (SELECT COUNT(*) FROM reserva res
                      WHERE res.id_via_res = v.id_via AND res.estado_pago = ?) AS reservas
               FROM viaje v
               LEFT JOIN rutas r     ON r.id_rut   = v.id_rut_via
               LEFT JOIN usuario u  ON u.id_usu   = v.id_usu_via
               LEFT JOIN vehiculo veh ON veh.id_veh = v.id_veh
              WHERE v.est_via IN (?, ?)
                AND DATE(v.fec_via) >= ?
              ORDER BY v.fec_via ASC, v.hor_sal_via ASC
              LIMIT " . (int)$limite,
            [Config::RES_CONFIRMADA, Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO, date('Y-m-d')]
        );
    }

    /** Últimos movimientos registrados en la auditoría del sistema. */
    public static function actividadReciente(int $limite = 8): array
    {
        return Database::all(
            "SELECT id_log, nom_usu_log, nom_rol_log, accion, descripcion, fec_log
               FROM sget_logs_auditoria
              ORDER BY id_log DESC
              LIMIT " . (int)$limite
        );
    }

    /* ================================================================== */
    /* 2. HISTORIAL DE VIAJES                                              */
    /* ================================================================== */

    /**
     * Historial paginado de viajes.
     * @param array{desde:string,hasta:string,estado:string,q:string} $filtros
     * @return array{filas:array,total:int,pagina:int,paginas:int,porPagina:int}
     */
    public static function historialViajes(array $filtros, int $pagina = 1, int $porPagina = 25): array
    {
        [$w, $p] = self::whereViajes($filtros);
        return self::paginar(self::SQL_VIAJES . " {$w} ORDER BY v.fec_via DESC, v.hor_sal_via DESC, v.id_via DESC",
                            $p, $pagina, $porPagina);
    }

    private const SQL_VIAJES =
        "SELECT v.id_via, v.fec_via, v.hor_sal_via, v.val_via, v.est_via, v.cup_tot, v.cup_dis,
                v.motivo_cancelacion, v.anotacion_cancelacion, v.fec_cancelacion,
                r.nom_rut, r.ori_rut, r.des_rut, r.duracion_min,
                u.nom_usu AS conductor, veh.pla_veh,
                (SELECT COUNT(*) FROM reserva res
                  WHERE res.id_via_res = v.id_via AND res.estado_pago = ?) AS reservas,
                (SELECT COALESCE(SUM(res.valor_pagado), 0) FROM reserva res
                  WHERE res.id_via_res = v.id_via AND res.estado_pago = ?) AS recaudo
           FROM viaje v
           LEFT JOIN rutas r      ON r.id_rut   = v.id_rut_via
           LEFT JOIN usuario u   ON u.id_usu   = v.id_usu_via
           LEFT JOIN vehiculo veh ON veh.id_veh = v.id_veh";

    private static function whereViajes(array $f): array
    {
        $w = [];
        $p = [Config::RES_CONFIRMADA, Config::RES_CONFIRMADA];   // parámetros de las subconsultas

        if (($f['desde'] ?? '') !== '') { $w[] = 'DATE(v.fec_via) >= ?'; $p[] = $f['desde']; }
        if (($f['hasta'] ?? '') !== '') { $w[] = 'DATE(v.fec_via) <= ?'; $p[] = $f['hasta']; }
        if (($f['estado'] ?? '') !== '' && in_array($f['estado'], Config::VIA_ESTADOS, true)) {
            $w[] = 'v.est_via = ?';
            $p[] = $f['estado'];
        }
        if (($f['conductor'] ?? '') !== '') { $w[] = 'v.id_usu_via = ?'; $p[] = (int)$f['conductor']; }

        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $w[] = '(r.nom_rut LIKE ? OR r.ori_rut LIKE ? OR r.des_rut LIKE ? OR u.nom_usu LIKE ? OR veh.pla_veh LIKE ? OR CAST(v.id_via AS CHAR) = ?)';
            $like = '%' . $q . '%';
            array_push($p, $like, $like, $like, $like, $like);
        }

        return [($w ? 'WHERE ' : '') . implode(' AND ', $w), $p];
    }

    /** Conductores para el filtro del historial de viajes. */
    public static function conductores(): array
    {
        return Database::all(
            "SELECT id_usu, nom_usu FROM usuario WHERE id_rol_usu = ? ORDER BY nom_usu ASC",
            [Config::ROL_CONDUCTOR]
        );
    }

    /**
     * RESUMEN DE PASAJEROS DE UN VIAJE: quién viajó, quién faltaba, quién debe.
     *
     * Es la respuesta a «¿quiénes viajaron en este viaje y por qué no aparece
     * este pasajero en el informe?». Se separa `embarco` del estado de pago a
     * propósito, porque son dos hechos distintos: se puede haber pagado al
     * abordar y sí viajar, o haber pagado antes y no presentarse.
     *
     * @return array{totales:array, pasajeros:array}
     */
    public static function pasajerosDeViaje(int $idViaje): array
    {
        $manifiesto = ReservaService::manifiesto($idViaje);

        $totales = [
            'puestos'      => 0, 'viajeros' => 0, 'no_presentados' => 0,
            'sin_decidir'  => 0, 'cancelados' => 0,
            'pagado'       => 0.0, 'por_cobrar' => 0.0, 'perdido' => 0.0,
            'debe'         => 0.0,   // TODO lo que deben los puestos vivos
        ];
        $pasajeros = [];

        foreach ($manifiesto as $m) {
            $totales['puestos']      += $m['puestos'];
            $totales['viajeros']     += $m['embarcaron'];
            $totales['no_presentados'] += $m['no_embarcaron'];
            $totales['sin_decidir']  += $m['sin_decidir'];
            $totales['cancelados']   += $m['cancelados'];

            if ($m['pagados'] > 0)   $totales['pagado'] += $m['debe'];
            if ($m['pendientes'] > 0) $totales['por_cobrar'] += $m['debe'];

            // `debe` es la suma de los puestos VIVOS (pagados y pendientes): es
            // el total que la columna «Debe» muestra al final de la tabla.
            if ($m['pagados'] + $m['pendientes'] > 0) $totales['debe'] += $m['debe'];
            // Lo que se cobró de quien no subió al bus.
            if ($m['embarcaron'] === 0 && $m['no_embarcaron'] > 0 && $m['pagados'] > 0) {
                $totales['perdido'] += $m['debe'];
            }

            $pasajeros[] = $m;
        }

        return ['totales' => $totales, 'pasajeros' => $pasajeros];
    }

    /**
     * Historial de no-presentaciones: quién se quedó en casa, en qué viaje y
     * por qué. Es el informe que responde «los pasajeros que no subieron».
     */
    public static function noPresentaciones(array $filtros, int $limite = 100): array
    {
        $w = ['res.embarco = 0', 'res.estado_pago <> ?'];
        $p = [Config::RES_CANCELADA];

        if (($filtros['desde'] ?? '') !== '') { $w[] = 'DATE(v.fec_via) >= ?'; $p[] = $filtros['desde']; }
        if (($filtros['hasta'] ?? '') !== '') { $w[] = 'DATE(v.fec_via) <= ?'; $p[] = $filtros['hasta']; }

        $q = trim((string)($filtros['q'] ?? ''));
        if ($q !== '') {
            $w[] = '(u.nom_usu LIKE ? OR u.num_doc_usu LIKE ? OR res.motivo_cancelacion LIKE ?)';
            $like = '%' . $q . '%';
            array_push($p, $like, $like, $like);
        }

        $w[] = 'res.embarque_fec IS NOT NULL';   // solo marcas reales, no NULL

        return Database::all(
            "SELECT res.id_res, res.motivo_cancelacion, res.embarque_fec,
                    res.estado_pago, res.valor_pagado, res.metodo_pago,
                    v.id_via, v.fec_via, v.hor_sal_via,
                    r.nom_rut, r.ori_rut, r.des_rut,
                    u.nom_usu AS pasajero, u.num_doc_usu
               FROM reserva res
               INNER JOIN viaje v   ON v.id_via   = res.id_via_res
               INNER JOIN usuario u ON u.id_usu   = res.id_usu_res
               LEFT  JOIN rutas r   ON r.id_rut   = v.id_rut_via
              WHERE " . implode(' AND ', $w) . "
              ORDER BY v.fec_via DESC, v.hor_sal_via DESC, res.embarque_fec DESC
              LIMIT " . (int)$limite,
            $p
        );
    }

    /* ================================================================== */
    /* 3. HISTORIAL DE USUARIOS                                            */
    /* ================================================================== */

    /**
     * Historial paginado de usuarios con su actividad real.
     * @param array{rol:int,estado:int,q:string} $filtros
     */
    public static function historialUsuarios(array $filtros, int $pagina = 1, int $porPagina = 25): array
    {
        [$w, $p] = self::whereUsuarios($filtros);
        return self::paginar(self::SQL_USUARIOS . " {$w} ORDER BY u.id_usu DESC", $p, $pagina, $porPagina);
    }

    private const SQL_USUARIOS =
        "SELECT u.id_usu, u.tip_doc_usu, u.num_doc_usu, u.nom_usu, u.corre_usu, u.tel_usu,
                u.estado, u.est_con_usu, u.google_id, u.fecha_acepta_politica,
                r.nom_rol, r.id_rol,
                (SELECT COUNT(*) FROM reserva res WHERE res.id_usu_res = u.id_usu) AS reservas,
                (SELECT COALESCE(SUM(res.valor_pagado), 0) FROM reserva res
                   WHERE res.id_usu_res = u.id_usu AND res.estado_pago = ?) AS pagado,
                (SELECT MAX(res.fech_res) FROM reserva res WHERE res.id_usu_res = u.id_usu) AS ultima_reserva,
                (SELECT COUNT(*) FROM viaje v WHERE v.id_usu_via = u.id_usu) AS viajes_conducidos,
                (SELECT COUNT(*) FROM calificacion c WHERE c.id_usu_des = u.id_usu) AS calificaciones,
                (SELECT ROUND(AVG(c.pun_cal), 2) FROM calificacion c WHERE c.id_usu_des = u.id_usu) AS promedio
           FROM usuario u
           LEFT JOIN rol r ON r.id_rol = u.id_rol_usu";

    private static function whereUsuarios(array $f): array
    {
        $w = [];
        $p = [Config::RES_CONFIRMADA];   // parámetro de la subconsulta `pagado`

        if ((int)($f['rol'] ?? 0) > 0)   { $w[] = 'u.id_rol_usu = ?'; $p[] = (int)$f['rol']; }
        if (($f['estado'] ?? '') !== '' && in_array((int)$f['estado'], [0, 1], true)) {
            $w[] = 'u.estado = ?';
            $p[] = (int)$f['estado'];
        }

        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $w[] = '(u.nom_usu LIKE ? OR u.num_doc_usu LIKE ? OR u.corre_usu LIKE ? OR u.tel_usu LIKE ?)';
            $like = '%' . $q . '%';
            array_push($p, $like, $like, $like, $like);
        }

        return [($w ? 'WHERE ' : '') . implode(' AND ', $w), $p];
    }

    /* ================================================================== */
    /* 4. HISTORIAL Y RENDIMIENTO DE RUTAS                                 */
    /* ================================================================== */

    /** Una fila por ruta con su uso real en el período. */
    public static function historialRutas(string $desde, string $hasta, int $limite = 100): array
    {
        return Database::all(
            "SELECT r.id_rut, r.nom_rut, r.ori_rut, r.des_rut, r.duracion_min, r.val_rut, r.estado, r.img_rut,
                    COUNT(DISTINCT v.id_via) viajes,
                    COALESCE(SUM(v.cup_tot), 0) cupos,
                    COALESCE(SUM((SELECT COUNT(*) FROM reserva res
                                   WHERE res.id_via_res = v.id_via AND res.estado_pago = ?)), 0) pasajeros,
                    COALESCE(SUM((SELECT COALESCE(SUM(res.valor_pagado), 0) FROM reserva res
                                   WHERE res.id_via_res = v.id_via AND res.estado_pago = ?)), 0) ingresos,
                    MAX(v.fec_via) ultima_salida
               FROM rutas r
               LEFT JOIN viaje v ON v.id_rut_via = r.id_rut AND DATE(v.fec_via) BETWEEN ? AND ?
              GROUP BY r.id_rut, r.nom_rut, r.ori_rut, r.des_rut, r.duracion_min, r.val_rut, r.estado, r.img_rut
              ORDER BY ingresos DESC, viajes DESC, r.nom_rut ASC
              LIMIT " . (int)$limite,
            [Config::RES_CONFIRMADA, Config::RES_CONFIRMADA, $desde, $hasta]
        );
    }

    /* ================================================================== */
    /* 5. GANANCIAS                                                        */
    /* ================================================================== */

    /** Indicadores del período seleccionado. */
    public static function indicadores(string $desde, string $hasta): array
    {
        $viajes = (int) Database::scalar(
            "SELECT COUNT(*) FROM viaje WHERE DATE(fec_via) BETWEEN ? AND ?", [$desde, $hasta]);

        $viajesCancelados = (int) Database::scalar(
            "SELECT COUNT(*) FROM viaje WHERE DATE(fec_via) BETWEEN ? AND ? AND est_via = ?",
            [$desde, $hasta, Config::VIA_CANCELADO]);

        $viajesFinalizados = (int) Database::scalar(
            "SELECT COUNT(*) FROM viaje WHERE DATE(fec_via) BETWEEN ? AND ? AND est_via = ?",
            [$desde, $hasta, Config::VIA_FINALIZADO]);

        $ingresos = (float) Database::scalar(
            "SELECT COALESCE(SUM(valor_pagado), 0) FROM reserva
              WHERE estado_pago = ? AND DATE(COALESCE(fecha_pago, fech_res)) BETWEEN ? AND ?",
            [Config::RES_CONFIRMADA, $desde, $hasta]);

        $reservas = (int) Database::scalar(
            "SELECT COUNT(*) FROM reserva
              WHERE estado_pago = ? AND DATE(COALESCE(fecha_pago, fech_res)) BETWEEN ? AND ?",
            [Config::RES_CONFIRMADA, $desde, $hasta]);

        $reservasCanceladas = (int) Database::scalar(
            "SELECT COUNT(*) FROM reserva WHERE estado_pago = ? AND DATE(fech_res) BETWEEN ? AND ?",
            [Config::RES_CANCELADA, $desde, $hasta]);

        $porCobrar = (float) Database::scalar(
            "SELECT COALESCE(SUM(valor_pagado), 0) FROM reserva
              WHERE estado_pago <> ? AND DATE(fech_res) BETWEEN ? AND ?",
            [Config::RES_CANCELADA, $desde, $hasta]);

        $cuposOfertados = (int) Database::scalar(
            "SELECT COALESCE(SUM(cup_tot), 0) FROM viaje WHERE DATE(fec_via) BETWEEN ? AND ?", [$desde, $hasta]);

        $ocupacion = $cuposOfertados > 0
            ? min(100, round((max(0, $reservas) / $cuposOfertados) * 100, 1))
            : 0.0;

        return [
            'viajes'             => $viajes,
            'viajes_cancelados'  => $viajesCancelados,
            'viajes_finalizados' => $viajesFinalizados,
            'ingresos'           => $ingresos,
            'por_cobrar'         => $porCobrar,
            'reservas'           => $reservas,
            'reservas_canceladas'=> $reservasCanceladas,
            'cupos_ofertados'    => $cuposOfertados,
            'cupos_vendidos'     => max(0, $reservas),
            'ocupacion'          => $ocupacion,
            'ticket_promedio'    => $reservas > 0 ? $ingresos / $reservas : 0.0,
            'tasa_cancelacion'   => $viajes > 0 ? round(($viajesCancelados / $viajes) * 100, 1) : 0.0,
        ];
    }

    /** Serie diaria de ingresos y reservas (rellena los días sin datos). */
    public static function serieDiaria(string $desde, string $hasta): array
    {
        $filas = Database::all(
            "SELECT DATE(COALESCE(fecha_pago, fech_res)) dia,
                    COALESCE(SUM(valor_pagado), 0) ingresos,
                    COUNT(*) reservas
               FROM reserva
              WHERE estado_pago = ? AND DATE(COALESCE(fecha_pago, fech_res)) BETWEEN ? AND ?
              GROUP BY DATE(COALESCE(fecha_pago, fech_res))
              ORDER BY dia ASC",
            [Config::RES_CONFIRMADA, $desde, $hasta]
        );

        $mapa = [];
        foreach ($filas as $f) $mapa[$f['dia']] = $f;

        $dias = (int)ceil((strtotime($hasta) - strtotime($desde)) / 86400) + 1;
        $dias = max(1, min($dias, 366));

        $serie = [];
        for ($i = 0; $i < $dias; $i++) {
            $dia = date('Y-m-d', strtotime($desde . " +{$i} days"));
            $serie[] = [
                'dia'      => $dia,
                'etiqueta' => date('d/m', strtotime($dia)),
                'ingresos' => (float)($mapa[$dia]['ingresos'] ?? 0),
                'reservas' => (int)($mapa[$dia]['reservas'] ?? 0),
            ];
        }
        return $serie;
    }

    /** Variación porcentual frente al período inmediatamente anterior. */
    public static function comparativa(string $desde, string $hasta): array
    {
        [$pIni, $pFin] = self::periodoAnterior($desde, $hasta);
        $actual    = self::indicadores($desde, $hasta);
        $anterior  = self::indicadores($pIni, $pFin);

        $variacion = static function (float $antes, float $ahora): ?float {
            // Sin base de comparación NO se inventa un porcentaje: mostrar
            // «+100 %» porque antes no había nada es un dato falso.
            if ($antes <= 0) return null;
            return round((($ahora - $antes) / $antes) * 100, 1);
        };

        return [
            'desde'          => $pIni,
            'hasta'          => $pFin,
            'ingresos_antes' => $anterior['ingresos'],
            'ingresos_ahora' => $actual['ingresos'],
            'ingresos_pct'   => $variacion($anterior['ingresos'], $actual['ingresos']),
            'reservas_antes' => $anterior['reservas'],
            'reservas_ahora' => $actual['reservas'],
            'reservas_pct'   => $variacion($anterior['reservas'], $actual['reservas']),
            'viajes_antes'   => $anterior['viajes'],
            'viajes_ahora'   => $actual['viajes'],
            'viajes_pct'     => $variacion($anterior['viajes'], $actual['viajes']),
        ];
    }

    /** Ingresos por método de pago. */
    public static function porMetodoPago(string $desde, string $hasta): array
    {
        return Database::all(
            "SELECT metodo_pago, COUNT(*) transacciones, COALESCE(SUM(valor_pagado), 0) ingresos
               FROM reserva
              WHERE estado_pago = ? AND DATE(COALESCE(fecha_pago, fech_res)) BETWEEN ? AND ?
              GROUP BY metodo_pago
              ORDER BY ingresos DESC",
            [Config::RES_CONFIRMADA, $desde, $hasta]
        );
    }

    /** Ingresos por ruta (top del período). */
    public static function ingresosPorRuta(string $desde, string $hasta, int $limite = 8): array
    {
        return Database::all(
            "SELECT r.nom_rut, r.ori_rut, r.des_rut, r.duracion_min,
                    COUNT(res.id_res) reservas,
                    COALESCE(SUM(res.valor_pagado), 0) ingresos
               FROM reserva res
               INNER JOIN viaje v ON v.id_via = res.id_via_res
               INNER JOIN rutas r ON r.id_rut = v.id_rut_via
              WHERE res.estado_pago = ?
                AND DATE(COALESCE(res.fecha_pago, res.fech_res)) BETWEEN ? AND ?
              GROUP BY r.id_rut, r.nom_rut, r.ori_rut, r.des_rut, r.duracion_min
              ORDER BY ingresos DESC, reservas DESC
              LIMIT " . (int)$limite,
            [Config::RES_CONFIRMADA, $desde, $hasta]
        );
    }

    /** Ingresos por conductor del período. */
    public static function ingresosPorConductor(string $desde, string $hasta, int $limite = 10): array
    {
        return Database::all(
            "SELECT u.id_usu, u.nom_usu,
                    COUNT(DISTINCT v.id_via) viajes,
                    COUNT(res.id_res) reservas,
                    COALESCE(SUM(res.valor_pagado), 0) ingresos,
                    (SELECT ROUND(AVG(c.pun_cal), 2) FROM calificacion c WHERE c.id_usu_rem = u.id_usu) AS calificacion
               FROM usuario u
               LEFT JOIN viaje v    ON v.id_usu_via   = u.id_usu AND DATE(v.fec_via) BETWEEN ? AND ?
               LEFT JOIN reserva res ON res.id_via_res = v.id_via AND res.estado_pago = ?
              WHERE u.id_rol_usu = ?
              GROUP BY u.id_usu, u.nom_usu
              ORDER BY ingresos DESC, viajes DESC
              LIMIT " . (int)$limite,
            [$desde, $hasta, Config::RES_CONFIRMADA, Config::ROL_CONDUCTOR]
        );
    }

    /** Pasajeros que más han pagado en el período. */
    public static function topPasajeros(string $desde, string $hasta, int $limite = 8): array
    {
        return Database::all(
            "SELECT u.nom_usu, u.num_doc_usu, u.tip_doc_usu,
                    COUNT(res.id_res) reservas,
                    COALESCE(SUM(res.valor_pagado), 0) pagado
               FROM reserva res
               INNER JOIN usuario u ON u.id_usu = res.id_usu_res
              WHERE res.estado_pago = ?
                AND DATE(COALESCE(res.fecha_pago, res.fech_res)) BETWEEN ? AND ?
              GROUP BY u.id_usu, u.nom_usu, u.num_doc_usu, u.tip_doc_usu
              ORDER BY pagado DESC, reservas DESC
              LIMIT " . (int)$limite,
            [Config::RES_CONFIRMADA, $desde, $hasta]
        );
    }

    /** Reservas del período (historial de cobros). */
    public static function reservas(string $desde, string $hasta, int $limite = 100): array
    {
        return Database::all(
            "SELECT res.id_res, res.fech_res, res.metodo_pago, res.valor_pagado, res.estado_pago,
                    u.nom_usu AS pasajero, u.num_doc_usu,
                    r.nom_rut, r.ori_rut, r.des_rut, v.fec_via, v.hor_sal_via
               FROM reserva res
               INNER JOIN usuario u ON u.id_usu  = res.id_usu_res
               INNER JOIN viaje v   ON v.id_via = res.id_via_res
               LEFT  JOIN rutas r   ON r.id_rut = v.id_rut_via
              WHERE DATE(COALESCE(res.fecha_pago, res.fech_res)) BETWEEN ? AND ?
              ORDER BY COALESCE(res.fecha_pago, res.fech_res) DESC, res.id_res DESC
              LIMIT " . (int)$limite,
            [$desde, $hasta]
        );
    }

    /* ================================================================== */
    /* Utilidades internas                                                     */
    /* ================================================================== */

    /**
     * Ejecuta una consulta paginada sobre un SELECT con sus subconsultas ya
     * escritas. Cuenta sobre un envoltorio para no repetir el SQL.
     */
    private static function paginar(string $select, array $params, int $pagina, int $porPagina): array
    {
        $porPagina = max(10, min(100, $porPagina));
        $total     = (int) Database::scalar("SELECT COUNT(*) FROM ({$select}) AS t", $params);
        $paginas   = max(1, (int)ceil($total / $porPagina));
        $pagina    = max(1, min($pagina, $paginas));

        $filas = Database::all($select . " LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina), $params);

        return [
            'filas'     => $filas,
            'total'     => $total,
            'pagina'    => $pagina,
            'paginas'   => $paginas,
            'porPagina' => $porPagina,
        ];
    }

    /* ================================================================== */
    /* Formato                                                             */
    /* ================================================================== */

    /** $1.234.567 (formato colombiano, sin decimales). */
    public static function money(float $v): string
    {
        return '$' . number_format($v, 0, ',', '.');
    }

    public static function moneyPreciso(float $v): string
    {
        return '$' . number_format($v, 2, ',', '.');
    }

    public static function numero($v): string
    {
        return number_format((float)$v, 0, ',', '.');
    }

    /** Texto de la variación (ej. «+12,4 %» / «-3,1 %»). */
    public static function variacion(float $pct): string
    {
        return ($pct > 0 ? '+' : '') . number_format($pct, 1, ',', '.') . ' %';
    }
}

<?php
/**
 * services/ExportService.php
 * -----------------------------------------------------------------------------
 * EXPORTACIÓN DE TABLAS A CSV — un único motor para todo el sistema
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   Solo `Admin/logs.php` podía exportar, y lo hacía con su propio bloque
 *   `fputcsv`. Cada módulo nuevo tendría que copiarlo, y cada copia se
 *   desviaría: unas usarían coma y otras punto y coma, unas pondrían BOM y
 *   otras no, y casi ninguna se acordaría de escapar el caso que rompe Excel.
 *
 *   Aquí vive una vez: el motor, el catálogo de tablas y las reglas de seguridad.
 *
 * -----------------------------------------------------------------------------
 * LO QUE ESTE ARCHIVO PROTEGE (Y NO ES COSA MENOR)
 * -----------------------------------------------------------------------------
 *   1. INYECCIÓN DE FÓRMULAS EN EXCEL/LOGSHEET.
 *      Una celda que empieza por `=`, `+`, `-` o `@` NO es texto: Excel la
 *      ejecuta. Si alguien guarda una ruta llamada
 *      `=HYPERLINK("http://x","clic")` o introduce un nombre `=cmd|'…'!A0`, al
 *      abrir el CSV se ejecuta ese código en la máquina del usuario.
 *      Aquí cada celda que empiece por esos caracteres se antepone una apóstrofo,
 *      que es la forma estándar de neutralizarlo sin alterar lo que se ve.
 *
 *   2. BOM UTF-8.
 *      Sin él, Excel abre los acentos y la eñe como caracteres rotos.
 *
 *   3. NOMBRE DE ARCHIVO.
 *      Se construye desde un slug interno, nunca desde texto del usuario: un
 *      `Content-Disposition` con comillas o saltos de línea es una inyección de
 *      cabeceras.
 *
 *   4. NUNCA se exportan contraseñas ni datos sensibles.
 *      `usuario.pass_usu` no aparece en ninguna definición de tabla, y el
 *      catálogo es exhaustivo: si una columna no está declarada, no sale.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class ExportService
{
    /** Separador `;`: es el que Excel reconoce sin asistente de importación. */
    private const SEPARADOR = ';';

    /** Tope de filas por exportación. */
    public const MAX_FILAS = 20000;

    /* ================================================================== */
    /* Catálogo de tablas exportables                                      */
    /* ================================================================== */

    /**
     * Definición de cada tabla: qué permiso exige, qué columnas lleva y cómo
     * se obtiene cada fila.
     *
     * @return array<string,array{etiqueta:string, permiso:?string, columnas:array<int,string>, sql:string, params:array}>
     */
    public static function tablas(): array
    {
        return [
            /* ---------------------------------------------------------------- */
            'rutas' => [
                'etiqueta' => 'rutas',
                'permiso'  => 'rutas',
                'columnas' => ['id_rut', 'nom_rut', 'ori_rut', 'des_rut', 'dis_rut', 'val_rut', 'hora_salida', 'duracion_min', 'estado', 'img_rut'],
                'sql' => 'SELECT id_rut, nom_rut, ori_rut, des_rut, dis_rut, val_rut, hora_salida, duracion_min, estado, img_rut
                            FROM rutas ORDER BY nom_rut ASC',
                'params' => [],
            ],

            /* ---------------------------------------------------------------- */
            'vehiculos' => [
                'etiqueta' => 'vehiculos',
                'permiso'  => 'vehiculos',
                'columnas' => ['id_veh', 'pla_veh', 'mode_veh', 'cap_veh', 'est_veh'],
                'sql' => 'SELECT id_veh, pla_veh, mode_veh, cap_veh, est_veh
                            FROM vehiculo ORDER BY pla_veh ASC',
                'params' => [],
            ],

            /* ---------------------------------------------------------------- */
            /* NUNCA `pass_usu`: las contraseñas no salen de la base de datos. */
            'usuarios' => [
                'etiqueta' => 'usuarios',
                'permiso'  => 'usuarios',
                'columnas' => ['id_usu', 'tip_doc_usu', 'num_doc_usu', 'nom_usu', 'corre_usu', 'tel_usu', 'rol', 'estado', 'est_con_usu', 'acepta_politica'],
                'sql' => 'SELECT u.id_usu, u.tip_doc_usu, u.num_doc_usu, u.nom_usu, u.corre_usu, u.tel_usu,
                                 r.nom_rol AS rol, u.estado, u.est_con_usu, u.acepta_politica
                            FROM usuario u
                            LEFT JOIN rol r ON r.id_rol = u.id_rol_usu
                           ORDER BY u.nom_usu ASC',
                'params' => [],
            ],

            /* ---------------------------------------------------------------- */
            'viajes' => [
                'etiqueta' => 'viajes',
                'permiso'  => 'viajes',
                'columnas' => ['id_via', 'nom_via', 'nom_rut', 'ori_rut', 'des_rut', 'fec_via', 'hor_sal_via', 'hor_lleg_via',
                               'conductor', 'pla_veh', 'val_via', 'cup_tot', 'cup_dis', 'est_via', 'salio'],
                'sql' => 'SELECT v.id_via, v.nom_via, rt.nom_rut, rt.ori_rut, rt.des_rut,
                                 v.fec_via, v.hor_sal_via, v.hor_lleg_via,
                                 c.nom_usu AS conductor, ve.pla_veh,
                                 v.val_via, v.cup_tot, v.cup_dis, v.est_via, v.salio
                            FROM viaje v
                            LEFT JOIN rutas rt   ON rt.id_rut  = v.id_rut_via
                            LEFT JOIN usuario c ON c.id_usu   = v.id_usu_via
                            LEFT JOIN vehiculo ve ON ve.id_veh = v.id_veh
                           ORDER BY v.fec_via DESC, v.hor_sal_via DESC',
                'params' => [],
            ],

            /* ---------------------------------------------------------------- */
            'reservas' => [
                'etiqueta' => 'reservas',
                'permiso'  => 'asignaciones',
                'columnas' => ['id_res', 'id_via_res', 'nom_rut', 'nom_via', 'fec_via', 'hor_sal_via',
                               'pasajero', 'num_doc_usu', 'metodo_pago', 'valor_pagado', 'estado_pago',
                               'fecha_pago', 'embarco'],
                'sql' => 'SELECT r.id_res, r.id_via_res, rt.nom_rut, v.nom_via, v.fec_via, v.hor_sal_via,
                                 u.nom_usu AS pasajero, u.num_doc_usu,
                                 r.metodo_pago, r.valor_pagado, r.estado_pago, r.fecha_pago, r.embarco
                            FROM reserva r
                            LEFT JOIN usuario u ON u.id_usu   = r.id_usu_res
                            LEFT JOIN viaje v   ON v.id_via   = r.id_via_res
                            LEFT JOIN rutas rt  ON rt.id_rut  = v.id_rut_via
                           ORDER BY r.fech_res DESC',
                'params' => [],
            ],

            /* ---------------------------------------------------------------- */
            'reportes' => [
                'etiqueta' => 'reportes-de-pasajeros',
                'permiso'  => 'reportes_pasajeros',
                'columnas' => ['id_rep', 'fecha', 'estado', 'pasajero', 'num_doc_usu', 'id_via_rep', 'nom_via', 'descripcion'],
                'sql' => 'SELECT rp.id_rep, rp.fecha, rp.estado,
                                 u.nom_usu AS pasajero, u.num_doc_usu,
                                 rp.id_via_rep, v.nom_via, rp.descripcion
                            FROM reportes_pasajeros rp
                            LEFT JOIN usuario u ON u.id_usu = rp.id_usu_rep
                            LEFT JOIN viaje v   ON v.id_via = rp.id_via_rep
                           ORDER BY rp.fecha DESC',
                'params' => [],
            ],

            /* ---------------------------------------------------------------- */
            'calificaciones' => [
                'etiqueta' => 'calificaciones',
                'permiso'  => 'ranking_conductores',
                'columnas' => ['id_cal', 'fec_cal', 'pun_cal', 'com_cal', 'id_via_cal', 'nom_rut', 'conductor', 'pasajero', 'num_doc_usu'],
                'sql' => 'SELECT c.id_cal, c.fec_cal, c.pun_cal, c.com_cal, c.id_via_cal,
                                 rt.nom_rut, cd.nom_usu AS conductor, rem.nom_usu AS pasajero, rem.num_doc_usu
                            FROM calificacion c
                            LEFT JOIN viaje v   ON v.id_via  = c.id_via_cal
                            LEFT JOIN rutas rt  ON rt.id_rut = v.id_rut_via
                            LEFT JOIN usuario cd ON cd.id_usu = c.id_usu_des
                            LEFT JOIN usuario rem ON rem.id_usu = c.id_usu_rem
                           ORDER BY c.fec_cal DESC',
                'params' => [],
            ],

            /* ---------------------------------------------------------------- */
            /* CONDUCTOR · solo sus propios viajes: se filtra por el usuario. */
            'mis_viajes' => [
                'etiqueta' => 'mis-viajes',
                'permiso'  => 'mis_viajes',
                'soloPropias' => true,
                'columnas' => ['id_via', 'nom_rut', 'des_rut', 'fec_via', 'hor_sal_via', 'pla_veh', 'val_via',
                               'reservas', 'cup_dis', 'est_via'],
                'sql' => 'SELECT v.id_via, rt.nom_rut, rt.des_rut, v.fec_via, v.hor_sal_via, ve.pla_veh,
                                 v.val_via,
                                 (SELECT COUNT(*) FROM reserva rr WHERE rr.id_via_res = v.id_via AND rr.estado_pago <> ?) AS reservas,
                                 v.cup_dis, v.est_via
                            FROM viaje v
                            LEFT JOIN rutas rt     ON rt.id_rut  = v.id_rut_via
                            LEFT JOIN vehiculo ve ON ve.id_veh  = v.id_veh
                           ORDER BY v.fec_via DESC, v.hor_sal_via DESC',
                'params' => [Config::RES_CANCELADA],
            ],

            /* ---------------------------------------------------------------- */
            /* PASAJERO · solo sus propias reservas. */
            'mis_reservas' => [
                'etiqueta' => 'mis-reservas',
                'permiso'  => 'reservas',
                'soloPropias' => true,
                'columnas' => ['id_res', 'nom_rut', 'fec_via', 'hor_sal_via', 'nom_via', 'viaje_estado',
                               'metodo_pago', 'valor_pagado', 'estado_pago', 'fecha_reserva'],
                'sql' => 'SELECT r.id_res, rt.nom_rut, v.fec_via, v.hor_sal_via, v.nom_via, v.est_via AS viaje_estado,
                                 r.metodo_pago, r.valor_pagado, r.estado_pago, r.fech_res AS fecha_reserva
                            FROM reserva r
                            LEFT JOIN viaje v  ON v.id_via  = r.id_via_res
                            LEFT JOIN rutas rt ON rt.id_rut = v.id_rut_via
                           ORDER BY r.fech_res DESC',
                'params' => [],
            ],
        ];
    }

    /** Tablas que el usuario autenticado puede exportar AHORA MISMO. */
    public static function tablasDisponibles(): array
    {
        $disponibles = [];
        foreach (self::tablas() as $clave => $cfg) {
            if (self::puedeExportar($clave)) {
                $disponibles[$clave] = $cfg['etiqueta'];
            }
        }
        return $disponibles;
    }

    public static function existe(string $tabla): bool
    {
        return array_key_exists($tabla, self::tablas());
    }

    /** ¿La sesión actual tiene permiso para exportar esta tabla? */
    public static function puedeExportar(string $tabla): bool
    {
        $cfg = self::tablas()[$tabla] ?? null;
        if ($cfg === null) {
            return false;
        }

        // Control por rol para las tablas que son «las mías».
        if (!empty($cfg['soloPropias'])) {
            $rol = Auth::rol();
            if (!in_array($rol, [Config::ROL_CONDUCTOR, Config::ROL_PASAJERO], true)) {
                // El administrador ya tiene las tablas completas.
                return true;
            }
        }

        $permiso = $cfg['permiso'];
        return $permiso === null ? true : Auth::tieneAcceso((string)$permiso);
    }

    /* ================================================================== */
    /* Generación                                                          */
    /* ================================================================== */

    /**
     * Devuelve el CSV como texto.
     *
     * Se separa de `descargar()` para poder probarlo sin cabeceras HTTP ni
     * efectos de salida: una función pura es una función verificable.
     */
    public static function csv(string $tabla, array $filtros = []): array
    {
        $cfg = self::tablas()[$tabla] ?? null;
        if ($cfg === null) {
            return ['ok' => false, 'mensaje' => 'Esa tabla no se puede exportar.', 'csv' => '', 'filas' => 0];
        }

        if (!self::puedeExportar($tabla)) {
            return ['ok' => false, 'mensaje' => 'No tienes permiso para exportar estos datos.', 'csv' => '', 'filas' => 0];
        }

        $params = (array)($cfg['params'] ?? []);
        if (!empty($cfg['soloPropias'])) {
            $params[] = Auth::id();
        }

        $sql = (string)$cfg['sql'];
        $texto = (string)($filtros['q'] ?? '');
        if ($texto !== '') {
            // La búsqueda en vivo del frontend llega como `q` y se aplica en el
            // servidor, para que lo exportado SEA lo que el usuario está viendo.
            $columnasBuscables = array_slice((array)$cfg['columnas'], 0, 6);
            $predicados = [];
            foreach ($columnasBuscables as $columna) {
                $predicados[] = "COALESCE(CAST({$columna} AS CHAR), '') LIKE ?";
                $params[] = '%' . $texto . '%';
            }
            if ($predicados !== []) {
                $sql .= ' AND (' . implode(' OR ', $predicados) . ')';
            }
        }

        try {
            $filas = Database::all($sql . ' LIMIT ' . self::MAX_FILAS, $params);
        } catch (Throwable $e) {
            error_log('[SGET][ExportService] ' . $tabla . ': ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo generar el archivo.', 'csv' => '', 'filas' => 0];
        }

        $manilla = fopen('php://temp', 'r+');
        // BOM: sin él Excel rompe los acentos y la eñe.
        fwrite($manilla, "\xEF\xBB\xBF");
        fputcsv($manilla, array_map([self::class, 'etiquetaColumna'], (array)$cfg['columnas']), self::SEPARADOR);

        $encabezado = (array)$cfg['columnas'];
        foreach ($filas as $fila) {
            $celdas = [];
            foreach ($encabezado as $columna) {
                $celdas[] = self::celda($fila[$columna] ?? null, $columna);
            }
            fputcsv($manilla, $celdas, self::SEPARADOR);
        }

        rewind($manilla);
        $csv = (string)stream_get_contents($manilla);
        fclose($manilla);

        return [
            'ok'      => true,
            'mensaje' => '',
            'csv'     => $csv,
            'filas'   => count($filas),
            'total'   => count($filas),
            'truncado' => count($filas) >= self::MAX_FILAS,
        ];
    }

    /** Manda el CSV al navegador como descarga. */
    public static function descargar(string $tabla, array $filtros = []): bool
    {
        $r = self::csv($tabla, $filtros);
        if (!$r['ok']) {
            return false;
        }

        $cfg = self::tablas()[$tabla];
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)$cfg['etiqueta']));
        $nombre = 'sget-' . ($slug !== '' ? $slug : 'tabla')
            . '-' . date('Ymd-His') . '.csv';

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            // Nombre generado desde un slug, nunca desde texto del usuario.
            header('Content-Disposition: attachment; filename="' . $nombre . '"');
            header('Content-Length: ' . strlen($r['csv']));
            header('Cache-Control: no-store');
        }

        echo $r['csv'];

        if (class_exists('Logger')) {
            Logger::registrar(Database::pdo(), 'EXPORTAR_TABLA', sprintf(
                'Se exportaron %d fila(s) de la tabla «%s» por %s.',
                $r['filas'],
                (string)$cfg['etiqueta'],
                Auth::nombre()
            ));
        }

        return true;
    }

    /* ================================================================== */
    /* Formato de celdas                                                  */
    /* ================================================================== */

    /**
     * Convierte un valor de la base de datos en una celda de CSV segura.
     *
     * LA INYECCIÓN DE FÓRMULAS ES EL PUNTO CLAVE
     *   `=`, `+`, `-` y `@` al principio de una celda NO son texto para Excel:
     *   los interpreta como fórmula y la ejecuta al abrir el archivo. Un nombre
     *   de usuario o el texto de un reporte los escribe una persona sin saber
     *   que estaba escribiendo código.
     *
     *   La mitigación estándar es anteponer una apóstrofo, que Excel interpreta
     *   como «esto es texto». No cambia lo que se ve ni cómo se compara.
     */
    public static function celda($valor, string $columna = ''): string
    {
        // Fechas y horas: formato legible y sin sorpresas de zona horaria.
        $texto = match (true) {
            $valor === null => '',
            is_bool($valor)   => $valor ? 'Sí' : 'No',
            is_float($valor)  => rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ','),
            default           => (string)$valor,
        };

        $texto = str_replace(["\r", "\n", "\t"], ' ', $texto);
        $texto = trim($texto);

        if ($texto === '') {
            return '';
        }

        if (preg_match('/^[=+\-@\t\r]/', $texto)) {
            $texto = "'" . $texto;
        }

        // Columnas que son DECIMAL en la base de datos y llegan como texto:
        // se formatean como número para que la hoja los sume.
        if (preg_match('/^(val_rut|dis_rut|val_via|valor_pagado|promedio|pun_cal)$/', $columna)
            && is_numeric(str_replace(',', '.', $texto))) {
            return str_replace(',', '.', $texto);
        }

        return $texto;
    }

    /** Nombre legible de una columna para la cabecera del CSV. */
    public static function etiquetaColumna(string $columna): string
    {
        $mapa = [
            'id_rut' => 'ID Ruta', 'nom_rut' => 'Ruta', 'ori_rut' => 'Origen', 'des_rut' => 'Destino',
            'dis_rut' => 'Distancia (km)', 'val_rut' => 'Tarifa', 'hora_salida' => 'Hora de salida',
            'duracion_min' => 'Duración (min)', 'estado' => 'Estado', 'img_rut' => 'Imagen',

            'id_veh' => 'ID Vehículo', 'pla_veh' => 'Placa', 'mode_veh' => 'Modelo',
            'cap_veh' => 'Puestos', 'est_veh' => 'Estado',

            'id_usu' => 'ID Usuario', 'tip_doc_usu' => 'Tipo de documento', 'num_doc_usu' => 'Documento',
            'nom_usu' => 'Nombre', 'corre_usu' => 'Correo', 'tel_usu' => 'Teléfono', 'rol' => 'Rol',
            'est_con_usu' => 'Disponibilidad del conductor', 'acepta_politica' => 'Acepta la política',

            'id_via' => 'ID Viaje', 'nom_via' => 'Viaje', 'fec_via' => 'Fecha',
            'hor_sal_via' => 'Hora de salida', 'hor_lleg_via' => 'Hora de llegada',
            'conductor' => 'Conductor', 'pla_veh' => 'Placa', 'cup_tot' => 'Puestos totales',
            'cup_dis' => 'Puestos libres', 'salio' => '¿Salió?',

            'id_res' => 'ID Reserva', 'id_via_res' => 'ID Viaje', 'fec_res' => 'Fecha del viaje',
            'pasajero' => 'Pasajero', 'metodo_pago' => 'Método de pago', 'valor_pagado' => 'Valor pagado',
            'estado_pago' => 'Estado del pago', 'fecha_pago' => 'Fecha del pago',
            'embarco' => 'Embarcó', 'fecha_reserva' => 'Fecha de la reserva', 'viaje_estado' => 'Estado del viaje',

            'id_rep' => 'Folio', 'fecha' => 'Fecha', 'descripcion' => 'Descripción',

            'id_cal' => 'ID Calificación', 'fec_cal' => 'Fecha', 'pun_cal' => 'Puntaje',
            'com_cal' => 'Comentario', 'id_via_cal' => 'ID Viaje', 'nom_via' => 'Nombre del viaje',
            'id_via_rep' => 'ID Viaje',

            'reservas' => 'Reservas',
        ];

        return $mapa[$columna] ?? ucfirst(str_replace('_', ' ', $columna));
    }
}
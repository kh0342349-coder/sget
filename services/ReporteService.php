<?php
/**
 * services/ReporteService.php
 * -----------------------------------------------------------------------------
 * MÓDULO: REPORTES Y QUEJAS DE LOS PASAJEROS
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   La tabla `reportes_pasajeros` ya estaba en el esquema, y había una página
 *   (Admin/reportes_pasajeros.php) para gestionarla… con un formulario que
 *   enviaba a `actualizar_reporte.php`, un archivo que NO existe, y que no
 *   estaba enlazada desde ningún sitio. Es decir: el módulo estaba muerto.
 *
 *   Aquí vive la lógica: quién puede reportar, los estados del flujo y el aviso
 *   al pasajero cuando se resuelve.
 *
 * FLUJO
 *   Pendiente → Asignado → Completado      (o Cerrado si no procede)
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class ReporteService
{
    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_ASIGNADO   = 'asignado';
    public const ESTADO_COMPLETADO = 'completado';
    public const ESTADO_CERRADO    = 'cerrado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_ASIGNADO,
        self::ESTADO_COMPLETADO,
        self::ESTADO_CERRADO,
    ];

    /* ================================================================== */
    /* Consulta                                                            */
    /* ================================================================== */

    public static function todos(int $limite = 200): array
    {
        return Database::all(
            "SELECT rep.*, u.nom_usu AS pasajero, u.num_doc_usu, v.nom_via,
                    DATE_FORMAT(rep.fecha, '%d/%m/%Y %H:%i') AS fecha_legible
               FROM reportes_pasajeros rep
               LEFT JOIN usuario u ON u.id_usu = rep.id_usu_rep
               LEFT JOIN viaje v   ON v.id_via = rep.id_via_rep
              ORDER BY FIELD(rep.estado, 'pendiente', 'asignado', 'completado', 'cerrado'),
                       rep.fecha DESC
              LIMIT " . (int)$limite
        );
    }

    public static function porId(int $id): ?array
    {
        return Database::one("SELECT * FROM reportes_pasajeros WHERE id_rep = ?", [$id]);
    }

    /** Reportes abiertos de un pasajero (los que ve en su panel). */
    public static function porPasajero(int $idPasajero, int $limite = 50): array
    {
        return Database::all(
            "SELECT rep.*, v.nom_via
               FROM reportes_pasajeros rep
               LEFT JOIN viaje v ON v.id_via = rep.id_via_rep
              WHERE rep.id_usu_rep = ?
              ORDER BY rep.fecha DESC
              LIMIT " . (int)$limite,
            [$idPasajero]
        );
    }

    public static function resumen(): array
    {
        $filas = Database::all("SELECT estado, COUNT(*) n FROM reportes_pasajeros GROUP BY estado");
        $mapa  = [];
        foreach ($filas as $f) $mapa[(string)$f['estado']] = (int)$f['n'];

        $total = array_sum($mapa);
        return [
            'total'      => $total,
            'pendientes' => $mapa[self::ESTADO_PENDIENTE] ?? 0,
            'asignados'  => $mapa[self::ESTADO_ASIGNADO] ?? 0,
            'completados'=> $mapa[self::ESTADO_COMPLETADO] ?? 0,
            'cerrados'   => $mapa[self::ESTADO_CERRADO] ?? 0,
        ];
    }

    /* ================================================================== */
    /* Escritura                                                           */
    /* ================================================================== */

    /**
     * Un pasajero reporta una incidencia sobre un viaje suyo.
     *
     * El viaje es OBLIGATORIO: `reportes_pasajeros.id_via_rep` es NOT NULL y,
     * sobre todo, un reporte sin viaje no se puede investigating después. El
     * flujo es el que pide el dominio:
     *
     *     Pasajero → elige viaje/reserva → crea reporte → Admin revisa
     *
     * @return array{ok:bool, mensaje:string, id:int}
     */
    public static function crear(int $idPasajero, int $idViaje, string $descripcion): array
    {
        $fallo = static fn(string $m): array => ['ok' => false, 'id' => 0, 'mensaje' => $m];

        if ($idPasajero <= 0) {
            return $fallo('Inicia sesión para reportar.');
        }

        $descripcion = trim($descripcion);
        if (mb_strlen($descripcion) < 15) {
            return $fallo('Describe el problema con al menos 15 caracteres.');
        }
        if (mb_strlen($descripcion) > 500) {
            $descripcion = mb_substr($descripcion, 0, 500);
        }

        if ($idViaje <= 0) {
            return $fallo('Selecciona el viaje sobre el que quieres reportar.');
        }

        /* Control de propiedad: el reporte tiene que versar sobre un viaje del
           propio pasajero. Antes bastaba con que el id fuera de un viaje
           cualquiera. */
        $tiene = (int) Database::scalar(
            'SELECT COUNT(*) FROM reserva WHERE id_usu_res = ? AND id_via_res = ?',
            [$idPasajero, $idViaje]
        );
        if ($tiene === 0) {
            return $fallo('Solo puedes reportar sobre un viaje tuyo.');
        }

        try {
            $id = Database::insert(
                "INSERT INTO reportes_pasajeros (id_usu_rep, id_via_rep, descripcion, estado, fecha)
                 VALUES (?, ?, ?, ?, NOW())",
                [$idPasajero, $idViaje, $descripcion, self::ESTADO_PENDIENTE]
            );
        } catch (Throwable $e) {
            error_log('[SGET][ReporteService::crear] ' . $e->getMessage());
            return $fallo('No se pudo registrar el reporte.');
        }

        // Aviso inmediato al propio pasajero con el folio
        NotificacionService::enviar($idPasajero, NotificacionService::TIPO_GENERAL,
            'Reporte recibido · folio #' . $id,
            "Registramos tu reporte con el folio #$id.\n\n" .
            "La administración lo está revisando y podrás seguir su estado desde tu panel. " .
            "Si es urgente, preséntate en la terminal con tu documento.\n\n— Equipo SGET",
            $idViaje
        );

        Logger::registrar(Database::pdo(), 'CREAR_REPORTE', sprintf(
            'Reporte #%d creado por el pasajero #%d sobre el viaje #%d.',
            $id, $idPasajero, $idViaje
        ));

        return ['ok' => true, 'id' => $id, 'mensaje' => 'Reporte registrado. Te avisaremos cuando avance.'];
    }

    /** Viajes sobre los que este pasajero puede abrir un reporte. */
    public static function viajesReportables(int $idPasajero, int $limite = 40): array
    {
        return Database::all(
            "SELECT v.id_via, v.fec_via, v.hor_sal_via, v.est_via, r.nom_rut,
                    (SELECT COUNT(*) FROM reportes_pasajeros rp
                      WHERE rp.id_usu_rep = ? AND rp.id_via_rep = v.id_via) AS ya_reportado
               FROM reserva res
               INNER JOIN viaje v    ON v.id_via    = res.id_via_res
               LEFT  JOIN rutas r    ON r.id_rut    = v.id_rut_via
              WHERE res.id_usu_res = ? AND res.estado_pago <> ?
              GROUP BY v.id_via, v.fec_via, v.hor_sal_via, v.est_via, r.nom_rut
              ORDER BY v.fec_via DESC, v.hor_sal_via DESC
              LIMIT " . (int)$limite,
            [$idPasajero, $idPasajero, Config::RES_CANCELADA]
        );
    }

    /**
     * Cambia el estado de un reporte (lo usa el administrador).
     *
     * @return array{ok:bool, mensaje:string}
     */
    public static function actualizar(int $id, string $estado, int $idViaje = 0): array
    {
        $reporte = self::porId($id);
        if (!$reporte) {
            return ['ok' => false, 'mensaje' => 'El reporte no existe.'];
        }
        if (!in_array($estado, self::ESTADOS, true)) {
            return ['ok' => false, 'mensaje' => 'El estado no es válido.'];
        }

        $idViaje = $idViaje > 0 ? $idViaje : (int)$reporte['id_via_rep'];

        try {
            Database::query(
                "UPDATE reportes_pasajeros SET estado = ?, id_via_rep = ? WHERE id_rep = ?",
                [$estado, $idViaje, $id]
            );
        } catch (Throwable $e) {
            error_log('[SGET][ReporteService::actualizar] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo actualizar el reporte.'];
        }

        // El pasajero sigue el avance desde su buzón
        $mensajes = [
            self::ESTADO_ASIGNADO   => 'Tu reporte está siendo revisado por el equipo.',
            self::ESTADO_COMPLETADO => 'Tu reporte se resolvió. Si el problema continúa, escríbenos de nuevo.',
            self::ESTADO_CERRADO    => 'Tu reporte se cerró.',
        ];

        if (isset($mensajes[$estado])) {
            NotificacionService::enviar(
                (int)$reporte['id_usu_rep'],
                NotificacionService::TIPO_GENERAL,
                'Reporte #' . $id . ' · ' . self::etiquetaEstado($estado),
                $mensajes[$estado],
                $idViaje > 0 ? $idViaje : null,
                'reporte:' . $id . ':' . $estado
            );
        }

        Logger::registrar(Database::pdo(), 'ACTUALIZAR_REPORTE', sprintf(
            'Reporte #%d pasó a «%s» por %s.', $id, $estado, Auth::nombre()
        ));

        return ['ok' => true, 'mensaje' => 'Reporte #' . $id . ' actualizado a «' . self::etiquetaEstado($estado) . '».'];
    }

    public static function eliminar(int $id): array
    {
        if (!self::porId($id)) {
            return ['ok' => false, 'mensaje' => 'El reporte no existe.'];
        }
        Database::query("DELETE FROM reportes_pasajeros WHERE id_rep = ?", [$id]);
        Logger::registrar(Database::pdo(), 'ELIMINAR_REPORTE', sprintf('Reporte #%d eliminado por %s.', $id, Auth::nombre()));
        return ['ok' => true, 'mensaje' => 'Reporte eliminado.'];
    }

    /* ================================================================== */
    /* Presentación                                                        */
    /* ================================================================== */

    public static function etiquetaEstado(string $estado): string
    {
        return [
            self::ESTADO_PENDIENTE  => 'Pendiente',
            self::ESTADO_ASIGNADO   => 'Asignado',
            self::ESTADO_COMPLETADO => 'Completado',
            self::ESTADO_CERRADO    => 'Cerrado',
        ][strtolower($estado)] ?? ucfirst($estado);
    }

    public static function claseEstado(string $estado): string
    {
        return [
            self::ESTADO_PENDIENTE  => 'sget-badge--error',
            self::ESTADO_ASIGNADO   => 'sget-badge--aviso',
            self::ESTADO_COMPLETADO => 'sget-badge--exito',
            self::ESTADO_CERRADO    => 'sget-badge--neutro',
        ][strtolower($estado)] ?? 'sget-badge--neutro';
    }
}

<?php
/**
 * services/CalificacionService.php
 * -----------------------------------------------------------------------------
 * MÓDULO: CALIFICACIONES DE CONDUCTORES
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   El pasajero podía abrir el formulario de calificación… y el formulario
 *   enviaba a `guardar_calificacion.php`, un archivo que NO existía. Es decir:
 *   el sistema de reseñas estaba muerto. El admin tampoco tenía forma de
 *   analizarlo porque la escritura estaba dispersa en páginas heredadas.
 *
 *   Aquí vive la única lógica: quién puede calificar a quién, una sola vez por
 *   viaje, y el aviso al conductor.
 *
 * REGLAS
 *   · Solo el pasajero que TIENE reserva confirmada en ese viaje puede calificar.
 *   · Una calificación por pasajero y viaje (no se puede volver a enviar para
 *     «subir» la nota).
 *   · El puntaje es de 1 a 5 y el comentario es opcional (máx. 255).
 *   · Calificar notifica al conductor, que es quien recibe el impacto real.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class CalificacionService
{
    public const PUNTOS = [1, 2, 3, 4, 5];

    /* ================================================================== */
    /* Consulta                                                            */
    /* ================================================================== */

    /**
     * ¿Este pasajero puede calificar este viaje?
     *
     * Reglas (todas en backend; el botón del formulario no protege nada):
     *   1. Que el viaje exista y tenga conductor asignado.
     *   2. Que el pasajero tenga una reserva VIVA en ese viaje (no cancelada).
     *   3. Que el viaje esté FINALIZADO. No se puede calificar un viaje
     *      programado ni uno en curso: la nota mide una experiencia que aún no
     *      ha ocurrido. Un viaje cancelado tampoco.
     *   4. Que no exista ya una calificación de ese pasajero para ese viaje.
     *
     * @return array{ok:bool, motivo?:string, conductor?:int, ruta?:string}
     */
    public static function puedeCalificar(int $idPasajero, int $idViaje): array
    {
        if ($idPasajero <= 0 || $idViaje <= 0) {
            return ['ok' => false, 'motivo' => 'Datos incompletos.'];
        }

        /* La comprobación en PHP da un mensaje claro al usuario, pero NO es la
           barrera: dos peticiones simultáneas pasarían las dos por aquí. La
           garantía real es la restricción `UNIQUE (id_via_cal, id_usu_rem)` de
           la tabla `calificacion`, y el error 1062 se traduce abajo. */
        $yaCalifico = (int) Database::scalar(
            "SELECT COUNT(*) FROM calificacion WHERE id_usu_rem = ? AND id_via_cal = ?",
            [$idPasajero, $idViaje]
        );
        if ($yaCalifico > 0) {
            return ['ok' => false, 'motivo' => 'Ya calificaste este viaje.'];
        }

        $reserva = Database::one(
            "SELECT v.id_usu_via, v.est_via, r.nom_rut
               FROM reserva res
               INNER JOIN viaje v ON v.id_via = res.id_via_res
               LEFT JOIN rutas r   ON r.id_rut = v.id_rut_via
              WHERE res.id_usu_res = ? AND res.id_via_res = ? AND res.estado_pago <> ?
              LIMIT 1",
            [$idPasajero, $idViaje, Config::RES_CANCELADA]
        );

        if (!$reserva) {
            return ['ok' => false, 'motivo' => 'Necesitas una reserva tuya en ese viaje para calificarlo.'];
        }

        /* El viaje tiene que haber TERMINADO. Antes no se comprobaba y se podía
           calificar un viaje que aún no salía, lo que convertía el ranking de
           conductores en ruido. */
        $estado = (string)$reserva['est_via'];
        if ($estado === Config::VIA_CANCELADO) {
            return ['ok' => false, 'motivo' => 'Ese viaje fue cancelado, así que no se puede calificar.'];
        }
        if ($estado !== Config::VIA_FINALIZADO) {
            return ['ok' => false, 'motivo' => 'Solo puedes calificar un viaje cuando ya haya terminado.'];
        }

        if ((int)$reserva['id_usu_via'] <= 0) {
            return ['ok' => false, 'motivo' => 'Ese viaje no tiene conductor asignado.'];
        }

        return ['ok' => true, 'conductor' => (int)$reserva['id_usu_via'], 'ruta' => (string)$reserva['nom_rut']];
    }

    /**
     * Viajes del pasajero que puede calificar (con y sin nota puesta).
     * Solo los FINALIZADOS y sin una calificación previa registrada por él.
     */
    public static function pendientes(int $idPasajero, int $limite = 50): array
    {
        return Database::all(
            "SELECT DISTINCT v.id_via, v.fec_via, v.hor_sal_via, v.id_usu_via,
                    r.nom_rut, u.nom_usu AS conductor,
                    (SELECT c.pun_cal FROM calificacion c
                      WHERE c.id_via_cal = v.id_via AND c.id_usu_rem = ? LIMIT 1) AS pun_cal,
                    (SELECT c.com_cal FROM calificacion c
                      WHERE c.id_via_cal = v.id_via AND c.id_usu_rem = ? LIMIT 1) AS com_cal
               FROM reserva res
               INNER JOIN viaje v   ON v.id_via   = res.id_via_res
               INNER JOIN usuario u ON u.id_usu   = v.id_usu_via
               LEFT JOIN rutas r    ON r.id_rut   = v.id_rut_via
              WHERE res.id_usu_res = ?
                AND res.estado_pago <> ?
                AND v.est_via = ?
              ORDER BY v.fec_via DESC, v.hor_sal_via DESC
              LIMIT " . (int)$limite,
            [$idPasajero, $idPasajero, $idPasajero, Config::RES_CANCELADA, Config::VIA_FINALIZADO]
        );
    }

    /** Reseñas recibidas por un conductor (`id_usu_des` = quien fue calificado). */
    public static function deConductor(int $idConductor, int $limite = 100): array
    {
        return Database::all(
            "SELECT c.id_cal, c.pun_cal, c.com_cal, c.fec_cal,
                    u.nom_usu AS pasajero, r.nom_rut
               FROM calificacion c
               INNER JOIN usuario u ON u.id_usu  = c.id_usu_rem
               LEFT JOIN viaje v   ON v.id_via  = c.id_via_cal
               LEFT JOIN rutas r   ON r.id_rut  = v.id_rut_via
              WHERE c.id_usu_rem = ?
              ORDER BY c.fec_cal DESC
              LIMIT " . (int)$limite,
            [$idConductor]
        );
    }

    /** Promedio y total de calificación de un conductor. */
    public static function resumen(int $idConductor): array
    {
        $fila = Database::one(
            "SELECT COUNT(*) total, ROUND(AVG(pun_cal), 2) promedio,
                    SUM(CASE WHEN pun_cal >= 4 THEN 1 ELSE 0 END) buenas,
                    SUM(CASE WHEN pun_cal <= 2 THEN 1 ELSE 0 END) malas
               FROM calificacion WHERE id_usu_des = ?",
            [$idConductor]
        ) ?: ['total' => 0, 'promedio' => null, 'buenas' => 0, 'malas' => 0];

        $fila['total']    = (int)$fila['total'];
        $fila['buenas']   = (int)$fila['buenas'];
        $fila['malas']    = (int)$fila['malas'];
        $fila['promedio'] = $fila['promedio'] !== null ? (float)$fila['promedio'] : null;

        return $fila;
    }

    /** Tablero de conductores mejor y peor valorados (para el admin). */
    public static function ranking(int $limite = 20): array
    {
        return Database::all(
            "SELECT u.id_usu, u.nom_usu,
                    COUNT(c.id_cal) reseñas,
                    ROUND(AVG(c.pun_cal), 2) promedio
               FROM usuario u
               INNER JOIN calificacion c ON c.id_usu_des = u.id_usu
              WHERE u.id_rol_usu = ?
              GROUP BY u.id_usu, u.nom_usu
              ORDER BY promedio DESC, reseñas DESC
              LIMIT " . (int)$limite,
            [Config::ROL_CONDUCTOR]
        );
    }

    /* ================================================================== */
    /* Escritura                                                           */
    /* ================================================================== */

    /**
     * Registra la calificación de un pasajero.
     *
     * @return array{ok:bool, mensaje:string}
     */
    public static function registrar(int $idPasajero, int $idViaje, int $puntos, string $comentario = ''): array
    {
        if (!in_array($puntos, self::PUNTOS, true)) {
            return ['ok' => false, 'mensaje' => 'La puntuación debe estar entre 1 y 5 estrellas.'];
        }

        $permiso = self::puedeCalificar($idPasajero, $idViaje);
        if (!$permiso['ok']) {
            return ['ok' => false, 'mensaje' => $permiso['motivo']];
        }

        $comentario = mb_substr(trim($comentario), 0, 255);

        try {
            Database::insert(
                "INSERT INTO calificacion (id_via_cal, id_usu_rem, id_usu_des, pun_cal, com_cal)
                 VALUES (?, ?, ?, ?, ?)",
                [$idViaje, $idPasajero, (int)$permiso['conductor'], $puntos, $comentario !== '' ? $comentario : null]
            );
        } catch (Throwable $e) {
            /* 1062 = duplicate key. Es la BARREERA REAL contra la doble
               calificación: la comprobación de `puedeCalificar()` se ejecuta
               fuera de cualquier transacción, así que dos peticiones simultáneas
               la superarían las dos. La restricción UNIQUE del motor es la que
               resuelve la carrera, y aquí se traduce a un mensaje claro. */
            if (Database::errorEs($e, [1062])) {
                return ['ok' => false, 'mensaje' => 'Ya calificaste este viaje.'];
            }
            error_log('[SGET][CalificacionService::registrar] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo guardar la calificación.'];
        }

        NotificacionService::notificarCalificacion(
            (int)$permiso['conductor'],
            $idViaje,
            (string)$permiso['ruta'],
            $puntos,
            $comentario
        );

        Logger::registrar(Database::pdo(), 'CALIFICAR', sprintf(
            'Pasajero #%d calificó con %d/5 el viaje #%d (conductor #%d).',
            $idPasajero, $puntos, $idViaje, (int)$permiso['conductor']
        ));

        return ['ok' => true, 'mensaje' => '¡Gracias! Tu calificación ya fue registrada.'];
    }

    /** Etiqueta textual de la nota (para las tablas y los listados). */
    public static function etiquetaPuntos(int $puntos): string
    {
        return $puntos . ' de 5';
    }

    public static function clasePuntos(int $puntos): string
    {
        if ($puntos >= 4) return 'sget-badge--exito';
        if ($puntos >= 3) return 'sget-badge--aviso';
        return 'sget-badge--error';
    }
}

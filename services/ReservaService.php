<?php
/**
 * services/ReservaService.php
 * -----------------------------------------------------------------------------
 * MÓDULO: RESERVAS Y RECAUDO
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   La reserva se escribía desde tres sitios distintos con SQL propio:
 *     · Admin/asignaciones.php   (recaudo en terminal)
 *     · Pasajero/procesar_reserva.php (reserva online)
 *     · y se leía en dos módulos más.
 *
 *   Y ahí estaba el bug más grave del sistema: el recaudo del admin insertaba
 *   `estado_pago = 'Completado'`, un valor que NO existe en el ENUM
 *   ('Pendiente','Confirmada','Cancelada'). El INSERT fallaba, el código no
 *   comprobaba el resultado y aun así pintaba "¡Asignación registrada con
 *   éxito!" con el ticket de la reserva 0. Como además todo lo demás usaba
 *   'Pendiente', nunca hubo una sola reserva 'Confirmada'… y por eso el módulo
 *   de Ganancias mostraba $0 siempre.
 *
 *   Aquí vive la ÚNICA lógica de reserva: capacidad, cobro, cancelación y aviso
 *   al pasajero. Las tres pantallas llaman a este servicio.
 *
 * MODELO DE DATOS
 *   Un PUESTO reservado = una fila en `reserva`. Si un pasajero compra 3
 *   puestos se crean 3 filas. Es lo que ya asumían la ocupación de cupos y los
 *   informes (`cupos_vendidos = COUNT(reservas)`), y se mantiene a propósito:
 *   cambiarlo ahora obligaría a rehacer el histórico de reportes.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class ReservaService
{
    /* ================================================================== */
    /* Consultas                                                           */
    /* ================================================================== */

    /** Cupos que quedan en un viaje (nunca negativo). */
    public static function cuposDisponibles(int $idViaje): int
    {
        $v = Database::one(
            "SELECT cup_tot, cup_dis,
                    (SELECT COUNT(*) FROM reserva r WHERE r.id_via_res = v.id_via AND r.estado_pago <> ?) AS ocupados
               FROM viaje v WHERE v.id_via = ?",
            [Config::RES_CANCELADA, $idViaje]
        );
        if (!$v) return 0;

        $totales = (int)($v['cup_tot'] ?? 0);
        $libres  = $v['cup_dis'] === null ? $totales : (int)$v['cup_dis'];

        // `cup_dis` puede quedar desfasado si se borraron filas a mano: manda
        // el conteo real de reservas vivas.
        $real = $totales - (int)$v['ocupados'];
        return max(0, min($libres, $real));
    }

    /** Datos de reserva + viaje + ruta, para mostrar y notificar. */
    public static function porId(int $idReserva): ?array
    {
        return Database::one(
            "SELECT res.*, v.fec_via, v.hor_sal_via, v.val_via, v.est_via,
                    r.nom_rut, r.ori_rut, r.des_rut,
                    u.nom_usu AS pasajero, u.num_doc_usu, u.tel_usu
               FROM reserva res
               INNER JOIN viaje v   ON v.id_via = res.id_via_res
               LEFT  JOIN rutas r   ON r.id_rut = v.id_rut_via
               INNER JOIN usuario u ON u.id_usu = res.id_usu_res
              WHERE res.id_res = ?",
            [$idReserva]
        );
    }

    /** Reservas de un viaje (recaudo del día). */
    public static function porViaje(int $idViaje): array
    {
        return Database::all(
            "SELECT res.id_res, res.fech_res, res.metodo_pago, res.valor_pagado, res.estado_pago, res.fecha_pago,
                    u.nom_usu AS pasajero, u.num_doc_usu, u.tel_usu
               FROM reserva res
               INNER JOIN usuario u ON u.id_usu = res.id_usu_res
              WHERE res.id_via_res = ?
              ORDER BY res.fech_res DESC, res.id_res DESC",
            [$idViaje]
        );
    }

    /** Historial de reservas de un pasajero. */
    public static function porPasajero(int $idPasajero, int $limite = 200): array
    {
        return Database::all(
            "SELECT res.id_res, res.fech_res, res.estado_pago, res.metodo_pago, res.valor_pagado,
                    v.id_via, v.fec_via, v.hor_sal_via, v.est_via,
                    r.nom_rut, r.ori_rut, r.des_rut,
                    (SELECT COUNT(*) FROM calificacion c
                      WHERE c.id_via_cal = v.id_via AND c.id_usu_rem = res.id_usu_res) AS calificado
               FROM reserva res
               INNER JOIN viaje v ON v.id_via = res.id_via_res
               LEFT  JOIN rutas r ON r.id_rut = v.id_rut_via
              WHERE res.id_usu_res = ?
              ORDER BY v.fec_via DESC, v.hor_sal_via DESC, res.id_res DESC
              LIMIT " . (int)$limite,
            [$idPasajero]
        );
    }

    /** ¿Este pasajero ya tiene puesto en este viaje? */
    public static function pasajeroYaReservo(int $idViaje, int $idPasajero): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM reserva
              WHERE id_via_res = ? AND id_usu_res = ? AND estado_pago <> ?",
            [$idViaje, $idPasajero, Config::RES_CANCELADA]
        );
    }

    public static function metodosPago(): array
    {
        return ['Efectivo', 'Efectivo al Abordar', 'Transferencia', 'Tarjeta', 'Nequi', 'Daviplata', 'PSE'];
    }

    /* ================================================================== */
    /* Creación (reserva online del pasajero)                              */
    /* ================================================================== */

    /**
     * Reserva uno o varios puestos para un pasajero.
     *
     * @param array $op  ['metodo' => string, 'valor' => float, 'confirmar' => bool]
     * @return array{ok:bool, mensaje:string, ids:array<int>, puestos:int}
     */
    public static function crear(int $idViaje, int $idPasajero, int $puestos = 1, array $op = []): array
    {
        $puestos = max(1, $puestos);
        $confirmar = !empty($op['confirmar']);

        $viaje = Database::one(
            "SELECT v.*, r.nom_rut, r.ori_rut, r.des_rut
               FROM viaje v
               LEFT JOIN rutas r ON r.id_rut = v.id_rut_via
              WHERE v.id_via = ?",
            [$idViaje]
        );

        if (!$viaje) {
            return ['ok' => false, 'mensaje' => 'El viaje no existe.', 'ids' => [], 'puestos' => 0];
        }
        if (!in_array((string)$viaje['est_via'], [Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO], true)) {
            return ['ok' => false, 'mensaje' => 'Ese viaje ya no está disponible para reservar.', 'ids' => [], 'puestos' => 0];
        }

        $disponibles = self::cuposDisponibles($idViaje);
        if ($puestos > $disponibles) {
            return ['ok' => false, 'ids' => [], 'puestos' => 0,
                    'mensaje' => "Solo quedan {$disponibles} cupos disponibles en este viaje."];
        }

        $metodo = (string)($op['metodo'] ?? 'Efectivo al Abordar');
        $valor  = (float)($op['valor'] ?? 0);
        $estado = $confirmar ? Config::RES_CONFIRMADA : Config::RES_PENDIENTE;

        try {
            Database::begin();

            $ids = [];
            for ($i = 0; $i < $puestos; $i++) {
                $ids[] = Database::insert(
                    "INSERT INTO reserva (id_via_res, id_usu_res, fech_res, metodo_pago, valor_pagado, estado_pago, fecha_pago)
                     VALUES (?, ?, NOW(), ?, ?, ?, ?)",
                    [
                        $idViaje, $idPasajero, $metodo,
                        $valor > 0 ? $valor : null,
                        $estado,
                        $confirmar ? date('Y-m-d H:i:s') : null,
                    ]
                );
            }

            // cup_dis se recalcula desde la realidad, no a ciegas: asi un
            // dato previo erroneo no deja el viaje con cupos negativos.
            $libres = max(0, (int)$viaje['cup_tot'] - (int)self::ocupadosEn($idViaje));
            Database::query("UPDATE viaje SET cup_dis = ? WHERE id_via = ?", [$libres, $idViaje]);

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][ReservaService::crear] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar la reserva. Inténtalo de nuevo.', 'ids' => [], 'puestos' => 0];
        }

        $primerId = (int)($ids[0] ?? 0);
        $ruta = trim(($viaje['nom_rut'] ?? '') . ' (' . ($viaje['ori_rut'] ?? '?') . ' → ' . ($viaje['des_rut'] ?? '?') . ')');

        if ($confirmar && $primerId > 0) {
            NotificacionService::notificarReservaConfirmada($idPasajero, [
                'id_res'  => $primerId,
                'id_via'  => $idViaje,
                'ruta'    => $ruta,
                'fec_via' => (string)$viaje['fec_via'],
                'hor_sal_via' => (string)$viaje['hor_sal_via'],
                'valor'   => $valor,
                'metodo'  => $metodo,
            ]);
        }

        return [
            'ok'      => true,
            'ids'     => $ids,
            'puestos' => $puestos,
            'mensaje' => sprintf(
                'Reserva registrada: %d puesto(s) en el viaje #%d%s.',
                $puestos, $idViaje, $confirmar ? ' (pago confirmado)' : ' (pago pendiente en terminal)'
            ),
        ];
    }

    /* ================================================================== */
    /* Cobro / confirmación (recaudo en terminal)                            */
    /* ================================================================== */

    /**
     * Confirma el pago de UNA reserva (un puesto).
     *
     * @return array{ok:bool, mensaje:string}
     */
    public static function confirmarPago(int $idReserva, float $valor, string $metodo = 'Efectivo'): array
    {
        $res = self::porId($idReserva);
        if (!$res) return ['ok' => false, 'mensaje' => 'La reserva no existe.'];
        if ($res['estado_pago'] === Config::RES_CONFIRMADA) {
            return ['ok' => false, 'mensaje' => 'Esa reserva ya estaba confirmada.'];
        }
        if ($res['estado_pago'] === Config::RES_CANCELADA) {
            return ['ok' => false, 'mensaje' => 'No se puede cobrar una reserva cancelada.'];
        }

        try {
            Database::query(
                "UPDATE reserva
                    SET estado_pago = ?, valor_pagado = ?, metodo_pago = ?, fecha_pago = NOW()
                  WHERE id_res = ?",
                [Config::RES_CONFIRMADA, $valor > 0 ? $valor : (float)$res['valor_pagado'], $metodo !== '' ? $metodo : (string)$res['metodo_pago'], $idReserva]
            );
        } catch (Throwable $e) {
            error_log('[SGET][ReservaService::confirmarPago] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar el cobro.'];
        }

        NotificacionService::notificarReservaConfirmada((int)$res['id_usu_res'], [
            'id_res'  => $idReserva,
            'id_via'  => (int)$res['id_via_res'],
            'ruta'    => trim(($res['nom_rut'] ?? '') . ' (' . ($res['ori_rut'] ?? '?') . ' → ' . ($res['des_rut'] ?? '?') . ')'),
            'fec_via' => (string)$res['fec_via'],
            'hor_sal_via' => (string)$res['hor_sal_via'],
            'valor'   => $valor,
            'metodo'  => $metodo,
        ]);

        Logger::registrar(Database::pdo(), 'CONFIRMAR_PAGO', sprintf(
            'Reserva #%d cobrada ($%s, %s) por %s.',
            $idReserva, number_format($valor, 0, ',', '.'), $metodo, Auth::nombre()
        ));

        return ['ok' => true, 'mensaje' => 'Cobro registrado y pasajero notificado.'];
    }

    /* ================================================================== */
    /* Cancelación                                                          */
    /* ================================================================== */

    /**
     * Cancela un puesto reservado y devuelve el cupo al viaje.
     *
     * @return array{ok:bool, mensaje:string}
     */
    public static function cancelar(int $idReserva, string $motivo = ''): array
    {
        $res = self::porId($idReserva);
        if (!$res) return ['ok' => false, 'mensaje' => 'La reserva no existe.'];
        if ($res['estado_pago'] === Config::RES_CANCELADA) {
            return ['ok' => false, 'mensaje' => 'Esa reserva ya estaba cancelada.'];
        }

        try {
            Database::begin();
            Database::query(
                "UPDATE reserva SET estado_pago = ? WHERE id_res = ?",
                [Config::RES_CANCELADA, $idReserva]
            );
            self::recalcularCupos((int)$res['id_via_res']);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][ReservaService::cancelar] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo cancelar la reserva.'];
        }

        NotificacionService::notificarReservaCancelada(
            (int)$res['id_usu_res'],
            $idReserva,
            (int)$res['id_via_res'],
            trim(($res['nom_rut'] ?? '') . ' (' . ($res['ori_rut'] ?? '?') . ' → ' . ($res['des_rut'] ?? '?') . ')'),
            $motivo
        );

        Logger::registrar(Database::pdo(), 'CANCELAR_RESERVA', sprintf(
            'Reserva #%d cancelada por %s. Motivo: %s.',
            $idReserva, Auth::nombre(), $motivo !== '' ? $motivo : '—'
        ));

        return ['ok' => true, 'mensaje' => 'Reserva cancelada; el cupo volvió al viaje y el pasajero fue avisado.'];
    }

    /* ================================================================== */
    /* Pasajero ocasional (recaudo sin cuenta registrada)                   */
    /* ================================================================== */

    /**
     * Crea (o reutiliza) un usuario pasajero para una atención en mostrador.
     * Se usa en el recaudo de personas que no se han registrado nunca.
     *
     * @return array{ok:bool, mensaje:string, id:int}
     */
    public static function pasajeroOcasional(string $nombre, string $documento = '', string $telefono = ''): array
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return ['ok' => false, 'mensaje' => 'Escribe el nombre del pasajero.', 'id' => 0];
        }

        if ($documento !== '') {
            $existente = Database::one(
                "SELECT id_usu FROM usuario WHERE num_doc_usu = ? AND id_rol_usu = ?",
                [$documento, Config::ROL_PASAJERO]
            );
            if ($existente) return ['ok' => true, 'id' => (int)$existente['id_usu'], 'mensaje' => 'Pasajero ya registrado.'];
        }

        $doc = $documento !== '' ? $documento : 'OCAS-' . date('ymd') . '-' . random_int(1000, 9999);
        $correo = 'ocasional-' . preg_replace('/\D+/', '', $doc) . '@sget.local';

        try {
            $id = Database::insert(
                "INSERT INTO usuario
                    (num_doc_usu, tip_doc_usu, nom_usu, corre_usu, tel_usu, id_rol_usu, pass_usu, estado)
                 VALUES (?, 'CC', ?, ?, ?, ?, ?, ?)",
                [
                    $doc, $nombre, $correo,
                    $telefono !== '' ? $telefono : null,
                    Config::ROL_PASAJERO,
                    password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    Config::USU_ACTIVO,
                ]
            );
        } catch (Throwable $e) {
            error_log('[SGET][ReservaService::pasajeroOcasional] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar el pasajero ocasional.', 'id' => 0];
        }

        return ['ok' => true, 'id' => $id, 'mensaje' => 'Pasajero ocasional registrado.'];
    }

    /* ================================================================== */
    /* Interno                                                             */
    /* ================================================================== */

    private static function ocupadosEn(int $idViaje): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM reserva WHERE id_via_res = ? AND estado_pago <> ?",
            [$idViaje, Config::RES_CANCELADA]
        );
    }

    /** Deja `cup_dis` igual a la realidad (cupos libres = total - reservados). */
    public static function recalcularCupos(int $idViaje): int
    {
        $totales = (int) Database::scalar("SELECT cup_tot FROM viaje WHERE id_via = ?", [$idViaje]);
        $libres  = max(0, $totales - self::ocupadosEn($idViaje));
        Database::query("UPDATE viaje SET cup_dis = ? WHERE id_via = ?", [$libres, $idViaje]);
        return $libres;
    }
}

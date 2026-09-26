<?php
/**
 * services/ViajeService.php
 * -----------------------------------------------------------------------------
 * MODULO: VIAJES / DESPACHO
 * -----------------------------------------------------------------------------
 * FIX #3 del "Data Default Fallback" (el mas grave):
 *   `viaje.fec_via` y `viaje.hor_sal_via` eran DATETIME y se llenaban con un
 *   <input type="date"> y un <input type="time">. MySQL rellenaba:
 *       hor_sal_via = 0000-00-00 00:00:00   (FECHA CERO)
 *       fec_via     = 2026-09-26 00:00:00
 *   Rompia TIMESTAMP(fec_via, hor_sal_via), el cierre automatico y cualquier
 *   calculo de "el viaje ya salio".
 *
 *   Ahora: fec_via = DATE, hor_sal_via = TIME, ambos NOT NULL, y toda escritura
 *   pasa por Fecha::fecha()/Fecha::hora() que rechazan entradas invalidas.
 *
 * REGLAS DE NEGOCIO centralizadas aqui (antes vivian duplicadas en 4 archivos):
 *   - Al crear/editar: el conductor y el vehiculo quedan OCUPADOS (0).
 *   - Al finalizar o cancelar: vuelven a DISPONIBLE (1).
 *   - Cancelar ANTES de la salida exige anotacion obligatoria (>= 15 chars),
 *     avisa a los pasajeros y marca sus reservas como canceladas.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class ViajeService
{
    /* ================================================================== */
    /* Consultas                                                           */
    /* ================================================================== */

    public static function listar(array $filtros = []): array
    {
        $where  = [];
        $params = [];

        $estados = $filtros['estados'] ?? [Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO];
        $where[] = 'v.est_via IN (' . implode(',', array_fill(0, count($estados), '?')) . ')';
        $params  = array_merge($params, $estados);

        if (!empty($filtros['ruta']))    { $where[] = 'v.id_rut_via = ?'; $params[] = (int)$filtros['ruta']; }
        if (!empty($filtros['conductor'])){ $where[] = 'v.id_usu_via = ?'; $params[] = (int)$filtros['conductor']; }

        $sql = "SELECT v.*,
                       r.nom_rut, r.ori_rut, r.des_rut, r.dis_rut, r.img_rut, r.hora_salida AS hora_ruta,
                       u.nom_usu  AS conductor,
                       ve.pla_veh, ve.mode_veh, ve.cap_veh,
                       (SELECT COUNT(*) FROM reserva re WHERE re.id_via_res = v.id_via
                          AND re.estado_pago = 'Confirmada') AS num_reservas
                  FROM viaje v
                  LEFT JOIN rutas r    ON r.id_rut  = v.id_rut_via
                  LEFT JOIN usuario u  ON u.id_usu  = v.id_usu_via
                  LEFT JOIN vehiculo ve ON ve.id_veh = v.id_veh
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY v.fec_via ASC, v.hor_sal_via ASC, v.id_via DESC";

        return Database::all($sql, $params);
    }

    public static function porId(int $id): ?array
    {
        return Database::one(
            "SELECT v.*, r.nom_rut, r.ori_rut, r.des_rut, r.img_rut, r.hora_salida AS hora_ruta,
                    u.nom_usu AS conductor, ve.pla_veh, ve.mode_veh
               FROM viaje v
               LEFT JOIN rutas r     ON r.id_rut  = v.id_rut_via
               LEFT JOIN usuario u   ON u.id_usu  = v.id_usu_via
               LEFT JOIN vehiculo ve ON ve.id_veh = v.id_veh
              WHERE v.id_via = ?",
            [$id]
        );
    }

    public static function conductoresDisponibles(): array
    {
        return Database::all(
            "SELECT u.id_usu, u.nom_usu, u.tel_usu
               FROM usuario u
              WHERE u.id_rol_usu = ?
                AND u.estado = 1
                AND COALESCE(u.est_con_usu, 1) = ?
                AND u.id_usu NOT IN (SELECT id_usu_via FROM viaje WHERE est_via IN ('Programado','En curso'))
              ORDER BY u.nom_usu ASC",
            [Config::ROL_CONDUCTOR, Config::CON_DISPONIBLE]
        );
    }

    public static function capacidadRestante(int $idViaje, int $idVehiculo): ?int
    {
        $cap = (int) Database::scalar("SELECT cap_veh FROM vehiculo WHERE id_veh = ?", [$idVehiculo]);
        if ($cap <= 0) return null;
        $reservadas = (int) Database::scalar(
            "SELECT COUNT(*) FROM reserva WHERE id_via_res = ? AND estado_pago = 'Confirmada'",
            [$idViaje]
        );
        return max(0, $cap - $reservadas);
    }

    /* ================================================================== */
    /* Validacion                                                          */
    /* ================================================================== */

    public static function validar(array $post): Validator
    {
        $id = (int)($post['id_via'] ?? 0);

        $v = Validator::de($post)
            ->requerido('id_rut_via', 'La ruta programada')
            ->entero('id_rut_via', 'La ruta programada', 1)
            ->requerido('id_usu_via', 'El conductor')
            ->entero('id_usu_via', 'El conductor', 1)
            ->requerido('id_veh', 'El vehículo')
            ->entero('id_veh', 'El vehículo', 1)
            ->fecha('fec_via', 'La fecha de salida')
            ->hora('hor_sal_via', 'La hora de salida')
            ->requerido('val_via', 'La tarifa')
            ->decimal('val_via', 'La tarifa', 1, 99999999);

        // Coherencia fecha/hora: no se puede agendar en el pasado
        try {
            $fecha = Fecha::fecha($post['fec_via'] ?? '', 'fecha de salida');
            $hora  = Fecha::hora($post['hor_sal_via'] ?? '', 'hora de salida', true);
            $instante = $fecha . ' ' . $hora;
            if ($id === 0 && strtotime($instante) < time() - 300) {
                $v->agrega('fec_via', 'La fecha y hora de salida no pueden estar en el pasado.');
            }
        } catch (ValueError $e) {
            $v->agrega('fec_via', $e->getMessage());
        }

        // Disponibilidad real del conductor y del vehiculo
        if (!$v->falla()) {
            if ($id === 0) {
                if (!self::_recursoLibre('conductor', (int)$post['id_usu_via'])) {
                    $v->agrega('id_usu_via', 'El conductor ya está asignado a otro viaje activo.');
                }
                if (!self::_recursoLibre('vehiculo', (int)$post['id_veh'])) {
                    $v->agrega('id_veh', 'El vehículo ya está asignado a otro viaje activo.');
                }
            }
            if (!self::_vehiculoOperativo((int)$post['id_veh'])) {
                $v->agrega('id_veh', 'El vehículo seleccionado está fuera de servicio.');
            }
            if (!self::_conductorActivo((int)$post['id_usu_via'])) {
                $v->agrega('id_usu_via', 'El conductor seleccionado está inactivo.');
            }
        }

        return $v;
    }

    /**
     * Verifica que un recurso (conductor o vehículo) no esté asignado a otro
     * viaje abierto. La columna se resuelve desde una lista blanca: nunca se
     * interpola texto procedente de la petición.
     */
    private static function _recursoLibre(string $recurso, int $id): bool
    {
        $columnas = [
            'conductor' => 'id_usu_via',
            'vehiculo'  => 'id_veh',
        ];
        if (!isset($columnas[$recurso])) {
            throw new InvalidArgumentException("Recurso no permitido: {$recurso}");
        }
        $col = $columnas[$recurso];
        $sql = "SELECT COUNT(*) FROM viaje
                 WHERE {$col} = ? AND est_via IN ('Programado','En curso')";
        return (int) Database::scalar($sql, [$id]) === 0;
    }

    private static function _vehiculoOperativo(int $id): bool
    {
        $e = Database::scalar("SELECT est_veh FROM vehiculo WHERE id_veh = ?", [$id]);
        return $e !== null && (int)$e === Config::VEH_DISPONIBLE;
    }

    private static function _conductorActivo(int $id): bool
    {
        $e = Database::scalar("SELECT estado FROM usuario WHERE id_usu = ?", [$id]);
        return $e !== null && (int)$e === Config::USU_ACTIVO;
    }

    /* ================================================================== */
    /* Escritura                                                           */
    /* ================================================================== */

    public static function guardar(array $post): array
    {
        $v = self::validar($post);
        if ($v->falla()) {
            return ['ok' => false, 'errores' => $v->errores(), 'mensaje' => $v->primerError()];
        }

        $id      = (int)($post['id_via'] ?? 0);
        $idRuta  = (int)$post['id_rut_via'];
        $idUsu   = (int)$post['id_usu_via'];
        $idVeh   = (int)$post['id_veh'];
        $fec     = Fecha::fecha($post['fec_via'], 'fecha de salida');
        $hora    = Fecha::hora($post['hor_sal_via'], 'hora de salida', true);
        $val     = (float)$post['val_via'];
        $llegada = Fecha::hora($post['hor_lleg_via'] ?? null, 'hora de llegada', false);
        $cupos   = (int)($post['cup_tot'] ?? 0);
        if ($cupos <= 0) {
            $cupos = (int) (Database::scalar("SELECT cap_veh FROM vehiculo WHERE id_veh = ?", [$idVeh]) ?? 0);
        }

        // Tarifa: si el administrador la deja en 0, se hereda la tarifa de la ruta
        if ($val <= 0) {
            $val = RutaService::tarifa($idRuta) ?? 0.0;
        }

        try {
            Database::begin();

            if ($id > 0) {
                // Liberar los recursos que se reemplazan
                $previo = self::porId($id);
                if ($previo) {
                    if ((int)$previo['id_usu_via'] !== $idUsu) self::_liberarConductor((int)$previo['id_usu_via']);
                    if ((int)($previo['id_veh'] ?? 0) !== $idVeh) self::_liberarVehiculo((int)($previo['id_veh'] ?? 0));
                }

                Database::query(
                    "UPDATE viaje
                        SET id_rut_via = ?, id_usu_via = ?, id_veh = ?, fec_via = ?, hor_sal_via = ?,
                            hor_lleg_via = ?, val_via = ?, cup_tot = ?, cup_dis = ?, est_via = ?
                      WHERE id_via = ?",
                    [$idRuta, $idUsu, $idVeh, $fec, $hora, $llegada, $val, $cupos, $cupos, Config::VIA_PROGRAMADO, $id]
                );
            } else {
                $id = Database::insert(
                    "INSERT INTO viaje
                        (nom_via, id_rut_via, id_usu_via, id_veh, fec_via, hor_sal_via, hor_lleg_via,
                         val_via, cup_tot, cup_dis, est_via, salio)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
                    [
                        sprintf('Viaje #%d', (int) (Database::scalar("SELECT COALESCE(MAX(id_via),0)+1 FROM viaje"))),
                        $idRuta, $idUsu, $idVeh, $fec, $hora, $llegada, $val, $cupos, $cupos, Config::VIA_PROGRAMADO,
                    ]
                );
            }

            self::_ocuparConductor($idUsu);
            self::_ocuparVehiculo($idVeh);

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][ViajeService::guardar] ' . $e->getMessage());
            return ['ok' => false, 'errores' => ['general' => 'No se pudo guardar el viaje.'],
                    'mensaje' => 'No se pudo guardar el viaje: ' . $e->getMessage()];
        }

        Logger::registrar(
            Database::pdo(),
            $id > 0 && isset($post['__editado']) ? 'EDITAR_VIAJE' : 'CREAR_VIAJE',
            sprintf('Viaje #%d programado para el %s %s (conductor %d / vehiculo %d)',
                $id, $fec, $hora, $idUsu, $idVeh)
        );

        return ['ok' => true, 'id' => $id, 'mensaje' => $id > 0 ? 'Viaje actualizado correctamente.' : 'Viaje programado correctamente.'];
    }

    /* ================================================================== */
    /* CANCELACION (flujo nuevo)                                           */
    /* ================================================================== */

    /**
     * Cancela un viaje.
     *
     * Reglas:
     *  - Si el viaje NO ha salido: la anotacion es OBLIGATORIA (min. 15 chars).
     *  - Si ya salió: basta el motivo, pero la anotacion sigue siendo recomendada.
     *  - Siempre: motivo obligatorio, se notifica a los pasajeros con reserva
     *    activa y sus reservas quedan en estado 'Cancelada' (sin cobro).
     *
     * @return array{ok:bool, mensaje:string, notificados?:int}
     */
    public static function cancelar(int $idViaje, string $motivo, string $anotacion): array
    {
        $viaje = self::porId($idViaje);
        if (!$viaje) {
            return ['ok' => false, 'mensaje' => 'El viaje no existe o ya fue eliminado.'];
        }
        if (in_array($viaje['est_via'], Config::VIA_ESTADOS_CERRADOS, true)) {
            return ['ok' => false, 'mensaje' => 'Este viaje ya está cerrado (' . $viaje['est_via'] . '); no se puede cancelar.'];
        }

        [$yaSalio, $instante] = Fecha::yaSalio($viaje['fec_via'], $viaje['hor_sal_via'], Config::TOLERANCIA_SALIDA_MIN);

        // --- Validacion de la anotacion segun el momento de la salida --------
        $motivo    = trim($motivo);
        $anotacion = trim($anotacion);

        if ($motivo === '') {
            return ['ok' => false, 'mensaje' => 'Selecciona el motivo de la cancelación.'];
        }
        if (!$yaSalio && mb_strlen($anotacion) < Config::MIN_ANOTACION_CANCELACION) {
            return ['ok' => false, 'mensaje' => sprintf(
                'Este viaje aún no sale (%s). Debes escribir una anotación de al menos %d caracteres explicando la cancelación; se enviará a los pasajeros.',
                Fecha::legible($instante),
                Config::MIN_ANOTACION_CANCELACION
            )];
        }

        try {
            Database::begin();

            // 1) Se identifica a los pasajeros ANTES de cancelar sus reservas:
            //    si se hiciera después, la consulta no encontraría a nadie y el
            //    aviso nunca llegaría (el fallo que hay que evitar).
            $pasajeros = NotificacionService::pasajerosReservados($idViaje);

            Database::query(
                "UPDATE viaje
                    SET est_via = ?, motivo_cancelacion = ?, anotacion_cancelacion = ?,
                        cancelado_por = ?, fec_cancelacion = ?
                  WHERE id_via = ?",
                [Config::VIA_CANCELADO, $motivo, $anotacion, Auth::id(), date('Y-m-d H:i:s'), $idViaje]
            );

            // Reservas: se cancelan automaticamente (quedan trazables, no se borran)
            $reservas = (int) Database::query(
                "UPDATE reserva SET estado_pago = ? WHERE id_via_res = ? AND estado_pago <> ?",
                [Config::RES_CANCELADA, $idViaje, Config::RES_CANCELADA]
            )->rowCount();

            // Recursos liberados
            self::_liberarConductor((int)$viaje['id_usu_via']);
            self::_liberarVehiculo((int)($viaje['id_veh'] ?? 0));

            // Aviso a pasajeros
            $notificados = NotificacionService::notificarCancelacionViaje($idViaje, [
                'nom_ruta'  => trim(($viaje['nom_rut'] ?? '') . ' (' . ($viaje['ori_rut'] ?? '?') . ' → ' . ($viaje['des_rut'] ?? '?') . ')'),
                'conductor' => $viaje['conductor'] ?? 'pasajero',
                'salida'    => $instante,
                'motivo'    => ViajeService::etiquetaMotivo($motivo),
            ], $anotacion !== '' ? $anotacion : 'Sin anotación adicional.', $pasajeros);

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][ViajeService::cancelar] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo cancelar el viaje: ' . $e->getMessage()];
        }

        Logger::registrar(Database::pdo(), 'CANCELAR_VIAJE', sprintf(
            'Viaje #%d «%s» cancelado por %s. Motivo: %s. Anotación: %s. Pasajeros notificados: %d, reservas canceladas: %d.',
            $idViaje, $viaje['nom_rut'] ?? '', Auth::nombre(), $motivo, $anotacion ?: '—', $notificados, $reservas
        ));

        return ['ok' => true, 'notificados' => $notificados, 'reservas' => $reservas, 'mensaje' => sprintf(
            'Viaje #%d cancelado. Se notificó a %d pasajero(s) y se cancelaron %d reserva(s).',
            $idViaje, $notificados, $reservas
        )];
    }

    /* ================================================================== */
    /* Finalizacion                                                        */
    /* ================================================================== */

    public static function finalizar(int $idViaje): array
    {
        $viaje = self::porId($idViaje);
        if (!$viaje) return ['ok' => false, 'mensaje' => 'El viaje no existe.'];
        if (in_array($viaje['est_via'], Config::VIA_ESTADOS_CERRADOS, true)) {
            return ['ok' => false, 'mensaje' => 'El viaje ya está cerrado.'];
        }

        Database::begin();
        Database::query("UPDATE viaje SET est_via = ? WHERE id_via = ?", [Config::VIA_FINALIZADO, $idViaje]);
        self::_liberarConductor((int)$viaje['id_usu_via']);
        self::_liberarVehiculo((int)($viaje['id_veh'] ?? 0));
        Database::commit();

        Logger::registrar(Database::pdo(), 'FINALIZAR_VIAJE', "Viaje #{$idViaje} finalizado por " . Auth::nombre() . '.');
        return ['ok' => true, 'mensaje' => "Viaje #{$idViaje} finalizado. Conductor y vehículo liberados."];
    }

    public static function marcarEnCurso(int $idViaje): array
    {
        $viaje = self::porId($idViaje);
        if (!$viaje) return ['ok' => false, 'mensaje' => 'El viaje no existe.'];

        [$yaSalio] = Fecha::yaSalio($viaje['fec_via'], $viaje['hor_sal_via'], Config::TOLERANCIA_SALIDA_MIN);
        if (!$yaSalio) {
            return ['ok' => false, 'mensaje' => 'El viaje aún no sale; no puede marcarse en curso.'];
        }

        Database::query("UPDATE viaje SET est_via = ?, salio = 1 WHERE id_via = ?", [Config::VIA_EN_CURSO, $idViaje]);
        return ['ok' => true, 'mensaje' => "Viaje #{$idViaje} marcado «En curso»."];
    }

    /* ================================================================== */
    /* Cierre automatico de viajes vencidos                                */
    /* ================================================================== */

    /**
     * Cierra viajes cuya salida+hasta supero el plazo operativo.
     * Ejecuta al cargar el panel de viajes; devuelve cuantos cerro.
     */
    public static function cerrarVencidos(int $horas = 24): int
    {
        $filas = Database::all(
            "SELECT id_via, id_usu_via, id_veh
               FROM viaje
              WHERE est_via IN ('Programado','En curso')
                AND fec_via IS NOT NULL
                AND hor_sal_via IS NOT NULL
                AND TIMESTAMP(fec_via, hor_sal_via) <= (NOW() - INTERVAL ? HOUR)",
            [$horas]
        );

        foreach ($filas as $f) {
            Database::query("UPDATE viaje SET est_via = ? WHERE id_via = ?", [Config::VIA_FINALIZADO, (int)$f['id_via']]);
            self::_liberarConductor((int)$f['id_usu_via']);
            self::_liberarVehiculo((int)($f['id_veh'] ?? 0));
        }

        return count($filas);
    }

    /* ================================================================== */
    /* Sincronizacion de estados (0 = ocupado, 1 = disponible)            */
    /* ================================================================== */

    private static function _ocuparConductor(int $id): void
    {
        if ($id > 0) Database::query("UPDATE usuario SET est_con_usu = ? WHERE id_usu = ?", [Config::CON_OCUPADO, $id]);
    }

    private static function _liberarConductor(int $id): void
    {
        if ($id > 0) Database::query("UPDATE usuario SET est_con_usu = ? WHERE id_usu = ?", [Config::CON_DISPONIBLE, $id]);
    }

    private static function _ocuparVehiculo(int $id): void
    {
        if ($id > 0) Database::query("UPDATE vehiculo SET est_veh = ? WHERE id_veh = ?", [Config::VEH_FUERA_SERVICIO, $id]);
    }

    private static function _liberarVehiculo(int $id): void
    {
        if ($id > 0) Database::query("UPDATE vehiculo SET est_veh = ? WHERE id_veh = ?", [Config::VEH_DISPONIBLE, $id]);
    }

    /* ================================================================== */
    /* Presentacion                                                        */
    /* ================================================================== */

    public static function claseEstado(string $estado): string
    {
        return [
            Config::VIA_PROGRAMADO => 'sget-badge sget-badge--info',
            Config::VIA_EN_CURSO   => 'sget-badge sget-badge--aviso',
            Config::VIA_FINALIZADO => 'sget-badge sget-badge--neutro',
            Config::VIA_CANCELADO  => 'sget-badge sget-badge--error',
        ][$estado] ?? 'sget-badge sget-badge--neutro';
    }

    public static function iconoEstado(string $estado): string
    {
        return [
            Config::VIA_PROGRAMADO => 'fa-calendar-check',
            Config::VIA_EN_CURSO   => 'fa-truck-fast',
            Config::VIA_FINALIZADO => 'fa-flag-checkered',
            Config::VIA_CANCELADO  => 'fa-ban',
        ][$estado] ?? 'fa-circle';
    }

    /** Motivos de cancelación disponibles en el modal (valor => etiqueta). */
    public static function motivosCancelacion(): array
    {
        return [
            'averia_unidad'   => 'Avería de la unidad',
            'reprogramacion'  => 'Reprogramación del servicio',
            'falta_conductor' => 'Falta de conductor disponible',
            'cond_clima'      => 'Condiciones climáticas',
            'orden_admin'     => 'Orden de la administración',
            'sin_pasajeros'   => 'Sin pasajeros reservados',
            'otro'            => 'Otro motivo',
        ];
    }

    /** Etiqueta legible de un motivo (para el mensaje al pasajero). */
    public static function etiquetaMotivo(string $clave): string
    {
        return self::motivosCancelacion()[$clave] ?? ucfirst(str_replace('_', ' ', $clave));
    }
}

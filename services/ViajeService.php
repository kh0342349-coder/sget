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
                       r.nom_rut, r.ori_rut, r.des_rut, r.dis_rut, r.img_rut, r.duracion_min,
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
            "SELECT v.*, r.nom_rut, r.ori_rut, r.des_rut, r.img_rut, r.duracion_min,
                    u.nom_usu AS conductor, ve.pla_veh, ve.mode_veh
               FROM viaje v
               LEFT JOIN rutas r     ON r.id_rut  = v.id_rut_via
               LEFT JOIN usuario u   ON u.id_usu  = v.id_usu_via
               LEFT JOIN vehiculo ve ON ve.id_veh = v.id_veh
              WHERE v.id_via = ?",
            [$id]
        );
    }

    /**
     * Conductores con su disponibilidad REAL para la fecha/hora del viaje.
     *
     * ANTES: `COALESCE(est_con_usu,1) = 1 AND id_usu NOT IN (SELECT id_usu_via
     * FROM viaje WHERE est_via IN ('Programado','En curso'))`. Eso tenía dos
     * fallos opuestos: un viaje de MAÑANA bloqueaba al conductor desde HOY, y
     * dos viajes del mismo día siempre se rechazaban aunque no se solaparan.
     *
     * AHORA delega en `DisponibilidadService`, la MISMA lógica que valida al
     * guardar. El selector y el backend no pueden discrepar.
     *
     * @param string $fecha  'Y-m-d' (vacío = hoy)
     * @param string $hora   'H:i:s' (vacío = ahora)
     */
    public static function conductoresDisponibles(string $fecha = '', string $hora = '', int $exceptoIdViaje = 0): array
    {
        $ventana = self::ventanaDeConsulta($fecha, $hora);
        return DisponibilidadService::conductores(
            date('Y-m-d', $ventana['inicio']),
            date('H:i:s', $ventana['inicio']),
            $exceptoIdViaje
        );
    }

    /** Normaliza fecha/hora del formulario; vacío = ahora. */
    private static function ventanaDeConsulta(string $fecha, string $hora): array
    {
        $fecha = trim($fecha);
        $hora  = trim($hora);
        if ($fecha !== '') {
            try {
                $fecha = Fecha::fecha($fecha);
            } catch (ValueError $e) {
                $fecha = date('Y-m-d');
            }
        } else {
            $fecha = date('Y-m-d');
        }
        if ($hora !== '') {
            try {
                $hora = (string)Fecha::hora($hora, 'hora', true);
            } catch (ValueError $e) {
                $hora = date('H:i:s');
            }
        } else {
            $hora = date('H:i:s');
        }
        return ['inicio' => strtotime($fecha . ' ' . $hora)];
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

    /**
     * ¿Este usuario es el conductor de este viaje?
     *
     * Es la PUERTA que hace falta antes de dejar ver el manifiesto de pasajeros
     * a un conductor: sin ella, cualquier conductor autenticado podría pedir el
     * manifiesto de cualquier viaje por el API y ver la lista de pasajeros
     * (documentos y teléfonos) de viajes que no son suyos. El admin pasa
     * siempre.
     *
     * @param int $idUsuario 0 = el usuario de la sesión
     */
    public static function esConductorDelViaje(int $idViaje, int $idUsuario = 0): bool
    {
        $idUsuario = $idUsuario > 0 ? $idUsuario : Auth::id();
        if ($idUsuario <= 0 || $idViaje <= 0) return false;

        return (int) Database::scalar(
            "SELECT COUNT(*) FROM viaje WHERE id_via = ? AND id_usu_via = ?",
            [$idViaje, $idUsuario]
        ) > 0;
    }

    /**
     * El manifiesto de pasajeros es de uso del admin y del CONDUCTOR DEL VIAJE.
     * Cualquier otro rol recibe el mensaje de siempre, sin revelar si el viaje
     * existe o no.
     */
    public static function puedeVerManifiesto(int $idViaje): bool
    {
        if (Auth::rol() === Config::ROL_ADMIN) return true;
        return self::esConductorDelViaje($idViaje);
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
            ->hora('hor_sal_via', 'La hora de salida');

        /* La TARIFA no se pide en el formulario a propósito: la define la RUTA.
           Antes era obligatoria, así que el conductor tenía que saber el precio
           (y la herencia de la tarifa que hace guardar() era inalcanzable,
           porque validar() ya había devuelto el error). Ahora se valida
           solamente si viene escrita. */
        if (isset($post['val_via']) && (string)$post['val_via'] !== '') {
            $v->decimal('val_via', 'La tarifa', 0, 99999999);
        }

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

        /* Disponibilidad real del conductor y del vehículo.
           ANTES la comprobación era «¿tiene ALGÚN viaje abierto?», sin mirar
           fecha ni hora: un viaje mañana a las 10:00 bloqueaba al conductor
           desde hoy, y dos viajes del mismo día siempre chocaban aunque uno
           terminara antes de que empezara el otro.

           Ahora se compara la VENTANA HORARIA completa (salida → llegada +
           margen) del viaje nuevo contra la de cada viaje existente. Dos viajes
           del mismo día son válidos siempre que sus ventanas no se solapen, y
           un viaje de otro día nunca bloquea por sí solo.

           En una EDICIÓN se excluye el propio viaje, si no nunca se podría
           cambiar nada de un viaje que ya tiene conductor y vehículo. */
        if (!$v->falla()) {
            $ventana = self::ventanaDelPost($post, $idRutaPrevia = (int) Database::scalar(
                'SELECT id_rut_via FROM viaje WHERE id_via = ?',
                [$id]
            ));

            $rConductor = DisponibilidadService::comprobarConductor((int)$post['id_usu_via'], $ventana, $id);
            if (!$rConductor['ok']) {
                $v->agrega('id_usu_via', $rConductor['mensaje']);
            }

            $rVehiculo = DisponibilidadService::comprobarVehiculo((int)$post['id_veh'], $ventana, $id);
            if (!$rVehiculo['ok']) {
                $v->agrega('id_veh', $rVehiculo['mensaje']);
            }
        }

        return $v;
    }

    /**
     * Ventana temporal del viaje que se quiere guardar.
     *
     * La duración se resuelve con la MISMA prioridad que usa el resto del
     * sistema (llegada del viaje → duración de la ruta → defecto), de modo que
     * «cuándo choca» y «cuándo termina» nunca dan números distintos.
     */
    private static function ventanaDelPost(array $post, int $idRutaPrevia = 0): array
    {
        $duracionRuta = 0;
        if ($idRutaPrevia > 0) {
            $duracionRuta = (int) (Database::scalar(
                'SELECT duracion_min FROM rutas WHERE id_rut = ?',
                [$idRutaPrevia]
            ) ?? 0);
        }

        return DisponibilidadService::ventana([
            'fec_via'      => (string)($post['fec_via'] ?? ''),
            'hor_sal_via'  => (string)($post['hor_sal_via'] ?? ''),
            'hor_lleg_via' => (string)($post['hor_lleg_via'] ?? ''),
            'duracion_min' => $duracionRuta,
        ]);
    }

    /**
     * Verifica que un recurso (conductor o vehículo) no tenga OTRO viaje que se
     * solape con la ventana del nuevo viaje.
     *
     * Antes miraba `est_via IN ('Programado','En curso')` y nada más: sin fecha,
     * sin hora y sin duración. Era un «¿tiene algo abierto?» en vez de un
     * «¿choca en este horario?». Toda la lógica real está ahora en
     * `DisponibilidadService`; este método se conserva como_atajo interno para
     * no romper las llamadas existentes.
     *
     * @param string   $recurso  `conductor` | `vehiculo`
     * @param int      $id       conductor o vehículo
     * @param int      $exceptoIdViaje  viaje en edición (se excluye)
     * @param array    $ventana  ventana del viaje nuevo
     */
    private static function _recursoLibre(string $recurso, int $id, int $exceptoIdViaje = 0, array $ventana = []): bool
    {
        $columna = match ($recurso) {
            'conductor' => 'id_usu_via',
            'vehiculo'  => 'id_veh',
            default     => throw new InvalidArgumentException("Recurso no permitido: {$recurso}"),
        };
        unset($columna);   // la columna la resuelve el servicio, con lista blanca

        if ($ventana === []) {
            $ventana = DisponibilidadService::ventana([
                'fec_via'     => date('Y-m-d'),
                'hor_sal_via' => date('H:i:s'),
            ]);
        }

        return DisponibilidadService::conflictos($recurso, $id, $ventana, $exceptoIdViaje) === [];
    }

    private static function _vehiculoOperativo(int $id): bool
    {
        $e = Database::scalar("SELECT est_veh FROM vehiculo WHERE id_veh = ?", [$id]);
        return $e !== null && VehiculoService::operativo((string) $e);
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
        /* RESTRICCIÓN ESTRICTA AL ROL CONDUCTOR: los conductores NO pueden
           crear ni programar viajes. La interfaz ya oculta el botón; este
           bloqueo en el backend impide cualquier intento por POST o API. */
        if (Auth::rol() === Config::ROL_CONDUCTOR) {
            return ['ok' => false, 'errores' => ['general' => 'Los conductores no pueden crear ni programar viajes.'],
                    'mensaje' => 'Restricción de rol: los conductores no pueden programar viajes.'];
        }

        $v = self::validar($post);
        if ($v->falla()) {
            return ['ok' => false, 'errores' => $v->errores(), 'mensaje' => $v->primerError()];
        }

        /* `$eraEdicion` se calcula ANTES de escribir.
           El fallo que corregía esto: `$id` vale el id recién insertado en un alta,
           así que la comprobación posterior `if ($id > 0)` era SIEMPRE cierta y
           TODOS los altas respondían «Viaje actualizado correctamente». */
        $id        = (int)($post['id_via'] ?? 0);
        $eraEdicion = $id > 0;

        /* Estado previo del viaje, leído ANTES del UPDATE: sin esto no se puede
           saber después qué cambió, porque la consulta devolvería los valores
           nuevos y el conductor nunca recibiría el aviso de su reasignación. */
        $previo = $eraEdicion ? self::porId($id) : null;
        if ($eraEdicion && !$previo) {
            return ['ok' => false, 'errores' => ['id_via' => 'El viaje que intentas editar ya no existe.'],
                    'mensaje' => 'El viaje que intentas editar ya no existe.'];
        }

        $idRuta  = (int)$post['id_rut_via'];
        $idUsu   = (int)$post['id_usu_via'];
        $idVeh   = (int)$post['id_veh'];
        $fec     = Fecha::fecha($post['fec_via'], 'fecha de salida');
        $hora    = Fecha::hora($post['hor_sal_via'], 'hora de salida', true);
        $val     = (float)($post['val_via'] ?? 0);
        $llegada = Fecha::hora($post['hor_lleg_via'] ?? null, 'hora de llegada', false);
        $cupos   = (int)($post['cup_tot'] ?? 0);
        if ($cupos <= 0) {
            $cupos = (int) (Database::scalar("SELECT cap_veh FROM vehiculo WHERE id_veh = ?", [$idVeh]) ?? 0);
        }

        /* Ventana temporal del viaje: es la que se usa para el bloqueo de
           concurrencia y para decidir si el recurso queda ocupado. */
        $ventana = DisponibilidadService::ventana([
            'fec_via'      => $fec,
            'hor_sal_via'  => $hora,
            'hor_lleg_via' => $llegada,
            'duracion_min' => (int)(Database::scalar('SELECT duracion_min FROM rutas WHERE id_rut = ?', [$idRuta]) ?? 0),
        ]);

        // Tarifa: si el administrador la deja en 0, se hereda la tarifa de la ruta
        if ($val <= 0) {
            $val = RutaService::tarifa($idRuta) ?? 0.0;
        }

        try {
            Database::begin();

            /* ------------------------------------------------------------------
               BLOQUEO CONTRA ASIGNACIONES SIMULTÁNEAS
               ------------------------------------------------------------------
               El problema real que se corrige aquí:

                   Admin A  -> comprueba que el vehículo 7 está libre  -> sí
                   Admin B  -> comprueba que el vehículo 7 está libre  -> sí
                   A        -> inserta el viaje con id_veh = 7
                   B        -> inserta el viaje con id_veh = 7

               `validar()` se ejecuta FUERA de la transacción, así que las dos
               comprobaciones ven el mismo estado y el sistema acaba con un
               vehículo en dos viajes incompatibles a la vez.

              La solución es la misma que en `ReservaService::crear()`:
               `SELECT … FOR UPDATE` sobre el recurso. InnoDB bloquea la fila
               hasta que la transacción termine, de modo que la segunda
               transacción ESPERA y, cuando se despierta, ya ve el viaje que
               escribió la primera. La validación se repite dentro del
               bloqueo: el mensaje al usuario se mantiene y, sobre todo, la
               garantía deja de depender del orden de llegada de los dos
               administradores.

               El orden de los bloqueos es SIEMPRE conductor → vehículo. Un orden
               fijo es lo que evita un interbloqueo: si cada transacción toma
               los dos en el mismo orden, ninguna puede quedar esperando
               mientras la otra tiene el recurso que necesita.
            ------------------------------------------------------------------- */
            $conductorBloqueado = Database::one(
                'SELECT id_usu FROM usuario WHERE id_usu = ? FOR UPDATE',
                [$idUsu]
            );
            if (!$conductorBloqueado) {
                Database::rollback();
                return ['ok' => false, 'errores' => ['id_usu_via' => 'El conductor seleccionado ya no existe.'],
                        'mensaje' => 'El conductor seleccionado ya no existe.'];
            }

            $vehiculoBloqueado = Database::one(
                'SELECT id_veh, est_veh FROM vehiculo WHERE id_veh = ? FOR UPDATE',
                [$idVeh]
            );
            if (!$vehiculoBloqueado) {
                Database::rollback();
                return ['ok' => false, 'errores' => ['id_veh' => 'El vehículo seleccionado ya no existe.'],
                        'mensaje' => 'El vehículo seleccionado ya no existe.'];
            }

            // Revalidación DENTRO del bloqueo (fuera, ya se hizo una pasada).
            // Ahora compara la ventana REAL del viaje nuevo, no «tiene algo
            // abierto»: así dos administradores pueden crear viajes el mismo
            // día con el mismo conductor siempre que no se solapen.
            $rConductor = DisponibilidadService::comprobarConductor($idUsu, $ventana, $id);
            if (!$rConductor['ok']) {
                Database::rollback();
                return ['ok' => false, 'errores' => ['id_usu_via' => $rConductor['mensaje']],
                        'mensaje' => $rConductor['mensaje']];
            }

            $rVehiculo = DisponibilidadService::comprobarVehiculo($idVeh, $ventana, $id);
            if (!$rVehiculo['ok']) {
                Database::rollback();
                return ['ok' => false, 'errores' => ['id_veh' => $rVehiculo['mensaje']],
                        'mensaje' => $rVehiculo['mensaje']];
            }

            if ($eraEdicion) {
                /* Estado: un viaje que ya estaba «En curso» o «Cancelado» no
                   vuelve a «Programado» por editarlo. Antes sí: se perdía el
                   rastro de que había salido. */
                $nuevoEstado = in_array((string)($previo['est_via'] ?? ''), Config::VIA_ESTADOS_CERRADOS, true)
                    ? (string)$previo['est_via']
                    : Config::VIA_PROGRAMADO;

                Database::query(
                    "UPDATE viaje
                        SET id_rut_via = ?, id_usu_via = ?, id_veh = ?, fec_via = ?, hor_sal_via = ?,
                            hor_lleg_via = ?, val_via = ?, cup_tot = ?, cup_dis = ?, est_via = ?
                      WHERE id_via = ?",
                    [$idRuta, $idUsu, $idVeh, $fec, $hora, $llegada, $val, $cupos, $cupos, $nuevoEstado, $id]
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

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][ViajeService::guardar] ' . $e->getMessage());
            return ['ok' => false, 'errores' => ['general' => 'No se pudo guardar el viaje.'],
                    'mensaje' => 'No se pudo guardar el viaje: ' . $e->getMessage()];
        }

        /* ------------------------------------------------------------------------
           SINCRONIZACIÓN DE RECURSOS (FUERA de la transacción)
           ------------------------------------------------------------------------
           Se hace después del COMMIT y NO es transaccional a propósito: al
           liberar y ocupar son dos UPDATEs sobre filas ajenas al viaje, y si
           fallara dentro de la transacción se perderían ambas cosas a la vez.
           El peor caso razonable es que una unidad quede «Disponible» mientras
           ya tiene un viaje: se corrige en el siguiente guardado y no se
           duplican ni se pierden reservas ni datos de este viaje.
        ---------------------------------------------------------------------- */
        $cambios = [];

        /* ------------------------------------------------------------------------
           ESTADO DE LOS RECURSOS
           ------------------------------------------------------------------------
           Se recalcula desde las VENTANAS REALES, no con un «ocupar/liberar»
           a ciegas. Motivo: si al editar un viaje se le quita un vehículo, ese
           vehículo no queda automáticamente libre si OTRO viaje suyo sigue
           dentro de su ventana; y al revés, un viaje de mañana NO debe dejar
           al conductor bloqueado desde hoy.

           Por eso, tras guardar, se recalculan solo los dos recursos afectados
           (el anterior y el nuevo de cada cosa). El resto de la flota ya lo
           recalcula `sincronizarEstado()`.
        ---------------------------------------------------------------------- */
        $afectados = [
            'conductor' => [$idUsu],
            'vehiculo'  => [$idVeh],
        ];

        if ($eraEdicion) {
            $conductorPrevio = (int)($previo['id_usu_via'] ?? 0);
            $vehiculoPrevio  = (int)($previo['id_veh'] ?? 0);

            if ($conductorPrevio !== $idUsu) {
                $afectados['conductor'][] = $conductorPrevio;
                $cambios[] = 'conductor';
            }
            if ($vehiculoPrevio !== $idVeh) {
                $afectados['vehiculo'][] = $vehiculoPrevio;
                $cambios[] = 'vehiculo';
            }
        }

        foreach ($afectados as $recurso => $ids) {
            foreach (array_unique($ids) as $idRecurso) {
                if ($idRecurso <= 0) {
                    continue;
                }
                $ocupado = DisponibilidadService::ocupadoAhora($recurso, $idRecurso);

                if ($recurso === 'conductor') {
                    Database::query(
                        'UPDATE usuario SET est_con_usu = ? WHERE id_usu = ?',
                        [$ocupado ? Config::CON_OCUPADO : Config::CON_DISPONIBLE, $idRecurso]
                    );
                } else {
                    // Solo se toca si el administrador no laRETIRÓ del servicio.
                    Database::query(
                        'UPDATE vehiculo SET est_veh = ?
                          WHERE id_veh = ? AND est_veh IN (?, ?)',
                        [
                            $ocupado ? Config::VEH_ASIGNADO : Config::VEH_DISPONIBLE,
                            $idRecurso,
                            Config::VEH_ASIGNADO,
                            Config::VEH_DISPONIBLE,
                        ]
                    );
                }
            }
        }

        // Aviso al conductor. Se hace FUERA de la transacción: si el buzón falla,
        // el viaje ya quedó guardado y no se debe perder por un simple aviso.
        $detalle = Database::one(
            "SELECT r.nom_rut, r.ori_rut, r.des_rut, v.cup_tot, veh.pla_veh
               FROM viaje v
               INNER JOIN rutas r     ON r.id_rut = v.id_rut_via
               LEFT JOIN vehiculo veh ON veh.id_veh = v.id_veh
              WHERE v.id_via = ?",
            [$id]
        ) ?: [];

        /* En una edición solo se avisa si cambió algo que de verdad le importa al
           conductor. La comparación usa `$previo` (el estado ANTIGUO), no una
           consulta posterior al UPDATE: con esa comprobación posterior el conductor nunca se
           enteraba de que le habían cambiado el horario o la unidad. */
        $cambioRelevante = true;
        if ($eraEdicion) {
            $cambioRelevante = (int)($previo['id_usu_via'] ?? 0) !== $idUsu
                || (string)($previo['fec_via'] ?? '') !== $fec
                || (string)($previo['hor_sal_via'] ?? '') !== $hora
                || (int)($previo['id_veh'] ?? 0) !== $idVeh
                || (string)($previo['nom_rut'] ?? '') !== (string)($detalle['nom_rut'] ?? '');
        }

        if ($cambioRelevante) {
            NotificacionService::notificarAsignacionConductor($idUsu, $id, [
                'ruta'    => (string)($detalle['nom_rut'] ?? ''),
                'origen'  => (string)($detalle['ori_rut'] ?? ''),
                'destino' => (string)($detalle['des_rut'] ?? ''),
                'salida'  => Fecha::legible($fec . ' ' . $hora),
                'placa'   => (string)($detalle['pla_veh'] ?? ''),
                'cupos'   => (int)($detalle['cup_tot'] ?? $cupos),
                'cambios' => $cambios,
            ]);
        }

        Logger::registrar(
            Database::pdo(),
            $eraEdicion ? 'EDITAR_VIAJE' : 'CREAR_VIAJE',
            sprintf('Viaje #%d %s para el %s %s (conductor %d / vehiculo %d)',
                $id, $eraEdicion ? 'actualizado' : 'programado', $fec, $hora, $idUsu, $idVeh)
        );

        $mensaje = 'Viaje programado correctamente.';
        if ($eraEdicion) {
            $mensaje = $cambioRelevante
                ? 'Viaje actualizado correctamente. Se notificó al conductor del cambio.'
                : 'Viaje actualizado correctamente.';
        }

        return ['ok' => true, 'id' => $id, 'mensaje' => $mensaje];
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
        $vencio = self::vencio($viaje);

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

        // Si ya venció la duración del trayecto, el viaje se cerró solo.
        if ($vencio) {
            return ['ok' => false, 'mensaje' => sprintf(
                'El viaje #%d ya cumplió su duración de trayecto (terminó el %s) y se cerró automáticamente, por lo que ya no se puede cancelar.',
                $idViaje, Fecha::legible(self::instanteVencimiento($viaje))
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

            // Y al CONDUCTOR: era el destinatario que faltaba. Sin este aviso
            // el conductor se enteraba de la cancelación al llegar a la terminal.
            NotificacionService::notificarCancelacionConductor(
                (int)$viaje['id_usu_via'],
                $idViaje,
                trim(($viaje['nom_rut'] ?? '') . ' (' . ($viaje['ori_rut'] ?? '?') . ' → ' . ($viaje['des_rut'] ?? '?') . ')'),
                $instante,
                ViajeService::etiquetaMotivo($motivo),
                $anotacion !== '' ? $anotacion : 'Sin anotación adicional.'
            );

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

    /**
     * ¿Se puede FINALIZAR este viaje a mano?
     *
     * ANTES la única condición era «que no esté ya cerrado», así que un viaje
     * programado para MAÑANA se podía terminar hoy desde la lista: aparecía un
     * botón «Terminar» que no tenía sentido, y el backend lo aceptaba igual
     * porque no miraba la hora. El resultado eran viajes «finalizados» que
     * nunca salieron y con los pasajeros notificados de un trayecto inexistente.
     *
     * Regla vigente: para terminar un viaje tiene que haber SALIDO. Si aún no ha
     * salido, lo correcto es CANCELARLO (que además notifica y libera).
     *
     * @return array{0:bool, 1:string}  [permitido, motivo del rechazo]
     */
    public static function puedeFinalizar(array $viaje): array
    {
        if ((int)($viaje['id_via'] ?? 0) <= 0) {
            return [false, 'El viaje no existe.'];
        }
        if (in_array((string)($viaje['est_via'] ?? ''), Config::VIA_ESTADOS_CERRADOS, true)) {
            return [false, 'Este viaje ya está cerrado; no se puede volver a finalizar.'];
        }

        [$yaSalio, $instante] = Fecha::yaSalio(
            (string)($viaje['fec_via'] ?? ''),
            (string)($viaje['hor_sal_via'] ?? ''),
            Config::TOLERANCIA_SALIDA_MIN
        );

        if (!$yaSalio) {
            $salida = $instante !== '' ? Fecha::legible($instante) : 'su hora de salida';
            return [false, sprintf(
                'Este viaje sale el %s: todavía no ha salido, así que no se puede marcar como finalizado. Si no va a salir, cancélalo.',
                $salida
            )];
        }

        return [true, ''];
    }

    public static function finalizar(int $idViaje): array
    {
        $viaje = self::porId($idViaje);
        if (!$viaje) return ['ok' => false, 'mensaje' => 'El viaje no existe.'];

        /* CONTROL POR OBJETO: se comprueba sobre ESTE viaje, no solo el rol.
           Es la misma regla que usa la interfaz para pintar el botón, así que
           no puede pasar «lo vi en la lista y el servidor me dice que no». */
        [$ok, $motivo] = self::puedeFinalizar($viaje);
        if (!$ok) {
            return ['ok' => false, 'mensaje' => $motivo];
        }

        Database::begin();
        Database::query(
            "UPDATE viaje SET est_via = ?, salio = 1 WHERE id_via = ?",
            [Config::VIA_FINALIZADO, $idViaje]
        );
        self::_liberarConductor((int)$viaje['id_usu_via']);
        self::_liberarVehiculo((int)($viaje['id_veh'] ?? 0));
        Database::commit();

        // El estado de la flota se recalcula desde las ventanas reales: si el
        // conductor tenía OTRO viaje en marcha, no debe quedar libre.
        try {
            DisponibilidadService::refrescarEstados();
        } catch (Throwable $e) {
            error_log('[SGET][finalizar] ' . $e->getMessage());
        }

        Logger::registrar(Database::pdo(), 'FINALIZAR_VIAJE', "Viaje #{$idViaje} finalizado por " . Auth::nombre() . '.');

        // Aviso a los pasajeros: el viaje terminó y ya pueden calificarlo.
        NotificacionService::notificarSalida(
            $idViaje,
            trim(($viaje['nom_rut'] ?? '') . ' (' . ($viaje['ori_rut'] ?? '?') . ' → ' . ($viaje['des_rut'] ?? '?') . ')'),
            Fecha::legible($viaje['fec_via'] . ' ' . $viaje['hor_sal_via'])
        );

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

        // Los pasajeros reservados se enteran de que la unidad ya salió.
        NotificacionService::notificarSalida(
            $idViaje,
            trim(($viaje['nom_rut'] ?? '') . ' (' . ($viaje['ori_rut'] ?? '?') . ' → ' . ($viaje['des_rut'] ?? '?') . ')'),
            Fecha::legible($viaje['fec_via'] . ' ' . $viaje['hor_sal_via'])
        );

        /* ---------------------------------------------------------------
           AVISO DE VIAJE PERDIDO
           Al arrancar la unidad, quien tenía puesto y no embarcó se queda
           con las manos vacías y sin enterarse: se le avisa de una vez, aquí.
           `ReservaService::avisarViajePerdido()` solo notifica a los puestos
           con `embarco` sin decidir y sella la marca para que la
           sincronización de estado (que corre en cada apertura de un módulo)
           no lo repita. Un fallo aquí NO puede tumbar el arranque del viaje.
        ---------------------------------------------------------------- */
        $perdidos = 0;
        try {
            $r = ReservaService::avisarViajePerdido($idViaje);
            $perdidos = (int)($r['avisados'] ?? 0);
        } catch (Throwable $e) {
            error_log('[SGET][ViajeService::marcarEnCurso] ' . $e->getMessage());
        }

        return [
            'ok'       => true,
            'avisados' => $perdidos,
            'mensaje'  => $perdidos > 0
                ? sprintf('Viaje #%d marcado «En curso». Se avisó a %d pasajero(s) de que su viaje salió sin ellos.', $idViaje, $perdidos)
                : "Viaje #{$idViaje} marcado «En curso».",
        ];
    }

    /* ================================================================== */
    /* CICLO DE VIDA TEMPORAL: salida real, llegada esperada y vencimiento */
    /* ================================================================== */

    /**
     * Duración del trayecto en minutos.
     * Prioridad: la hora de llegada del viaje → la duración de la ruta →
     * el valor por defecto del sistema. Nunca devuelve null ni 0.
     */
    public static function duracionMin(array $viaje): int
    {
        $salida  = Fecha::instanteSalida($viaje['fec_via'] ?? null, $viaje['hor_sal_via'] ?? null);
        $llegada = Fecha::soloHora($viaje['hor_lleg_via'] ?? '');

        /* `hor_lleg_via` es una HORA suelta, sin fecha. Compararla con
           `strtotime()` contra un fecha y hora completas mezclaba dos bases:
           `strtotime('09:30')` devuelve HOY a las 09:30, así que para un viaje de
           ayer la diferencia no eran 150 minutos, sino los ~10 000 que hay
           entre las dos fechas. El resultado era un trayecto absurdo que
           cerraba el viaje mucho antes de tiempo (o lo mantenía abierto
           días). Aquí se reconstruye la llegada sobre la FECHA DE SALIDA, y si
           la hora de llegada es anterior a la de salida, el trayecto cruza la
           medianoche y se cuenta como día siguiente. */
        if ($llegada !== '' && $salida !== null) {
            $dia      = substr($salida, 0, 10);
            $llegadaF = strtotime($dia . ' ' . $llegada);
            $salidaTs = strtotime($salida);

            if ($llegadaF !== false && $llegadaF <= $salidaTs) {
                $llegadaF += 86400;   // llega al día siguiente
            }

            $min = (int) round(($llegadaF - $salidaTs) / 60);
            if ($min > 0 && $min <= 2880) {   // 48 h: por encima, es un dato corrupto
                return $min;
            }
        }

        if (isset($viaje['duracion_min']) && (int)$viaje['duracion_min'] > 0) {
            return (int)$viaje['duracion_min'];
        }

        return Config::DURACION_VIAJE_MIN_POR_DEFECTO;
    }

    /**
     * Instante en el que el viaje DEBERÍA haber terminado:
     *     hora de salida + duración del trayecto (+ margen de cortesía).
     *
     * Sustituye al antiguo "24 horas después de la salida", que cerraba viajes
     * a destiempo o los dejaba abiertos días enteros.
     */
    public static function instanteVencimiento(array $viaje, ?int $margenMin = null): ?string
    {
        $salida = Fecha::instanteSalida($viaje['fec_via'] ?? null, $viaje['hor_sal_via'] ?? null);
        if ($salida === null) return null;

        $margen = $margenMin ?? Config::MARGEN_CIERRE_AUTOMATICO_MIN;
        $fin    = strtotime($salida) + (self::duracionMin($viaje) * 60) + ($margen * 60);

        return date(Fecha::FMT_MYSQL, $fin);
    }

    /** ¿Ya pasó la hora de salida? */
    public static function yaSalio(array $viaje): bool
    {
        $salida = Fecha::instanteSalida($viaje['fec_via'] ?? null, $viaje['hor_sal_via'] ?? null);
        return $salida !== null && strtotime($salida) <= time();
    }

    /** ¿Ya se cumplió salida + duración? */
    public static function vencio(array $viaje, ?int $margenMin = null): bool
    {
        $fin = self::instanteVencimiento($viaje, $margenMin);
        return $fin !== null && strtotime($fin) <= time();
    }

    /** Fase legible del viaje para la interfaz. */
    public static function fase(array $viaje): array
    {
        $salida = Fecha::instanteSalida($viaje['fec_via'] ?? null, $viaje['hor_sal_via'] ?? null);

        if ($salida === null) {
            return ['clave' => 'sin_fecha', 'etiqueta' => 'Sin fecha', 'clase' => 'sget-badge--neutro', 'icono' => 'fa-circle-question'];
        }
        if (!self::yaSalio($viaje)) {
            return ['clave' => 'programado', 'etiqueta' => 'Programado', 'clase' => 'sget-badge--info', 'icono' => 'fa-calendar-check'];
        }
        if (self::vencio($viaje)) {
            return ['clave' => 'vencido', 'etiqueta' => 'Vencido', 'clase' => 'sget-badge--aviso', 'icono' => 'fa-hourglass-end'];
        }
        return ['clave' => 'en_curso', 'etiqueta' => 'En curso', 'clase' => 'sget-badge--exito', 'icono' => 'fa-truck-fast'];
    }

    /* ================================================================== */
    /* Sincronizacion automatica con el reloj                              */
    /* ================================================================== */

    /**
     * Sincroniza el estado de los viajes abiertos con el reloj:
     *   1. los que ya pasaron su hora de salida quedan «En curso» (salio = 1);
     *   2. los que cumplieron salida + duración se cierran como «Finalizado»
     *      y liberan conductor y vehículo.
     *
     * Es idempotente: se puede ejecutar en cada carga sin duplicar efectos.
     *
     * @return array{marcar:int, cerrar:int, viajes:array<int,int>}
     */
    public static function sincronizarEstado(bool $cierraAutomatico = true): array
    {
        $defecto = Config::DURACION_VIAJE_MIN_POR_DEFECTO;
        $margen  = Config::MARGEN_CIERRE_AUTOMATICO_MIN;

        $abiertos = Database::all(
            "SELECT v.id_via, v.fec_via, v.hor_sal_via, v.hor_lleg_via, v.est_via, v.salio,
                    v.id_usu_via, v.id_veh, v.nom_via,
                    COALESCE(r.duracion_min, ?) AS duracion_min
               FROM viaje v
               LEFT JOIN rutas r ON r.id_rut = v.id_rut_via
              WHERE v.est_via IN ('Programado', 'En curso')
                AND v.fec_via IS NOT NULL
                AND v.hor_sal_via IS NOT NULL",
            [$defecto]
        );

        $marcados  = 0;
        $cerrados  = [];
        $idsMarcar = [];
        $idsCerrar = [];

        foreach ($abiertos as $v) {
            $salio = self::yaSalio($v);

            if ($salio) {
                $marcados++;
                if ((int)$v['salio'] === 0) {
                    $idsMarcar[] = (int)$v['id_via'];
                }
                if ($v['est_via'] === Config::VIA_PROGRAMADO) {
                    Database::query("UPDATE viaje SET est_via = ?, salio = 1 WHERE id_via = ?",
                        [Config::VIA_EN_CURSO, (int)$v['id_via']]);
                }
            }

            if ($cierraAutomatico && self::vencio($v, $margen)) {
                $idsCerrar[] = (int)$v['id_via'];
            }
        }

        if ($idsMarcar) {
            Database::query(
                'UPDATE viaje SET salio = 1 WHERE id_via IN (' . implode(',', array_map('intval', $idsMarcar)) . ')'
            );
        }

        foreach ($idsCerrar as $id) {
            $v = null;
            foreach ($abiertos as $c) {
                if ((int)$c['id_via'] === $id) { $v = $c; break; }
            }
            if (!$v) continue;

            Database::query("UPDATE viaje SET est_via = ? WHERE id_via = ?", [Config::VIA_FINALIZADO, $id]);
            self::_liberarConductor((int)$v['id_usu_via']);
            self::_liberarVehiculo((int)($v['id_veh'] ?? 0));
            $cerrados[] = $id;
        }

        /* El estado de las unidades se recalcula DESPUÉS de cerrar, para que un
           recurso con dos viajes consecutivos (07:00-09:00 y 14:00-16:00) siga
           ocupado con el segundo y solo se libere al terminar este último. */
        if ($idsCerrar || $idsMarcar) {
            try {
                DisponibilidadService::refrescarEstados();
            } catch (Throwable $e) {
                error_log('[SGET][sincronizarEstado] ' . $e->getMessage());
            }
        }

        return ['marcar' => $marcados, 'cerrar' => count($cerrados), 'viajes' => $cerrados];
    }

    /**
     * Atajo legacy: devuelve cuántos viajes se cerraron automáticamente.
     * @deprecated Usa sincronizarEstado()['cerrar'].
     */
    public static function cerrarVencidos(int $horas = 24): int
    {
        return self::sincronizarEstado(true)['cerrar'];
    }

    /* ================================================================== */
    /* Sincronizacion de estados (0 = ocupado, 1 = disponible)            */
    /* ================================================================== */

    /** @deprecated Usa `DisponibilidadService::marcarConductorOcupado()`. */
    private static function _ocuparConductor(int $id): void
    {
        DisponibilidadService::marcarConductorOcupado($id);
    }

    /** @deprecated Usa `DisponibilidadService::liberarConductor()`. */
    private static function _liberarConductor(int $id): void
    {
        DisponibilidadService::liberarConductor($id);
    }

    /**
     * Ocupa un vehículo para un viaje.
     *
     * SOLO cambia las unidades que estaban «Disponible»: si el administrador
     * la pasó a Mantenimiento o Fuera de servicio mientras había viajes abiertos,
     * ese estado manda y no se pisa. Antes se escribía siempre el estado
     * «fuera de servicio», de modo que cualquier viaje dejaba la flota llena de
     * averías y el selector de vehículos se vaciaba.
     *
     * @deprecated Usa `DisponibilidadService::marcarVehiculoAsignado()`.
     */
    private static function _ocuparVehiculo(int $id): void
    {
        DisponibilidadService::marcarVehiculoAsignado($id);
    }

    /** Libera un vehículo al cerrar el viaje, sin tocar estados ajenos. */
    private static function _liberarVehiculo(int $id): void
    {
        DisponibilidadService::liberarVehiculo($id);
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

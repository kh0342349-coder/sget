<?php
/**
 * services/DisponibilidadService.php
 * -----------------------------------------------------------------------------
 * ÚNICA FUENTE DE VERDAD DE LA DISPONIBILIDAD DE CONDUCTORES Y VEHÍCULOS
 * -----------------------------------------------------------------------------
 * EL PROBLEMA QUE RESUELVE
 *   La disponibilidad se decidía mirando únicamente si el recurso tenía algún
 *   viaje en estado `Programado` o `En curso`:
 *
 *       WHERE id_usu_via = ? AND est_via IN ('Programado','En curso')
 *
 *   Eso produce dos errores opuestos y muy visibles:
 *
 *     · UN VIAJE FUTURO BLOQUEA INDEFINIDAMENTE. Un viaje mañana a las 10:00
 *       marcaba al conductor como «ocupado» desde hoy, así que no podía hacer
 *       nada más hasta mañana.
 *
 *     · DOS VIAJES EL MISMO DÍA SIEMPRE ENTRAN EN CONFLICTO. El conductor podía
 *       hacer 07:00-09:00 y luego 14:00-16:00 sin ningún problema, pero el
 *       sistema lo rechazaba porque a las 07:00 ya tenía un viaje abierto.
 *
 *   Y lo peor: el SELECTOR y la VALIDACIÓN no compartían lógica. El selector
 *   miraba `est_veh = 'Disponible'` y la validación miraba el estado del
 *   viaje, así que se ofrecían unidades que después el backend rechazaba.
 *
 * LA REGLA APLICADA
 *   Un viaje ocupa el intervalo
 *
 *       [salida − margen ······· llegada + margen]
 *
 *   Dos viajes chocan cuando esos intervalos se SOLAPAN. Fuera de ahí, el mismo
 *   conductor o vehículo se puede reutilizar, incluso el mismo día.
 *
 *       A 10:00→12:00      B 11:30→13:30   -> CONFLICTO (se solapan)
 *       A 10:00→12:00      B 14:00→16:00   -> SIN CONFLICTO
 *       A 01/10 10:00→12:00  B 02/10 08:00→10:00  -> SIN CONFLICTO
 *
 * LA DURACIÓN DE LA VENTANA
 *   Mismo orden de prioridad que usa `ViajeService::duracionMin()`, para que
 *   «cuándo termina un viaje» y «cuándo libera el recurso» nunca discrepen:
 *
 *       1. `viaje.hor_lleg_via`  (hora de llegada que define el conductor)
 *       2. `rutas.duracion_min`  (la que se carga al crear la ruta)
 *       3. `Config::DURACION_VIAJE_MIN_POR_DEFECTO`
 *
 * ESTADOS QUE SIGUEN CONTANDO
 *   · Programado / En curso  -> siempre pueden seguir occupando.
 *   · Finalizado             -> solo si su ventana (fin + margen) aún no pasó.
 *     Un viaje que terminó a las 12:00 y cuyo margen acaba a las 12:15 sigue
 *     reservando el recurso hasta las 12:15. Pasada esa hora, se ignora.
 *   · Cancelado              -> NUNCA cuenta. Un viaje cancelado libera.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class DisponibilidadService
{
    /* ================================================================== */
    /* Ventana temporal                                                   */
    /* ================================================================== */

    /**
     * Duración del trayecto en minutos con la prioridad documentada arriba.
     *
     * @param array $viaje  fila con `fec_via`, `hor_sal_via`, `hor_lleg_via`
     *                      y, opcionalmente, `duracion_min` (de `rutas`)
     */
    public static function duracionMin(array $viaje): int
    {
        $salida = Fecha::instanteSalida(
            (string)($viaje['fec_via'] ?? ''),
            (string)($viaje['hor_sal_via'] ?? '')
        );
        if ($salida === null) {
            return Config::DURACION_VIAJE_MIN_POR_DEFECTO;
        }

        $llegada = Fecha::soloHora($viaje['hor_lleg_via'] ?? '');

        if ($llegada !== '') {
            $dia      = substr($salida, 0, 10);
            $salidaTs = strtotime($salida);
            $llegadaTs = strtotime($dia . ' ' . $llegada);

            if ($llegadaTs !== false && $llegadaTs <= $salidaTs) {
                $llegadaTs += 86400;   // el trayecto cruza la medianoche
            }

            if ($llegadaTs !== false) {
                $min = (int) round(($llegadaTs - $salidaTs) / 60);
                // 2880 min = 48 h. Por encima, el dato es corrupto y no se fía.
                if ($min > 0 && $min <= 2880) {
                    return $min;
                }
            }
        }

        if ((int)($viaje['duracion_min'] ?? 0) > 0) {
            return (int)$viaje['duracion_min'];
        }

        return Config::DURACION_VIAJE_MIN_POR_DEFECTO;
    }

    /**
     * Ventana ABSOLUTA que ocupa un viaje, en marcas de tiempo.
     *
     * @return array{inicio:int, fin:int, margen:int, ok:bool, duracion:int}
     *         `ok = false` cuando el viaje no tiene fecha/hora utilizables.
     */
    public static function ventana(array $viaje, ?int $margenMin = null): array
    {
        $margen = ($margenMin ?? Config::MARGEN_DISPONIBILIDAD_MIN) * 60;
        $salida = Fecha::instanteSalida(
            (string)($viaje['fec_via'] ?? ''),
            (string)($viaje['hor_sal_via'] ?? '')
        );

        if ($salida === null) {
            return ['inicio' => 0, 'fin' => 0, 'margen' => $margen, 'ok' => false, 'duracion' => 0];
        }

        $inicio = (int)strtotime($salida);
        $duracion = self::duracionMin($viaje) * 60;

        return [
            'inicio'   => $inicio,
            'fin'      => $inicio + $duracion,
            'margen'   => $margen,
            'ok'       => true,
            'duracion' => (int)round($duracion / 60),
        ];
    }

    /** Ventana a partir de fecha y hora sueltas (lo que manda el formulario). */
    public static function ventanaDe(string $fecha, string $hora, int $duracionMin = 0, ?string $llegada = null): array
    {
        return self::ventana([
            'fec_via'      => $fecha,
            'hor_sal_via'  => $hora,
            'hor_lleg_via' => $llegada,
            'duracion_min' => $duracionMin,
        ]);
    }

    /* ================================================================== */
    /* Detección de solapamientos                                          */
    /* ================================================================== */

    /** ¿Se solapan dos intervalos, ya ampliados con el margen? */
    public static function seSolapan(array $a, array $b): bool
    {
        if (!$a['ok'] || !$b['ok']) {
            // Sin fecha válida no se puede afirmar nada: se trata como conflicto
            // para no abrir un hueco por un dato corrupto.
            return true;
        }
        return ($a['inicio'] - $a['margen']) < ($b['fin'] + $b['margen'])
            && ($b['inicio'] - $b['margen']) < ($a['fin'] + $a['margen']);
    }

    /**
     * Viajes existentes que chocan con una ventana dada para un recurso.
     *
     * @param string $recurso  `conductor` | `vehiculo` (lista blanca: nunca se
     *                         interpola texto de la petición en el SQL)
     * @param int    $id       id del conductor o del vehículo
     * @param int    $exceptoIdViaje  viaje en edición: se excluye a sí mismo
     * @return array<int,array>  filas de `viaje` en conflicto
     */
    public static function conflictos(string $recurso, int $id, array $ventana, int $exceptoIdViaje = 0): array
    {
        $columna = match ($recurso) {
            'conductor' => 'v.id_usu_via',
            'vehiculo'  => 'v.id_veh',
            default     => throw new InvalidArgumentException("Recurso no permitido: {$recurso}"),
        };

        if ($id <= 0) {
            return [];
        }

        $sql = "SELECT v.id_via, v.nom_via, v.fec_via, v.hor_sal_via, v.hor_lleg_via, v.est_via,
                       r.nom_rut, COALESCE(r.duracion_min, ?) AS duracion_min
                  FROM viaje v
                  LEFT JOIN rutas r ON r.id_rut = v.id_rut_via
                 WHERE {$columna} = ?
                   AND v.est_via <> ?
                   AND v.fec_via IS NOT NULL
                   AND v.hor_sal_via IS NOT NULL";
        $params = [Config::DURACION_VIAJE_MIN_POR_DEFECTO, $id, Config::VIA_CANCELADO];

        if ($exceptoIdViaje > 0) {
            $sql .= ' AND v.id_via <> ?';
            $params[] = $exceptoIdViaje;
        }

        // Solo tiene sentido revisar las ventanas que ROZAN la ventana nueva:
        // un margen generoso de un día hacia delante y hacia atrás descarta de
        // entrada los viajes de días lejanos.
        if ($ventana['ok']) {
            $desde = $ventana['inicio'] - 86400 - $ventana['margen'];
            $hasta = $ventana['fin'] + 86400 + $ventana['margen'];
            $sql .= ' AND TIMESTAMP(v.fec_via, v.hor_sal_via) BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)';
            $params[] = $desde;
            $params[] = $hasta;
        }

        $sql .= ' ORDER BY v.fec_via ASC, v.hor_sal_via ASC';

        $conflictos = [];
        foreach (Database::all($sql, $params) as $fila) {
            // Un viaje ya finalizado solo cuenta si su ventana todavía no venció.
            if ((string)$fila['est_via'] === Config::VIA_FINALIZADO && !self::ventanaVigente($fila)) {
                continue;
            }
            if (self::seSolapan($ventana, self::ventana($fila))) {
                $conflictos[] = $fila;
            }
        }

        return $conflictos;
    }

    /** ¿La ventana de este viaje todavía alcanza a ocupar un recurso? */
    public static function ventanaVigente(array $viaje, ?int $margenMin = null): bool
    {
        $v = self::ventana($viaje, $margenMin);
        if (!$v['ok']) {
            return false;
        }
        return ($v['fin'] + $v['margen']) >= time();
    }

    /** ¿Está el recurso ocupado AHORA MISMO (dentro de alguna ventana)? */
    public static function ocupadoAhora(string $recurso, int $id): bool
    {
        return self::conflictos($recurso, $id, self::ventana([
            'fec_via' => date('Y-m-d'),
            'hor_sal_via' => date('H:i:s'),
        ]), 0) !== [];
    }

    /* ================================================================== */
    /* Validación de un recurso concreto                                  */
    /* ================================================================== */

    /**
     * ¿Se puede asignar este CONDUCTOR a la ventana dada?
     *
     * @return array{ok:bool, mensaje:string, conflictos:array}
     */
    public static function comprobarConductor(int $idConductor, array $ventana, int $exceptoIdViaje = 0): array
    {
        if ($idConductor <= 0) {
            return ['ok' => false, 'mensaje' => 'El conductor indicado no es válido.', 'conflictos' => []];
        }

        $fila = Database::one(
            'SELECT id_usu, nom_usu, estado FROM usuario WHERE id_usu = ?',
            [$idConductor]
        );
        if (!$fila) {
            return ['ok' => false, 'mensaje' => 'El conductor seleccionado no existe.', 'conflictos' => []];
        }
        if ((int)$fila['estado'] !== Config::USU_ACTIVO) {
            return ['ok' => false, 'mensaje' => 'El conductor seleccionado está inactivo.', 'conflictos' => []];
        }

        $conflictos = self::conflictos('conductor', $idConductor, $ventana, $exceptoIdViaje);
        if ($conflictos !== []) {
            return [
                'ok'         => false,
                'mensaje'    => self::mensajeConflicto((string)$fila['nom_usu'], $conflictos),
                'conflictos' => $conflictos,
            ];
        }

        return ['ok' => true, 'mensaje' => '', 'conflictos' => []];
    }

    /**
     * ¿Se puede asignar este VEHÍCULO a la ventana dada?
     *
     * @return array{ok:bool, mensaje:string, conflictos:array}
     */
    public static function comprobarVehiculo(int $idVehiculo, array $ventana, int $exceptoIdViaje = 0): array
    {
        if ($idVehiculo <= 0) {
            return ['ok' => false, 'mensaje' => 'El vehículo indicado no es válido.', 'conflictos' => []];
        }

        $fila = Database::one(
            'SELECT id_veh, pla_veh, est_veh FROM vehiculo WHERE id_veh = ?',
            [$idVehiculo]
        );
        if (!$fila) {
            return ['ok' => false, 'mensaje' => 'El vehículo seleccionado no existe.', 'conflictos' => []];
        }

        /* Un vehículo en mantenimiento o fuera de servicio NUNCA puede recibir
           un viaje, tenga el horario que tenga. */
        if (!VehiculoService::operativo((string)$fila['est_veh'])) {
            return [
                'ok'      => false,
                'mensaje' => sprintf(
                    'El vehículo %s está en %s y no puede recibir viajes.',
                    (string)$fila['pla_veh'],
                    mb_strtolower(VehiculoService::normalizar((string)$fila['est_veh']))
                ),
                'conflictos' => [],
            ];
        }

        $conflictos = self::conflictos('vehiculo', $idVehiculo, $ventana, $exceptoIdViaje);
        if ($conflictos !== []) {
            return [
                'ok'         => false,
                'mensaje'    => self::mensajeConflicto((string)$fila['pla_veh'], $conflictos),
                'conflictos' => $conflictos,
            ];
        }

        return ['ok' => true, 'mensaje' => '', 'conflictos' => []];
    }

    /** Mensaje legible para el administrador: qué choca con qué y cuándo. */
    public static function mensajeConflicto(string $nombre, array $conflictos): string
    {
        $c = $conflictos[0];
        $ventana = self::ventana($c);

        $cuando = sprintf(
            '%s de %s a %s',
            Fecha::legible((string)$c['fec_via'], false),
            Fecha::soloHora((string)$c['hor_sal_via']),
            $ventana['ok'] ? Fecha::soloHora(date(Fecha::FMT_MYSQL, $ventana['fin'])) : '—'
        );

        return sprintf(
            '%s ya tiene el viaje «%s» de %s%s.',
            $nombre,
            (string)($c['nom_rut'] ?? ($c['nom_via'] ?? 'sin ruta')),
            $cuando,
            count($conflictos) > 1 ? sprintf(' (y %d viaje(s) más)', count($conflictos) - 1) : ''
        );
    }

    /* ================================================================== */
    /* Listas para los selectores (misma lógica que la validación)        */
    /* ================================================================== */

    /**
     * Conductores con su disponibilidad REAL para una fecha/hora concreta.
     *
     * @return array<int,array{id_usu:int, nom_usu:string, tel_usu:?string, disponible:bool, motivo:string}>
     */
    public static function conductores(string $fecha, string $hora, int $exceptoIdViaje = 0): array
    {
        $ventana = self::ventanaDe($fecha, $hora);

        $filas = Database::all(
            'SELECT u.id_usu, u.nom_usu, u.tel_usu, u.estado
               FROM usuario u
              WHERE u.id_rol_usu = ? AND u.estado = ?
              ORDER BY u.nom_usu ASC',
            [Config::ROL_CONDUCTOR, Config::USU_ACTIVO]
        );

        foreach ($filas as &$f) {
            $r = self::comprobarConductor((int)$f['id_usu'], $ventana, $exceptoIdViaje);
            $f['disponible'] = $r['ok'];
            $f['motivo']     = $r['mensaje'];
        }
        unset($f);

        return $filas;
    }

    /**
     * Vehículos con su disponibilidad REAL para una fecha/hora concreta.
     *
     * @return array<int,array{id_veh:int, pla_veh:string, mode_veh:string,
     *                         cap_veh:int, disponible:bool, motivo:string}>
     */
    public static function vehiculos(string $fecha, string $hora, int $exceptoIdViaje = 0): array
    {
        $ventana = self::ventanaDe($fecha, $hora);

        $filas = Database::all(
            'SELECT id_veh, pla_veh, mode_veh, cap_veh, est_veh
               FROM vehiculo
              ORDER BY pla_veh ASC'
        );

        foreach ($filas as &$f) {
            $estado = VehiculoService::normalizar((string)$f['est_veh']);
            $f['estado'] = $estado;

            if (!VehiculoService::operativo($estado)) {
                $f['disponible'] = false;
                $f['motivo']     = sprintf('Vehículo en %s.', mb_strtolower($estado));
                continue;
            }

            $r = self::comprobarVehiculo((int)$f['id_veh'], $ventana, $exceptoIdViaje);
            $f['disponible'] = $r['ok'];
            $f['motivo']     = $r['mensaje'];
        }
        unset($f);

        return $filas;
    }

    /* ================================================================== */
    /* Estados reflejados en las tablas                                   */
    /* ================================================================== */

    /**
     * Recalcula `usuario.est_con_usu` y `vehiculo.est_veh` a partir de las
     * VENTANAS REALES, no del último `guardar()`.
     *
     * Esto es lo que arregla «un viaje mañana a las 10:00 no debe tener al
     * conductor bloqueado hoy»:
     *
     *     ahora está DENTRO de alguna ventana  -> Ocupado / Asignado
     *     ahora está FUERA  de todas            -> Disponible
     *
     * Los estados Mantenimiento y Fuera de servicio son decisiones del
     * administrador y NUNCA se tocan aquí.
     *
     * @return array{conductores:int, vehiculos:int}
     */
    public static function refrescarEstados(): array
    {
        $conductoresCambiados = 0;
        $vehiculosCambiados   = 0;

        /* --- Conductores --- */
        $conductores = Database::all(
            "SELECT id_usu, est_con_usu FROM usuario WHERE id_rol_usu = ? AND estado = ?",
            [Config::ROL_CONDUCTOR, Config::USU_ACTIVO]
        );

        foreach ($conductores as $c) {
            $ocupado = self::ocupadoAhora('conductor', (int)$c['id_usu']);
            $estadoActual = (int)($c['est_con_usu'] ?? Config::CON_DISPONIBLE);
            $deseado = $ocupado ? Config::CON_OCUPADO : Config::CON_DISPONIBLE;

            if ($estadoActual !== $deseado) {
                Database::query(
                    'UPDATE usuario SET est_con_usu = ? WHERE id_usu = ?',
                    [$deseado, (int)$c['id_usu']]
                );
                $conductoresCambiados++;
            }
        }

        /* --- Vehículos --- */
        $vehiculos = Database::all(
            'SELECT id_veh, est_veh FROM vehiculo'
        );

        foreach ($vehiculos as $v) {
            $estado = VehiculoService::normalizar((string)$v['est_veh']);

            // Decisión del administrador: se respeta siempre.
            if ($estado === Config::VEH_MANTENIMIENTO || $estado === Config::VEH_FUERA_SERVICIO) {
                continue;
            }

            $deseado = self::ocupadoAhora('vehiculo', (int)$v['id_veh'])
                ? Config::VEH_ASIGNADO
                : Config::VEH_DISPONIBLE;

            if ($estado !== $deseado) {
                Database::query(
                    'UPDATE vehiculo SET est_veh = ? WHERE id_veh = ?',
                    [$deseado, (int)$v['id_veh']]
                );
                $vehiculosCambiados++;
            }
        }

        return ['conductores' => $conductoresCambiados, 'vehiculos' => $vehiculosCambiados];
    }

    /**
     * Libera explícitamente un recurso. Se usa al cancelar y al finalizar un
     * viaje, y es idempotente: se puede llamar aunque ya estuviera libre.
     */
    public static function liberarConductor(int $id): void
    {
        if ($id > 0) {
            Database::query(
                'UPDATE usuario SET est_con_usu = ? WHERE id_usu = ?',
                [Config::CON_DISPONIBLE, $id]
            );
        }
    }

    public static function liberarVehiculo(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        // Solo se saca de «Asignado»: si está en mantenimiento o fuera de
        // servicio, esa es la decisión vigente y manda.
        Database::query(
            'UPDATE vehiculo SET est_veh = ? WHERE id_veh = ? AND est_veh = ?',
            [Config::VEH_DISPONIBLE, $id, Config::VEH_ASIGNADO]
        );
    }

    /**
     * Marca un recurso como ocupado. Idempotente y no invasivo: si el
     * administrador puso la unidad en mantenimiento, no se pisa.
     */
    public static function marcarConductorOcupado(int $id): void
    {
        if ($id > 0) {
            Database::query(
                'UPDATE usuario SET est_con_usu = ? WHERE id_usu = ?',
                [Config::CON_OCUPADO, $id]
            );
        }
    }

    public static function marcarVehiculoAsignado(int $id): void
    {
        if ($id > 0) {
            Database::query(
                'UPDATE vehiculo SET est_veh = ? WHERE id_veh = ? AND est_veh = ?',
                [Config::VEH_ASIGNADO, $id, Config::VEH_DISPONIBLE]
            );
        }
    }
}

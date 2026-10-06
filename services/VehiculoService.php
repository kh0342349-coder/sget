<?php
/**
 * services/VehiculoService.php
 * -----------------------------------------------------------------------------
 * MÓDULO: FLOTA
 * -----------------------------------------------------------------------------
 * ESTADOS DE LA UNIDAD
 *   `vehiculo.est_veh` es un ENUM de cuatro valores y cada uno significa algo
 *   distinto:
 *
 *       Disponible        → puede recibir un viaje
 *       Asignado          → tiene un viaje abierto; lo pone y lo quita el
 *                           sistema, no el administrador a mano
 *       Mantenimiento     → la unidad existe pero está en revisión
 *       Fuera de servicio → avería, venta o baja: no vuelve al servicio sola
 *
 *   ANTES era `tinyint(1)`: 1 = Disponible y 0 = TODO LO DEMÁS. Eso hacía que una
 *   unidad en mantenimiento fuera indistinguible de una averiada, y que al
 *   asignarle un viaje (`ViajeService::_ocuparVehiculo`) quedara registrada como
 *   «fuera de servicio», es decir, averiada, solo por estar trabajando.
 *
 *   La migración 009 convierte la columna y traduce los valores antiguos.
 *   `normalizar()` acepta igualmente el 0/1 antiguo para que ninguna pantalla
 *   quede mostrando «Fuera de servicio» donde dice «Mantenimiento».
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class VehiculoService
{
    /* ================================================================== */
    /* Consulta                                                            */
    /* ================================================================== */

    public static function todos(bool $soloOperativos = false): array
    {
        $sql = "SELECT v.*,
                       (SELECT COUNT(*) FROM viaje vi
                         WHERE vi.id_veh = v.id_veh
                           AND vi.est_via IN ('Programado','En curso')) AS viajes_asignados
                  FROM vehiculo v";
        if ($soloOperativos) {
            $sql .= ' WHERE v.est_veh = ?';
        }
        $sql .= ' ORDER BY v.id_veh DESC';

        $filas = $soloOperativos ? Database::all($sql, [Config::VEH_DISPONIBLE]) : Database::all($sql);
        foreach ($filas as &$f) {
            $f['est_veh'] = self::normalizar((string)($f['est_veh'] ?? ''));
        }
        unset($f);

        return $filas;
    }

    public static function porId(int $id): ?array
    {
        $f = Database::one('SELECT * FROM vehiculo WHERE id_veh = ?', [$id]);
        if ($f) {
            $f['est_veh'] = self::normalizar((string)($f['est_veh'] ?? ''));
        }
        return $f;
    }

    /**
     * Unidades que pueden recibir un viaje en la fecha/hora indicados.
     *
     * ANTES: `est_veh = 'Disponible' AND id_veh NOT IN (SELECT id_veh FROM viaje
     * WHERE est_via IN ('Programado','En curso'))`. Dos fallos: una unidad con
     * un viaje mañana quedaba descartada hoy, y dos viajes del mismo día
     * siempre se rechazaban aunque no se solaparan.
     *
     * AHORA devuelve TODAS las unidades con un indicador `disponible` calculado
     * por `DisponibilidadService` — la MISMA función que valida al guardar — de
     * modo que lo que ve el administrador y lo que acepta el backend no puedan
     * discrepar.
     *
     * @return array<int,array{id_veh:int, pla_veh:string, mode_veh:string,
     *                         cap_veh:int, disponible:bool, motivo:string}>
     */
    public static function disponiblesParaDespacho(string $fecha = '', string $hora = '', int $exceptoIdViaje = 0): array
    {
        $fecha = trim($fecha) !== '' ? $fecha : date('Y-m-d');
        $hora  = trim($hora)  !== '' ? $hora  : date('H:i:s');
        return DisponibilidadService::vehiculos($fecha, $hora, $exceptoIdViaje);
    }

    public static function placaExiste(string $placa, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM vehiculo WHERE pla_veh = ?';
        $p = [$placa];
        if ($exceptoId !== null) { $sql .= ' AND id_veh <> ?'; $p[] = $exceptoId; }
        return (int) Database::scalar($sql, $p) > 0;
    }

    /* ================================================================== */
    /* Estados                                                             */
    /* ================================================================== */

    /**
     * Traduce cualquier valor (incluido el 0/1 legacy) a un estado del ENUM.
     * Un valor desconocido NO se inventa: se marca «Fuera de servicio», que es
     * el estado conservador (no se despacha una unidad en estado desconocido).
     */
    public static function normalizar(string $valor): string
    {
        $valor = trim($valor);

        return match (mb_strtolower($valor)) {
            'disponible', '1', 'activo', 'libre'  => Config::VEH_DISPONIBLE,
            'asignado', 'ocupado', '2', 'en ruta'  => Config::VEH_ASIGNADO,
            'mantenimiento', '3', 'revisión', 'revision' => Config::VEH_MANTENIMIENTO,
            'fuera de servicio', 'fuera_de_servicio', '0', 'inactivo', 'baja'
                                                 => Config::VEH_FUERA_SERVICIO,
            default                               => Config::VEH_FUERA_SERVICIO,
        };
    }

    /** ¿La unidad está disponible para recibir un viaje? */
    public static function operativo(?string $estado): bool
    {
        return $estado !== null && self::normalizar($estado) === Config::VEH_DISPONIBLE;
    }

    public static function etiquetaEstado(?string $estado): string
    {
        return self::normalizar((string) $estado);
    }

    /** Clase de badge según el estado (un único color por situación). */
    public static function claseEstado(?string $estado): string
    {
        return match (self::normalizar((string) $estado)) {
            Config::VEH_DISPONIBLE     => 'sget-badge sget-badge--exito',
            Config::VEH_ASIGNADO       => 'sget-badge sget-badge--info',
            Config::VEH_MANTENIMIENTO  => 'sget-badge sget-badge--aviso',
            default                    => 'sget-badge sget-badge--error',
        };
    }

    /** Icono asociado al estado. */
    public static function iconoEstado(?string $estado): string
    {
        return match (self::normalizar((string) $estado)) {
            Config::VEH_DISPONIBLE     => 'fa-circle-check',
            Config::VEH_ASIGNADO       => 'fa-truck-fast',
            Config::VEH_MANTENIMIENTO  => 'fa-wrench',
            default                    => 'fa-ban',
        };
    }

    /** Explicación corta de cada estado, para los tooltips de la interfaz. */
    public static function ayudaEstado(?string $estado): string
    {
        return match (self::normalizar((string) $estado)) {
            Config::VEH_DISPONIBLE     => 'Libre: puede asignarse a un viaje nuevo.',
            Config::VEH_ASIGNADO       => 'Tiene un viaje abierto. El sistema lo libera al finalizarlo o cancelarlo.',
            Config::VEH_MANTENIMIENTO  => 'En revisión: no puede recibir viajes hasta volver a Disponible.',
            default                    => 'Fuera de operación: no puede recibir viajes.',
        };
    }

    /* ================================================================== */
    /* Validación                                                         */
    /* ================================================================== */

    public static function validar(array $post): Validator
    {
        $id     = (int)($post['id_veh'] ?? 0);
        $placa  = strtoupper(trim((string)($post['pla_veh'] ?? '')));
        $estado = self::normalizar((string)($post['est_veh'] ?? Config::VEH_DISPONIBLE));

        $v = Validator::de($post)
            ->requerido('pla_veh', 'La placa')
            ->texto('pla_veh', 'La placa', 5, 10)
            ->requerido('mode_veh', 'La línea / modelo')
            ->texto('mode_veh', 'La línea / modelo', 2, 50)
            ->requerido('cap_veh', 'La capacidad')
            ->entero('cap_veh', 'La capacidad', 1, 120);

        $v->agregaSi(
            $placa !== '' && self::placaExiste($placa, $id > 0 ? $id : null),
            'pla_veh',
            'Ya existe un vehículo registrado con esa placa.'
        );

        // `Asignado` no se elige a mano: lo pone el sistema al crear un viaje.
        // Aceptarlo haría que una unidad quedara «en ruta» sin tener ninguna.
        $v->agregaSi(
            $estado === Config::VEH_ASIGNADO,
            'est_veh',
            'El estado «Asignado» lo aplica el sistema cuando la unidad tiene un viaje. Elige Disponible, Mantenimiento o Fuera de servicio.'
        );

        return $v;
    }

    /* ================================================================== */
    /* Escritura                                                          */
    /* ================================================================== */

    public static function guardar(array $post): array
    {
        $v = self::validar($post);
        if ($v->falla()) {
            return ['ok' => false, 'errores' => $v->errores(), 'mensaje' => $v->primerError()];
        }

        $id     = (int)($post['id_veh'] ?? 0);
        $placa  = strtoupper(trim((string)$post['pla_veh']));
        $modelo = trim((string)$post['mode_veh']);
        $cap    = (int)$post['cap_veh'];
        $estado = self::normalizar((string)($post['est_veh'] ?? Config::VEH_DISPONIBLE));

        // Si la unidad tenía viajes abiertos, no se puede pasar a Mantenimiento
        // o Fuera de servicio: quedaría trabajando con el estado de averiada.
        if ($id > 0 && $estado !== Config::VEH_DISPONIBLE && $estado !== Config::VEH_ASIGNADO) {
            $abiertos = (int) Database::scalar(
                "SELECT COUNT(*) FROM viaje WHERE id_veh = ? AND est_via IN ('Programado','En curso')",
                [$id]
            );
            if ($abiertos > 0) {
                return ['ok' => false,
                        'errores' => ['est_veh' => "No se puede cambiar el estado: la unidad tiene {$abiertos} viaje(s) asignado(s). Ciérralos primero."],
                        'mensaje' => 'No se puede cambiar el estado del vehículo mientras tenga viajes asignados.'];
            }
        }

        try {
            Database::begin();
            if ($id > 0) {
                Database::query(
                    'UPDATE vehiculo SET pla_veh = ?, mode_veh = ?, cap_veh = ?, est_veh = ? WHERE id_veh = ?',
                    [$placa, $modelo, $cap, $estado, $id]
                );
            } else {
                $id = Database::insert(
                    'INSERT INTO vehiculo (pla_veh, mode_veh, cap_veh, est_veh) VALUES (?, ?, ?, ?)',
                    [$placa, $modelo, $cap, $estado]
                );
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][VehiculoService::guardar] ' . $e->getMessage());
            return ['ok' => false, 'errores' => ['general' => 'No se pudo guardar el vehículo.'], 'mensaje' => 'No se pudo guardar el vehículo.'];
        }

        Logger::registrar(Database::pdo(), $id > 0 && ($post['__existia'] ?? false) ? 'EDITAR_VEHICULO' : 'CREAR_VEHICULO',
            sprintf('Vehículo %s (%s) [%s] guardado por %s', $placa, $modelo, $estado, Auth::nombre()));

        return ['ok' => true, 'id' => $id, 'mensaje' => $id > 0 ? 'Vehículo actualizado.' : 'Vehículo registrado correctamente.'];
    }

    /**
     * Alterna entre «Disponible» y «Fuera de servicio».
     *
     * Si la unidad está en Mantenimiento vuelve a Disponible (es la salida
     * natural de una revisión). Si está Asignado se rechaza: ese estado es del
     * sistema y depende de que el viaje se cierre.
     */
    public static function alternarEstado(int $id): array
    {
        $veh = self::porId($id);
        if (!$veh) {
            return ['ok' => false, 'mensaje' => 'El vehículo no existe.'];
        }

        $actual = self::normalizar((string)$veh['est_veh']);

        if ($actual === Config::VEH_ASIGNADO) {
            $viaje = Database::scalar(
                "SELECT id_via FROM viaje WHERE id_veh = ? AND est_via IN ('Programado','En curso') LIMIT 1",
                [$id]
            );
            return ['ok' => false, 'mensaje' => $viaje
                ? "Esta unidad está asignada al viaje #{$viaje}. Finaliza o cancela ese viaje antes de cambiar su estado."
                : 'Esta unidad figura como asignada. Cierra el viaje abierto antes de cambiar su estado.'];
        }

        $nuevo = ($actual === Config::VEH_DISPONIBLE)
            ? Config::VEH_FUERA_SERVICIO
            : Config::VEH_DISPONIBLE;

        Database::query('UPDATE vehiculo SET est_veh = ? WHERE id_veh = ?', [$nuevo, $id]);

        Logger::registrar(Database::pdo(), 'ESTADO_VEHICULO',
            sprintf('Vehículo %s pasó de %s a %s.', $veh['pla_veh'], $actual, $nuevo));

        return [
            'ok'      => true,
            'estado'  => $nuevo,
            'mensaje' => $nuevo === Config::VEH_DISPONIBLE
                ? sprintf('Vehículo %s vuelve a estar disponible.', $veh['pla_veh'])
                : sprintf('Vehículo %s quedó fuera de servicio y ya no puede recibir viajes.', $veh['pla_veh']),
        ];
    }

    public static function eliminar(int $id): array
    {
        $veh = self::porId($id);
        if (!$veh) return ['ok' => false, 'mensaje' => 'El vehículo no existe.'];

        $viajes = (int) Database::scalar(
            "SELECT COUNT(*) FROM viaje WHERE id_veh = ? AND est_via IN ('Programado','En curso')",
            [$id]
        );
        if ($viajes > 0) {
            return ['ok' => false, 'mensaje' => "No se puede eliminar: el vehículo tiene {$viajes} viaje(s) asignado(s)."];
        }

        Database::query('DELETE FROM vehiculo WHERE id_veh = ?', [$id]);
        Logger::registrar(Database::pdo(), 'ELIMINAR_VEHICULO', "Vehículo {$veh['pla_veh']} eliminado.");
        return ['ok' => true, 'mensaje' => 'Vehículo eliminado correctamente.'];
    }
}
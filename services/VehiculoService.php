<?php
/**
 * services/VehiculoService.php
 * -----------------------------------------------------------------------------
 * MODULO: FLOTA
 * -----------------------------------------------------------------------------
 * FIX #2 del "Data Default Fallback":
 *   `vehiculo.est_veh` se manejaba con 0/1 en las páginas, pero assets/conexion.php
 *   definia ESTADO_DISPONIBLE=1 y ESTADO_OCUPADO=2, y Admin/viajes.php consultaba
 *   `est_veh = 1 OR est_veh = 'Activo'`. Consecuencia: vehiculos "disponibles"
 *   que no aparecian en el selector, o vehiculos marcados ocupados con estados
 *   que nadie interpretaba igual.
 *
 *   Ahora existe UNA sola tabla de verdad (Config::VEH_*) y la columna es
 *   NOT NULL con DEFAULT, por lo que nunca puede quedar NULL.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class VehiculoService
{
    /* ------------------------------------------------------------------ */

    public static function todos(bool $soloOperativos = false): array
    {
        $sql = "SELECT v.*,
                       (SELECT COUNT(*) FROM viaje vi
                         WHERE vi.id_veh = v.id_veh
                           AND vi.est_via IN ('Programado','En curso')) AS viajes_asignados
                  FROM vehiculo v";
        if ($soloOperativos) {
            $sql .= " WHERE v.est_veh = " . Config::VEH_DISPONIBLE;
        }
        $sql .= " ORDER BY v.id_veh DESC";
        return Database::all($sql);
    }

    public static function porId(int $id): ?array
    {
        return Database::one("SELECT * FROM vehiculo WHERE id_veh = ?", [$id]);
    }

    public static function disponiblesParaDespacho(): array
    {
        return Database::all(
            "SELECT v.id_veh, v.pla_veh, v.mode_veh, v.cap_veh
               FROM vehiculo v
              WHERE v.est_veh = ?
                AND v.id_veh NOT IN (
                      SELECT id_veh FROM viaje
                       WHERE est_via IN ('Programado','En curso') AND id_veh IS NOT NULL
                  )
              ORDER BY v.pla_veh ASC",
            [Config::VEH_DISPONIBLE]
        );
    }

    public static function placaExiste(string $placa, ?int $exceptoId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM vehiculo WHERE pla_veh = ?";
        $p = [$placa];
        if ($exceptoId !== null) { $sql .= " AND id_veh <> ?"; $p[] = $exceptoId; }
        return (int) Database::scalar($sql, $p) > 0;
    }

    /* ------------------------------------------------------------------ */

    public static function validar(array $post): Validator
    {
        $id = (int)($post['id_veh'] ?? 0);
        return Validator::de($post)
            ->requerido('pla_veh', 'La placa')
            ->texto('pla_veh', 'La placa', 5, 10)
            ->requerido('mode_veh', 'La línea / modelo')
            ->texto('mode_veh', 'La línea / modelo', 2, 50)
            ->requerido('cap_veh', 'La capacidad')
            ->entero('cap_veh', 'La capacidad', 1, 120)
            ->entero('est_veh', 'El estado operativo', 0, 1)
            ->agregaSi(
                strtoupper(trim((string)($post['pla_veh'] ?? ''))) !== ''
                && self::placaExiste(strtoupper(trim((string)$post['pla_veh'])), $id > 0 ? $id : null),
                'pla_veh',
                'Ya existe un vehículo registrado con esa placa.'
            );
    }

    /* ------------------------------------------------------------------ */

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
        $estado = (int)($post['est_veh'] ?? Config::VEH_DISPONIBLE);

        try {
            Database::begin();
            if ($id > 0) {
                Database::query(
                    "UPDATE vehiculo SET pla_veh = ?, mode_veh = ?, cap_veh = ?, est_veh = ? WHERE id_veh = ?",
                    [$placa, $modelo, $cap, $estado, $id]
                );
            } else {
                $id = Database::insert(
                    "INSERT INTO vehiculo (pla_veh, mode_veh, cap_veh, est_veh) VALUES (?, ?, ?, ?)",
                    [$placa, $modelo, $cap, $estado]
                );
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return ['ok' => false, 'errores' => ['general' => 'No se pudo guardar el vehículo.'], 'mensaje' => $e->getMessage()];
        }

        Logger::registrar(Database::pdo(), $id > 0 ? 'EDITAR_VEHICULO' : 'CREAR_VEHICULO',
            sprintf('Vehículo %s (%s) guardado por %s', $placa, $modelo, Auth::nombre()));

        return ['ok' => true, 'id' => $id, 'mensaje' => $id > 0 ? 'Vehículo actualizado.' : 'Vehículo registrado correctamente.'];
    }

    /** Alterna Disponible <-> Fuera de servicio (0/1, nunca texto). */
    public static function alternarEstado(int $id): array
    {
        $veh = self::porId($id);
        if (!$veh) {
            return ['ok' => false, 'mensaje' => 'El vehículo no existe.'];
        }
        $nuevo = ((int)$veh['est_veh'] === Config::VEH_DISPONIBLE)
            ? Config::VEH_FUERA_SERVICIO
            : Config::VEH_DISPONIBLE;

        // No sacar de servicio un vehículo con viaje en curso
        if ($nuevo === Config::VEH_FUERA_SERVICIO) {
            $activo = (int) Database::scalar(
                "SELECT COUNT(*) FROM viaje WHERE id_veh = ? AND est_via IN ('Programado','En curso')",
                [$id]
            );
            if ($activo > 0) {
                return ['ok' => false, 'mensaje' => "No se puede retirar de servicio: tiene {$activo} viaje(s) asignado(s)."];
            }
        }

        Database::query("UPDATE vehiculo SET est_veh = ? WHERE id_veh = ?", [$nuevo, $id]);
        Logger::registrar(Database::pdo(), 'ESTADO_VEHICULO',
            sprintf('Vehículo %s pasó a %s.', $veh['pla_veh'], self::etiquetaEstado($nuevo)));

        return ['ok' => true, 'estado' => $nuevo, 'mensaje' => 'Estado del vehículo actualizado: ' . self::etiquetaEstado($nuevo) . '.'];
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

        Database::query("DELETE FROM vehiculo WHERE id_veh = ?", [$id]);
        Logger::registrar(Database::pdo(), 'ELIMINAR_VEHICULO', "Vehículo {$veh['pla_veh']} eliminado.");
        return ['ok' => true, 'mensaje' => 'Vehículo eliminado correctamente.'];
    }

    /* ------------------------------------------------------------------ */

    public static function etiquetaEstado($estado): string
    {
        return ((int)$estado === Config::VEH_DISPONIBLE) ? 'Disponible' : 'Fuera de servicio';
    }

    public static function claseEstado($estado): string
    {
        return ((int)$estado === Config::VEH_DISPONIBLE)
            ? 'sget-badge sget-badge--exito'
            : 'sget-badge sget-badge--error';
    }
}

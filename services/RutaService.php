<?php
/**
 * services/RutaService.php
 * -----------------------------------------------------------------------------
 * MODULO: RUTAS
 * -----------------------------------------------------------------------------
 * FIX #1 del "Data Default Fallback":
 *   `rutas.ori_rut` y `rutas.des_rut` son NOT NULL, pero Admin/rutas.php hacia
 *   INSERT sin esos campos => MySQL guardaba cadena vacia ('') porque el motor
 *   no estaba en modo estricto. Resultado: rutas "sin salida y sin destino".
 *
 *   Ahora: origen, destino, distancia, hora de salida y tarifa son obligatorios
 *   y validados antes de escribir. No hay valores por defecto silenciosos.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class RutaService
{
    public const RUTAS_IMG = 'img/rutas';

    /* ------------------------------------------------------------------ */
    /* Consultas                                                           */
    /* ------------------------------------------------------------------ */

    public static function todas(bool $soloActivas = false): array
    {
        $sql = "SELECT r.*,
                       (SELECT COUNT(*) FROM viaje v WHERE v.id_rut_via = r.id_rut) AS num_viajes
                  FROM rutas r";
        if ($soloActivas) $sql .= " WHERE COALESCE(r.estado, 1) = 1";
        $sql .= " ORDER BY r.nom_rut ASC";
        return Database::all($sql);
    }

    public static function porId(int $id): ?array
    {
        return Database::one("SELECT * FROM rutas WHERE id_rut = ?", [$id]);
    }

    public static function tarifa(int $id): ?float
    {
        $v = Database::scalar("SELECT val_rut FROM rutas WHERE id_rut = ?", [$id]);
        return $v === null ? null : (float) $v;
    }

    public static function existeNombre(string $nombre, ?int $exceptoId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM rutas WHERE nom_rut = ?";
        $p = [$nombre];
        if ($exceptoId !== null) { $sql .= " AND id_rut <> ?"; $p[] = $exceptoId; }
        return (int) Database::scalar($sql, $p) > 0;
    }

    /* ------------------------------------------------------------------ */
    /* Validacion (usada por el modal de rutas)                            */
    /* ------------------------------------------------------------------ */

    public static function validar(array $post): Validator
    {
        $v = Validator::de($post)
            ->requerido('nom_rut', 'Nombre del trayecto')
            ->texto('nom_rut', 'El nombre del trayecto', 3, 100)
            ->requerido('ori_rut', 'Ciudad de salida (origen)')
            ->texto('ori_rut', 'La ciudad de salida', 2, 100)
            ->requerido('des_rut', 'Ciudad de destino')
            ->texto('des_rut', 'La ciudad de destino', 2, 100)
            ->requerido('val_rut', 'Tarifa base')
            ->decimal('val_rut', 'La tarifa base', 1, 99999999)
            ->decimal('dis_rut', 'La distancia', 0, 99999)
            ->hora('hora_salida', 'La hora de salida', false);

        // Regla de negocio: origen y destino deben ser distintos
        $ori = trim((string)($post['ori_rut'] ?? ''));
        $des = trim((string)($post['des_rut'] ?? ''));
        if ($ori !== '' && $des !== '' && mb_strtolower($ori) === mb_strtolower($des)) {
            $v->agrega('des_rut', 'La ciudad de destino debe ser diferente a la de salida.');
        }

        // Duplicados
        $id = (int)($post['id_rut'] ?? 0);
        $nom = trim((string)($post['nom_rut'] ?? ''));
        if ($nom !== '' && self::existeNombre($nom, $id > 0 ? $id : null)) {
            $v->agrega('nom_rut', 'Ya existe una ruta registrada con ese nombre.');
        }

        return $v;
    }

    /* ------------------------------------------------------------------ */
    /* Escritura                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{ok:bool, id?:int, errores?:array, mensaje?:string}
     */
    public static function guardar(array $post, array $archivo = null): array
    {
        $validador = self::validar($post);
        if ($validador->falla()) {
            return ['ok' => false, 'errores' => $validador->errores(), 'mensaje' => $validador->primerError()];
        }

        $id    = (int)($post['id_rut'] ?? 0);
        $ori   = trim((string)$post['ori_rut']);
        $des   = trim((string)$post['des_rut']);
        $nom   = trim((string)$post['nom_rut']);
        $dis   = isset($post['dis_rut']) && $post['dis_rut'] !== '' ? (float)$post['dis_rut'] : null;
        $val   = (float)$post['val_rut'];
        $hora  = Fecha::hora($post['hora_salida'] ?? null, 'hora de salida', false);
        $estado = isset($post['estado']) ? (int)$post['estado'] : 1;

        // --- Imagen (opcional) -------------------------------------------
        $imgActual = trim((string)($post['img_actual'] ?? ''));
        $img = $imgActual !== '' ? $imgActual : null;

        if ($archivo && !empty($archivo['name'])) {
            $subida = self::guardarImagen($archivo);
            if (isset($subida['error'])) {
                return ['ok' => false, 'errores' => ['img_rut' => $subida['error']], 'mensaje' => $subida['error']];
            }
            // Si se reemplaza la foto, se borra la anterior para no dejar huérfanos
            if ($imgActual !== '' && $imgActual !== $subida['nombre']) {
                self::eliminarImagen($imgActual);
            }
            $img = $subida['nombre'];
        }

        try {
            Database::begin();

            if ($id > 0) {
                $sql = "UPDATE rutas SET nom_rut = ?, ori_rut = ?, des_rut = ?, dis_rut = ?, val_rut = ?,
                                           hora_salida = ?, estado = ?, img_rut = ? WHERE id_rut = ?";
                Database::query($sql, [$nom, $ori, $des, $dis, $val, $hora, $estado, $img, $id]);
            } else {
                $sql = "INSERT INTO rutas (nom_rut, ori_rut, des_rut, dis_rut, val_rut, hora_salida, estado, img_rut)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $id = Database::insert($sql, [$nom, $ori, $des, $dis, $val, $hora, $estado, $img]);
            }

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('[SGET][RutaService] ' . $e->getMessage());
            return ['ok' => false, 'errores' => ['general' => 'No se pudo guardar la ruta. Revisa los datos e intenta de nuevo.'],
                    'mensaje' => 'No se pudo guardar la ruta.'];
        }

        Logger::registrar(
            Database::pdo(),
            $id > 0 && isset($post['__editando']) ? 'EDITAR_RUTA' : 'GUARDAR_RUTA',
            sprintf('Ruta #%d «%s» (%s → %s) guardada por %s', $id, $nom, $ori, $des, Auth::nombre())
        );

        return ['ok' => true, 'id' => $id, 'mensaje' => $id > 0 ? 'Ruta actualizada correctamente.' : 'Ruta creada correctamente.'];
    }

    public static function cambiarEstado(int $id, int $estado): bool
    {
        $r = Database::query(
            "UPDATE rutas SET estado = ? WHERE id_rut = ? AND COALESCE(estado, 1) <> 1",
            [$estado, $id]
        );
        if ($r->rowCount() === 0) {
            // Possibly already in that state; treat as success
            return self::porId($id) !== null;
        }
        Logger::registrar(Database::pdo(), 'SUSPENDER_RUTA', "Ruta #{$id} cambio de estado a {$estado}.");
        return true;
    }

    public static function eliminar(int $id): array
    {
        $ruta = self::porId($id);
        if (!$ruta) {
            return ['ok' => false, 'mensaje' => 'La ruta no existe o ya fue eliminada.'];
        }

        $viajes = (int) Database::scalar(
            "SELECT COUNT(*) FROM viaje WHERE id_rut_via = ? AND est_via NOT IN ('Finalizado','Cancelado')",
            [$id]
        );

        if ($viajes > 0) {
            return ['ok' => false, 'mensaje' => "No se puede eliminar: la ruta tiene {$viajes} viaje(s) activo(s) o programados."];
        }

        try {
            Database::begin();
            Database::query("DELETE FROM rutas WHERE id_rut = ?", [$id]);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return ['ok' => false, 'mensaje' => 'No se pudo eliminar la ruta: ' . $e->getMessage()];
        }

        if (!empty($ruta['img_rut'])) {
            self::eliminarImagen($ruta['img_rut']);
        }

        Logger::registrar(Database::pdo(), 'ELIMINAR_RUTA', "Ruta #{$id} «{$ruta['nom_rut']}» eliminada.");
        return ['ok' => true, 'mensaje' => 'Ruta eliminada correctamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Imagenes                                                            */
    /* ------------------------------------------------------------------ */

    public static function guardarImagen(array $archivo): array
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['error' => 'No se pudo subir la fotografía de la ruta.'];
        }
        if ($archivo['size'] > 3 * 1024 * 1024) {
            return ['error' => 'La fotografía supera el máximo de 3 MB.'];
        }
        $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $permitidos, true)) {
            return ['error' => 'Formato no permitido. Usa JPG, PNG o WEBP.'];
        }

        $dir = Config::raiz(self::RUTAS_IMG);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $nombre = 'ruta_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!@move_uploaded_file($archivo['tmp_name'], $dir . '/' . $nombre)) {
            return ['error' => 'No se pudo guardar la imagen en el servidor.'];
        }
        return ['nombre' => $nombre];
    }

    public static function eliminarImagen(string $nombre): void
    {
        // Defensa contra path traversal
        $nombre = basename($nombre);
        if ($nombre === '') return;
        $ruta = Config::raiz(self::RUTAS_IMG) . '/' . $nombre;
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    public static function urlImagen(?string $nombre): string
    {
        if (empty($nombre)) return '';
        $nombre = basename($nombre);
        if (!is_file(Config::raiz(self::RUTAS_IMG) . '/' . $nombre)) return '';
        return Config::RUTAS_IMG . '/' . rawurlencode($nombre);
    }

    /* ------------------------------------------------------------------ */
    /* Presentacion                                                        */
    /* ------------------------------------------------------------------ */

    public static function etiquetaViajes(int $cantidad): string
    {
        if ($cantidad === 0) return 'Sin viajes';
        return $cantidad === 1 ? '1 viaje' : $cantidad . ' viajes';
    }
}

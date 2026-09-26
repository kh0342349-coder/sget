<?php
/**
 * services/UsuarioService.php
 * -----------------------------------------------------------------------------
 * MODULO: USUARIOS
 * -----------------------------------------------------------------------------
 * FIX #4 del "Data Default Fallback":
 *   - `estado` admitia NULL: un usuario con NULL no caia en "Desactivados"
 *     (el filtro era `estado == 0`) ni en los contadores => invisible en la UI.
 *   - `est_con_usu` arrancaba en NULL para conductores y los SELECT comparaban
 *     contra 1 y contra 'Disponible' a la vez.
 *   - la contraseña se guardaba en texto plano desde el panel admin.
 *   - no existía `cambiar_estado_usu.php`, aunque la vista lo enlazaba (404).
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class UsuarioService
{
    public const ETIQUETA_ROL = [
        Config::ROL_ADMIN     => 'Administrador',
        Config::ROL_CONDUCTOR => 'Conductor',
        Config::ROL_PASAJERO  => 'Pasajero',
    ];

    /* ------------------------------------------------------------------ */

    public static function porId(int $id): ?array
    {
        return Database::one(
            "SELECT u.*, r.nom_rol
               FROM usuario u
               LEFT JOIN rol r ON r.id_rol = u.id_rol_usu
              WHERE u.id_usu = ?",
            [$id]
        );
    }

    public static function porDocumento(string $doc): ?array
    {
        return Database::one(
            "SELECT u.*, r.nom_rol
               FROM usuario u
               LEFT JOIN rol r ON r.id_rol = u.id_rol_usu
              WHERE u.num_doc_usu = ? LIMIT 1",
            [$doc]
        );
    }

    public static function todos(): array
    {
        return Database::all(
            "SELECT u.id_usu, u.tip_doc_usu, u.num_doc_usu, u.nom_usu, u.corre_usu, u.tel_usu,
                    u.id_rol_usu, u.estado, u.est_con_usu
               FROM usuario u
              ORDER BY u.id_rol_usu ASC, u.nom_usu ASC"
        );
    }

    /** Agrupa por rol y por estado para las pestañas de la vista. */
    public static function agrupar(): array
    {
        $grupos = [
            'tab-admins' => [], 'tab-conductores' => [], 'tab-pasajeros' => [], 'tab-inactivos' => [],
        ];
        foreach (self::todos() as $u) {
            if ((int)$u['estado'] === Config::USU_INACTIVO) { $grupos['tab-inactivos'][] = $u; continue; }
            switch ((int)$u['id_rol_usu']) {
                case Config::ROL_ADMIN:     $grupos['tab-admins'][] = $u; break;
                case Config::ROL_CONDUCTOR: $grupos['tab-conductores'][] = $u; break;
                default:                    $grupos['tab-pasajeros'][] = $u; break;
            }
        }
        return $grupos;
    }

    public static function documentoExiste(string $doc, ?int $exceptoId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM usuario WHERE num_doc_usu = ?";
        $p = [$doc];
        if ($exceptoId !== null) { $sql .= " AND id_usu <> ?"; $p[] = $exceptoId; }
        return (int) Database::scalar($sql, $p) > 0;
    }

    public static function correoExiste(string $correo, ?int $exceptoId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM usuario WHERE corre_usu = ?";
        $p = [$correo];
        if ($exceptoId !== null) { $sql .= " AND id_usu <> ?"; $p[] = $exceptoId; }
        return (int) Database::scalar($sql, $p) > 0;
    }

    /* ------------------------------------------------------------------ */

    public static function validar(array $post): Validator
    {
        $idDoc = trim((string)($post['id_usu'] ?? '0'));
        $id    = (int)$idDoc;

        $v = Validator::de($post)
            ->requerido('tip_doc_usu', 'El tipo de documento')
            ->enLista('tip_doc_usu', 'el tipo de documento', ['CC', 'TI', 'CE', 'G', 'NIT'])
            ->requerido('num_doc_usu', 'El número de documento')
            ->texto('num_doc_usu', 'El número de documento', 5, 20)
            ->requerido('nom_usu', 'El nombre completo')
            ->texto('nom_usu', 'El nombre completo', 3, 100)
            ->requerido('corre_usu', 'El correo electrónico')
            ->email('corre_usu', 'El correo electrónico')
            ->requerido('id_rol_usu', 'El rol')
            ->entero('id_rol_usu', 'El rol', 1, 3)
            ->texto('tel_usu', 'El teléfono', 7, 20);

        // El correo no puede ser duplicado
        $correo = trim((string)($post['corre_usu'] ?? ''));
        if ($correo !== '' && self::correoExiste($correo, $id > 0 ? $id : null)) {
            $v->agrega('corre_usu', 'Ese correo ya está registrado en el sistema.');
        }

        // El documento identifica al usuario: en modo edición se conserva
        $doc = trim((string)($post['num_doc_usu'] ?? ''));
        if ($doc !== '' && self::documentoExiste($doc, $id > 0 ? $id : null)) {
            $v->agrega('num_doc_usu', 'Ese número de documento ya está registrado.');
        }

        // En alta la contraseña es obligatoria (antes se ponía "123456" a la fuerza)
        if ($id === 0) {
            $v->requerido('contra_usu', 'La contraseña')->texto('contra_usu', 'La contraseña', 6, 100);
        }

        return $v;
    }

    /* ------------------------------------------------------------------ */

    public static function guardar(array $post): array
    {
        $v = self::validar($post);
        if ($v->falla()) {
            return ['ok' => false, 'errores' => $v->errores(), 'mensaje' => $v->primerError()];
        }

        $id     = (int)($post['id_usu'] ?? 0);
        $tipDoc = trim((string)$post['tip_doc_usu']);
        $numDoc = trim((string)$post['num_doc_usu']);
        $nombre = trim((string)$post['nom_usu']);
        $correo = trim((string)$post['corre_usu']);
        $tel    = trim((string)($post['tel_usu'] ?? '')) ?: null;
        $rol    = (int)$post['id_rol_usu'];
        $clave  = (string)($post['contra_usu'] ?? '');

        // Regla de estado: solo los conductores manejan est_con_usu
        $estadoConductor = ($rol === Config::ROL_CONDUCTOR) ? Config::CON_DISPONIBLE : null;
        $estadoCuenta    = isset($post['estado']) ? (int)$post['estado'] : Config::USU_ACTIVO;

        try {
            Database::begin();

            if ($id > 0) {
                $params = [$tipDoc, $numDoc, $nombre, $correo, $tel, $rol, $estadoCuenta, $estadoConductor];
                $sql = "UPDATE usuario
                           SET tip_doc_usu = ?, num_doc_usu = ?, nom_usu = ?, corre_usu = ?, tel_usu = ?,
                               id_rol_usu = ?, estado = ?, est_con_usu = ?";
                if ($clave !== '') {
                    $sql .= ", pass_usu = ?";
                    $params[] = password_hash($clave, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id_usu = ?";
                $params[] = $id;
                Database::query($sql, $params);
            } else {
                $id = Database::insert(
                    "INSERT INTO usuario
                        (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, tel_usu, id_rol_usu, pass_usu, estado, est_con_usu)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$tipDoc, $numDoc, $nombre, $correo, $tel, $rol,
                     password_hash($clave !== '' ? $clave : '123456', PASSWORD_DEFAULT),
                     $estadoCuenta, $estadoConductor]
                );
            }

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return ['ok' => false, 'errores' => ['general' => 'No se pudo guardar el usuario.'], 'mensaje' => $e->getMessage()];
        }

        Logger::registrar(Database::pdo(), $id > 0 ? 'EDITAR_USUARIO' : 'CREAR_USUARIO',
            sprintf('Usuario «%s» (Doc: %s, Rol: %s) guardado por %s',
                $nombre, $numDoc, self::ETIQUETA_ROL[$rol] ?? $rol, Auth::nombre()));

        return ['ok' => true, 'id' => $id, 'mensaje' => $id > 0 ? 'Usuario actualizado.' : 'Usuario creado correctamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Activar / Suspender                                                 */
    /* ------------------------------------------------------------------ */

    public static function cambiarEstado(int $idUsuario, int $estado, string $motivo = ''): array
    {
        if (Auth::id() === $idUsuario) {
            return ['ok' => false, 'mensaje' => 'No puedes suspender tu propia cuenta.'];
        }

        $u = self::porId($idUsuario);
        if (!$u) return ['ok' => false, 'mensaje' => 'El usuario no existe.'];

        $estado = $estado === Config::USU_INACTIVO ? Config::USU_INACTIVO : Config::USU_ACTIVO;

        // No suspender un conductor o vehículo con viaje en curso
        if ($estado === Config::USU_INACTIVO) {
            $viajes = (int) Database::scalar(
                "SELECT COUNT(*) FROM viaje WHERE id_usu_via = ? AND est_via IN ('Programado','En curso')",
                [$idUsuario]
            );
            if ($viajes > 0) {
                return ['ok' => false, 'mensaje' => "No se puede suspender: el usuario tiene {$viajes} viaje(s) en curso o programados."];
            }
        }

        if ((int)$u['estado'] === $estado) {
            return ['ok' => true, 'estado' => $estado, 'mensaje' => 'El usuario ya se encontraba en ese estado.'];
        }

        Database::query("UPDATE usuario SET estado = ? WHERE id_usu = ?", [$estado, $idUsuario]);

        Logger::registrar(Database::pdo(), $estado === Config::USU_INACTIVO ? 'SUSPENDER_USUARIO' : 'ACTIVAR_USUARIO',
            sprintf('Usuario «%s» (%s) %s por %s%s',
                $u['nom_usu'], $u['num_doc_usu'],
                $estado === Config::USU_INACTIVO ? 'suspendido' : 'reactivado',
                Auth::nombre(),
                $motivo !== '' ? ". Motivo: {$motivo}" : ''));

        return ['ok' => true, 'estado' => $estado, 'mensaje' => $estado === Config::USU_INACTIVO
            ? 'Cuenta suspendida. El usuario no podrá iniciar sesión.'
            : 'Cuenta reactivada correctamente.'];
    }

    public static function eliminar(int $idUsuario): array
    {
        if (Auth::id() === $idUsuario) {
            return ['ok' => false, 'mensaje' => 'No puedes eliminar tu propia cuenta.'];
        }

        $u = self::porId($idUsuario);
        if (!$u) return ['ok' => false, 'mensaje' => 'El usuario no existe.'];

        $viajes = (int) Database::scalar("SELECT COUNT(*) FROM viaje WHERE id_usu_via = ?", [$idUsuario]);
        if ($viajes > 0) {
            return ['ok' => false, 'mensaje' => "No se puede eliminar: el usuario tiene historial de {$viajes} viaje(s). Suspenda la cuenta en su lugar."];
        }

        Database::query("DELETE FROM usuario WHERE id_usu = ?", [$idUsuario]);
        Logger::registrar(Database::pdo(), 'ELIMINAR_USUARIO', "Usuario «{$u['nom_usu']}» eliminado por " . Auth::nombre() . '.');
        return ['ok' => true, 'mensaje' => 'Usuario eliminado correctamente.'];
    }

    /* ------------------------------------------------------------------ */

    public static function etiquetaEstado($estado): string
    {
        return (int)$estado === Config::USU_ACTIVO ? 'Activo' : 'Inactivo';
    }

    public static function claseEstado($estado): string
    {
        return (int)$estado === Config::USU_ACTIVO
            ? 'sget-badge sget-badge--exito'
            : 'sget-badge sget-badge--error';
    }

    public static function claseRol(int $rol): string
    {
        return [
            Config::ROL_ADMIN     => 'sget-badge sget-badge--info',
            Config::ROL_CONDUCTOR => 'sget-badge sget-badge--exito',
            Config::ROL_PASAJERO  => 'sget-badge sget-badge--neutro',
        ][$rol] ?? 'sget-badge sget-badge--neutro';
    }
}

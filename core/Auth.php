<?php
/**
 * core/Auth.php
 * -----------------------------------------------------------------------------
 * Sesion, roles y permisos. Sustituye a los chequeos inline dispersos en cada
 * pagina (`if (!isset($_SESSION['documento']) ... exit`), que era la causa
 * principal de modales/acciones accesibles sin autorizacion.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';

final class Auth
{
    public const ZONA_HORARIA = 'America/Bogota';

    private static bool $verificada = false;

    /* ------------------------------------------------------------------ */
    /**
     * Arranca (o reutiliza) la sesión.
     *
     * IMPORTANTE: NO se renombra la sesión. Muchas páginas heredadas hacen
     * `session_start()` ANTES de incluir `assets/conexion.php`, así que si
     * aquí se impusiera un nombre distinto, unas páginas y otras no verían la
     * misma sesión y el usuario aparecería “deslogueado” al navegar.
     */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Ya había una sesión abierta (página heredada): solo se fija la zona
            // horaria, que es lo único que podría haber quedado inconsistente.
            date_default_timezone_set(self::ZONA_HORARIA);
            return;
        }

        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_start();
        date_default_timezone_set(self::ZONA_HORARIA);
    }

    /* ------------------------------------------------------------------ */
    public static function id(): int
    {
        return (int) ($_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0);
    }

    public static function rol(): int
    {
        return (int) ($_SESSION['rol'] ?? $_SESSION['id_rol_usu'] ?? 0);
    }

    public static function nombre(): string
    {
        return (string) ($_SESSION['nombre_usuario'] ?? $_SESSION['nom_usu'] ?? 'Usuario SGET');
    }

    public static function documento(): string
    {
        return (string) ($_SESSION['documento'] ?? '');
    }

    public static function estaLogueado(): bool
    {
        return self::id() > 0 && self::documento() !== '';
    }

    /* ------------------------------------------------------------------ */
    /* Guardias                                                            */
    /* ------------------------------------------------------------------ */

    /** Exige sesion activa; si no, redirige al login. */
    public static function requerirSesion(): void
    {
        if (!self::estaLogueado()) {
            self::redirigirLogin();
        }
        self::verificarInactividad();
    }

    /** Exige sesion + rol Admin (por defecto). */
    public static function requerirRol(int ...$roles): void
    {
        self::requerirSesion();
        $rolActual = self::rol();
        foreach ($roles as $r) {
            if ($rolActual === $r) {
                return;
            }
        }
        self::denegar();
    }

    public static function requerirAdmin(): void { self::requerirRol(Config::ROL_ADMIN); }

    /** Exige permiso sobre un modulo/recurso (reemplaza AuthHelper::requerirAcceso). */
    public static function requerirAcceso(string $recurso): void
    {
        self::requerirSesion();
        if (!self::tieneAcceso($recurso)) {
            self::denegar($recurso);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Permisos                                                            */
    /* ------------------------------------------------------------------ */

    public static function tieneAcceso(string $recurso): bool
    {
        if (!self::estaLogueado()) return false;

        // El Admin tiene acceso total
        if (self::rol() === Config::ROL_ADMIN) return true;

        $permitido = Database::scalar(
            "SELECT up.permitido
               FROM usuario_permisos up
               INNER JOIN permisos p ON p.id_permiso = up.id_permiso
              WHERE up.id_usu = ?
                AND (p.nombre_permiso = ? OR p.modulo = ?)
              LIMIT 1",
            [self::id(), $recurso, $recurso]
        );

        // Sin registro explicito => permitido (comportamiento historico)
        if ($permitido === null) return true;

        return (int) $permitido === 1;
    }

    /* ------------------------------------------------------------------ */
    /* Inactividad                                                         */
    /* ------------------------------------------------------------------ */

    public static function verificarInactividad(int $minutos = 0): void
    {
        if (self::$verificada) return;

        if ($minutos <= 0) {
            $minutos = Config::MINUTOS_INACTIVIDAD;
        }

        $limite  = $minutos * 60;
        $ultimo  = $_SESSION['ultimo_acceso'] ?? null;
        $vence   = $ultimo !== null && (time() - (int) $ultimo) > $limite;

        // Conserva el instante original del bloqueo para que recargar la
        // página no reinicie el temporizador de 60 s del modal.
        if ($vence && empty($_SESSION['inactividad_bloqueada_en'])) {
            $_SESSION['inactividad_bloqueada_en'] = time();
        }

        if ($vence) {
            $_SESSION['sesion_bloqueada'] = true;
            $_SESSION['url_redirect']    = $_SERVER['REQUEST_URI'] ?? '';

            // Una petición AJAX/JSON no puede mostrar un modal: se corta aquí.
            if (self::peticionAjax()) {
                self::bloquear();
            }
            // En navegación normal se continúa: el modal de inactividad que
            // incluye includes/header.php bloquea la pantalla y pide la
            // contraseña. Redirigir a un endpoint JSON sería inútil.
        } else {
            $_SESSION['ultimo_acceso'] = time();
            unset($_SESSION['sesion_bloqueada']);
        }

        self::$verificada = true;
    }

    public static function bloquear(): void
    {
        if (self::peticionAjax()) {
            self::json([
                'status'   => 'bloqueado',
                'mensaje'  => 'La sesion ha sido bloqueada por inactividad.',
                'redirect' => Config::basePath() . '/index.php',
            ], 401);
        }
        // Navegación normal: se marca y el modal hace el resto.
        $_SESSION['sesion_bloqueada'] = true;
    }

    /* ------------------------------------------------------------------ */
    /* Desbloqueo por inactividad                                           */
    /* ------------------------------------------------------------------ */

    /** ¿La sesión está bloqueada en este momento? */
    public static function estaBloqueada(): bool
    {
        return !empty($_SESSION['sesion_bloqueada']);
    }

    /**
     * Comprueba la contraseña del usuario en sesión y desbloquea.
     * Devuelve [bool $ok, string $mensaje].
     */
    public static function desbloquear(string $password): array
    {
        $password = trim($password);
        if ($password === '') {
            return [false, 'Por favor ingresa tu contraseña.'];
        }

        $id = self::id();
        if ($id <= 0) {
            self::cerrar();
            return [false, 'La sesión expiró. Inicia sesión nuevamente.'];
        }

        $hash = Database::scalar("SELECT pass_usu FROM usuario WHERE id_usu = ?", [$id]);
        if ($hash === null) {
            return [false, 'No se encontró la cuenta. Inicia sesión nuevamente.'];
        }

        // Soporta cuentas heredadas en texto plano o con md5, y las nuevas en
        // password_hash(). Al validar una legacy se migra al vuelo.
        $ok = password_verify($password, (string)$hash)
           || hash_equals((string)$hash, md5($password))
           || hash_equals((string)$hash, $password);

        if (!$ok) {
            Logger::registrar(Database::pdo(), 'DESBLOQUEO_FALLIDO',
                "Intento fallido de desbloqueo por inactividad del usuario #{$id}.");
            return [false, 'Contraseña incorrecta. Inténtalo de nuevo.'];
        }

        if (!password_get_info((string)$hash)['algo']) {
            Database::query("UPDATE usuario SET pass_usu = ? WHERE id_usu = ?",
                [password_hash($password, PASSWORD_DEFAULT), $id]);
        }

        unset($_SESSION['sesion_bloqueada'], $_SESSION['inactividad_bloqueada_en'],
              $_SESSION['inactividad_temporizador_inicia_en'], $_SESSION['inactividad_cierra_en']);
        $_SESSION['ultimo_acceso'] = time();

        Logger::registrar(Database::pdo(), 'DESBLOQUEO', "Sesión desbloqueada por el usuario #{$id}.");

        return [true, 'Sesión desbloqueada correctamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Fin de sesion                                                       */
    /* ------------------------------------------------------------------ */

    public static function cerrar(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }
    /* ------------------------------------------------------------------ */
    /* Token anti-CSRF                                                      */
    /* ------------------------------------------------------------------ */
    public static function token(): string
    {
        if (empty($_SESSION['sget_csrf'])) {
            $_SESSION['sget_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['sget_csrf'];
    }

    /** Campo oculto listo para insertar en cualquier formulario. */
    public static function campoToken(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validarToken(?string $recibido): bool
    {
        return !empty($recibido)
            && !empty($_SESSION['sget_csrf'])
            && hash_equals($_SESSION['sget_csrf'], $recibido);
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */

    public static function peticionAjax(): bool
    {
        $ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        $json = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
        return $ajax || $json;
    }

    public static function json(array $data, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirigirLogin(): void
    {
        if (self::peticionAjax()) {
            self::json(['status' => 'error', 'mensaje' => 'Sesion no iniciada.'], 401);
        }
        $_SESSION['url_redirect'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . Config::basePath() . '/index.php');
        exit;
    }

    public static function denegar(?string $recurso = null): void
    {
        if (self::peticionAjax()) {
            self::json([
                'status'  => 'error',
                'mensaje' => $recurso
                    ? "Acceso restringido a este apartado: {$recurso}"
                    : 'Acceso restringido.',
            ], 403);
        }
        http_response_code(403);
        $modulo = $recurso ? htmlspecialchars($recurso, ENT_QUOTES, 'UTF-8') : 'este módulo';
        echo "<!DOCTYPE html><meta charset='utf-8'>"
           . "<meta name='viewport' content='width=device-width,initial-scale=1'>"
           . "<div style=\"font-family:system-ui,sans-serif;text-align:center;margin-top:10vh;background:#0f172a;color:#fff;"
           . "padding:40px 24px;border-radius:20px;max-width:480px;margin-left:auto;margin-right:auto;"
           . "border:1px solid rgba(255,255,255,.1);box-shadow:0 20px 40px rgba(0,0,0,.45)\">"
           . "<h2 style='color:#ef4444;margin-bottom:12px;font-size:22px'>&#128683; Acceso Restringido</h2>"
           . "<p style='color:#94a3b8;font-size:14px;line-height:1.6'>No tienes permisos para gestionar {$modulo}.</p>"
           . "<a href='javascript:history.back()' style='display:inline-block;margin-top:24px;padding:10px 24px;background:#3b82f6;"
           . "color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:14px'>Regresar</a></div>";
        exit;
    }
}

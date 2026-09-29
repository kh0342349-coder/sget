<?php
/**
 * core/bootstrap.php
 * -----------------------------------------------------------------------------
 * UNICO punto de entrada. Todo script del sistema debe comenzar con:
 *     require_once __DIR__ . '/../core/bootstrap.php';
 *
 * Carga: config, BD, sesión, servicios, Logger y normaliza el $conexion (mysqli)
 * normaliza la variable $conexion (mysqli) usada por el codigo antiguo.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

define('SGET_START', microtime(true));

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Fecha.php';
require_once __DIR__ . '/Flash.php';
require_once __DIR__ . '/Auth.php';

// --- Servicios -----------------------------------------------------------
require_once dirname(__DIR__) . '/services/RutaService.php';
require_once dirname(__DIR__) . '/services/VehiculoService.php';
require_once dirname(__DIR__) . '/services/UsuarioService.php';
require_once dirname(__DIR__) . '/services/NotificacionService.php';
require_once dirname(__DIR__) . '/services/LogService.php';
require_once dirname(__DIR__) . '/services/InformacionService.php';
require_once dirname(__DIR__) . '/services/ReservaService.php';
require_once dirname(__DIR__) . '/services/CalificacionService.php';
require_once dirname(__DIR__) . '/services/ReporteService.php';
require_once dirname(__DIR__) . '/services/AnuncioService.php';
require_once dirname(__DIR__) . '/services/ViajeService.php';

// --- Legado (se mantienen hasta migrar pagina por pagina) ---------------
require_once dirname(__DIR__) . '/helpers/Logger.php';

// --- Sesión --------------------------------------------------------------
Auth::iniciar();

/**
 * @deprecated Usa Database::pdo() o los servicios.
 * Se expone $conexion (mysqli) para que el código antiguo siga funcionando
 * sin romperse, pero está marcado para retirada.
 */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
    try {
        $conexion = @new mysqli(
            Config::DB_HOST,
            Config::DB_USER,
            Config::DB_PASS,
            Config::dbName()
        );
        if ($conexion->connect_errno) {
            throw new RuntimeException($conexion->connect_error);
        }
        $conexion->set_charset(Config::DB_CHARSET);
    } catch (Throwable $e) {
        Database::pdo(); // dispara el mensaje de error amigable y sale
    }
}

/**
 * Devuelve el nombre de archivo actual sin extensión (para breadcrumbs).
 */
function sget_pagina(): string
{
    return basename($_SERVER['PHP_SELF'] ?? 'index', '.php');
}

/**
 * Redirige y termina la ejecución.
 */
function sget_redirigir(string $url, int $codigo = 302): void
{
    if (!headers_sent()) {
        header('Location: ' . $url, true, $codigo);
    } else {
        echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
    }
    exit;
}

/**
 * Configuración de errores.
 *
 * En PRODUCCIÓN los errores nunca se muestran al usuario: van al log del
 * servidor. Eso es lo correcto, pero durante el desarrollo produce páginas en
 * blanco sin ninguna pista (pasó con un `number_format()` mal escrito en
 * Admin/logs.php: pantalla vacía y ni un error en pantalla).
 *
 * Por eso, si la variable de entorno SGET_DEBUG está activa, los errores SÍ se
 * muestran y además se inyecta un comentario al final del HTML con el mensaje,
 * de modo que una pantalla vacía sea fácil de diagnosticar.
 *
 *   Linux/macOS:  SGET_DEBUG=1 php -S 127.0.0.1:8899 -t .
 *   Windows:      set SGET_DEBUG=1 && php -S 127.0.0.1:8899 -t .
 */
$sgetDebug = in_array(getenv('SGET_DEBUG'), ['1', 'true', 'on'], true);
define('SGET_DEBUG', $sgetDebug);

error_reporting(E_ALL);
ini_set('display_errors', $sgetDebug ? '1' : '0');
ini_set('log_errors', '1');

if ($sgetDebug) {
    register_shutdown_function(static function (): void {
        $e = error_get_last();
        if ($e === null || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }
        echo "\n<!-- ============================================================ -->\n"
           . "<!-- ERROR PHP: " . htmlspecialchars((string)($e['message'] ?? ''), ENT_QUOTES, 'UTF-8') . "\n"
           . "     Archivo:  " . htmlspecialchars((string)($e['file'] ?? ''), ENT_QUOTES, 'UTF-8')
           . ':' . (int)($e['line'] ?? 0) . "\n"
           . "     ============================================================ -->\n";
    });
}

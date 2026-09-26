<?php
/**
 * core/bootstrap.php
 * -----------------------------------------------------------------------------
 * UNICO punto de entrada. Todo script del sistema debe comenzar con:
 *     require_once __DIR__ . '/../core/bootstrap.php';
 *
 * Carga: config, BD, helpers, servicios legacy (Logger/AuthHelper) y
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
require_once dirname(__DIR__) . '/services/ViajeService.php';

// --- Legado (se mantienen hasta migrar pagina por pagina) ---------------
require_once dirname(__DIR__) . '/helpers/Logger.php';
require_once dirname(__DIR__) . '/helpers/AuthHelper.php';

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
 * Convierte los errores PHP en una respuesta clara en modo desarrollo.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0'); // nunca al usuario final; se registra en el log
ini_set('log_errors', '1');

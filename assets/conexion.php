<?php
/**
 * assets/conexion.php
 * -----------------------------------------------------------------------------
 * SHIM DE COMPATIBILIDAD (deprecated).
 *
 * Toda la lógica de conexión vive ahora en:
 *     core/Database.php  (PDO, prepared statements, SQL estricto)
 *     core/Config.php   (credenciales y estados)
 *
 * Los scripts antiguos siguen haciendo `include '../assets/conexion.php'`,
 * por eso este archivo simplemente delega en el bootstrap. No anadir logica aqui.
 * -----------------------------------------------------------------------------
 */
require_once dirname(__DIR__) . '/core/bootstrap.php';

// Las constantes antiguas se mantienen como alias de Config para no romper
// callers legacy, pero los valores correctos viven en core/Config.php.
if (!defined('ESTADO_DISPONIBLE')) define('ESTADO_DISPONIBLE', Config::VEH_DISPONIBLE);
if (!defined('ESTADO_OCUPADO'))    define('ESTADO_OCUPADO',    Config::VEH_FUERA_SERVICIO);

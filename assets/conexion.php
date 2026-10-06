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

/*
 * Alias de las constantes antiguas, para no romper llamadores legacy.
 * Los valores correctos viven ÚNICOS en `core/Config.php`.
 *
 * `ESTADO_OCUPADO` apuntaba a «Fuera de servicio», que además de ser el valor
 * equivocado era el que dejaba la flota llena de averías: `vehiculo.est_veh`
 * ahora distingue los cuatro estados (Disponible · Asignado · Mantenimiento ·
 * Fuera de servicio) y «ocupado» es, literalmente, «Asignado».
 */
if (!defined('ESTADO_DISPONIBLE')) define('ESTADO_DISPONIBLE', Config::VEH_DISPONIBLE);
if (!defined('ESTADO_OCUPADO'))    define('ESTADO_OCUPADO',    Config::VEH_ASIGNADO);
if (!defined('ESTADO_MANTENIMIENTO')) define('ESTADO_MANTENIMIENTO', Config::VEH_MANTENIMIENTO);

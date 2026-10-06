<?php

/**
 * pruebas/_sesion_admin.php
 * -----------------------------------------------------------------------------
 * Sonda de sesión: abre una sesión de Administrador en el navegador para poder
 * inspeccionar los módulos internos (Admin/*) con Chrome headless.
 *
 * Uso:  http://127.0.0.1:8899/pruebas/_sesion_admin.php
 * Solo para desarrollo: fuera de producciónSIEMPRE debe quedar bloqueado.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);


require_once __DIR__ . '/_guardia.php';
require_once __DIR__ . '/../core/bootstrap.php';

if (!in_array(getenv('SGET_DEBUG'), ['1', 'true', 'on'], true) && getenv('SGET_SONDA') !== '1') {
    http_response_code(403);
    exit('Sonda deshabilitada: ejecútala con SGET_DEBUG=1 o SGET_SONDA=1.');
}

$documento = (string)($_GET['documento'] ?? '');
if ($documento === '') {
    $u = Database::one("SELECT num_doc_usu, nom_usu FROM usuario WHERE id_rol_usu = ? ORDER BY id_usu LIMIT 1",
        [Config::ROL_ADMIN]);
    $documento = (string)($u['num_doc_usu'] ?? '');
}

$_SESSION['id_usu']          = (int)Database::scalar("SELECT id_usu FROM usuario WHERE num_doc_usu = ?", [$documento]);
$_SESSION['documento']       = $documento;
$_SESSION['nombre_usuario']  = (string)Database::scalar("SELECT nom_usu FROM usuario WHERE num_doc_usu = ?", [$documento]);
$_SESSION['rol']             = Config::ROL_ADMIN;
$_SESSION['ultima_actividad'] = time();

/**
 * ?bloquear=1 deja la sesión en estado BLOQUEADO por inactividad, para poder
 * verificar el modal de bloqueo (includes/modal_inactividad.php) sin esperar
 * los minutos reales de inactividad.
 */
if (isset($_GET['bloquear'])) {
    $ahora = time();
    $_SESSION['ultimo_acceso'] = $ahora - ((Config::MINUTOS_INACTIVIDAD * 60) + 30);
    $_SESSION['sesion_bloqueada'] = true;
    $_SESSION['inactividad_bloqueada_en'] = $ahora - 5;
    $_SESSION['inactividad_temporizador_inicia_en'] = $ahora + Config::SEGUNDOS_GRACIA_INACTIVIDAD;
    $_SESSION['inactividad_cierra_en'] = $ahora + (Config::SEGUNDOS_GRACIA_INACTIVIDAD * 2);
}

echo 'Sonda OK · admin ' . htmlspecialchars($_SESSION['nombre_usuario'] ?? '', ENT_QUOTES, 'UTF-8');

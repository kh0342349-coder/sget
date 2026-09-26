<?php
/**
 * pruebas/_sesion_test.php
 * -----------------------------------------------------------------------------
 * SOLO PARA DESARROLLO. Crea una sesión de administrador simulada para poder
 * verificar el renderizado de las páginas protegidas sin hacer login manual.
 *
 * SE ACTIVA SOLO si:
 *   1. la petición viene de 127.0.0.1 / ::1, Y
 *   2. existe el archivo pruebas/.habilitar
 *
 * Para usarlo:
 *     touch pruebas/.habilitar
 *     php -S 127.0.0.1:8899 -t .
 *     php pruebas/render.php
 *     rm pruebas/.habilitar      <-- bórralo al terminar
 *
 * El archivo está en .gitignore y nunca debe existir en un servidor real.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

$remoto = $_SERVER['REMOTE_ADDR'] ?? '';
$local  = in_array($remoto, ['127.0.0.1', '::1'], true);

if (!$local || !is_file(__DIR__ . '/.habilitar')) {
    http_response_code(404);
    exit('404');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

$_SESSION['id_usu']         = 1;
$_SESSION['documento']      = '000000';
$_SESSION['rol']            = (int)($_GET['rol'] ?? Config::ROL_ADMIN);
$_SESSION['id_rol_usu']     = $_SESSION['rol'];
$_SESSION['nombre_usuario'] = 'Prueba Automática';
$_SESSION['ultimo_acceso']  = time();
$_SESSION['sget_idioma']    = 'es';

header('Content-Type: text/plain; charset=utf-8');
echo 'sesion de prueba lista (rol ' . $_SESSION['rol'] . ')';

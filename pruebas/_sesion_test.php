<?php

/**
 * pruebas/_sesion_test.php
 * -----------------------------------------------------------------------------
 * SOLO PARA DESARROLLO. Crea una sesión real del rol pedido para poder verificar
 * el renderizado de las páginas protegidas sin hacer login manual.
 *
 * SE ACTIVA SOLO si:
 *   1. la petición viene de 127.0.0.1 / ::1, Y
 *   2. existe el archivo pruebas/.habilitar
 *
 * Parámetros:
 *   ?rol=1|2|3   rol a simular (por defecto 1)
 *   ?ir=Ruta.php  página a la que redirigir tras crear la sesión
 *
 * IMPORTANTE: usa un usuario REAL de ese rol (id_usu + documento coincidentes).
 * Antes fijaba documento='000000' con id_usu=1, y las páginas que buscan al
 * conductor por número de documento terminaban en
 * "Error: Conductor no encontrado" — es decir, se probaban páginas que en
 * producción nunca se verían así.
 *
 *     touch pruebas/.habilitar
 *     php -S 127.0.0.1:8899 -t .
 *     php pruebas/render.php
 *     rm pruebas/.habilitar      <-- bórralo al terminar
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);


require_once __DIR__ . '/_guardia.php';
$remoto = $_SERVER['REMOTE_ADDR'] ?? '';
$local  = in_array($remoto, ['127.0.0.1', '::1'], true);

if (!$local || !is_file(__DIR__ . '/.habilitar')) {
    http_response_code(404);
    exit('404');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

$rol = (int)($_GET['rol'] ?? Config::ROL_ADMIN);
if (!in_array($rol, [Config::ROL_ADMIN, Config::ROL_CONDUCTOR, Config::ROL_PASAJERO], true)) {
    $rol = Config::ROL_ADMIN;
}

// Usuario real de ese rol; si no existe, se crea uno de prueba para el rol 3.
$usuario = Database::one(
    "SELECT id_usu, num_doc_usu, nom_usu, corre_usu
       FROM usuario
      WHERE id_rol_usu = ? AND estado = 1
      ORDER BY id_usu ASC
      LIMIT 1",
    [$rol]
);

if (!$usuario) {
    if ($rol !== Config::ROL_PASAJERO) {
        http_response_code(409);
        exit('No hay ningún usuario activo con el rol ' . $rol . '. Crea uno antes de probar.');
    }
    $doc = 'T' . random_int(900000, 999999);
    $id  = Database::insert(
        "INSERT INTO usuario (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, id_rol_usu, pass_usu, estado, est_con_usu)
         VALUES ('CC', ?, 'Pasajero de Prueba', ?, ?, ?, 1, NULL)",
        // El orden de los parámetros DEBE seguir el de los marcadores:
        // num_doc_usu, corre_usu, id_rol_usu, pass_usu
        [$doc, "smoke_$doc@test.local", Config::ROL_PASAJERO, password_hash('prueba123', PASSWORD_DEFAULT)]
    );
    $usuario = ['id_usu' => $id, 'num_doc_usu' => $doc, 'nom_usu' => 'Pasajero de Prueba'];
}

$_SESSION = [
    'id_usu'         => (int)$usuario['id_usu'],
    'documento'      => (string)$usuario['num_doc_usu'],
    'rol'            => $rol,
    'id_rol_usu'     => $rol,
    'nombre_usuario' => (string)$usuario['nom_usu'],
    'corre_usu'      => (string)($usuario['corre_usu'] ?? ''),
    'ultimo_acceso'  => time(),
    'sget_idioma'    => 'es',
    'sget_csrf'      => bin2hex(random_bytes(32)),
];

header('Content-Type: text/plain; charset=utf-8');
echo 'sesion de prueba lista (rol ' . $rol . ', doc ' . $_SESSION['documento'] . ')';

// Modo "ir a": establece la sesión y redirige a la página indicada.
//   pruebas/_sesion_test.php?rol=1&ir=Admin/rutas.php
$ir = (string)($_GET['ir'] ?? '');
if ($ir !== '' && preg_match('#^[a-zA-Z0-9_\-./?=&]+$#', $ir)) {
    header('Location: ' . Config::basePath() . '/' . ltrim($ir, '/'), true, 302);
    exit;
}

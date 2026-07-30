<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'sget';

$conexion = mysqli_connect($host, $user, $pass, $db);

if (!$conexion) {
    die("Error al conectar la base de datos: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, "utf8mb4");

define('ESTADO_DISPONIBLE', 1);
define('ESTADO_OCUPADO', 2);

?>
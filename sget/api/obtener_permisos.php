<?php
// api/obtener_permisos.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../assets/conexion.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';

$idAdmin = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 1;

// Capturar el ID sin importar cómo lo envíe el frontend
$idUsuarioTarget = intval($_GET['id_usu'] ?? $_GET['id'] ?? $_GET['user_id'] ?? 0);

if ($idUsuarioTarget <= 0) {
    echo json_encode([
        'status' => 'error',
        'mensaje' => 'ID de usuario inválido',
        'permisos' => []
    ]);
    exit();
}

// 1. Obtener nombre del usuario objetivo
$sqlRol = "SELECT nom_usu FROM usuario WHERE id_usu = ?";
$stmtRol = mysqli_prepare($conexion, $sqlRol);
mysqli_stmt_bind_param($stmtRol, "i", $idUsuarioTarget);
mysqli_stmt_execute($stmtRol);
$resRol = mysqli_stmt_get_result($stmtRol);
$userTarget = mysqli_fetch_assoc($resRol);
mysqli_stmt_close($stmtRol);

$nombreUsuario = $userTarget['nom_usu'] ?? 'Usuario';

// 2. Obtener TODOS los permisos de la tabla 'permisos' junto con el estado actual del usuario
$sqlPermisos = "SELECT p.id_permiso, p.nombre_permiso, p.modulo, p.descripcion, 
                       COALESCE(up.permitido, 0) AS permitido
                FROM permisos p
                LEFT JOIN usuario_permisos up ON p.id_permiso = up.id_permiso AND up.id_usu = ?";

$stmt = mysqli_prepare($conexion, $sqlPermisos);
mysqli_stmt_bind_param($stmt, "i", $idUsuarioTarget);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$permisos = [];
while ($row = mysqli_fetch_assoc($res)) {
    $permisos[] = [
        'id_permiso' => intval($row['id_permiso']),
        'nombre_permiso' => $row['nombre_permiso'],
        'modulo' => $row['modulo'],
        'descripcion' => $row['descripcion'],
        'permitido' => intval($row['permitido'])
    ];
}
mysqli_stmt_close($stmt);

// Devolver la respuesta empaquetada para que cualquier estructura de JS la reconozca al instante
echo json_encode([
    'status' => 'success',
    'usuario' => $nombreUsuario,
    'permisos' => $permisos,
    'data' => $permisos
]);
exit();
?>
<?php
// api/obtener_permisos.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../assets/conexion.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';

$idUsuarioTarget = isset($_GET['id_usu']) ? intval($_GET['id_usu']) : 0;

if ($idUsuarioTarget <= 0) {
    echo json_encode(['status' => 'error', 'mensaje' => 'ID de usuario no válido']);
    exit();
}

// 1. Obtener el rol del usuario target
$sqlRol = "SELECT id_rol_usu FROM usuario WHERE id_usu = ?";
$stmtRol = mysqli_prepare($conexion, $sqlRol);
mysqli_stmt_bind_param($stmtRol, "i", $idUsuarioTarget);
mysqli_stmt_execute($stmtRol);
$resRol = mysqli_stmt_get_result($stmtRol);
$user = mysqli_fetch_assoc($resRol);
mysqli_stmt_close($stmtRol);

if (!$user) {
    echo json_encode(['status' => 'error', 'mensaje' => 'Usuario no encontrado']);
    exit();
}

$idRolUsuario = intval($user['id_rol_usu']);

// 2. Traer ÚNICAMENTE los permisos correspondientes a su rol (o generales si id_rol es NULL)
$sql = "SELECT 
            p.id_permiso,
            p.modulo,
            p.nombre_permiso,
            p.descripcion,
            COALESCE(up.permitido, 0) AS permitido
        FROM permisos p
        LEFT JOIN usuario_permisos up 
               ON p.id_permiso = up.id_permiso 
              AND up.id_usu = ?
        WHERE p.id_rol = ? OR p.id_rol IS NULL
        ORDER BY p.modulo ASC, p.nombre_permiso ASC";

$stmt = mysqli_prepare($conexion, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $idUsuarioTarget, $idRolUsuario);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $permisos = [];

    while ($row = mysqli_fetch_assoc($resultado)) {
        $permisos[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'id_rol' => $idRolUsuario,
        'data' => $permisos
    ]);
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['status' => 'error', 'mensaje' => 'Error SQL: ' . mysqli_error($conexion)]);
}
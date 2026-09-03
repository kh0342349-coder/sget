<?php
// api/guardar_permisos.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../assets/conexion.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';

$idAdmin = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 1;

if (!AuthHelper::tienePermiso($conexion, $idAdmin, 'gestionar_permisos')) {
    echo json_encode(['status' => 'error', 'mensaje' => 'Acceso denegado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idUsuarioTarget = intval($_POST['id_usu'] ?? 0);
    $permisosSeleccionados = $_POST['permisos'] ?? []; // Array con los id_permiso marcados

    if ($idUsuarioTarget <= 0) {
        echo json_encode(['status' => 'error', 'mensaje' => 'Usuario no válido']);
        exit();
    }

    // 1. Obtener el rol del usuario target
    $sqlRol = "SELECT id_rol_usu FROM usuario WHERE id_usu = ?";
    $stmtRol = mysqli_prepare($conexion, $sqlRol);
    mysqli_stmt_bind_param($stmtRol, "i", $idUsuarioTarget);
    mysqli_stmt_execute($stmtRol);
    $resRol = mysqli_stmt_get_result($stmtRol);
    $userTarget = mysqli_fetch_assoc($resRol);
    mysqli_stmt_close($stmtRol);

    if (!$userTarget) {
        echo json_encode(['status' => 'error', 'mensaje' => 'Usuario no encontrado']);
        exit();
    }

    $idRolUsuario = intval($userTarget['id_rol_usu']);

    // 2. Traer ÚNICAMENTE los permisos correspondientes a su rol (o generales si id_rol es NULL)
    $sqlCat = "SELECT id_permiso FROM permisos WHERE id_rol = ? OR id_rol IS NULL";
    $stmtCat = mysqli_prepare($conexion, $sqlCat);
    mysqli_stmt_bind_param($stmtCat, "i", $idRolUsuario);
    mysqli_stmt_execute($stmtCat);
    $resCat = mysqli_stmt_get_result($stmtCat);
    
    if (!$resCat) {
        echo json_encode(['status' => 'error', 'mensaje' => 'Error al leer catálogo']);
        exit();
    }

    while ($row = mysqli_fetch_assoc($resCat)) {
        $idPermiso = $row['id_permiso'];
        $permitido = in_array($idPermiso, $permisosSeleccionados) ? 1 : 0;

        // Insertar o actualizar estado del permiso
        $sqlUpsert = "INSERT INTO usuario_permisos (id_usu, id_permiso, permitido) 
                      VALUES (?, ?, ?)
                      ON DUPLICATE KEY UPDATE permitido = VALUES(permitido)";
        
        $stmt = mysqli_prepare($conexion, $sqlUpsert);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iii", $idUsuarioTarget, $idPermiso, $permitido);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_stmt_close($stmtCat);

    echo json_encode(['status' => 'success', 'mensaje' => 'Permisos actualizados correctamente.']);
}
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

    // Obtener catálogo entero de permisos
    $resCat = mysqli_query($conexion, "SELECT id_permiso FROM permisos");
    
    if (!$resCat) {
        echo json_encode(['status' => 'error', 'mensaje' => 'Error al leer catálogo']);
        exit();
    }

    while ($row = mysqli_fetch_assoc($resCat)) {
        $idPermiso = $row['id_permiso'];
        $permitido = in_array($idPermiso, $permisosSeleccionados) ? 1 : 0;

        // Insertar o actualizar
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

    echo json_encode(['status' => 'success', 'mensaje' => 'Permisos actualizados correctamente.']);
}
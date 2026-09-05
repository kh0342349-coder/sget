<?php
// api/guardar_permisos.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../assets/conexion.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';

$idAdmin = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 1;

// Validación flexible de permisos para el admin
if (!AuthHelper::tienePermiso($conexion, $idAdmin, 'gestionar_permisos')) {
    // Si no tiene el permiso explícito, verificamos si al menos es Rol 1 para evitar bloqueos propios
    $resAdminCheck = mysqli_query($conexion, "SELECT id_rol_usu FROM usuario WHERE id_usu = " . intval($idAdmin));
    $rowAdmin = mysqli_fetch_assoc($resAdminCheck);
    if (!$rowAdmin || intval($rowAdmin['id_rol_usu']) !== 1) {
        echo json_encode(['status' => 'error', 'mensaje' => 'Acceso denegado: No tienes permisos para realizar esta acción.']);
        exit();
    }
}

// Capturar datos sin importar si vienen por POST tradicional o por JSON
$inputJSON = json_decode(file_get_contents('php://input'), true);

$idUsuarioTarget = intval(
    $inputJSON['id_usu'] ?? $inputJSON['id'] ?? $_POST['id_usu'] ?? $_POST['id'] ?? 0
);

$permisosSeleccionados = 
    $inputJSON['permisos'] ?? $_POST['permisos'] ?? [];

if ($idUsuarioTarget <= 0) {
    echo json_encode(['status' => 'error', 'mensaje' => 'ID de usuario no válido.']);
    exit();
}

if (!is_array($permisosSeleccionados)) {
    $permisosSeleccionados = [];
}

// Transacción segura en la base de datos
mysqli_begin_transaction($conexion);

try {
    // 1. Borrar los permisos anteriores de este usuario
    $stmtDel = mysqli_prepare($conexion, "DELETE FROM usuario_permisos WHERE id_usu = ?");
    mysqli_stmt_bind_param($stmtDel, "i", $idUsuarioTarget);
    mysqli_stmt_execute($stmtDel);
    mysqli_stmt_close($stmtDel);

    // 2. Insertar únicamente los permisos que el administrador dejó marcados
    if (!empty($permisosSeleccionados)) {
        $stmtIns = mysqli_prepare($conexion, "INSERT INTO usuario_permisos (id_usu, id_permiso, permitido) VALUES (?, ?, 1)");
        foreach ($permisosSeleccionados as $idPermiso) {
            $idPermisoInt = intval($idPermiso);
            if ($idPermisoInt > 0) {
                mysqli_stmt_bind_param($stmtIns, "ii", $idUsuarioTarget, $idPermisoInt);
                mysqli_stmt_execute($stmtIns);
            }
        }
        mysqli_stmt_close($stmtIns);
    }

    mysqli_commit($conexion);
    echo json_encode(['status' => 'success', 'mensaje' => 'Permisos guardados correctamente.']);

} catch (Exception $e) {
    mysqli_rollback($conexion);
    echo json_encode(['status' => 'error', 'mensaje' => 'Error al guardar en BD: ' . $e->getMessage()]);
}
exit();
?>
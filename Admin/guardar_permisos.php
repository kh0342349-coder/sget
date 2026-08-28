<?php
session_start();
include '../assets/conexion.php';

// Validar que solo un Administrador (rol 1) pueda ejecutar esta acción
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usu = intval($_POST['id_usu'] ?? 0);
    
    // Si se marcaron restricciones, las une con coma. Si no se marcó ninguna, queda como NULL
    $restricciones_array = $_POST['restricciones'] ?? [];
    $restricciones_str = !empty($restricciones_array) ? implode(',', array_map('trim', $restricciones_array)) : NULL;

    if ($id_usu > 0) {
        $stmt = $conexion->prepare("UPDATE usuario SET restricciones = ? WHERE id_usu = ?");
        $stmt->bind_param("si", $restricciones_str, $id_usu);
        
        if ($stmt->execute()) {
            $_SESSION['msg_admin'] = "Permisos actualizados correctamente.";
        } else {
            $_SESSION['msg_admin_error'] = "Error al actualizar los permisos.";
        }
        $stmt->close();
    }
}

header("Location: admin.php");
exit();
?>
<?php
include '../assets/conexion.php';
$id = $_GET['id'];

// 1. Verificamos si existen reportes con este viaje
$check = $conexion->prepare("SELECT id_rep FROM reportes_pasajeros WHERE id_via_rep = ?");
$check->bind_param("i", $id);
$check->execute();
$resultado = $check->get_result();

if ($resultado->num_rows > 0) {
    // Si hay reportes, no podemos borrar
    header("Location: viajes.php?status=error&msg=No se puede eliminar: este viaje tiene reportes registrados.");
} else {
    // Si no hay reportes, borramos
    $stmt = $conexion->prepare("DELETE FROM viaje WHERE id_via = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: viajes.php?status=success");
}
?>
<?php
include '../assets/conexion.php';

if (isset($_GET['id']) && isset($_GET['estado'])) {
    
    $id_veh = mysqli_real_escape_string($conexion, $_GET['id']);
    $nuevo_estado = mysqli_real_escape_string($conexion, $_GET['estado']);

    $sql = "UPDATE vehiculo SET est_veh = '$nuevo_estado' WHERE id_veh = '$id_veh'";

    if ($conexion->query($sql)) {
        header("Location: vehiculos.php?msj=estado_actualizado");
        exit();
    } else {
        echo "Error al cambiar el estado: " . $conexion->error;
    }
} else {
    header("Location: vehiculos.php");
    exit();
}
?>
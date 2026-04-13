<?php
include '../assets/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $placa     = mysqli_real_escape_string($conexion, $_POST['pla_veh']);
    $modelo    = mysqli_real_escape_string($conexion, $_POST['mode_veh']);
    $capacidad = mysqli_real_escape_string($conexion, $_POST['cap_veh']);
    
    $estado    = 1; 

    $sql = "INSERT INTO vehiculo (pla_veh, mode_veh, cap_veh, est_veh) 
            VALUES ('$placa', '$modelo', '$capacidad', '$estado')";

    if ($conexion->query($sql)) {
        header("Location: vehiculos.php?msj=guardado");
        exit();
    } else {
        echo "Error al registrar el vehículo: " . $conexion->error;
    }
} else {
    header("Location: vehiculos.php");
    exit();
}
?>
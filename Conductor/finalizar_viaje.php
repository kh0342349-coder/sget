<?php
date_default_timezone_set('America/Bogota');
include '../assets/conexion.php';
session_start();

if (isset($_GET['id'])) {
    $id_via = $_GET['id'];
    $id_usu = $_SESSION['id_usuario'];

    $update_viaje = "UPDATE viaje SET est_via = 'Finalizado' WHERE id_via = '$id_via'";
    
    if ($conexion->query($update_viaje)) {nible
        $conexion->query("UPDATE usuario SET est_usu = 'Disponible' WHERE id_usu = '$id_usu'");
        
        header("Location: conductor.php?msj=viaje_terminado");
    } else {
        echo "Error al finalizar: " . $conexion->error;
    }
}
?>
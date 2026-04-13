<?php
session_start();
include '../assets/conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_rut = $_POST['id_rut_via']; 
    $id_usu = $_POST['id_usu_via']; 
    $valor = $_POST['val_via'];
    $nombre_viaje = $_POST['nombre_viaje'] ?? 'Viaje Nuevo';
    
    $fecha_actual = date('Y-m-d H:i:s');

    $sql = "INSERT INTO viaje (nom_via, fec_via, hor_sal_via, val_via, id_rut_via, id_usu_via, est_via) 
            VALUES ('$nombre_viaje', '$fecha_actual', '$fecha_actual', '$valor', '$id_rut', '$id_usu', 'Activo')";

    if ($conexion->query($sql) === TRUE) {
        $sql_update_usu = "UPDATE usuario SET est_con_usu = 'Ocupado' WHERE id_usu = '$id_usu'";
        
        $conexion->query($sql_update_usu);

        header("Location: viajes.php?success=1");
        exit();
    } else {
        echo "Error al guardar el viaje: " . $conexion->error;
    }
}
?>
<?php
session_start();
include '../assets/conexion.php';

if (isset($_GET['id_via']) && isset($_GET['id_usu'])) {
    $id_via = $_GET['id_via'];
    $id_usu = $_GET['id_usu'];
    
    // Configuramos la hora local de Colombia
    date_default_timezone_set('America/Bogota');
    $hor_lleg_via = date('Y-m-d H:i:s');

    // 1. Actualizamos el viaje
    $sql_viaje = "UPDATE viaje SET hor_lleg_via = '$hor_lleg_via', est_via = 'Terminado' WHERE id_via = '$id_via'";

    if ($conexion->query($sql_viaje) === TRUE) {
        
        // 2. ¡DESCOMENTADO Y CORREGIDO!
        // Cambiamos 'est_usu' por 'est_con_usu' y usamos la constante ESTADO_DISPONIBLE (1)
        $sql_usuario = "UPDATE usuario SET est_con_usu = " . ESTADO_DISPONIBLE . " WHERE id_usu = '$id_usu'";
        $conexion->query($sql_usuario); 

        // Redireccionamos al éxito
        header("Location: viajes.php?msg=Viaje finalizado correctamente");
        exit(); 
        
    } else {
        echo "Error al terminar el viaje: " . $conexion->error;
    }
} else {
    header("Location: viajes.php");
    exit();
}
?>
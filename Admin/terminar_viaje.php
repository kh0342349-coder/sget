<?php
include '../assets/conexion.php';

if (isset($_GET['id_via']) && isset($_GET['id_usu'])) {
    $id_via = $_GET['id_via'];
    $id_usu = $_GET['id_usu'];
    
    // Configuramos la hora local de Colombia
    date_default_timezone_set('America/Bogota');
    $hor_lleg_via = date('Y-m-d H:i:s');

    // 1. Actualizamos el viaje (ESTO SÍ FUNCIONA SEGÚN SUS TABLAS)
    $sql_viaje = "UPDATE viaje SET hor_lleg_via = '$hor_lleg_via', est_via = 'Terminado' WHERE id_via = '$id_via'";

    if ($conexion->query($sql_viaje) === TRUE) {
        
        /* * ELIMINAMOS O COMENTAMOS LA LÍNEA DE 'est_usu' PORQUE NO EXISTE EN SU TABLA 'usuario'.
         * Si en el futuro quiere que el conductor quede 'Disponible', 
         * primero debe crear la columna en phpMyAdmin.
         */
        
        // $sql_usuario = "UPDATE usuario SET est_usu = 'Disponible' WHERE id_usu = '$id_usu'";
        // $conexion->query($sql_usuario); 

        // Redireccionamos al éxito
        header("Location: viajes.php?msg=Viaje finalizado correctamente");
        exit(); 
        
    } else {
        // Si hay un error en la tabla viaje, aquí nos avisará
        echo "Error al terminar el viaje: " . $conexion->error;
    }
} else {
    header("Location: viajes.php");
    exit();
}
?>
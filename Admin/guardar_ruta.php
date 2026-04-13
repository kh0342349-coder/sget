<?php
include '../assets/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $nombre = $_POST['nom_rut'];
    $origen = $_POST['ori_rut'];
    $destino = $_POST['des_rut'];
    $distancia = $_POST['dis_rut'];
    $precio = $_POST['val_rut'];

    $sql = "INSERT INTO rutas (nom_rut, dis_rut, ori_rut, des_rut, val_rut) 
            VALUES ('$nombre', '$distancia', '$origen', '$destino', '$precio')";

    if ($conexion->query($sql)) {

        header("Location: rutas.php?msj=exito");
        exit();
    } else {
        echo "Error al registrar la ruta: " . $conexion->error;
    }
} else {
    header("Location: rutas.php");
    exit();
}
?>
<?php
include '../assets/conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_rut = $_POST['id_rut'];
    $nuevo_precio = $_POST['val_rut'];
    $nombre = $_POST['nom_rut'];

    $sql = "UPDATE rutas SET nom_rut = '$nombre', val_rut = '$nuevo_precio' WHERE id_rut = '$id_rut'";

    if ($conexion->query($sql) === TRUE) {
        header("Location: rutas.php?msg=Ruta actualizada");
    } else {
        echo "Error: " . $conexion->error;
    }
}
?>
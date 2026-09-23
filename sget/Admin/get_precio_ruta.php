<?php
include '../assets/conexion.php';
if (isset($_POST['id_rut'])) {
    $id = $_POST['id_rut'];
    $query = "SELECT val_rut FROM rutas WHERE id_rut = '$id'";
    $resultado = $conexion->query($query);
    $row = $resultado->fetch_assoc();
    echo $row['val_rut'];
}
?>
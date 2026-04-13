<?php
include '../assets/conexion.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_original = $_POST['doc_original'];
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $rol = $_POST['rol'];

    $sql = "UPDATE usuario SET 
            nom_usu = '$nombre', 
            corre_usu = '$correo', 
            id_rol_usu = '$rol' 
            WHERE num_doc_usu = '$doc_original'";

    if ($conexion->query($sql)) {
        header("Location: usuarios.php?msg=actualizado");
    } else {
        echo "Error al actualizar: " . $conexion->error;
    }
} else {
    header("Location: usuarios.php");
}
?>
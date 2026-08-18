<?php
session_start();
include '../assets/conexion.php';

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $id_rut = mysqli_real_escape_string($conexion, $_GET['id']);

    // Consultar imagen actual para eliminarla del servidor
    $sql_img = "SELECT img_rut FROM rutas WHERE id_rut = '$id_rut'";
    $res_img = mysqli_query($conexion, $sql_img);
    if ($res_img && $row = mysqli_fetch_assoc($res_img)) {
        if (!empty($row['img_rut'])) {
            $archivo = "../uploads/rutas/" . $row['img_rut'];
            if (file_exists($archivo)) {
                unlink($archivo);
            }
        }
    }

    // Eliminar registro
    $sql = "DELETE FROM rutas WHERE id_rut = '$id_rut'";
    mysqli_query($conexion, $sql);
}

header("Location: rutas.php");
exit();
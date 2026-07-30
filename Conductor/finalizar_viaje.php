<?php
session_start();
include '../assets/conexion.php';

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 2) {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $id_viaje = $_GET['id'];
    $documento_conductor = $_SESSION['documento'];

    $conexion->begin_transaction();

    try {
        $sql_viaje = "UPDATE viaje SET est_via = 'Finalizado' WHERE id_via = '$id_viaje'";
        $conexion->query($sql_viaje);

        $sql_conductor = "UPDATE usuario SET est_con_usu = 1 WHERE num_doc_usu = '$documento_conductor'";
        
        if (!$conexion->query($sql_conductor)) {
            throw new Exception($conexion->error);
        }

        $conexion->commit();
        header("Location: viajes_conductor.php?status=success");
        exit();

    } catch (Exception $e) {
        $conexion->rollback();
        echo $e->getMessage();
    }
} else {
    header("Location: viajes_conductor.php");
    exit();
}
?>
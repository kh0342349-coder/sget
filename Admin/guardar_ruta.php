<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

// Verificación de seguridad (Solo Admin)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_via      = isset($_POST['id_via']) ? trim($_POST['id_via']) : '';
    $id_rut_via  = isset($_POST['id_rut_via']) ? trim($_POST['id_rut_via']) : '';
    $id_usu_via  = isset($_POST['id_usu_via']) ? trim($_POST['id_usu_via']) : '';
    $id_veh      = isset($_POST['id_veh_via']) ? trim($_POST['id_veh_via']) : '';
    $fec_via     = isset($_POST['fec_via']) ? trim($_POST['fec_via']) : '';
    $hor_sal_via = isset($_POST['hor_sal_via']) ? trim($_POST['hor_sal_via']) : '';
    $val_via     = isset($_POST['val_via']) ? floatval($_POST['val_via']) : 0;

    if (!empty($id_via)) {
        // --- MODO EDICIÓN / ACTUALIZACIÓN ---
        $sql = "UPDATE viaje SET 
                    id_rut_via = '$id_rut_via', 
                    id_usu_via = '$id_usu_via', 
                    id_veh = '$id_veh', 
                    fec_via = '$fec_via', 
                    hor_sal_via = '$hor_sal_via', 
                    val_via = '$val_via',
                    est_via = 'Activo' 
                WHERE id_via = '$id_via'";
    } else {
        // --- MODO INSERCIÓN (NUEVO VIAJE) ---
        $sql = "INSERT INTO viaje (id_rut_via, id_usu_via, id_veh, fec_via, hor_sal_via, val_via, est_via) 
                VALUES ('$id_rut_via', '$id_usu_via', '$id_veh', '$fec_via', '$hor_sal_via', '$val_via', 'Activo')";
    }

    if (mysqli_query($conexion, $sql)) {
        header("Location: viajes.php?status=success");
    } else {
        header("Location: viajes.php?status=error");
    }
    exit();
}
?>
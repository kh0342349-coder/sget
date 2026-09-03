<?php
session_start();
include '../assets/conexion.php';

// Verificación de seguridad (Solo Admin)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_via = $_POST['id_via'] ?? '';
    $id_rut_via = $_POST['id_rut_via'];
    $id_usu_via = $_POST['id_usu_via'];
    $id_veh_via = $_POST['id_veh_via'];
    $fec_via = $_POST['fec_via'];
    $hor_sal_via = $_POST['hor_sal_via'];
    $val_via = $_POST['val_via'];

    if (empty($id_via)) {
        // --- CREAR NUEVO VIAJE ---
        $stmt = $conexion->prepare("INSERT INTO viaje (id_rut_via, id_usu_via, id_veh, fec_via, hor_sal_via, val_via, est_via) VALUES (?, ?, ?, ?, ?, ?, 'Activo')");
        $stmt->bind_param("iiissd", $id_rut_via, $id_usu_via, $id_veh_via, $fec_via, $hor_sal_via, $val_via);
        
        if ($stmt->execute()) {
            // Opcional: Si manejas columnas de estado directo en las tablas usuario/vehiculo, puedes actualizarlas aquí a ocupado/0
            header("Location: viajes.php?status=success");
        } else {
            header("Location: viajes.php?status=error");
        }
        exit();
    } else {
        // --- EDITAR / ACTUALIZAR VIAJE ---
        $stmt = $conexion->prepare("UPDATE viaje SET id_rut_via = ?, id_usu_via = ?, id_veh = ?, fec_via = ?, hor_sal_via = ?, val_via = ? WHERE id_via = ?");
        $stmt->bind_param("iiissdi", $id_rut_via, $id_usu_via, $id_veh_via, $fec_via, $hor_sal_via, $val_via, $id_via);
        
        if ($stmt->execute()) {
            header("Location: viajes.php?status=success");
        } else {
            header("Location: viajes.php?status=error");
        }
        exit();
    }
}

header("Location: viajes.php");
exit();
?>
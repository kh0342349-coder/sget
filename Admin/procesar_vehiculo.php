<?php
// Archivo: Admin/procesar_vehiculo.php
session_start();
include '../assets/conexion.php';

// Verificación de seguridad (Solo Admin)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recogemos únicamente los campos correspondientes a la tabla vehículo
    $id_veh = $_POST['id_veh'] ?? '';
    $pla_veh = strtoupper(trim($_POST['pla_veh'] ?? ''));
    $mode_veh = trim($_POST['mode_veh'] ?? '');
    $cap_veh = intval($_POST['cap_veh'] ?? 0);
    $est_veh = intval($_POST['est_veh'] ?? 1);

    if (empty($id_veh)) {
        // --- CREAR NUEVO VEHÍCULO ---
        $stmt = $conexion->prepare("INSERT INTO vehiculo (pla_veh, mode_veh, cap_veh, est_veh) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $pla_veh, $mode_veh, $cap_veh, $est_veh);
        
        if ($stmt->execute()) {
            header("Location: vehiculos.php?status=success");
        } else {
            header("Location: vehiculos.php?status=error");
        }
        exit();
    } else {
        // --- ACTUALIZAR VEHÍCULO EXISTENTE ---
        $stmt = $conexion->prepare("UPDATE vehiculo SET pla_veh = ?, mode_veh = ?, cap_veh = ?, est_veh = ? WHERE id_veh = ?");
        $stmt->bind_param("ssiii", $pla_veh, $mode_veh, $cap_veh, $est_veh, $id_veh);
        
        if ($stmt->execute()) {
            header("Location: vehiculos.php?status=success");
        } else {
            header("Location: vehiculos.php?status=error");
        }
        exit();
    }
}

header("Location: vehiculos.php");
exit();
?>
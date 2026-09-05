<?php
// Archivo: Admin/procesar_viaje.php
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
            // Marcar conductor y vehículo como Ocupados (0 o 'Ocupado' según tu BD)
            $conexion->query("UPDATE usuario SET est_con_usu = 0 WHERE id_usu = $id_usu_via");
            $conexion->query("UPDATE vehiculo SET est_veh = 0 WHERE id_veh = $id_veh_via");

            header("Location: viajes.php?status=success");
        } else {
            header("Location: viajes.php?status=error");
        }
        exit();
    } else {
        // --- EDITAR / ACTUALIZAR VIAJE ---
        // 1. Obtener datos anteriores del viaje para liberar al conductor y vehículo viejos si cambiaron
        $stmt_old = $conexion->prepare("SELECT id_usu_via, id_veh FROM viaje WHERE id_via = ?");
        $stmt_old->bind_param("i", $id_via);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        if ($res_old->num_rows > 0) {
            $old_data = $res_old->fetch_assoc();
            $old_usu = $old_data['id_usu_via'];
            $old_veh = $old_data['id_veh'];

            // Si cambiaron de conductor o vehículo, liberamos los anteriores
            if ($old_usu != $id_usu_via) {
                $conexion->query("UPDATE usuario SET est_con_usu = 1 WHERE id_usu = $old_usu");
            }
            if ($old_veh != $id_veh_via) {
                $conexion->query("UPDATE vehiculo SET est_veh = 1 WHERE id_veh = $old_veh");
            }
        }

        // 2. Actualizar el viaje
        $stmt = $conexion->prepare("UPDATE viaje SET id_rut_via = ?, id_usu_via = ?, id_veh = ?, fec_via = ?, hor_sal_via = ?, val_via = ? WHERE id_via = ?");
        $stmt->bind_param("iiissdi", $id_rut_via, $id_usu_via, $id_veh_via, $fec_via, $hor_sal_via, $val_via, $id_via);
        
        if ($stmt->execute()) {
            // Asegurar que los nuevos queden ocupados
            $conexion->query("UPDATE usuario SET est_con_usu = 0 WHERE id_usu = $id_usu_via");
            $conexion->query("UPDATE vehiculo SET est_veh = 0 WHERE id_veh = $id_veh_via");

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
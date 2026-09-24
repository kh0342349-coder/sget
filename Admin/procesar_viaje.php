<?php
// Archivo: Admin/procesar_viaje.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../assets/conexion.php';
require_once '../helpers/Logger.php';

// PERMITIR EL ACCESO TANTO A ADMINISTRADORES (1) COMO A CONDUCTORES (2)
if (!isset($_SESSION['documento']) || !in_array($_SESSION['rol'] ?? 0, [1, 2])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_via      = $_POST['id_via'] ?? '';
    $id_rut_via  = intval($_POST['id_rut_via'] ?? 0);
    $id_usu_via  = intval($_POST['id_usu_via'] ?? 0);
    $id_veh_via  = intval($_POST['id_veh_via'] ?? 0);
    $fec_via     = $_POST['fec_via'] ?? '';
    $hor_sal_via = $_POST['hor_sal_via'] ?? '';
    $val_via     = $_POST['val_via'] ?? null;

    // Obtener la tarifa automáticamente desde la tabla 'rutas' si no se envía en el formulario
    if ((empty($val_via) || $val_via == 0) && $id_rut_via > 0) {
        $stmt_val = $conexion->prepare("SELECT val_rut FROM rutas WHERE id_rut = ?");
        if ($stmt_val) {
            $stmt_val->bind_param("i", $id_rut_via);
            $stmt_val->execute();
            $res_val = $stmt_val->get_result();
            if ($row_val = $res_val->fetch_assoc()) {
                $val_via = $row_val['val_rut'];
            }
            $stmt_val->close();
        }
    }

    $val_via = floatval($val_via ?? 0);

    if (empty($id_via)) {
        // --- CREAR NUEVO VIAJE ---
        $stmt = $conexion->prepare("INSERT INTO viaje (id_rut_via, id_usu_via, id_veh, fec_via, hor_sal_via, val_via, est_via) VALUES (?, ?, ?, ?, ?, ?, 'Activo')");
        $stmt->bind_param("iiissd", $id_rut_via, $id_usu_via, $id_veh_via, $fec_via, $hor_sal_via, $val_via);
        
        if ($stmt->execute()) {
            $nuevo_id_viaje = $stmt->insert_id;

            // Actualizar estado del conductor y vehículo a ocupados (0)
            $conexion->query("UPDATE usuario SET est_con_usu = 0 WHERE id_usu = $id_usu_via");
            $conexion->query("UPDATE vehiculo SET est_veh = 0 WHERE id_veh = $id_veh_via");

            // Registrar evento en auditoría
            Logger::registrar(
                $conexion, 
                'CREAR_VIAJE', 
                "Se programó el viaje ID #{$nuevo_id_viaje} para la fecha {$fec_via} a las {$hor_sal_via}"
            );

            // Redirección adaptada según el rol del usuario
            if ($_SESSION['rol'] == 2) {
                header("Location: ../Conductor/viaje_asignado.php?status=success");
            } else {
                header("Location: viajes.php?status=success");
            }
        } else {
            if ($_SESSION['rol'] == 2) {
                header("Location: ../Conductor/viaje_asignado.php?status=error");
            } else {
                header("Location: viajes.php?status=error");
            }
        }
        $stmt->close();
        exit();
    } else {
        // --- EDITAR / ACTUALIZAR VIAJE ---
        $stmt_old = $conexion->prepare("SELECT id_usu_via, id_veh FROM viaje WHERE id_via = ?");
        $stmt_old->bind_param("i", $id_via);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        if ($res_old->num_rows > 0) {
            $old_data = $res_old->fetch_assoc();
            $old_usu = $old_data['id_usu_via'];
            $old_veh = $old_data['id_veh'];

            if ($old_usu != $id_usu_via) {
                $conexion->query("UPDATE usuario SET est_con_usu = 1 WHERE id_usu = $old_usu");
            }
            if ($old_veh != $id_veh_via) {
                $conexion->query("UPDATE vehiculo SET est_veh = 1 WHERE id_veh = $old_veh");
            }
        }
        $stmt_old->close();

        $stmt = $conexion->prepare("UPDATE viaje SET id_rut_via = ?, id_usu_via = ?, id_veh = ?, fec_via = ?, hor_sal_via = ?, val_via = ? WHERE id_via = ?");
        $stmt->bind_param("iiissdi", $id_rut_via, $id_usu_via, $id_veh_via, $fec_via, $hor_sal_via, $val_via, $id_via);
        
        if ($stmt->execute()) {
            $conexion->query("UPDATE usuario SET est_con_usu = 0 WHERE id_usu = $id_usu_via");
            $conexion->query("UPDATE vehiculo SET est_veh = 0 WHERE id_veh = $id_veh_via");

            // Registrar evento en auditoría
            Logger::registrar(
                $conexion, 
                'ACTUALIZAR_VIAJE', 
                "Se actualizaron los datos del viaje ID #{$id_via}"
            );

            if ($_SESSION['rol'] == 2) {
                header("Location: ../Conductor/viaje_asignado.php?status=success");
            } else {
                header("Location: viajes.php?status=success");
            }
        } else {
            if ($_SESSION['rol'] == 2) {
                header("Location: ../Conductor/viaje_asignado.php?status=error");
            } else {
                header("Location: viajes.php?status=error");
            }
        }
        $stmt->close();
        exit();
    }
}

header("Location: ../index.php");
exit();
?>
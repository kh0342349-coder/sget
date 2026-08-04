<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

// 1. Validación de Sesión y Rol de Conductor (Rol 2)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 2) {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_viaje = (int)$_GET['id'];
    $documento_conductor = $_SESSION['documento'];
    $hor_lleg_via = date('Y-m-d H:i:s'); // Hora exacta de finalización

    // Iniciar Transacción
    $conexion->begin_transaction();

    try {
        // 2. Obtener el id_veh asociado al viaje antes de actualizarlo
        $sql_get_veh = "SELECT id_veh FROM viaje WHERE id_via = ?";
        $stmt_get = $conexion->prepare($sql_get_veh);
        $stmt_get->bind_param("i", $id_viaje);
        $stmt_get->execute();
        $res_veh = $stmt_get->get_result();
        $viaje_data = $res_veh->fetch_assoc();
        $id_vehiculo = $viaje_data['id_veh'] ?? null;
        $stmt_get->close();

        // 3. Marcar viaje como 'Finalizado', registra fecha/hora de llegada y libera cupos
        $sql_viaje = "UPDATE viaje v
                      INNER JOIN usuario u ON v.id_usu_via = u.id_usu
                      SET v.est_via = 'Finalizado', 
                          v.hor_lleg_via = ?, 
                          v.cup_dis = 0 
                      WHERE v.id_via = ? AND u.num_doc_usu = ?";
                      
        $stmt_viaje = $conexion->prepare($sql_viaje);
        $stmt_viaje->bind_param("sis", $hor_lleg_via, $id_viaje, $documento_conductor);
        
        if (!$stmt_viaje->execute()) {
            throw new Exception("Error al actualizar el estado del viaje: " . $stmt_viaje->error);
        }
        $stmt_viaje->close();

        // 4. Cambiar el estado del conductor a 1 (Disponible)
        $sql_conductor = "UPDATE usuario SET est_con_usu = 1 WHERE num_doc_usu = ?";
        
        $stmt_cond = $conexion->prepare($sql_conductor);
        $stmt_cond->bind_param("s", $documento_conductor);
        
        if (!$stmt_cond->execute()) {
            throw new Exception("Error al actualizar el estado del conductor: " . $stmt_cond->error);
        }
        $stmt_cond->close();

        // 5. Cambiar el estado del vehículo a 1 (Disponible)
        if ($id_vehiculo) {
            $sql_vehiculo = "UPDATE vehiculo SET est_veh = 1 WHERE id_veh = ?";
            $stmt_veh = $conexion->prepare($sql_vehiculo);
            $stmt_veh->bind_param("i", $id_vehiculo);
            
            if (!$stmt_veh->execute()) {
                throw new Exception("Error al actualizar el estado del vehículo: " . $stmt_veh->error);
            }
            $stmt_veh->close();
        }

        // Confirmar la transacción
        $conexion->commit();
        
        header("Location: viajes_conductor.php?status=success");
        exit();

    } catch (Exception $e) {
        // Revertir cambios si hay error
        $conexion->rollback();
        header("Location: viajes_conductor.php?status=error&msg=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: viajes_conductor.php");
    exit();
}
?>
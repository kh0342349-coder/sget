<?php
session_start();
include '../assets/conexion.php';

if (isset($_GET['id_via']) && isset($_GET['id_usu'])) {
    $id_via = (int)$_GET['id_via'];
    $id_usu = (int)$_GET['id_usu'];
    
    // Configuramos la hora local de Colombia
    date_default_timezone_set('America/Bogota');
    $hor_lleg_via = date('Y-m-d H:i:s');

    // Definimos 1 como estado disponible (puedes usar tu constante si la tienes definida)
    $estado_disponible = defined('ESTADO_DISPONIBLE') ? ESTADO_DISPONIBLE : 1;

    // Iniciar transacción para garantizar que se ejecuten los 3 cambios
    $conexion->begin_transaction();

    try {
        // 1. Obtener el id_veh asociado a este viaje antes de finalizarlo
        $sql_get_veh = "SELECT id_veh FROM viaje WHERE id_via = ?";
        $stmt_get = $conexion->prepare($sql_get_veh);
        $stmt_get->bind_param("i", $id_via);
        $stmt_get->execute();
        $res_veh = $stmt_get->get_result();
        $viaje_data = $res_veh->fetch_assoc();
        $id_vehiculo = $viaje_data['id_veh'] ?? null;
        $stmt_get->close();

        // 2. Actualizamos el viaje (hora de llegada y estado a 'Terminado')
        $sql_viaje = "UPDATE viaje SET hor_lleg_via = ?, est_via = 'Terminado' WHERE id_via = ?";
        $stmt_viaje = $conexion->prepare($sql_viaje);
        $stmt_viaje->bind_param("si", $hor_lleg_via, $id_via);
        if (!$stmt_viaje->execute()) {
            throw new Exception("Error al terminar el viaje: " . $stmt_viaje->error);
        }
        $stmt_viaje->close();

        // 3. Actualizamos el estado del conductor (est_con_usu = 1)
        $sql_usuario = "UPDATE usuario SET est_con_usu = ? WHERE id_usu = ?";
        $stmt_usuario = $conexion->prepare($sql_usuario);
        $stmt_usuario->bind_param("ii", $estado_disponible, $id_usu);
        if (!$stmt_usuario->execute()) {
            throw new Exception("Error al actualizar estado de usuario: " . $stmt_usuario->error);
        }
        $stmt_usuario->close();

        // 4. Actualizamos el estado del vehículo (est_veh = 1)
        if ($id_vehiculo) {
            $sql_vehiculo = "UPDATE vehiculo SET est_veh = ? WHERE id_veh = ?";
            $stmt_veh = $conexion->prepare($sql_vehiculo);
            $stmt_veh->bind_param("ii", $estado_disponible, $id_vehiculo);
            if (!$stmt_veh->execute()) {
                throw new Exception("Error al actualizar estado del vehículo: " . $stmt_veh->error);
            }
            $stmt_veh->close();
        }

        // Confirmamos todos los cambios
        $conexion->commit();

        header("Location: viajes.php?msg=Viaje finalizado correctamente");
        exit();

    } catch (Exception $e) {
        // En caso de fallar algo, revertimos
        $conexion->rollback();
        echo "Error al procesar la solicitud: " . $e->getMessage();
    }
} else {
    header("Location: viajes.php");
    exit();
}
?>
<?php
session_start();
include '../assets/conexion.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_viaje = $_POST['id_viaje'] ?? '';
    $id_conductor = $_POST['id_conductor'] ?? '';
    $id_vehiculo = $_POST['id_vehiculo'] ?? '';

    if (!empty($id_viaje) && !empty($id_conductor) && !empty($id_vehiculo)) {
        
        $conexion->begin_transaction();

        try {
            // CORRECCIÓN: Asegúrate que los nombres de las columnas sean IGUALES a los de tu DB
            // Si tu columna es 'fec_asig', úsala así:
            $sql_insert = "INSERT INTO asignacion (id_via_asig, id_usu_asig, id_veh_asig, fec_asig) VALUES (?, ?, ?, NOW())";
            
            $stmt1 = $conexion->prepare($sql_insert);
            $stmt1->bind_param("iii", $id_viaje, $id_conductor, $id_vehiculo);
            $stmt1->execute();

            $sql_update = "UPDATE viaje SET est_via = 'Asignado' WHERE id_via = ?";
            $stmt2 = $conexion->prepare($sql_update);
            $stmt2->bind_param("i", $id_viaje);
            $stmt2->execute();

            $conexion->commit();
            header("Location: asignaciones.php?status=success");
            
        } catch (Exception $e) {
            $conexion->rollback();
            // DEBUG: Si esto sigue fallando, comenta la línea de abajo y descomenta la siguiente:
            // die("Error en la base de datos: " . $e->getMessage()); 
            header("Location: asignaciones.php?status=error");
        }
    } else {
        header("Location: asignaciones.php?status=empty");
    }
}
?>
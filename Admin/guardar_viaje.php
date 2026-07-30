<?php
include '../assets/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso denegado.");
}

// 1. Captura de variables desde el $_POST
$id_conductor = $_POST['id_usu_via'] ?? null;
$id_ruta      = $_POST['id_rut_via'] ?? null;
$id_vehiculo  = $_POST['id_veh_via'] ?? $_POST['id_veh'] ?? null; // Compatible con ambos nombres
$fecha_salida = $_POST['hor_sal_via'] ?? $_POST['fec_via'] ?? null; 
$precio       = $_POST['val_via'] ?? null;
$capacidad    = $_POST['cup_tot'] ?? null;

// 2. Validación de campos obligatorios
if (!$id_conductor || !$id_ruta || !$id_vehiculo || !$fecha_salida || !$precio) {
    die("Error: Faltan datos obligatorios.");
}

try {
    $conexion->begin_transaction();

    // Comprobar disponibilidad del conductor
    $stmt = $conexion->prepare("SELECT est_con_usu FROM usuario WHERE id_usu = ? AND id_rol_usu = 2 FOR UPDATE");
    $stmt->bind_param("i", $id_conductor);
    $stmt->execute();
    $result = $stmt->get_result();
    $conductor = $result->fetch_assoc();

    if ($conductor && $conductor['est_con_usu'] == 1) {

        // Insertar el nuevo viaje
        $sqlViaje = "INSERT INTO viaje (id_rut_via, id_usu_via, id_veh, hor_sal_via, val_via, cup_tot) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmtViaje = $conexion->prepare($sqlViaje);
        $stmtViaje->bind_param("iiisii", $id_ruta, $id_conductor, $id_vehiculo, $fecha_salida, $precio, $capacidad);
        $stmtViaje->execute();

        // Cambiar estado del conductor a ocupado (0)
        $sqlUpdate = "UPDATE usuario SET est_con_usu = 0 WHERE id_usu = ?";
        $stmtUpdate = $conexion->prepare($sqlUpdate);
        $stmtUpdate->bind_param("i", $id_conductor);
        $stmtUpdate->execute();

        // Opcional: Cambiar estado del vehículo a ocupado (0)
        $sqlUpdateVeh = "UPDATE vehiculo SET est_veh = 0 WHERE id_veh = ?";
        $stmtUpdateVeh = $conexion->prepare($sqlUpdateVeh);
        $stmtUpdateVeh->bind_param("i", $id_vehiculo);
        $stmtUpdateVeh->execute();

        $conexion->commit();    
        header("Location: viajes.php?status=success");
        exit();
    } else {
        $conexion->rollback();
        $estadoActual = isset($conductor['est_con_usu']) ? $conductor['est_con_usu'] : 'No encontrado';
        echo "Error: El conductor seleccionado no está disponible (su estado actual es: " . $estadoActual . ").";
    }
} catch (Exception $e) {
    if (isset($conexion)) {
        $conexion->rollback();
    }
    echo "Error en el sistema: " . $e->getMessage();
}
?>
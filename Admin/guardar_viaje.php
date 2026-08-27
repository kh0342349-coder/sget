<?php
include '../assets/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso denegado.");
}

// 1. Captura de variables desde el $_POST
$id_conductor = $_POST['id_usu_via'] ?? null;
$id_ruta      = $_POST['id_rut_via'] ?? null;
$id_vehiculo  = $_POST['id_veh_via'] ?? $_POST['id_veh'] ?? null;
$fec_via      = $_POST['fec_via'] ?? null;
$hor_sal_via  = $_POST['hor_sal_via'] ?? null;
$precio       = $_POST['val_via'] ?? null;

// 2. Validación de campos obligatorios
if (!$id_conductor || !$id_ruta || !$id_vehiculo || !$fec_via || !$hor_sal_via || !$precio) {
    die("Error: Faltan datos obligatorios.");
}

try {
    $conexion->begin_transaction();

    // Comprobar si existe el conductor (Acepta 'Disponible' o 1)
    $stmt = $conexion->prepare("SELECT est_con_usu FROM usuario WHERE id_usu = ? AND id_rol_usu = 2 FOR UPDATE");
    $stmt->bind_param("i", $id_conductor);
    $stmt->execute();
    $result = $stmt->get_result();
    $conductor = $result->fetch_assoc();

    if ($conductor) {
        // Insertar el nuevo viaje con est_via = 'Activo' explícitamente y fec_via incluida
        $sqlViaje = "INSERT INTO viaje (id_rut_via, id_usu_via, id_veh, fec_via, hor_sal_via, val_via, est_via) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Activo')";
        
        $stmtViaje = $conexion->prepare($sqlViaje);
        $stmtViaje->bind_param("iiissd", $id_ruta, $id_conductor, $id_vehiculo, $fec_via, $hor_sal_via, $precio);
        $stmtViaje->execute();

        // Cambiar estado del conductor a 'Ocupado' (o 0)
        $sqlUpdate = "UPDATE usuario SET est_con_usu = 'Ocupado' WHERE id_usu = ?";
        $stmtUpdate = $conexion->prepare($sqlUpdate);
        $stmtUpdate->bind_param("i", $id_conductor);
        $stmtUpdate->execute();

        // Cambiar estado del vehículo a 'Inactivo' (o 0)
        $sqlUpdateVeh = "UPDATE vehiculo SET est_veh = 'Inactivo' WHERE id_veh = ?";
        $stmtUpdateVeh = $conexion->prepare($sqlUpdateVeh);
        $stmtUpdateVeh->bind_param("i", $id_vehiculo);
        $stmtUpdateVeh->execute();

        $conexion->commit();    
        header("Location: viajes.php?status=success");
        exit();
    } else {
        $conexion->rollback();
        echo "Error: El conductor seleccionado no fue encontrado.";
    }
} catch (Exception $e) {
    if (isset($conexion)) {
        $conexion->rollback();
    }
    echo "Error en el sistema: " . $e->getMessage();
}
?>
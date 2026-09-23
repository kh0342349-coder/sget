<?php
session_start();
include '../assets/conexion.php'; 

// 1. Verificamos que sea un pasajero (rol 3) y que exista la sesión
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 3 || !isset($_SESSION['id_usu'])) {
    header("Location: ../index.php");
    exit();
}

$id_via = $_GET['id_via'];
$id_usu = $_SESSION['id_usu']; 

// 2. Verificamos si aún hay cupos
$stmt = $conexion->prepare("SELECT cup_dis FROM viaje WHERE id_via = ?");
$stmt->bind_param("i", $id_via);
$stmt->execute();
$viaje = $stmt->get_result()->fetch_assoc();

if ($viaje && $viaje['cup_dis'] > 0) {
    // 3. Descontamos el cupo
    $update = $conexion->prepare("UPDATE viaje SET cup_dis = cup_dis - 1 WHERE id_via = ?");
    $update->bind_param("i", $id_via);
    $update->execute();

    // 4. Insertamos la reserva
    $sql_reserva = $conexion->prepare("INSERT INTO reserva (id_via_res, id_usu_res) VALUES (?, ?)");
    $sql_reserva->bind_param("ii", $id_via, $id_usu);
    
    if ($sql_reserva->execute()) {
        header("Location: viajes_pasajero.php?mensaje=reserva_exitosa");
    } else {
        echo "Error al registrar la reserva: " . $conexion->error;
    }
} else {
    echo "Lo sentimos, ya no quedan cupos para este viaje.";
    echo "<br><a href='viajes_pasajero.php'>Volver</a>";
}
?>
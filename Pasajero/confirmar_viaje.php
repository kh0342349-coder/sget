<?php
Date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

if (!isset($_SESSION['documento'])) {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id_rut'])) {
    $id_rut = $_GET['id_rut'];
    $documento = $_SESSION['documento'];

    $user_q = $conexion->query("SELECT id_usu FROM usuario WHERE num_doc_usu = '$documento'");
    $userData = $user_q->fetch_assoc();
    $id_pasajero = $userData['id_usu'];

    $fecha_actual = date("Y-m-d H:i:s");
    
    $sql = "INSERT INTO viaje (fec_via, est_via, id_rut_via, id_usu_via, val_via) 
            SELECT '$fecha_actual', 'Pendiente', id_rut, '$id_pasajero', val_rut 
            FROM rutas WHERE id_rut = '$id_rut'";

    if ($conexion->query($sql)) {
        echo "<script>
            alert('¡Listo, sumercé! Su solicitud quedó registrada. El administrador la revisará pronto.');
            window.location.href = 'mis_viajes_pasajero.php';
        </script>";
    } else {
        echo "Huy parce, hubo un error: " . $conexion->error;
    }
} else {
    header("Location: rutas_pasajero.php");
    exit();
}
?>
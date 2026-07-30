<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $documento = $_SESSION['documento'];
    $query_user = $conexion->query("SELECT id_usu FROM usuario WHERE num_doc_usu = '$documento'");
    $user_data = $query_user->fetch_assoc();
    $id_usu_rem = $user_data['id_usu'];

    $id_via_cal = $_POST['id_via'];     
    $id_usu_des = $_POST['id_cond'];   
    $pun_cal    = $_POST['puntos'];    
    $com_cal    = mysqli_real_escape_string($conexion, $_POST['comentario']); // Comentario seguro

  
    $sql = "INSERT INTO calificacion (id_via_cal, id_usu_rem, id_usu_des, pun_cal, com_cal) 
            VALUES ('$id_via_cal', '$id_usu_rem', '$id_usu_des', '$pun_cal', '$com_cal')";

    if ($conexion->query($sql)) {
       
        header("Location: ../pasajero/pasajero.php?res=ok");
    } else {
      
        header("Location: ../pasajero/pasajero.php?res=error");
    }
} else {
   
    header("Location: ../pasajero/pasajero.php");
}
?>
<?php
// Admin/guardar_usuario.php
include '../assets/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $tip_doc_usu = $_POST['tip_doc_usu'] ?? '';
    $num_doc_usu = $_POST['num_doc_usu'] ?? '';
    $nom_usu     = $_POST['nom_usu'] ?? '';
    $corre_usu   = $_POST['corre_usu'] ?? '';
    $id_rol_usu  = (int)($_POST['id_rol_usu'] ?? 3);
    $clave_usu   = password_hash($_POST['clave_usu'] ?? '', PASSWORD_BCRYPT);
    $estado      = 1; // Activo general

    // Si el nuevo usuario es Conductor (rol 2), se activa su disponibilidad
    $est_con_usu = ($id_rol_usu === 2) ? 1 : 0;

    $stmt = $conexion->prepare("INSERT INTO usuario (num_doc_usu, tip_doc_usu, nom_usu, corre_usu, pass_usu, id_rol_usu, estado, est_con_usu) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssiii", $num_doc_usu, $tip_doc_usu, $nom_usu, $corre_usu, $clave_usu, $id_rol_usu, $estado, $est_con_usu);

    if ($stmt->execute()) {
        header("Location: usuarios.php?msg=creado");
    } else {
        header("Location: usuarios.php?error=error_guardar");
    }
    
    $stmt->close();
    exit();
} else {
    header("Location: usuarios.php");
    exit();
}
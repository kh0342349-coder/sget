<?php
include '../assets/conexion.php';
$accion = $_GET['accion'] ?? '';

if ($accion == 'editar') {
    $id = $_POST['id_rut'];
    $nom = $_POST['nom_rut'];
    $ori = $_POST['ori_rut'];
    $des = $_POST['des_rut'];
    $dis = $_POST['dis_rut'];
    $pre = $_POST['val_rut'];

    $sql = "UPDATE rutas SET nom_rut=?, ori_rut=?, des_rut=?, dis_rut=?, val_rut=? WHERE id_rut=?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ssssdi", $nom, $ori, $des, $dis, $pre, $id);
    
    if ($stmt->execute()) {
        header("Location: rutas.php?status=success");
    } else {
        header("Location: rutas.php?status=error");
    }
    exit();
}
?>
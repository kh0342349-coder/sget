<?php
session_start();
include '../assets/conexion.php';

if (isset($_GET['doc']) && isset($_GET['nuevo_estado'])) {
    $doc = $_GET['doc'];
    $nuevo_estado = $_GET['nuevo_estado'];

    $stmt = $conexion->prepare("UPDATE usuario SET estado = ? WHERE num_doc_usu = ?");
    $stmt->bind_param("is", $nuevo_estado, $doc);

    if ($stmt->execute()) {
        header("Location: usuarios.php?msg=Estado actualizado correctamente");
    } else {
        // Si hay error, volvemos con un mensaje de error
        header("Location: usuarios.php?error=No se pudo actualizar el estado");
    }
    
    $stmt->close();
} else {
    header("Location: usuarios.php");
}

$conexion->close();
?>
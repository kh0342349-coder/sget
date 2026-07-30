<?php
session_start();
include '../assets/conexion.php';

// Validar privilegios y sesión
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = $_GET['id'];

    // Usamos try-catch para capturar el error de la base de datos
    try {
        $stmt = $conexion->prepare("DELETE FROM rutas WHERE id_rut = ?");
        $stmt->bind_param("i", $id); 
        $stmt->execute();
        
        // Si no hubo error, redirigimos como éxito
        header("Location: rutas.php?status=deleted");
    } catch (mysqli_sql_exception $e) {
        // Aquí capturamos el error de "Foreign Key Constraint"
        // Redirigimos a rutas.php con un estado de error específico
        header("Location: rutas.php?status=error_fk");
    }
    
    $stmt->close();
} else {
    header("Location: rutas.php");
}
exit();
?>
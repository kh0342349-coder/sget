<?php
session_start();
include 'assets/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom   = $_POST['nombre'];
    $tip   = $_POST['tipo_doc'];
    $num   = $_POST['doc'];
    $cor   = $_POST['email'];
    $pass  = $_POST['pass'];
    $pass2 = $_POST['pass2']; // Capturamos la segunda contraseña

    // 1. Validar que las contraseñas coincidan
    if ($pass !== $pass2) {
        $_SESSION['msg'] = "Las contraseñas no coinciden.";
        header("Location: registrar.php");
        exit();
    }

    // 2. Validar si el usuario ya existe
    $stmt_check = $conexion->prepare("SELECT num_doc_usu FROM usuario WHERE num_doc_usu = ?");
    $stmt_check->bind_param("i", $num);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        $_SESSION['msg'] = "El número de documento ya está registrado.";
        header("Location: registrar.php");
        exit();
    }

    // 3. Todo OK -> Encriptar e Insertar
    $password_hash = password_hash($pass, PASSWORD_DEFAULT);
    $id_rol = 3; 

    $stmt = $conexion->prepare("INSERT INTO usuario (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, pass_usu, id_rol_usu) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssi", $tip, $num, $nom, $cor, $password_hash, $id_rol);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "¡Registro exitoso! Ya puedes iniciar sesión.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['msg'] = "Error interno al guardar los datos.";
        header("Location: registrar.php");
        exit();
    }
} else {
    header("Location: registrar.php");
    exit();
}
?>
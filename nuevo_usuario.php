<?php
session_start();
include 'assets/conexion.php';

// Validar que la petición venga por el método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Captura y limpieza de variables
    $tip_doc    = trim($_POST['tip_doc_usu'] ?? '');
    $num_doc    = trim($_POST['num_doc_usu'] ?? '');
    $nombre     = trim($_POST['nom_usu'] ?? '');
    $correo     = trim($_POST['corre_usu'] ?? '');
    $clave      = $_POST['clave_usu'] ?? '';
    $conf_clave = $_POST['confirmar_clave_usu'] ?? '';

    // 1. Validar que no haya campos vacíos
    if (empty($tip_doc) || empty($num_doc) || empty($nombre) || empty($correo) || empty($clave) || empty($conf_clave)) {
        $_SESSION['msg_registro'] = "Por favor completa todos los campos del formulario.";
        $_SESSION['msg_registro_abrir'] = true;
        header('Location: index.php');
        exit();
    }

    // 2. Validar que las contraseñas coincidan
    if ($clave !== $conf_clave) {
        $_SESSION['msg_registro'] = "Las contraseñas ingresadas no coinciden.";
        $_SESSION['msg_registro_abrir'] = true;
        header('Location: index.php');
        exit();
    }

    // 3. VERIFICAR SI EL DOCUMENTO O CORREO YA EXISTEN EN LA BASE DE DATOS
    $stmt_check = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ? OR corre_usu = ?");
    $stmt_check->bind_param("ss", $num_doc, $correo);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // Coincidencia encontrada: rebota y abre el panel de registro con el mensaje de error
        $_SESSION['msg_registro'] = "El número de documento o correo ya se encuentra registrado.";
        $_SESSION['msg_registro_abrir'] = true;
        $stmt_check->close();
        header('Location: index.php');
        exit();
    }
    $stmt_check->close();

    // 4. Encriptar contraseña y preparar valores por defecto
    // Rol por defecto: 3 (Pasajero/Usuario estándar) | Estado: 1 (Activo)
    $hash_clave = password_hash($clave, PASSWORD_DEFAULT);
    $id_rol     = 3; 
    $estado     = 1;

    // 5. Insertar el nuevo usuario en la base de datos
    $stmt_insert = $conexion->prepare("INSERT INTO usuario (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, pass_usu, id_rol_usu, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert->bind_param("sssssii", $tip_doc, $num_doc, $nombre, $correo, $hash_clave, $id_rol, $estado);

    if ($stmt_insert->execute()) {
        // REGISTRO EXITOSO: Redirige, abre el panel de LOGIN y muestra el mensaje verde
        $_SESSION['msg_success_login'] = "¡Registro exitoso! Ya puedes iniciar sesión con tus credenciales.";
        $_SESSION['abrir_login'] = true;
        $stmt_insert->close();
        header('Location: index.php');
        exit();
    } else {
        // Error de inserción en MySQL
        $_SESSION['msg_registro'] = "Error del sistema al registrar el usuario. Inténtalo de nuevo.";
        $_SESSION['msg_registro_abrir'] = true;
        $stmt_insert->close();
        header('Location: index.php');
        exit();
    }

} else {
    // Si intentan ingresar directo por la URL sin enviar el formulario
    header('Location: index.php');
    exit();
}
?>
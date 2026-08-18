<?php
session_start();
include 'assets/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Nombres alineados con los inputs del formulario en modal_auth.php
    $tip_doc    = trim($_POST['tipo_doc'] ?? '');
    $num_doc    = trim($_POST['documento'] ?? '');
    $nombre     = trim($_POST['nom_usu'] ?? '');
    $correo     = trim($_POST['corre_usu'] ?? '');
    $clave      = $_POST['clave_usu'] ?? '';
    $conf_clave = $_POST['confirmar_clave'] ?? '';

    // 1. Validar campos vacíos
    if (empty($tip_doc) || empty($num_doc) || empty($nombre) || empty($correo) || empty($clave) || empty($conf_clave)) {
        $_SESSION['msg_registro'] = "Por favor completa todos los campos del formulario.";
        $_SESSION['msg_registro_abrir'] = true;
        header('Location: index.php');
        exit();
    }

    // 2. Validar coincidencia de contraseñas
    if ($clave !== $conf_clave) {
        $_SESSION['msg_registro'] = "Las contraseñas ingresadas no coinciden.";
        $_SESSION['msg_registro_abrir'] = true;
        header('Location: index.php');
        exit();
    }

    // 3. Verificar si el documento o correo ya existen
    $stmt_check = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ? OR corre_usu = ?");
    $stmt_check->bind_param("ss", $num_doc, $correo);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $_SESSION['msg_registro'] = "El número de documento o correo ya se encuentra registrado.";
        $_SESSION['msg_registro_abrir'] = true;
        $stmt_check->close();
        header('Location: index.php');
        exit();
    }
    $stmt_check->close();

    // 4. Hash de contraseña y valores por defecto
    $hash_clave = password_hash($clave, PASSWORD_DEFAULT);
    $id_rol     = 3; // 3 = Pasajero
    $estado     = 1; // 1 = Activo

    // 5. Insertar usuario
    $stmt_insert = $conexion->prepare("INSERT INTO usuario (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, pass_usu, id_rol_usu, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert->bind_param("sssssii", $tip_doc, $num_doc, $nombre, $correo, $hash_clave, $id_rol, $estado);

    if ($stmt_insert->execute()) {
        // ÉXITO: envía mensaje exitoso y abre directamente el modal de LOGIN
        $_SESSION['msg_success_login'] = "¡Cuenta creada exitosamente! Ya puedes ingresar con tu documento.";
        $_SESSION['abrir_login'] = true;
        $stmt_insert->close();
        header('Location: index.php');
        exit();
    } else {
        $_SESSION['msg_registro'] = "Error interno al registrar la cuenta. Inténtalo de nuevo.";
        $_SESSION['msg_registro_abrir'] = true;
        $stmt_insert->close();
        header('Location: index.php');
        exit();
    }

} else {
    header('Location: index.php');
    exit();
}
?>
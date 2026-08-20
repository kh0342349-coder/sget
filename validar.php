<?php
session_start();
include 'assets/conexion.php';

// 1. Validar presencia del token de reCAPTCHA
if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
    $_SESSION['msg'] = "Por favor complete la verificación reCAPTCHA.";
    $_SESSION['abrir_login'] = true;
    header('Location: index.php');
    exit();
}

$recaptcha_response = $_POST['g-recaptcha-response'];
// CLAVE SECRETA DE PRUEBA OFICIAL PARA LOCALHOST
$secret_key = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'; 

// 2. Comprobar con la API de Google
$api_url = "https://www.google.com/recaptcha/api/siteverify?secret=" . $secret_key . "&response=" . $recaptcha_response;
$verify_response = @file_get_contents($api_url);
$response_data = json_decode($verify_response);

if (!$response_data || !$response_data->success) {
    $_SESSION['msg'] = "Error en la verificación de seguridad.";
    $_SESSION['abrir_login'] = true;
    header('Location: index.php');
    exit();
}

// 3. Procesar autenticación normal de usuario
$doc = trim($_POST['documento'] ?? '');
$pass = trim($_POST['clave'] ?? '');

if (empty($doc) || empty($pass)) {
    $_SESSION['msg'] = "Por favor llene todos los campos.";
    $_SESSION['abrir_login'] = true;
    header('Location: index.php');
    exit();
}

$stmt = $conexion->prepare("SELECT id_usu, num_doc_usu, nom_usu, pass_usu, id_rol_usu, estado FROM usuario WHERE num_doc_usu = ?");
$stmt->bind_param("s", $doc);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data_user = $result->fetch_assoc();
    $hash = $data_user['pass_usu'];
    $estado = $data_user['estado'];

    if (password_verify($pass, $hash)) {
        if ($estado == 0) {
            $_SESSION['msg'] = "Su cuenta está desactivada. Contacte al administrador.";
            $_SESSION['abrir_login'] = true;
            $stmt->close();
            header('Location: index.php');
            exit();
        }

        session_regenerate_id(true);

        $_SESSION['id_usu'] = $data_user['id_usu'];
        $_SESSION['documento'] = $data_user['num_doc_usu'];
        $_SESSION['nombre_usuario'] = $data_user['nom_usu'];
        $_SESSION['rol'] = $data_user['id_rol_usu'];

        $rol = $data_user['id_rol_usu'];
        $stmt->close();

        switch ($rol) {
            case 1: header('Location: Admin/admin.php'); break;
            case 2: header('Location: Conductor/conductor.php'); break;
            case 3: header('Location: Pasajero/pasajero.php'); break;
            default: header('Location: index.php'); break;
        }
        exit();
    } else {
        $_SESSION['msg'] = "Error al ingresar la contraseña del usuario";
        $_SESSION['abrir_login'] = true;
        $stmt->close();
        header('Location: index.php');
        exit();
    }
} else {
    $_SESSION['msg'] = "El usuario no está registrado";
    $_SESSION['abrir_login'] = true;
    $stmt->close();
    header('Location: index.php');
    exit();
}
?>
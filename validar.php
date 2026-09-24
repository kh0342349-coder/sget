<?php
// Configurar las directivas de seguridad para la cookie de sesión antes de iniciarla
session_set_cookie_params([
    'lifetime' => 0,         // Persiste durante la sesión activa del navegador
    'path'     => '/',
    'domain'   => '',        // Asigna automáticamente el dominio/host actual
    'secure'   => true,      // Transmisión exclusiva mediante HTTPS
    'httponly' => true,      // Inaccesible mediante JS/document.cookie (Anti-XSS)
    'samesite' => 'Lax'      // Protección contra ataques CSRF
]);

session_start();
include 'assets/conexion.php';
require_once 'helpers/Logger.php'; // Helper de auditoría

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

// Consultar usuario en la base de datos
$stmt = $conexion->prepare("SELECT id_usu, num_doc_usu, nom_usu, pass_usu, id_rol_usu, estado FROM usuario WHERE num_doc_usu = ?");
$stmt->bind_param("s", $doc);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data_user = $result->fetch_assoc();
    $hash = $data_user['pass_usu'];
    $estado = $data_user['estado'];

    // Verificar si la clave coincide (soporta contraseñas hash de PHP y texto plano)
    $es_valida = false;

    if (password_verify($pass, $hash)) {
        $es_valida = true;
    } elseif ($pass === $hash) {
        // Si la clave está en texto plano en la BD, la valida y actualiza a Hash seguro
        $es_valida = true;
        $nuevo_hash = password_hash($pass, PASSWORD_DEFAULT);
        $update_stmt = $conexion->prepare("UPDATE usuario SET pass_usu = ? WHERE id_usu = ?");
        $update_stmt->bind_param("si", $nuevo_hash, $data_user['id_usu']);
        $update_stmt->execute();
        $update_stmt->close();
    }

    if ($es_valida) {
        if ($estado == 0) {
            // REGISTRO EN AUDITORÍA: Intento de ingreso en cuenta inactiva
            Logger::registrar(
                $conexion, 
                'LOGIN_BLOQUEADO', 
                "Intento de inicio de sesión en cuenta desactivada (Doc: {$doc}).", 
                $data_user['id_usu'], 
                $data_user['nom_usu']
            );

            $_SESSION['msg'] = "Su cuenta está desactivada. Contacte al administrador.";
            $_SESSION['abrir_login'] = true;
            $stmt->close();
            header('Location: index.php');
            exit();
        }

        session_regenerate_id(true);

        $_SESSION['id_usu']         = $data_user['id_usu'];
        $_SESSION['documento']      = $data_user['num_doc_usu'];
        $_SESSION['nombre_usuario'] = $data_user['nom_usu'];
        $_SESSION['nom_usu']        = $data_user['nom_usu'];
        $_SESSION['rol']            = $data_user['id_rol_usu'];

        // Determinar nombre del rol para guardar en la auditoría
        $nombre_rol = match((int)$data_user['id_rol_usu']) {
            1 => 'Administrador',
            2 => 'Conductor',
            3 => 'Pasajero',
            default => 'Sin Rol'
        };
        $_SESSION['nom_rol'] = $nombre_rol;

        $_SESSION['restricciones'] = '';

        $rol = $data_user['id_rol_usu'];
        $stmt->close();

        // REGISTRO EN AUDITORÍA: Inicio de sesión exitoso
        Logger::registrar(
            $conexion, 
            'LOGIN', 
            "El usuario '{$data_user['nom_usu']}' (Doc: {$doc}) inició sesión exitosamente.",
            $data_user['id_usu'],
            $data_user['nom_usu'],
            $nombre_rol
        );

        switch ($rol) {
            case 1: header('Location: Admin/admin.php'); break;
            case 2: header('Location: Conductor/conductor.php'); break;
            case 3: header('Location: Pasajero/pasajero.php'); break;
            default: header('Location: index.php'); break;
        }
        exit();
    } else {
        // REGISTRO EN AUDITORÍA: Clave incorrecta
        Logger::registrar(
            $conexion, 
            'LOGIN_FALLIDO', 
            "Contraseña errónea para el usuario con documento: {$doc}."
        );

        $_SESSION['msg'] = "Error al ingresar la contraseña del usuario";
        $_SESSION['abrir_login'] = true;
        $stmt->close();
        header('Location: index.php');
        exit();
    }
} else {
    // REGISTRO EN AUDITORÍA: Usuario no existente
    Logger::registrar(
        $conexion, 
        'LOGIN_FALLIDO', 
        "Intento de ingreso con documento no registrado: {$doc}."
    );

    $_SESSION['msg'] = "El usuario no está registrado";
    $_SESSION['abrir_login'] = true;
    $stmt->close();
    header('Location: index.php');
    exit();
}
?>
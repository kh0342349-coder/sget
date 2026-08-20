<?php
session_start();
header('Content-Type: application/json');

require_once '../assets/conexion.php'; 

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? null;

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token no recibido.']);
    exit;
}

$google_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
$response = @file_get_contents($google_url);

if ($response === FALSE) {
    echo json_encode(['success' => false, 'message' => 'Token de Google no válido o expirado.']);
    exit;
}

$payload = json_decode($response, true);

if (isset($payload['email'])) {
    $email     = mysqli_real_escape_string($conexion, $payload['email']);
    $nombre    = mysqli_real_escape_string($conexion, $payload['name'] ?? 'Usuario Google');
    $google_id = mysqli_real_escape_string($conexion, $payload['sub']);

    $sql_check = "SELECT * FROM usuario WHERE corre_usu = '$email' OR google_id = '$google_id'";
    $res_check = mysqli_query($conexion, $sql_check);

    if ($res_check && mysqli_num_rows($res_check) > 0) {
        $usuario = mysqli_fetch_assoc($res_check);
        $id_usuario = $usuario['id_usu'];
        $id_rol     = (int)$usuario['id_rol_usu'];
        $nombre_db  = $usuario['nom_usu'];

        if (empty($usuario['google_id'])) {
            mysqli_query($conexion, "UPDATE usuario SET google_id = '$google_id' WHERE id_usu = $id_usuario");
        }
    } else {
        $id_rol_defecto = 3;
        $sql_insert = "INSERT INTO usuario (nom_usu, corre_usu, id_rol_usu, estado, google_id) 
                       VALUES ('$nombre', '$email', $id_rol_defecto, 1, '$google_id')";
        
        if (mysqli_query($conexion, $sql_insert)) {
            $id_usuario = mysqli_insert_id($conexion);
            $id_rol     = $id_rol_defecto;
            $nombre_db  = $nombre;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar usuario: ' . mysqli_error($conexion)]);
            exit;
        }
    }

    $_SESSION['id_usu']     = $id_usuario;
    $_SESSION['nom_usu']    = $nombre_db;
    $_SESSION['corre_usu']  = $email;
    $_SESSION['id_rol_usu'] = $id_rol;

    switch ($id_rol) {
        case 1: $redirect = 'Admin/admin.php'; break;
        case 2: $redirect = 'Conductor/conductor.php'; break;
        case 3: default: $redirect = 'Pasajero/pasajero.php'; break;
    }

    echo json_encode(['success' => true, 'redirect' => $redirect, 'message' => 'Sesión iniciada correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Respuesta no válida de Google.']);
}
?>
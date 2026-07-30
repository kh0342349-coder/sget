<?php
session_start();
include 'assets/conexion.php';

// 1. Validar que la petición venga por POST y con las variables requeridas
if (!isset($_POST['documento'], $_POST['clave'])) {
    $_SESSION['msg'] = "Por favor ingrese todos los campos requeridos.";
    $_SESSION['abrir_login'] = true; // <-- Para que JS abra el panel de login
    header('Location: index.php');
    exit();
}

$doc = trim($_POST['documento']);
$pass = trim($_POST['clave']);

if (empty($doc) || empty($pass)) {
    $_SESSION['msg'] = "Por favor llene todos los campos.";
    $_SESSION['abrir_login'] = true; // <-- Para que JS abra el panel de login
    header('Location: index.php');
    exit();
}

// 2. Consulta con Stored Procedure / Prepared Statement
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
            $_SESSION['abrir_login'] = true; // <-- Para que JS abra el panel de login
            $stmt->close();
            header('Location: index.php');
            exit();
        }

        // 3. Prevenir fijación de sesión regenerando el ID
        session_regenerate_id(true);

        // Guardamos los datos en la sesión
        $_SESSION['id_usu'] = $data_user['id_usu']; 
        $_SESSION['documento'] = $data_user['num_doc_usu'];
        $_SESSION['nombre_usuario'] = $data_user['nom_usu']; 
        $_SESSION['rol'] = $data_user['id_rol_usu'];

        $rol = $data_user['id_rol_usu'];
        $stmt->close();

        // Redirección según rol
        switch ($rol) {
            case 1:
                header('Location: Admin/admin.php');
                break;
            case 2:
                header('Location: Conductor/conductor.php');
                break;
            case 3:
                header('Location: Pasajero/pasajero.php');
                break;
            default:
                header('Location: index.php');
                break;
        }
        exit(); 
    } else {
        $_SESSION['msg'] = "Error al ingresar la contraseña del usuario";
        $_SESSION['abrir_login'] = true; // <-- Para que JS abra el panel de login
        $stmt->close();
        header('Location: index.php');
        exit();
    }
} else {
    $_SESSION['msg'] = "El usuario no está registrado";
    $_SESSION['abrir_login'] = true; // <-- Para que JS abra el panel de login
    $stmt->close();
    header('Location: index.php');
    exit();
}
?>
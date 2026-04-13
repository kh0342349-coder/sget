<?php
session_start();
include 'assets/conexion.php';

$doc = $_POST['doc'];
$pass = $_POST['pass'];
$stmt = $conexion->prepare("SELECT num_doc_usu, nom_usu, pass_usu, id_rol_usu, estado FROM usuario WHERE num_doc_usu = ?");
$stmt->bind_param("i", $doc);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0)
{
    $data_user = $result->fetch_assoc();
    $hash = $data_user['pass_usu'];
    $estado = $data_user['estado'];

    if(password_verify($pass, $hash))
    {
        if($estado == 0) {
            $_SESSION['msg'] = "Su cuenta está desactivada. Contacte al administrador.";
            header('Location: index.php');
            exit();
        }

        $_SESSION['documento'] = $data_user['num_doc_usu'];
        $_SESSION['nombre_usuario'] = $data_user['nom_usu']; 
        $_SESSION['rol'] = $data_user['id_rol_usu'];

        $rol = $data_user['id_rol_usu'];
        switch($rol)
        {
            case 1: header('Location: Admin/admin.php'); break;
            case 2: header('Location: Conductor/conductor.php'); break;
            case 3: header('Location: Pasajero/pasajero.php'); break;
        }
        exit(); 
    }
    else
    {
        $_SESSION['msg'] = "Error al ingresar la contraseña del usuario";
        header('Location: index.php');
        exit();
    }
}
else
{
    $_SESSION['msg'] = "El usuario no esta registrado";
    header('Location: registrar.php');
    exit();
}
?>
<?php
// Archivo: Admin/procesar_usuario.php
session_start();
include '../assets/conexion.php';

// Verificación de seguridad (Solo Admin)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tip_doc_usu = $_POST['tip_doc_usu'];
    $num_doc_usu = $_POST['num_doc_usu'];
    $nom_usu = $_POST['nom_usu'];
    $corre_usu = $_POST['corre_usu'];
    $id_rol_usu = intval($_POST['id_rol_usu']);
    $contra_usu = $_POST['contra_usu'];

    // Si el rol es Conductor (2), establecemos est_con_usu en 1 (Disponible), de lo contrario NULL
    $est_con_usu = ($id_rol_usu == 2) ? 1 : NULL;

    // Verificamos si el usuario ya existe en la base de datos por su número de documento
    $check = $conexion->prepare("SELECT num_doc_usu FROM usuario WHERE num_doc_usu = ?");
    $check->bind_param("s", $num_doc_usu);
    $check->execute();
    $resultado = $check->get_result();

    if ($resultado->num_rows > 0) {
        // --- ACTUALIZAR USUARIO EXISTENTE ---
        if (!empty($contra_usu)) {
            // Si se ingresó una contraseña nueva, actualizamos 'pass_usu' y también verificamos el estado del conductor
            $stmt = $conexion->prepare("UPDATE usuario SET tip_doc_usu = ?, nom_usu = ?, corre_usu = ?, id_rol_usu = ?, pass_usu = ?, est_con_usu = COALESCE(?, est_con_usu) WHERE num_doc_usu = ?");
            // Nota: Usamos COALESCE para que si pasa a conductor y estaba en NULL, se ponga en 1, o puedes forzarlo directamente:
            // Si prefieres que siempre que se edite a conductor se ponga disponible, actualiza la consulta abajo:
            $stmt = $conexion->prepare("UPDATE usuario SET tip_doc_usu = ?, nom_usu = ?, corre_usu = ?, id_rol_usu = ?, pass_usu = ?, est_con_usu = CASE WHEN ? = 2 THEN IFNULL(est_con_usu, 1) ELSE est_con_usu END WHERE num_doc_usu = ?");
            $stmt->bind_param("sssissi", $tip_doc_usu, $nom_usu, $corre_usu, $id_rol_usu, $contra_usu, $id_rol_usu, $num_doc_usu);
        } else {
            // Si la contraseña está en blanco, mantenemos la contraseña actual
            $stmt = $conexion->prepare("UPDATE usuario SET tip_doc_usu = ?, nom_usu = ?, corre_usu = ?, id_rol_usu = ?, est_con_usu = CASE WHEN ? = 2 THEN IFNULL(est_con_usu, 1) ELSE est_con_usu END WHERE num_doc_usu = ?");
            $stmt->bind_param("sssisi", $tip_doc_usu, $nom_usu, $corre_usu, $id_rol_usu, $id_rol_usu, $num_doc_usu);
        }
        
        if ($stmt->execute()) {
            header("Location: usuarios.php?status=success");
        } else {
            header("Location: usuarios.php?status=error");
        }
        exit();

    } else {
        // --- CREAR NUEVO USUARIO ---
        $password_a_guardar = !empty($contra_usu) ? $contra_usu : '123456';
        
        // Si es conductor, $est_con_usu será 1 (Disponible), de lo contrario NULL
        $stmt = $conexion->prepare("INSERT INTO usuario (num_doc_usu, tip_doc_usu, nom_usu, corre_usu, id_rol_usu, pass_usu, estado, est_con_usu) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
        $stmt->bind_param("ssssisi", $num_doc_usu, $tip_doc_usu, $nom_usu, $corre_usu, $id_rol_usu, $password_a_guardar, $est_con_usu);
        
        if ($stmt->execute()) {
            header("Location: usuarios.php?status=success");
        } else {
            header("Location: usuarios.php?status=error");
        }
        exit();
    }
}

header("Location: usuarios.php");
exit();
?>
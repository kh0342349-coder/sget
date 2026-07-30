<?php
session_start();
include '../assets/conexion.php';

// --- Seguridad: solo administradores autenticados pueden editar usuarios ---
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: gestion_usuarios.php");
    exit();
}

// --- Nombres de campo alineados EXACTAMENTE con el <form> de edición ---
$doc_original = trim($_POST['num_doc_usu'] ?? '');
$tip_doc      = trim($_POST['tip_doc_usu'] ?? '');
$nombre       = trim($_POST['nom_usu'] ?? '');
$correo       = trim($_POST['corre_usu'] ?? '');
$rol          = $_POST['id_rol_usu'] ?? '';

// --- Validaciones básicas ---
if ($doc_original === '' || $nombre === '' || $correo === '' || $rol === '') {
    header("Location: gestion_usuarios.php?error=campos_invalidos");
    exit();
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    header("Location: gestion_usuarios.php?error=correo_invalido");
    exit();
}

if (!in_array($rol, ['1', '2', '3'], true)) {
    header("Location: gestion_usuarios.php?error=rol_invalido");
    exit();
}

// --- Regla de negocio: un admin no puede quitarse su propio rol de administrador ---
if ($doc_original === $_SESSION['documento'] && $rol != 1) {
    header("Location: usuarios.php?error=mismo_usuario");
    exit();
}

// --- Determinación de la disponibilidad del conductor ---
// Si el rol pasa a Conductor ('2'), est_con_usu debe ser 1 (disponible).
// Para Administrador ('1') o Pasajero ('3'), est_con_usu se ajusta a 0.
$est_con = ($rol === '2') ? 1 : 0;

// --- Update con sentencia preparada (incluye est_con_usu) ---
$sql = "UPDATE usuario 
        SET tip_doc_usu = ?, nom_usu = ?, corre_usu = ?, id_rol_usu = ?, est_con_usu = ? 
        WHERE num_doc_usu = ?";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    error_log("Error al preparar UPDATE usuario: " . $conexion->error);
    header("Location: usuarios.php?error=servidor");
    exit();
}

// "ssssis": 4 strings (tip_doc, nombre, correo, rol), 1 entero (est_con_usu), 1 string (doc_original)
$stmt->bind_param("ssssis", $tip_doc, $nombre, $correo, $rol, $est_con, $doc_original);

if ($stmt->execute()) {
    if ($stmt->affected_rows === 0) {
        // Verificamos si el usuario existe para diferenciar si no había cambios o si no existe
        $check = $conexion->prepare("SELECT 1 FROM usuario WHERE num_doc_usu = ?");
        $check->bind_param("s", $doc_original);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            header("Location: usuarios.php?error=no_encontrado");
        } else {
            // Usuario existe, simplemente no había cambios que guardar
            header("Location: usuarios.php?msg=actualizado");
        }
        $check->close();
    } else {
        header("Location: usuarios.php?msg=actualizado");
    }
} else {
    error_log("Error al actualizar usuario: " . $stmt->error);
    header("Location: usuarios.php?error=servidor");
}

$stmt->close();
$conexion->close();
exit();
?>
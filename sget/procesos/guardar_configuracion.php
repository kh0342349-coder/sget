<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../assets/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idUsuario = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0;
    $nombreForm = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $idioma = $_POST['idioma'] ?? 'es';

    // Guardar el idioma seleccionado en la sesión para que aplique de forma global
    $_SESSION['sget_idioma'] = $idioma;

    // Manejo de la foto de perfil si se subió una nueva
    $fotoPerfilNombre = null;
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto_perfil']['tmp_name'];
        $fileName = $_FILES['foto_perfil']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'user_' . $idUsuario . '_' . time() . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/../uploads/';
            
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $fotoPerfilNombre = $newFileName;
            }
        }
    }

    // Nombres exactos de las columnas según tu esquema: nom_usu, corre_usu, tel_usu
    if ($fotoPerfilNombre) {
        $stmt = $conexion->prepare("UPDATE usuario SET nom_usu = ?, corre_usu = ?, tel_usu = ?, foto = ? WHERE id_usu = ?");
        $stmt->bind_param("ssssi", $nombreForm, $correo, $telefono, $fotoPerfilNombre, $idUsuario);
    } else {
        $stmt = $conexion->prepare("UPDATE usuario SET nom_usu = ?, corre_usu = ?, tel_usu = ? WHERE id_usu = ?");
        $stmt->bind_param("sssi", $nombreForm, $correo, $telefono, $idUsuario);
    }

    if ($stmt->execute()) {
        // Actualizar variables de sesión para reflejar los cambios al instante
        $_SESSION['nombre_usuario'] = $nombreForm;
        $_SESSION['correo'] = $correo;
        $_SESSION['telefono'] = $telefono;
        if ($fotoPerfilNombre) {
            $_SESSION['foto'] = $fotoPerfilNombre;
        }
    }
    $stmt->close();

    // Redireccionar de vuelta con éxito y el idioma seleccionado
    header("Location: " . $_SERVER['HTTP_REFERER'] . "?config=success&lang=" . $idioma);
    exit();
}
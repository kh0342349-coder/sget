<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

// Verificación de seguridad (Solo Admin)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_rut          = isset($_POST['id_rut']) ? trim($_POST['id_rut']) : '';
    $nom_rut         = isset($_POST['nom_rut']) ? trim($_POST['nom_rut']) : '';
    $ori_rut         = isset($_POST['ori_rut']) ? trim($_POST['ori_rut']) : '';
    $des_rut         = isset($_POST['des_rut']) ? trim($_POST['des_rut']) : '';
    $dis_rut         = isset($_POST['dis_rut']) ? floatval($_POST['dis_rut']) : 0;
    $val_rut         = isset($_POST['val_rut']) ? floatval($_POST['val_rut']) : 0;
    $eliminar_imagen = isset($_POST['eliminar_imagen']) ? $_POST['eliminar_imagen'] : 0;

    $nombre_imagen = null;

    // Obtener la imagen actual en caso de edición
    if (!empty($id_rut)) {
        $stmt_img = $conexion->prepare("SELECT img_rut FROM rutas WHERE id_rut = ?");
        $stmt_img->bind_param("s", $id_rut);
        $stmt_img->execute();
        $res_img = $stmt_img->get_result();
        if ($row_img = $res_img->fetch_assoc()) {
            $nombre_imagen = $row_img['img_rut'];
        }
        $stmt_img->close();
    }

    // Ruta física del servidor (Desde Admin/ sube un nivel hacia img/rutas/)
    $directorio_destino = "../img/rutas/";

    // Verificar que la carpeta exista, si no la crea automáticamente
    if (!file_exists($directorio_destino)) {
        mkdir($directorio_destino, 0777, true);
    }

    // 1. Eliminar imagen si se solicitó en el formulario
    if ($eliminar_imagen == 1 && !empty($nombre_imagen)) {
        if (file_exists($directorio_destino . $nombre_imagen)) {
            unlink($directorio_destino . $nombre_imagen);
        }
        $nombre_imagen = null;
    }

    // 2. Procesar subida de nueva imagen
    if (isset($_FILES['img_rut']) && $_FILES['img_rut']['error'] == UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['img_rut']['tmp_name'];
        $file_name = $_FILES['img_rut']['name'];
        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($ext, $extensiones_permitidas)) {
            // Eliminar imagen anterior al reemplazar
            if (!empty($nombre_imagen) && file_exists($directorio_destino . $nombre_imagen)) {
                unlink($directorio_destino . $nombre_imagen);
            }

            // Nombre único estandarizado como se observa en tu VS Code
            $nuevo_nombre = "ruta_" . uniqid() . "." . $ext;
            $ruta_final   = $directorio_destino . $nuevo_nombre;

            if (move_uploaded_file($file_tmp, $ruta_final)) {
                $nombre_imagen = $nuevo_nombre;
            }
        }
    }

    // 3. Guardar en Base de Datos
    if (!empty($id_rut)) {
        // MODO EDICIÓN
        $stmt = $conexion->prepare("UPDATE rutas SET 
                    nom_rut = ?, 
                    ori_rut = ?, 
                    des_rut = ?, 
                    dis_rut = ?, 
                    val_rut = ?, 
                    img_rut = ? 
                WHERE id_rut = ?");
        $stmt->bind_param("sssddss", $nom_rut, $ori_rut, $des_rut, $dis_rut, $val_rut, $nombre_imagen, $id_rut);
    } else {
        // MODO INSERCIÓN
        $stmt = $conexion->prepare("INSERT INTO rutas (nom_rut, ori_rut, des_rut, dis_rut, val_rut, img_rut) 
                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdds", $nom_rut, $ori_rut, $des_rut, $dis_rut, $val_rut, $nombre_imagen);
    }

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: rutas.php?status=success");
        exit();
    } else {
        $stmt->close();
        header("Location: rutas.php?status=error");
        exit();
    }
} else {
    header("Location: rutas.php");
    exit();
}
?>
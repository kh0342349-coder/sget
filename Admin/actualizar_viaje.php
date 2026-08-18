<?php
// 1. Incluir la conexión con la ruta correcta
require_once '../assets/conexion.php'; 

// 2. Validar que la petición se envíe por método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 3. Sanitizar y capturar los parámetros enviados por el modal
    $id_via      = isset($_POST['id_via']) ? intval($_POST['id_via']) : 0;
    $id_rut_via  = isset($_POST['id_rut_via']) ? intval($_POST['id_rut_via']) : 0;
    $id_usu_via  = isset($_POST['id_usu_via']) ? intval($_POST['id_usu_via']) : 0;
    $id_veh      = isset($_POST['id_veh_via']) ? intval($_POST['id_veh_via']) : 0;
    $fec_via     = isset($_POST['fec_via']) ? mysqli_real_escape_string($conexion, $_POST['fec_via']) : '';
    $hor_sal_via = isset($_POST['hor_sal_via']) ? mysqli_real_escape_string($conexion, $_POST['hor_sal_via']) : '';
    $val_via     = isset($_POST['val_via']) ? floatval($_POST['val_via']) : 0;

    // Obtener la página desde la que se mandó el formulario para la redirección segura
    $pagina_origen = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    
    // Limpiar parámetros query antiguos si ya existían en la URL
    $url_base = strtok($pagina_origen, '?');

    if ($id_via > 0) {
        
        // 4. Sentencia SQL optimizada para la tabla 'viaje' (Incluyendo est_via = 'Activo')
        $sql = "UPDATE viaje 
                SET id_rut_via  = $id_rut_via,
                    id_usu_via  = $id_usu_via,
                    id_veh      = $id_veh,
                    fec_via     = '$fec_via',
                    hor_sal_via = '$hor_sal_via',
                    val_via     = $val_via,
                    est_via     = 'Activo'
                WHERE id_via    = $id_via";

        // 5. Ejecutar actualización
        if (mysqli_query($conexion, $sql)) {
            // Redirigir a la vista original activando la alerta de éxito
            header("Location: " . $url_base . "?status=success"); 
            exit();
        } else {
            // Si falla la BD, redirigir con el flag de error
            header("Location: " . $url_base . "?status=error");
            exit();
        }

    } else {
        header("Location: " . $url_base . "?status=error");
        exit();
    }

} else {
    // Protección si acceden directamente por la barra de direcciones
    header("Location: ../Admin/");
    exit();
}
?>
<?php
// 1. Incluir la conexión a la base de datos
require_once '../assets/conexion.php'; 

// 2. Validar que la petición se realice por el método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 3. Capturar y sanitizar todos los datos recibidos desde el formulario
    $id_veh  = isset($_POST['id_veh']) ? intval($_POST['id_veh']) : 0;
    $pla_veh = isset($_POST['pla_veh']) ? mysqli_real_escape_string($conexion, trim($_POST['pla_veh'])) : '';
    $cap_veh = isset($_POST['cap_veh']) ? intval($_POST['cap_veh']) : 0;
    $est_veh = isset($_POST['est_veh']) ? intval($_POST['est_veh']) : 1; 

    // Capturar la URL de origen para redireccionar dinámicamente y evitar errores 404
    $pagina_origen = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    $url_base = strtok($pagina_origen, '?');

    // 4. Validar que el ID sea válido y la placa no venga vacía
    if ($id_veh > 0 && !empty($pla_veh)) {
        
        // 5. Sentencia SQL para actualizar la placa, capacidad y el estado del vehículo
        $sql = "UPDATE vehiculo 
                SET pla_veh = '$pla_veh',
                    cap_veh = $cap_veh,
                    est_veh = $est_veh
                WHERE id_veh = $id_veh";

        // 6. Ejecutar la consulta en la base de datos
        if (mysqli_query($conexion, $sql)) {
            header("Location: " . $url_base . "?status=success"); 
            exit();
        } else {
            // Error en la consulta SQL
            header("Location: " . $url_base . "?status=error");
            exit();
        }

    } else {
        // ID o Placa no válidos
        header("Location: " . $url_base . "?status=error");
        exit();
    }

} else {
    // Redirección si se intenta ingresar directamente desde la URL por GET
    header("Location: ../Admin/");
    exit();
}
?>
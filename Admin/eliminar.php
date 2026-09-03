<?php
session_start();
include '../assets/conexion.php';

// Verificación de sesión y rol de administrador
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['tipo'])) {
    $id = $_GET['id'];
    $tipo = $_GET['tipo'];

    if ($tipo === 'ruta') {
        $id_rut = mysqli_real_escape_string($conexion, $id);

        // Consultar imagen actual para eliminarla del servidor
        $sql_img = "SELECT img_rut FROM rutas WHERE id_rut = '$id_rut'";
        $res_img = mysqli_query($conexion, $sql_img);
        
        if ($res_img && $row = mysqli_fetch_assoc($res_img)) {
            if (!empty($row['img_rut'])) {
                $archivo = "../uploads/rutas/" . $row['img_rut'];
                if (file_exists($archivo)) {
                    unlink($archivo);
                }
            }
        }

        // Eliminar registro
        $sql = "DELETE FROM rutas WHERE id_rut = '$id_rut'";
        mysqli_query($conexion, $sql);
<?php
session_start();
include '../assets/conexion.php';

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['tipo'])) {
    $id = $_GET['id'];
    $tipo = $_GET['tipo'];

    if ($tipo === 'ruta') {
        $id_rut = mysqli_real_escape_string($conexion, $id);

        // Consultar imagen actual para eliminarla del servidor
        $sql_img = "SELECT img_rut FROM rutas WHERE id_rut = '$id_rut'";
        $res_img = mysqli_query($conexion, $sql_img);
        
        if ($res_img && $row = mysqli_fetch_assoc($res_img)) {
            if (!empty($row['img_rut'])) {
                $archivo = "../uploads/rutas/" . $row['img_rut'];
                if (file_exists($archivo)) {
                    unlink($archivo);
                }
            }
        }

        // Eliminar registro de ruta
        $sql = "DELETE FROM rutas WHERE id_rut = '$id_rut'";
        mysqli_query($conexion, $sql);

        header("Location: rutas.php");
        exit();

    } elseif ($tipo === 'viaje') {
        // 1. Obtener los datos del viaje antes de borrarlo para liberar al conductor y vehículo
        $stmt_info = $conexion->prepare("SELECT id_usu_via, id_veh FROM viaje WHERE id_via = ?");
        $stmt_info->bind_param("i", $id);
        $stmt_info->execute();
        $res_info = $stmt_info->get_result();
        
        if ($res_info->num_rows > 0) {
            $viaje_data = $res_info->fetch_assoc();
            $id_conductor = $viaje_data['id_usu_via'];
            $id_vehiculo = $viaje_data['id_veh'];

            // 2. Verificamos si existen reportes con este viaje
            $check = $conexion->prepare("SELECT id_rep FROM reportes_pasajeros WHERE id_via_rep = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $resultado = $check->get_result();

            if ($resultado->num_rows > 0) {
                // Si hay reportes, no podemos borrar
                header("Location: viajes.php?status=error&msg=No se puede eliminar: este viaje tiene reportes registrados.");
                exit();
            } else {
                // Si no hay reportes, borramos el viaje
                $stmt = $conexion->prepare("DELETE FROM viaje WHERE id_via = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();

                // 3. LIBERAR CONDUCTOR Y VEHÍCULO (Asegura que queden disponibles)
                // Ajusta los nombres de columnas según tu base de datos si cambian (ej. est_con_usu = 1 o 'Disponible')
                if ($id_conductor) {
                    $liberar_usu = $conexion->prepare("UPDATE usuario SET est_con_usu = 1 WHERE id_usu = ?");
                    $liberar_usu->bind_param("i", $id_conductor);
                    $liberar_usu->execute();
                }

                if ($id_vehiculo) {
                    $liberar_veh = $conexion->prepare("UPDATE vehiculo SET est_veh = 1 WHERE id_veh = ?");
                    $liberar_veh->bind_param("i", $id_vehiculo);
                    $liberar_veh->execute();
                }

                header("Location: viajes.php?status=success");
                exit();
            }
        }
    }
}

header("Location: ../index.php");
exit();
?>
        header("Location: rutas.php");
        exit();

    } elseif ($tipo === 'viaje') {
        // 1. Verificamos si existen reportes con este viaje usando consultas preparadas
        $check = $conexion->prepare("SELECT id_rep FROM reportes_pasajeros WHERE id_via_rep = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $resultado = $check->get_result();

        if ($resultado->num_rows > 0) {
            // Si hay reportes, no podemos borrar
            header("Location: viajes.php?status=error&msg=No se puede eliminar: este viaje tiene reportes registrados.");
            exit();
        } else {
            // Si no hay reportes, borramos
            $stmt = $conexion->prepare("DELETE FROM viaje WHERE id_via = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            header("Location: viajes.php?status=success");
            exit();
        }
    }
}

// Si no se envía el tipo o id correcto, redirigir
header("Location: ../index.php");
exit();
?>
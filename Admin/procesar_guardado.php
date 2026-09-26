<?php
date_default_timezone_set('America/Bogota');
session_start();
require_once '../assets/conexion.php';

// Validar que solo un Administrador (rol 1) pueda ejecutar estas acciones
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Obtener URL de origen para redireccionar dinámicamente
$pagina_origen = $_SERVER['HTTP_REFERER'] ?? 'admin.php';
$url_base = strtok($pagina_origen, '?');
$metodo = $_SERVER['REQUEST_METHOD'];

// Identificar módulo o entidad enviado por el formulario
$modulo = $_REQUEST['modulo'] ?? $_REQUEST['entidad'] ?? $_REQUEST['accion'] ?? '';

// Detección automática de módulo según los campos POST si no viene 'modulo' especificado
if (empty($modulo) || in_array($modulo, ['crear', 'editar'])) {
    if (isset($_POST['restricciones']) || isset($_POST['id_usu_permisos'])) {
        $modulo = 'permisos';
    } elseif (isset($_POST['ori_rut']) || isset($_POST['nom_rut'])) {
        $modulo = 'ruta';
    } elseif (isset($_POST['num_doc_usu']) || isset($_POST['corre_usu']) || isset($_POST['nom_usu'])) {
        $modulo = 'usuario';
    } elseif (isset($_POST['pla_veh'])) {
        $modulo = 'vehiculo';
    } elseif (isset($_POST['id_rut_via']) || isset($_POST['fec_via']) || isset($_POST['id_via'])) {
        $modulo = 'viaje';
    }
}

switch ($modulo) {

    // ==========================================
    // 1. CREAR / ACTUALIZAR PERMISOS
    // ==========================================
    case 'permisos':
        if ($metodo === 'POST') {
            $id_usu = intval($_POST['id_usu'] ?? $_POST['id_usu_permisos'] ?? 0);
            $restricciones_array = $_POST['restricciones'] ?? [];
            $restricciones_str = !empty($restricciones_array) ? implode(',', array_map('trim', $restricciones_array)) : NULL;

            if ($id_usu > 0) {
                $stmt = $conexion->prepare("UPDATE usuario SET restricciones = ? WHERE id_usu = ?");
                $stmt->bind_param("si", $restricciones_str, $id_usu);

                if ($stmt->execute()) {
                    $_SESSION['msg_admin'] = "Permisos actualizados correctamente.";
                    header("Location: " . $url_base . "?status=success");
                } else {
                    die("<h3 style='color:red;'>Error al actualizar permisos:</h3> " . $stmt->error);
                }
                $stmt->close();
                exit();
            }
        }
        break;

    // ==========================================
    // 2. CREAR / ACTUALIZAR RUTA
    // ==========================================
    case 'ruta':
        if ($metodo === 'POST') {
            $id_rut          = isset($_POST['id_rut']) ? trim($_POST['id_rut']) : '';
            $nom_rut         = isset($_POST['nom_rut']) ? trim($_POST['nom_rut']) : '';
            $ori_rut         = isset($_POST['ori_rut']) ? trim($_POST['ori_rut']) : '';
            $des_rut         = isset($_POST['des_rut']) ? trim($_POST['des_rut']) : '';
            $dis_rut         = isset($_POST['dis_rut']) ? floatval($_POST['dis_rut']) : 0;
            $val_rut         = isset($_POST['val_rut']) ? floatval($_POST['val_rut']) : 0;
            $eliminar_imagen = isset($_POST['eliminar_imagen']) ? $_POST['eliminar_imagen'] : 0;

            $nombre_imagen = null;

            // Obtener imagen actual si es edición
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

            $directorio_destino = "../img/rutas/";
            if (!file_exists($directorio_destino)) {
                mkdir($directorio_destino, 0777, true);
            }

            if ($eliminar_imagen == 1 && !empty($nombre_imagen)) {
                if (file_exists($directorio_destino . $nombre_imagen)) {
                    unlink($directorio_destino . $nombre_imagen);
                }
                $nombre_imagen = null;
            }

            if (isset($_FILES['img_rut']) && $_FILES['img_rut']['error'] == UPLOAD_ERR_OK) {
                $file_tmp  = $_FILES['img_rut']['tmp_name'];
                $file_name = $_FILES['img_rut']['name'];
                $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                if (in_array($ext, $extensiones_permitidas)) {
                    if (!empty($nombre_imagen) && file_exists($directorio_destino . $nombre_imagen)) {
                        unlink($directorio_destino . $nombre_imagen);
                    }
                    $nuevo_nombre = "ruta_" . uniqid() . "." . $ext;
                    $ruta_final   = $directorio_destino . $nuevo_nombre;

                    if (move_uploaded_file($file_tmp, $ruta_final)) {
                        $nombre_imagen = $nuevo_nombre;
                    }
                }
            }

            if (!empty($id_rut)) {
                // MODO ACTUALIZAR RUTA
                $stmt = $conexion->prepare("UPDATE rutas SET nom_rut = ?, ori_rut = ?, des_rut = ?, dis_rut = ?, val_rut = ?, img_rut = ? WHERE id_rut = ?");
                $stmt->bind_param("sssddss", $nom_rut, $ori_rut, $des_rut, $dis_rut, $val_rut, $nombre_imagen, $id_rut);
            } else {
                // MODO CREAR RUTA
                $stmt = $conexion->prepare("INSERT INTO rutas (nom_rut, ori_rut, des_rut, dis_rut, val_rut, img_rut) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssdds", $nom_rut, $ori_rut, $des_rut, $dis_rut, $val_rut, $nombre_imagen);
            }

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: " . $url_base . "?status=success&msg=ruta_guardada");
                exit();
            } else {
                die("<h3 style='color:red;'>Error al guardar la ruta:</h3> " . $stmt->error);
            }
        }
        break;

    // ==========================================
    // 3. CREAR / ACTUALIZAR USUARIO
    // ==========================================
    case 'editar':
    case 'usuario':
        if ($metodo === 'POST') {
            $doc_original = trim($_POST['num_doc_usu'] ?? $_POST['num_doc'] ?? $_POST['documento'] ?? '');
            $tip_doc      = trim($_POST['tip_doc_usu'] ?? $_POST['tip_doc'] ?? 'CC');
            $nombre       = trim($_POST['nom_usu'] ?? $_POST['nombre'] ?? '');
            $correo       = trim($_POST['corre_usu'] ?? $_POST['correo'] ?? '');
            $rol          = $_POST['id_rol_usu'] ?? $_POST['id_rol'] ?? $_POST['rol'] ?? '3';

            // Detección automática si el formulario está editando o creando
            $modo_edicion = isset($_POST['es_edicion']) || isset($_POST['modo_edit']) || (isset($_POST['accion']) && $_POST['accion'] === 'editar') || !empty($doc_original);

            if ($doc_original === '' || $nombre === '' || $correo === '') {
                header("Location: " . $url_base . "?error=campos_invalidos");
                exit();
            }

            $est_con = ($rol == 2) ? 1 : 0;

            if ($modo_edicion) {
                // MODO ACTUALIZAR USUARIO
                if ($doc_original === $_SESSION['documento'] && $rol != 1) {
                    header("Location: " . $url_base . "?error=mismo_usuario");
                    exit();
                }

                $sql = "UPDATE usuario 
                        SET tip_doc_usu = ?, 
                            nom_usu     = ?, 
                            corre_usu   = ?, 
                            id_rol_usu  = ?, 
                            est_con_usu = ? 
                        WHERE num_doc_usu = ?";

                $stmt = $conexion->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssiss", $tip_doc, $nombre, $correo, $rol, $est_con, $doc_original);
                    
                    if ($stmt->execute()) {
                        $stmt->close();
                        header("Location: " . $url_base . "?msg=actualizado");
                        exit();
                    } else {
                        die("<h3 style='color:red;'>Error al ejecutar UPDATE usuario:</h3> " . $stmt->error);
                    }
                } else {
                    die("<h3 style='color:red;'>Error en la preparación SQL del usuario:</h3> " . $conexion->error);
                }
            } else {
                // MODO CREAR NUEVO USUARIO
                $clave_raw = $_POST['clave_usu'] ?? $_POST['pass_usu'] ?? '123456';
                $clave_usu = password_hash($clave_raw, PASSWORD_BCRYPT);
                $estado    = 1;

                $sql = "INSERT INTO usuario (num_doc_usu, tip_doc_usu, nom_usu, corre_usu, pass_usu, id_rol_usu, estado, est_con_usu) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conexion->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssssiii", $doc_original, $tip_doc, $nombre, $correo, $clave_usu, $rol, $estado, $est_con);
                    if ($stmt->execute()) {
                        $stmt->close();
                        header("Location: " . $url_base . "?msg=creado");
                        exit();
                    } else {
                        die("<h3 style='color:red;'>Error al crear usuario en MySQL:</h3> " . $stmt->error);
                    }
                }
            }
        }
        break;

    // ==========================================
    // 4. CREAR / ACTUALIZAR VEHÍCULO
    // ==========================================
    case 'vehiculo':
        if ($metodo === 'POST') {
            $id_veh    = intval($_POST['id_veh'] ?? 0);
            $placa     = trim($_POST['pla_veh'] ?? '');
            $capacidad = intval($_POST['cap_veh'] ?? 0);
            $modelo    = trim($_POST['mode_veh'] ?? '');
            $est_veh   = intval($_POST['est_veh'] ?? 1);

            if ($id_veh > 0) {
                // MODO ACTUALIZAR VEHÍCULO
                $stmt = $conexion->prepare("UPDATE vehiculo SET pla_veh = ?, cap_veh = ?, est_veh = ? WHERE id_veh = ?");
                $stmt->bind_param("siii", $placa, $capacidad, $est_veh, $id_veh);
            } else {
                // MODO CREAR VEHÍCULO
                $stmt = $conexion->prepare("INSERT INTO vehiculo (pla_veh, mode_veh, cap_veh, est_veh) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssii", $placa, $modelo, $capacidad, $est_veh);
            }

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: " . $url_base . "?msj=guardado&status=success");
                exit();
            } else {
                die("<h3 style='color:red;'>Error en el vehículo:</h3> " . $stmt->error);
            }
        }
        break;

    // ==========================================
    // 5. CREAR / ACTUALIZAR VIAJE
    // ==========================================
    case 'viaje':
        if ($metodo === 'POST') {
            $id_via       = intval($_POST['id_via'] ?? 0);
            $id_conductor = intval($_POST['id_usu_via'] ?? 0);
            $id_ruta      = intval($_POST['id_rut_via'] ?? 0);
            $id_vehiculo  = intval($_POST['id_veh_via'] ?? $_POST['id_veh'] ?? 0);
            $fec_via      = $_POST['fec_via'] ?? '';
            $hor_sal_via  = $_POST['hor_sal_via'] ?? '';
            $precio       = floatval($_POST['val_via'] ?? 0);

            if ($id_via > 0) {
                // MODO ACTUALIZAR VIAJE
                $stmt = $conexion->prepare("UPDATE viaje SET id_rut_via = ?, id_usu_via = ?, id_veh = ?, fec_via = ?, hor_sal_via = ?, val_via = ?, est_via = 'Programado' WHERE id_via = ?");
                $stmt->bind_param("iiissdi", $id_ruta, $id_conductor, $id_vehiculo, $fec_via, $hor_sal_via, $precio, $id_via);

                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: " . $url_base . "?status=success");
                    exit();
                } else {
                    die("<h3 style='color:red;'>Error al actualizar viaje:</h3> " . $stmt->error);
                }
            } else {
                // MODO CREAR VIAJE NUEVO (Con Transacción)
                if (!$id_conductor || !$id_ruta || !$id_vehiculo || !$fec_via || !$hor_sal_via || !$precio) {
                    header("Location: " . $url_base . "?error=faltan_datos");
                    exit();
                }

                try {
                    $conexion->begin_transaction();

                    $stmt = $conexion->prepare("SELECT est_con_usu FROM usuario WHERE id_usu = ? AND id_rol_usu = 2 FOR UPDATE");
                    $stmt->bind_param("i", $id_conductor);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $conductor = $result->fetch_assoc();

                    if ($conductor) {
                        $sqlViaje = "INSERT INTO viaje (id_rut_via, id_usu_via, id_veh, fec_via, hor_sal_via, val_via, est_via) VALUES (?, ?, ?, ?, ?, ?, 'Programado')";
                        $stmtViaje = $conexion->prepare($sqlViaje);
                        $stmtViaje->bind_param("iiissd", $id_ruta, $id_conductor, $id_vehiculo, $fec_via, $hor_sal_via, $precio);
                        $stmtViaje->execute();

                        $sqlUpdate = "UPDATE usuario SET est_con_usu = 'Ocupado' WHERE id_usu = ?";
                        $stmtUpdate = $conexion->prepare($sqlUpdate);
                        $stmtUpdate->bind_param("i", $id_conductor);
                        $stmtUpdate->execute();

                        $sqlUpdateVeh = "UPDATE vehiculo SET est_veh = 0 WHERE id_veh = ?";
                        $stmtUpdateVeh = $conexion->prepare($sqlUpdateVeh);
                        $stmtUpdateVeh->bind_param("i", $id_vehiculo);
                        $stmtUpdateVeh->execute();

                        $conexion->commit();
                        header("Location: " . $url_base . "?status=success");
                        exit();
                    } else {
                        $conexion->rollback();
                        header("Location: " . $url_base . "?error=conductor_no_encontrado");
                        exit();
                    }
                } catch (Exception $e) {
                    if (isset($conexion)) {
                        $conexion->rollback();
                    }
                    die("Error en el sistema: " . $e->getMessage());
                }
            }
        }
        break;

    default:
        header("Location: " . $url_base);
        break;
}

$conexion->close();
exit();
?>
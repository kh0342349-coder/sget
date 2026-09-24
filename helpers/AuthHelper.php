<?php
// helpers/AuthHelper.php

class AuthHelper {

    /**
     * Inicia la sesión de forma segura y verifica si el tiempo de inactividad
     * ha sido superado.
     *
     * @param int $minutosInactividad Tiempo límite en minutos (por defecto 15).
     */
    public static function verificarInactividad($minutosInactividad = 15) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $idUsuario = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0;

        // Si hay una sesión activa, verificamos el tiempo transcurrido
        if ($idUsuario > 0) {
            $tiempoLimiteSegundos = $minutosInactividad * 60;

            if (isset($_SESSION['ultimo_acceso'])) {
                $tiempoTranscurrido = time() - $_SESSION['ultimo_acceso'];

                if ($tiempoTranscurrido > $tiempoLimiteSegundos) {
                    // Guardar la URL donde se encontraba el usuario
                    $_SESSION['url_redirect'] = $_SERVER['REQUEST_URI'];
                    
                    // Marcar estado de inactividad
                    $_SESSION['sesion_bloqueada'] = true;

                    // Si la solicitud es mediante AJAX / JSON
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'status' => 'bloqueado',
                            'mensaje' => 'La sesión ha sido bloqueada por inactividad.',
                            'redirect' => '/desbloquear_sesion.php?inactivo=1'
                        ]);
                        exit();
                    }

                    // Redirección tradicional
                    header("Location: /desbloquear_sesion.php?inactivo=1");
                    exit();
                }
            }

            // Actualizar timestamp del último acceso activo
            $_SESSION['ultimo_acceso'] = time();
        }
    }

    /**
     * Verifica si un usuario tiene acceso a un módulo o recurso específico.
     * Si es Administrador (rol 1), otorga acceso total por defecto.
     * Para otros usuarios, consulta la tabla 'usuario_permisos'.
     */
    public static function tieneAcceso($conexion, $idUsuario, $recurso) {
        // Verificar tiempo de inactividad previo a cualquier consulta
        self::verificarInactividad();

        $idUsuario = intval($idUsuario);
        if ($idUsuario <= 0) return false;

        // 1. Obtener el rol del usuario
        $sqlRol = "SELECT id_rol_usu FROM usuario WHERE id_usu = ?";
        $stmtRol = mysqli_prepare($conexion, $sqlRol);
        if ($stmtRol) {
            mysqli_stmt_bind_param($stmtRol, "i", $idUsuario);
            mysqli_stmt_execute($stmtRol);
            $resRol = mysqli_stmt_get_result($stmtRol);
            if ($rowRol = mysqli_fetch_assoc($resRol)) {
                // SI ES ADMINISTRADOR (id_rol_usu = 1), TIENE ACCESO TOTAL SIEMPRE
                if (intval($rowRol['id_rol_usu']) === 1) {
                    mysqli_stmt_close($stmtRol);
                    return true;
                }
            }
            mysqli_stmt_close($stmtRol);
        }

        // 2. Para otros roles, verificar si el recurso está bloqueado en 'usuario_permisos'
        $sql = "SELECT up.permitido 
                FROM usuario_permisos up
                INNER JOIN permisos p ON up.id_permiso = p.id_permiso
                WHERE up.id_usu = ? AND (p.nombre_permiso = ? OR p.modulo = ?)";
        
        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iss", $idUsuario, $recurso, $recurso);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($res)) {
                $permitido = intval($row['permitido']);
                mysqli_stmt_close($stmt);
                return $permitido === 1; // 1: Permitido, 0: Denegado
            }
            mysqli_stmt_close($stmt);
        }
        
        return true; // Permitido por defecto si no está explícitamente restricto
    }

    /**
     * Exige acceso a un módulo o recurso, deteniendo la ejecución y mostrando 
     * una pantalla de acceso restringido si el usuario lo tiene prohibido.
     */
    public static function requerirAcceso($conexion, $idUsuario, $recurso) {
        if (!self::tieneAcceso($conexion, $idUsuario, $recurso)) {
            // Si es petición AJAX / JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'mensaje' => 'Acceso restringido a este apartado: ' . $recurso]);
                exit();
            }

            // Vista HTML de bloqueo amigable
            echo "<div style='font-family:sans-serif; text-align:center; margin-top:80px; background:#0f172a; color:#fff; padding:40px; border-radius:16px; max-width:450px; margin-left:auto; margin-right:auto; box-shadow: 0 10px 25px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1);'>
                    <h2 style='color:#ef4444; margin-bottom:12px; font-size:22px;'>Acceso Restringido</h2>
                    <p style='color:#94a3b8; font-size:14px; line-height:1.5;'>No tienes permitido acceder o gestionar este módulo (<b>{$recurso}</b>).</p>
                    <a href='javascript:history.back()' style='display:inline-block; margin-top:24px; padding:10px 24px; background:#3b82f6; color:#fff; text-decoration:none; border-radius:8px; font-weight:600; font-size:14px;'>Regresar</a>
                  </div>";
            exit();
        }
    }

    // =========================================================================
    // MÉTODOS DE COMPATIBILIDAD
    // =========================================================================
    
    public static function tienePermiso($conexion, $idUsuario, $recurso) {
        return self::tieneAcceso($conexion, $idUsuario, $recurso);
    }

    public static function requerirPermiso($conexion, $idUsuario, $recurso) {
        self::requerirAcceso($conexion, $idUsuario, $recurso);
    }

    public static function requerirModulo($conexion, $idUsuario, $recurso) {
        self::requerirAcceso($conexion, $idUsuario, $recurso);
    }
}
?>
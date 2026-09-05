<?php
// helpers/AuthHelper.php

class AuthHelper {

    /**
     * Verifica si un usuario tiene acceso a un módulo o recurso específico.
     * Lee la columna 'restricciones' de la tabla 'usuario' (separado por comas).
     * Retorna false si está restringido (bloqueado), true si está permitido por defecto.
     */
    public static function tieneAcceso($conexion, $idUsuario, $recurso) {
        $idUsuario = intval($idUsuario);
        if ($idUsuario <= 0) return false;

        $sql = "SELECT restricciones FROM usuario WHERE id_usu = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $idUsuario);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($res)) {
                $restriccionesStr = $row['restricciones'] ?? '';
                if (!empty($restriccionesStr)) {
                    $denegados = array_map('trim', explode(',', $restriccionesStr));
                    if (in_array($recurso, $denegados)) {
                        mysqli_stmt_close($stmt);
                        return false; 
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        return true; // Permitido por defecto si no está restringido
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
    // MÉTODOS DE COMPATIBILIDAD (Para evitar errores si algún archivo los llama)
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
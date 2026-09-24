<?php
// helpers/Logger.php

class Logger {
    
    public static function registrar($conexion, $accion, $descripcion, $id_usu = null, $nom_usu = null, $nom_rol = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $id     = $id_usu ?? $_SESSION['id_usu'] ?? $_SESSION['id_usuario'] ?? null;
        $nombre = $nom_usu ?? $_SESSION['nom_usu'] ?? $_SESSION['nombre'] ?? 'Anónimo / Sistema';
        $rol    = $nom_rol ?? $_SESSION['nom_rol'] ?? $_SESSION['rol'] ?? 'Sin Rol';

        $ip = $_SERVER['HTTP_CLIENT_IP'] 
              ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
              ?? $_SERVER['REMOTE_ADDR'] 
              ?? 'Desconocida';

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

        try {
            $sql = "INSERT INTO sget_logs_auditoria 
                    (id_usu, nom_usu_log, nom_rol_log, accion, descripcion, ip_origen, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conexion->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("issssss", $id, $nombre, $rol, $accion, $descripcion, $ip, $userAgent);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Error al registrar auditoría en SGET: " . $e->getMessage());
        }
    }
}
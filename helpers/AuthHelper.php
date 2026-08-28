<?php
// helpers/AuthHelper.php

class AuthHelper {

    /**
     * Verifica si un usuario tiene permiso para ejecutar una acción
     */
    public static function tienePermiso($conexion, $idUsuario, $nombrePermiso) {
        $idUsuario = intval($idUsuario);
        
        if ($idUsuario <= 0) {
            return false;
        }

        // 1. Verificar si es Administrador principal (id_rol_usu = 1)
        // Usamos ? en lugar de :id_usu para MySQLi
        $sqlRol = "SELECT id_rol_usu FROM usuario WHERE id_usu = ?";
        $stmtRol = mysqli_prepare($conexion, $sqlRol);
        
        if ($stmtRol) {
            mysqli_stmt_bind_param($stmtRol, "i", $idUsuario);
            mysqli_stmt_execute($stmtRol);
            $resRol = mysqli_stmt_get_result($stmtRol);

            if ($user = mysqli_fetch_assoc($resRol)) {
                if ($user['id_rol_usu'] == 1) {
                    return true; // Acceso total automático para administradores
                }
            }
            mysqli_stmt_close($stmtRol);
        }

        // 2. Consultar permiso específico asignado en la BD
        $sql = "SELECT up.permitido 
                FROM usuario_permisos up
                INNER JOIN permisos p ON up.id_permiso = p.id_permiso
                WHERE up.id_usu = ? AND p.nombre_permiso = ?";
        
        $stmt = mysqli_prepare($conexion, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "is", $idUsuario, $nombrePermiso);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            if ($fila = mysqli_fetch_assoc($res)) {
                $permitido = (int)$fila['permitido'] === 1;
                mysqli_stmt_close($stmt);
                return $permitido;
            }
            mysqli_stmt_close($stmt);
        }

        return false;
    }

    /**
     * Bloquea la vista/endpoint si no cuenta con el permiso
     */
    public static function requerirPermiso($conexion, $idUsuario, $nombrePermiso) {
        if (!self::tienePermiso($conexion, $idUsuario, $nombrePermiso)) {
            http_response_code(403);
            die("<div style='text-align:center; padding:50px; font-family:sans-serif;'>
                    <h2>403 - Acceso Denegado</h2>
                    <p>No tienes permiso para acceder a esta función.</p>
                 </div>");
        }
    }
}
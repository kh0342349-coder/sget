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

        // Consultar permiso específico asignado en la BD para este usuario
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
     * Muestra un modal elegante de acceso denegado y detiene la ejecución
     */
    public static function requerirPermiso($conexion, $idUsuario, $nombrePermiso) {
        if (!self::tienePermiso($conexion, $idUsuario, $nombrePermiso)) {
            http_response_code(403);
            
            // Renderizamos un modal flotante con Tailwind CSS acorde al diseño de SGET
            echo '<script src="https://cdn.tailwindcss.com"></script>';
            echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">';
            echo '<body class="bg-[#0b0f19] text-slate-200 flex items-center justify-center min-h-screen m-0 font-sans">
                    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
                        <div class="bg-[#1e293b] border border-white/10 w-full max-w-md rounded-[32px] shadow-2xl overflow-hidden p-6 text-center space-y-4 transform transition-all">
                            <div class="w-14 h-14 rounded-2xl bg-red-500/10 text-red-500 flex items-center justify-center mx-auto text-2xl shadow-inner">
                                <i class="fas fa-ban"></i>
                            </div>
                            <h3 class="text-base font-black text-white tracking-wide">Acceso Denegado</h3>
                            <p class="text-xs text-slate-400 leading-relaxed px-2">No tienes los permisos necesarios para acceder a esta función o módulo del sistema SGET.</p>
                            <div class="pt-2">
                                <button onclick="history.back()" class="w-full py-3 bg-gradient-to-r from-sky-400 to-blue-600 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl transition-all shadow-lg hover:opacity-90 cursor-pointer">
                                    Volver Atrás
                                </button>
                            </div>
                        </div>
                    </div>
                  </body>';
            exit();
        }
    }
}
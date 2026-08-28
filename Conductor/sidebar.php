<?php
// 1. Asegurar inicio de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Incluir la conexión si no ha sido cargada
if (!isset($conexion)) {
    include '../assets/conexion.php';
}

$documento_sidebar = $_SESSION['documento'] ?? '';
$restricciones_sidebar = '';

// 3. Consultar las restricciones activas directamente desde la Base de Datos
if (!empty($documento_sidebar)) {
    $stmt_sb = $conexion->prepare("SELECT restricciones FROM usuario WHERE num_doc_usu = ?");
    $stmt_sb->bind_param("s", $documento_sidebar);
    $stmt_sb->execute();
    $res_sb = $stmt_sb->get_result();
    if ($res_sb && $res_sb->num_rows > 0) {
        $restricciones_sidebar = $res_sb->fetch_assoc()['restricciones'] ?? '';
    }
    $stmt_sb->close();
}

// 4. Helper para validar acceso en cada item del menú
if (!function_exists('tiene_acceso_sb')) {
    function tiene_acceso_sb($permiso, $cadena_restricciones) {
        if (empty($cadena_restricciones)) {
            return true;
        }
        $denegados = explode(',', $cadena_restricciones);
        return !in_array($permiso, $denegados);
    }
}

$paginaActual = basename($_SERVER['PHP_SELF']); 
?>

<!-- BARRA LATERAL (SIDEBAR) -->
<aside class="fixed top-0 left-0 w-64 h-screen bg-white dark:bg-[#0f172a] border-r border-slate-200 dark:border-white/5 flex flex-col justify-between z-50 transition-colors duration-300">
    
    <div>
        <!-- Encabezado con LOGO DINÁMICO -->
        <div class="h-16 flex items-center px-6 border-b border-slate-200 dark:border-white/5">
            <a href="conductor.php" class="flex items-center group">
                <!-- Logo para Tema Claro -->
                <img id="sidebar-logo-light" 
                     src="../img/largo-blanco.png" 
                     alt="SGET Logo" 
                     class="h-9 w-auto object-contain block dark:hidden filter drop-shadow-sm group-hover:scale-105 transition-all duration-300">
                
                <!-- Logo para Tema Oscuro -->
                <img id="sidebar-logo-dark" 
                     src="../img/largo-negro.png" 
                     alt="SGET Logo" 
                     class="h-9 w-auto object-contain hidden dark:block filter drop-shadow-md group-hover:scale-105 transition-all duration-300">
            </a>
        </div>

        <!-- Menú de Navegación principal -->
        <nav class="p-4 space-y-1.5">
            
            <!-- Dashboard (Acceso General Siempre Visible) -->
            <a href="conductor.php" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-xs transition-all <?php echo ($paginaActual == 'conductor.php' || $paginaActual == 'dashboard_conductor.php') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-white'; ?>">
                <i class="fas fa-th-large text-sm"></i>
                <span>Dashboard</span>
            </a>

            <!-- Mis Viajes (Se oculta si el admin asigna 'ver_rutas') -->
            <?php if (tiene_acceso_sb('ver_rutas', $restricciones_sidebar)): ?>
            <a href="viajes_conductor.php" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-xs transition-all <?php echo ($paginaActual == 'viajes_conductor.php') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-white'; ?>">
                <i class="fas fa-route text-sm"></i>
                <span>Mis Viajes</span>
            </a>
            <?php endif; ?>

            <!-- Viaje Asignado -->
            <a href="viaje_asignado.php" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-xs transition-all <?php echo ($paginaActual == 'viaje_asignado.php') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-white'; ?>">
                <i class="fas fa-user text-sm"></i>
                <span>Viaje Asignado</span>
            </a>

            <!-- Mis Reseñas (Se oculta si el admin asigna 'ver_ranking') -->
            <?php if (tiene_acceso_sb('ver_ranking', $restricciones_sidebar)): ?>
            <a href="resenas_conductor.php" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-xs transition-all <?php echo ($paginaActual == 'resenas_conductor.php' || $paginaActual == 'reseñas_conductor.php') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-white'; ?>">
                <i class="fas fa-star text-sm"></i>
                <span>Mis Reseñas</span>
            </a>
            <?php endif; ?>

        </nav>
    </div>

    <!-- Botón Inferior de Salida -->
    <div class="p-4 border-t border-slate-200 dark:border-white/5">
        <a href="../logout.php" 
           class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-xs text-red-500 hover:bg-red-500/10 transition-all">
            <i class="fas fa-sign-out-alt text-sm"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>

</aside>
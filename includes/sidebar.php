<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conexion)) {
    include_once __DIR__ . '/../assets/conexion.php';
}

$rolUsuario = $_SESSION['rol'] ?? $_SESSION['id_rol_usu'] ?? 0;
$pagina_actual = basename($_SERVER['PHP_SELF']);

// --- FUNCIONES UNIFICADAS PARA MARCAR EL MENÚ ACTIVO ---
function verificarClaseActiva($archivos, $paginaActual) {
    $esActivo = is_array($archivos) ? in_array($paginaActual, $archivos) : ($paginaActual === $archivos);
    return $esActivo ? 'active-link font-black text-sky-500 dark:text-sky-400' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold';
}

function verificarIconoActivo($archivos, $paginaActual) {
    $esActivo = is_array($archivos) ? in_array($paginaActual, $archivos) : ($paginaActual === $archivos);
    return $esActivo ? 'text-sky-500 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-900 group-hover:dark:text-white';
}

// --- RESTRICCIONES DE CONDUCTOR (Solo si es Rol 2) ---
$restricciones_sidebar = '';
if ($rolUsuario == 2 && isset($_SESSION['documento'])) {
    $stmt_sb = $conexion->prepare("SELECT restricciones FROM usuario WHERE num_doc_usu = ?");
    $stmt_sb->bind_param("s", $_SESSION['documento']);
    $stmt_sb->execute();
    $res_sb = $stmt_sb->get_result();
    if ($res_sb && $res_sb->num_rows > 0) {
        $restricciones_sidebar = $res_sb->fetch_assoc()['restricciones'] ?? '';
    }
    $stmt_sb->close();
}

if (!function_exists('tiene_acceso_sb')) {
    function tiene_acceso_sb($permiso, $cadena_restricciones) {
        if (empty($cadena_restricciones)) return true;
        $denegados = explode(',', $cadena_restricciones);
        return !in_array($permiso, $denegados);
    }
}
?>

<style>
    /* Transición sincronizada para sidebar y contenido (Heredado del Admin) */
    #sidebar-menu, #main-content-wrapper { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; }
    @media (min-width: 1024px) { #main-content-wrapper { margin-left: 17.5rem !important; } }
    body.sidebar-collapsed #sidebar-menu { width: 5.5rem !important; }
    @media (min-width: 1024px) { body.sidebar-collapsed #main-content-wrapper { margin-left: 8rem !important; } }
    body.sidebar-collapsed #sidebar-menu .sidebar-text { opacity: 0 !important; display: none !important; width: 0 !important; }
    body.sidebar-collapsed #sidebar-menu nav a, body.sidebar-collapsed #sidebar-menu button { justify-content: center !important; padding-left: 0 !important; padding-right: 0 !important; width: 3.5rem !important; margin-left: auto !important; margin-right: auto !important; }
    body.sidebar-collapsed #sidebar-menu nav a i, body.sidebar-collapsed #sidebar-menu button i { margin: 0 !important; font-size: 1.15rem !important; }
    @media (max-width: 1023px) {
        #sidebar-menu { transform: translateX(-110%); }
        body.sidebar-open #sidebar-menu { transform: translateX(0) !important; }
        #main-content-wrapper { margin-left: 0 !important; }
    }
</style>

<!-- SIDEBAR ISLA FLOTANTE -->
<aside id="sidebar-menu" class="w-64 fixed top-4 bottom-4 left-4 h-[calc(100vh-2rem)] z-50 rounded-[28px] shadow-2xl flex flex-col overflow-hidden border bg-white/95 dark:bg-[#0f172a]/85 border-slate-200/90 dark:border-white/10 backdrop-blur-xl transition-all duration-300">
    
        <div class="px-4 py-6 border-b border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-[#1e293b]/20 transition-colors duration-300 flex items-center justify-center min-h-[85px]">
        <!-- Logo para modo claro -->
        <img src="../img/largo-blanco.png" alt="Logo SGET" class="h-14 w-auto max-w-full object-contain block dark:hidden">
        
        <!-- Logo para modo oscuro -->
        <img src="../img/largo-negro.png" alt="Logo SGET" class="h-14 w-auto max-w-full object-contain hidden dark:block">
    </div>



    <!-- MENÚ DE NAVEGACIÓN DINÁMICO -->
    <nav class="mt-4 px-3 flex-grow overflow-y-auto space-y-1.5 text-xs custom-scrollbar">
        
        <?php if ($rolUsuario == 1): // ================== ADMIN ================== ?>
            <a href="admin.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('admin.php', $pagina_actual); ?>">
                <i class="fas fa-chart-pie text-sm shrink-0 <?php echo verificarIconoActivo('admin.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Dashboard</span>
            </a>
            <a href="usuarios.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('usuarios.php', $pagina_actual); ?>">
                <i class="fas fa-users-cog text-sm shrink-0 <?php echo verificarIconoActivo('usuarios.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Usuarios & Roles</span>
            </a>
            <a href="asignaciones.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('asignaciones.php', $pagina_actual); ?>">
                <i class="fas fa-cash-register text-sm shrink-0 <?php echo verificarIconoActivo('asignaciones.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Recaudo & Abordaje</span>
            </a>
            <a href="rutas.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('rutas.php', $pagina_actual); ?>">
                <i class="fas fa-route text-sm shrink-0 <?php echo verificarIconoActivo('rutas.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Gestión de Rutas</span>
            </a>
            <a href="viajes.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes_3.php', $pagina_actual); ?>">
                <i class="fas fa-calendar-alt text-sm shrink-0 <?php echo verificarIconoActivo('viajes_3.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Programación Viajes</span>
            </a>
            <a href="vehiculos.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('vehiculos.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('vehiculos.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Flota de Vehículos</span>
            </a>
            <a href="gestion_permisos.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('gestion_permisos.php', $pagina_actual); ?>">
                <i class="fas fa-key text-sm shrink-0 <?php echo verificarIconoActivo('gestion_permisos.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Permisos</span>
            </a>
            <a href="ranking_conductores.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('ranking_conductores.php', $pagina_actual); ?>">
                <i class="fas fa-star text-sm shrink-0 <?php echo verificarIconoActivo('ranking_conductores.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Calificaciones</span>
            </a>
            <a href="reportes.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('reportes.php', $pagina_actual); ?>">
                <i class="fas fa-file-invoice-dollar text-sm shrink-0 <?php echo verificarIconoActivo('reportes.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Reportes Generales</span>
            </a>
        
        <?php elseif ($rolUsuario == 2): // ================== CONDUCTOR ================== ?>
            <a href="conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva(['conductor.php', 'dashboard_conductor.php'], $pagina_actual); ?>">
                <i class="fas fa-chart-pie text-sm shrink-0 <?php echo verificarIconoActivo(['conductor.php', 'dashboard_conductor.php'], $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Dashboard</span>
            </a>
            <?php if (tiene_acceso_sb('ver_rutas', $restricciones_sidebar)): ?>
            <a href="viajes_conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes_conductor.php', $pagina_actual); ?>">
                <i class="fas fa-route text-sm shrink-0 <?php echo verificarIconoActivo('viajes_conductor.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Mis Viajes</span>
            </a>
            <?php endif; ?>
            <a href="viaje_asignado.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viaje_asignado.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('viaje_asignado.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Viaje Asignado</span>
            </a>
            <?php if (tiene_acceso_sb('ver_ranking', $restricciones_sidebar)): ?>
            <a href="resenas_conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva(['resenas_conductor.php', 'reseñas_conductor.php'], $pagina_actual); ?>">
                <i class="fas fa-star text-sm shrink-0 <?php echo verificarIconoActivo(['resenas_conductor.php', 'reseñas_conductor.php'], $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Mis Reseñas</span>
            </a>
            <?php endif; ?>

        <?php elseif ($rolUsuario == 3): // ================== PASAJERO ================== ?>
            <a href="pasajero.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('pasajero.php', $pagina_actual); ?>">
                <i class="fas fa-th-large text-sm shrink-0 <?php echo verificarIconoActivo('pasajero.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Inicio</span>
            </a>
            <a href="viajes_pasajero.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes_pasajero.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('viajes_pasajero.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Ver Viajes</span>
            </a>
            <a href="historial_pasajero.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('historial_pasajero.php', $pagina_actual); ?>">
                <i class="fas fa-history text-sm shrink-0 <?php echo verificarIconoActivo('historial_pasajero.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Historial</span>
            </a>
            <a href="calificar.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('calificar.php', $pagina_actual); ?>">
                <i class="fas fa-star text-sm shrink-0 <?php echo verificarIconoActivo('calificar.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Calificaciones</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- FOOTER DEL SIDEBAR: BOTÓN DE AYUDA GLOBAL -->
    <div class="p-3 border-t border-slate-200/80 dark:border-white/10 space-y-1.5 shrink-0">
        <button onclick="abrirModalAyuda()" data-tooltip="Ayuda del Módulo" class="sidebar-link w-full flex items-center justify-center gap-2.5 bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 py-3 rounded-2xl text-xs font-bold transition-all border border-sky-500/20 cursor-pointer">
            <i class="fas fa-question-circle text-sm shrink-0"></i>
            <span class="sidebar-text truncate">Soporte y Ayuda</span>
        </button>
    </div>
</aside>
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

// --- SISTEMA UNIFICADO DE RESTRICCIONES (ADMIN, CONDUCTOR, PASAJERO) ---
$restricciones_admin_array = [];
$restricciones_usuario_str = '';

if (isset($_SESSION['id_usu'])) {
    $idUsuSession = $_SESSION['id_usu'];
    
    if ($rolUsuario == 1) {
        // Administradores: Se consultan desde la tabla independiente 'restricciones'
        $stmt_adm = $conexion->prepare("SELECT modulo FROM restricciones WHERE id_usu = ?");
        if ($stmt_adm) {
            $stmt_adm->bind_param("i", $idUsuSession);
            $stmt_adm->execute();
            $res_adm = $stmt_adm->get_result();
            while ($row = $res_adm->fetch_assoc()) {
                $restricciones_admin_array[] = $row['modulo'];
            }
            $stmt_adm->close();
        }
    } else {
        // Pasajeros y Conductores: Se consultan desde la columna 'restricciones' en la tabla 'usuario'
        $stmt_usu = $conexion->prepare("SELECT restricciones FROM usuario WHERE id_usu = ?");
        if ($stmt_usu) {
            $stmt_usu->bind_param("i", $idUsuSession);
            $stmt_usu->execute();
            $res_usu = $stmt_usu->get_result();
            if ($res_usu && $res_usu->num_rows > 0) {
                $restricciones_usuario_str = $res_usu->fetch_assoc()['restricciones'] ?? '';
            }
            $stmt_usu->close();
        }
    }
}

if (!function_exists('tiene_acceso_sb')) {
    function tiene_acceso_sb($permiso, $rol, $restr_str, $restr_arr) {
        if ($rol == 1) {
            // Si es admin, verificamos si el módulo ESTÁ en el array (lo que significa que está bloqueado)
            return !in_array($permiso, $restr_arr);
        } else {
            // Si es conductor/pasajero, se verifica en el string separado por comas
            if (empty($restr_str)) return true;
            $denegados = explode(',', $restr_str);
            return !in_array($permiso, $denegados);
        }
    }
}
?>

<style>
    /* Transición sincronizada para sidebar y contenido */
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
            
            <?php if (tiene_acceso_sb('admin', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="admin.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('admin.php', $pagina_actual); ?>">
                <i class="fas fa-chart-pie text-sm shrink-0 <?php echo verificarIconoActivo('admin.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['dashboard'] ?? 'Dashboard'; ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (tiene_acceso_sb('usuarios', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="usuarios.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('usuarios.php', $pagina_actual); ?>">
                <i class="fas fa-users-cog text-sm shrink-0 <?php echo verificarIconoActivo('usuarios.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['usuarios'] ?? 'Usuarios & Roles'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('asignaciones', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="asignaciones.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('asignaciones.php', $pagina_actual); ?>">
                <i class="fas fa-cash-register text-sm shrink-0 <?php echo verificarIconoActivo('asignaciones.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['recaudo'] ?? 'Recaudo & Abordaje'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('rutas', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="rutas.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('rutas.php', $pagina_actual); ?>">
                <i class="fas fa-route text-sm shrink-0 <?php echo verificarIconoActivo('rutas.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['rutas'] ?? 'Gestión de Rutas'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('viajes', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="viajes.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes_3.php', $pagina_actual); ?>">
                <i class="fas fa-calendar-alt text-sm shrink-0 <?php echo verificarIconoActivo('viajes_3.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['viajes'] ?? 'Programación Viajes'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('vehiculos', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="vehiculos.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('vehiculos.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('vehiculos.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['vehiculos'] ?? 'Flota de Vehículos'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('gestion_permisos', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="gestion_permisos.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('gestion_permisos.php', $pagina_actual); ?>">
                <i class="fas fa-key text-sm shrink-0 <?php echo verificarIconoActivo('gestion_permisos.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['permisos'] ?? 'Permisos'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('ranking_conductores', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="ranking_conductores.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('ranking_conductores.php', $pagina_actual); ?>">
                <i class="fas fa-star text-sm shrink-0 <?php echo verificarIconoActivo('ranking_conductores.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['calificaciones'] ?? 'Calificaciones'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('reportes', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="reportes.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('reportes.php', $pagina_actual); ?>">
                <i class="fas fa-file-invoice-dollar text-sm shrink-0 <?php echo verificarIconoActivo('reportes.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['reportes'] ?? 'Reportes Generales'; ?></span>
            </a>
            <?php endif; ?>
        
        <?php elseif ($rolUsuario == 2): // ================== CONDUCTOR ================== ?>
            <a href="conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva(['conductor.php', 'dashboard_conductor.php'], $pagina_actual); ?>">
                <i class="fas fa-chart-pie text-sm shrink-0 <?php echo verificarIconoActivo(['conductor.php', 'dashboard_conductor.php'], $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['dashboard'] ?? 'Dashboard'; ?></span>
            </a>
            
            <?php if (tiene_acceso_sb('ver_rutas', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="viajes_conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes_conductor.php', $pagina_actual); ?>">
                <i class="fas fa-route text-sm shrink-0 <?php echo verificarIconoActivo('viajes_conductor.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Mis Viajes</span>
            </a>
            <?php endif; ?>
            
            <a href="viaje_asignado.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viaje_asignado.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('viaje_asignado.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Viaje Asignado</span>
            </a>
            
            <?php if (tiene_acceso_sb('ver_ranking', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
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
            
            <?php if (tiene_acceso_sb('ver_viajes', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="viajes_pasajero.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes_pasajero.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('viajes_pasajero.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Ver Viajes</span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('historial', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="historial_pasajero.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('historial_pasajero.php', $pagina_actual); ?>">
                <i class="fas fa-history text-sm shrink-0 <?php echo verificarIconoActivo('historial_pasajero.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Historial</span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('calificar', $rolUsuario, $restricciones_usuario_str, $restricciones_admin_array)): ?>
            <a href="calificar.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('calificar.php', $pagina_actual); ?>">
                <i class="fas fa-star text-sm shrink-0 <?php echo verificarIconoActivo('calificar.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['calificaciones'] ?? 'Calificaciones'; ?></span>
            </a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    <!-- FOOTER DEL SIDEBAR: CONFIGURACIÓN Y AYUDA GLOBAL -->
    <div class="p-3 border-t border-slate-200/80 dark:border-white/10 space-y-1.5 shrink-0">
        <!-- Botón de Configuración -->
        <button type="button" onclick="abrirModalConfiguracion()" class="sidebar-link w-full flex items-center gap-3 px-4 py-3 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200/50 dark:hover:bg-white/5 rounded-2xl transition-all cursor-pointer font-bold">
            <i class="fas fa-cog text-sm shrink-0"></i>
            <span class="sidebar-text truncate"><?php echo $lang['configuracion'] ?? 'Configuración'; ?></span>
        </button>
    
        <!-- Botón de Soporte y Ayuda -->
        <button onclick="abrirModalAyuda()" data-tooltip="Ayuda del Módulo" class="sidebar-link w-full flex items-center justify-center gap-2.5 bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 py-3 rounded-2xl text-xs font-bold transition-all border border-sky-500/20 cursor-pointer">
            <i class="fas fa-question-circle text-sm shrink-0"></i>
            <span class="sidebar-text truncate"><?php echo $lang['soporte'] ?? 'Soporte y Ayuda'; ?></span>
        </button>
    </div>
</aside>
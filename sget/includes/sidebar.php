<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conexion)) {
    include_once __DIR__ . '/../assets/conexion.php';
}

$idiomaActualSidebar = $_SESSION['sget_idioma'] ?? 'es';
if ($idiomaActualSidebar === 'en') {
    @include_once __DIR__ . '/../lang/en.php';
} else {
    @include_once __DIR__ . '/../lang/es.php';
}

$rolUsuario = $_SESSION['rol'] ?? $_SESSION['id_rol_usu'] ?? 0;
$pagina_actual = basename($_SERVER['PHP_SELF']);

// --- FUNCIONES PARA MARCAR EL MENÚ ACTIVO ---
function verificarClaseActiva($archivos, $paginaActual) {
    $esActivo = is_array($archivos) ? in_array($paginaActual, $archivos) : ($paginaActual === $archivos);
    return $esActivo ? 'active-link font-black text-sky-500 dark:text-sky-400' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold';
}

function verificarIconoActivo($archivos, $paginaActual) {
    $esActivo = is_array($archivos) ? in_array($paginaActual, $archivos) : ($paginaActual === $archivos);
    return $esActivo ? 'text-sky-500 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-900 group-hover:dark:text-white';
}

// --- SISTEMA DE RESTRICCIONES ---
$permisos_denegados_array = [];

if (isset($_SESSION['id_usu'])) {
    $idUsuSession = intval($_SESSION['id_usu']);
    
    $sql_perm = "SELECT p.nombre_permiso, p.modulo 
                 FROM usuario_permisos up
                 INNER JOIN permisos p ON up.id_permiso = p.id_permiso
                 WHERE up.id_usu = ? AND up.permitido = 0";
                 
    if (isset($conexion) && $conexion) {
        $stmt_perm = $conexion->prepare($sql_perm);
        if ($stmt_perm) {
            $stmt_perm->bind_param("i", $idUsuSession);
            $stmt_perm->execute();
            $res_perm = $stmt_perm->get_result();
            while ($row = $res_perm->fetch_assoc()) {
                $permisos_denegados_array[] = $row['nombre_permiso'];
                $permisos_denegados_array[] = $row['modulo'];
            }
            $stmt_perm->close();
        }
    }
}

if (!function_exists('tiene_acceso_sb')) {
    function tiene_acceso_sb($permiso, $permisos_denegados) {
        return !in_array($permiso, $permisos_denegados);
    }
}
?>

<script>
    window.SGET_LANGUAGE_URL = '../set_language.php';
    document.documentElement.setAttribute('data-language', '<?= htmlspecialchars($idiomaActualSidebar, ENT_QUOTES, 'UTF-8') ?>');
</script>
<script src="../js/i18n.js?v=20260908-1"></script>

<style>
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

<aside id="sidebar-menu" class="w-64 fixed top-4 bottom-4 left-4 h-[calc(100vh-2rem)] z-50 rounded-[28px] shadow-2xl flex flex-col overflow-hidden border bg-white/95 dark:bg-[#0f172a]/85 border-slate-200/90 dark:border-white/10 backdrop-blur-xl transition-all duration-300">
    
    <div class="px-4 py-6 border-b border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-[#1e293b]/20 transition-colors duration-300 flex items-center justify-center min-h-[85px]">
        <img src="../img/largo-blanco.png" alt="Logo SGET" class="h-14 w-auto max-w-full object-contain block dark:hidden">
        <img src="../img/largo-negro.png" alt="Logo SGET" class="h-14 w-auto max-w-full object-contain hidden dark:block">
    </div>

    <nav class="mt-4 px-3 flex-grow overflow-y-auto space-y-1.5 text-xs custom-scrollbar">
        
        <?php if ($rolUsuario == 1): // ADMIN ?>
            
            <?php if (tiene_acceso_sb('admin', $permisos_denegados_array)): ?>
            <a href="admin.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('admin.php', $pagina_actual); ?>">
                <i class="fas fa-chart-pie text-sm shrink-0 <?php echo verificarIconoActivo('admin.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['dashboard'] ?? 'Dashboard'; ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (tiene_acceso_sb('usuarios', $permisos_denegados_array)): ?>
            <a href="usuarios.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('usuarios.php', $pagina_actual); ?>">
                <i class="fas fa-users-cog text-sm shrink-0 <?php echo verificarIconoActivo('usuarios.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['usuarios'] ?? 'Usuarios & Roles'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('asignaciones', $permisos_denegados_array)): ?>
            <a href="asignaciones.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('asignaciones.php', $pagina_actual); ?>">
                <i class="fas fa-cash-register text-sm shrink-0 <?php echo verificarIconoActivo('asignaciones.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['recaudo'] ?? 'Recaudo & Abordaje'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('rutas', $permisos_denegados_array)): ?>
            <a href="rutas.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('rutas.php', $pagina_actual); ?>">
                <i class="fas fa-route text-sm shrink-0 <?php echo verificarIconoActivo('rutas.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['rutas'] ?? 'Gestión de Rutas'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('viajes', $permisos_denegados_array)): ?>
            <a href="viajes.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes_3.php', $pagina_actual); ?>">
                <i class="fas fa-calendar-alt text-sm shrink-0 <?php echo verificarIconoActivo('viajes_3.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['viajes'] ?? 'Programación Viajes'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('vehiculos', $permisos_denegados_array)): ?>
            <a href="vehiculos.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('vehiculos.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('vehiculos.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['vehiculos'] ?? 'Flota de Vehículos'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('gestion_permisos', $permisos_denegados_array)): ?>
            <a href="gestion_permisos.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('gestion_permisos.php', $pagina_actual); ?>">
                <i class="fas fa-key text-sm shrink-0 <?php echo verificarIconoActivo('gestion_permisos.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['permisos'] ?? 'Permisos'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('ranking_conductores', $permisos_denegados_array)): ?>
            <a href="ranking_conductores.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('ranking_conductores.php', $pagina_actual); ?>">
                <i class="fas fa-star text-sm shrink-0 <?php echo verificarIconoActivo('ranking_conductores.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['calificaciones'] ?? 'Calificaciones'; ?></span>
            </a>
            <?php endif; ?>

           <?php if (tiene_acceso_sb('logs', $permisos_denegados_array)): ?>
            <a href="logs.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('logs.php', $pagina_actual); ?>">
                <i class="fas fa-file-alt text-sm shrink-0 <?php echo verificarIconoActivo('logs.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['logs'] ?? 'Logs de Auditoría'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('reportes', $permisos_denegados_array)): ?>
            <a href="reportes.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('reportes.php', $pagina_actual); ?>">
                <i class="fas fa-file-invoice-dollar text-sm shrink-0 <?php echo verificarIconoActivo('reportes.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['reportes'] ?? 'Reportes Generales'; ?></span>
            </a>
            <?php endif; ?>
        
        <?php elseif ($rolUsuario == 2): // CONDUCTOR ?>
            <a href="conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva(['conductor.php', 'dashboard_conductor.php'], $pagina_actual); ?>">
                <i class="fas fa-chart-pie text-sm shrink-0 <?php echo verificarIconoActivo(['conductor.php', 'dashboard_conductor.php'], $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['dashboard'] ?? 'Dashboard'; ?></span>
            </a>
            
            <?php if (tiene_acceso_sb('ver_rutas', $permisos_denegados_array)): ?>
            <a href="viajes_conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes_conductor.php', $pagina_actual); ?>">
                <i class="fas fa-route text-sm shrink-0 <?php echo verificarIconoActivo('viajes_conductor.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Mis Viajes</span>
            </a>
            <?php endif; ?>
            
            <a href="viaje_asignado.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viaje_asignado.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('viaje_asignado.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Viaje Asignado</span>
            </a>
            
            <?php if (tiene_acceso_sb('ver_ranking', $permisos_denegados_array)): ?>
            <a href="resenas_conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva(['resenas_conductor.php', 'reseñas_conductor.php'], $pagina_actual); ?>">
                <i class="fas fa-star text-sm shrink-0 <?php echo verificarIconoActivo(['resenas_conductor.php', 'reseñas_conductor.php'], $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Mis Reseñas</span>
            </a>
            <?php endif; ?>

        <?php elseif ($rolUsuario == 3): // PASAJERO ?>
            <a href="pasajero.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('pasajero.php', $pagina_actual); ?>">
                <i class="fas fa-th-large text-sm shrink-0 <?php echo verificarIconoActivo('pasajero.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Inicio</span>
            </a>
            
            <?php if (tiene_acceso_sb('ver_viajes', $permisos_denegados_array)): ?>
            <a href="viajes_pasajero.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes_pasajero.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('viajes_pasajero.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Ver Viajes</span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('historial', $permisos_denegados_array)): ?>
            <a href="historial_pasajero.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('historial_pasajero.php', $pagina_actual); ?>">
                <i class="fas fa-history text-sm shrink-0 <?php echo verificarIconoActivo('historial_pasajero.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Historial</span>
            </a>
            <?php endif; ?>

            <?php if (tiene_acceso_sb('calificar', $permisos_denegados_array)): ?>
            <a href="calificar.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('calificar.php', $pagina_actual); ?>">
                <i class="fas fa-star text-sm shrink-0 <?php echo verificarIconoActivo('calificar.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['calificaciones'] ?? 'Calificaciones'; ?></span>
            </a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    <div class="p-3 border-t border-slate-200/80 dark:border-white/10 space-y-1.5 shrink-0">
        <select data-sget-language aria-label="Language" title="Seleccionar idioma / Select language" style="min-width: 108px;" class="sget-language-selector w-full px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-white/5 text-xs font-black text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-white/10 cursor-pointer">
            <option value="es" <?= $idiomaActualSidebar === 'es' ? 'selected' : '' ?>>🇪🇸 ESP</option>
            <option value="en" <?= $idiomaActualSidebar === 'en' ? 'selected' : '' ?>>🇺🇸 ENG</option>
        </select>

        <button onclick="abrirModalAyuda()" data-tooltip="Ayuda del Módulo" class="sidebar-link w-full flex items-center justify-center gap-2.5 bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 py-3 rounded-2xl text-xs font-bold transition-all border border-sky-500/20 cursor-pointer">
            <i class="fas fa-question-circle text-sm shrink-0"></i>
            <span class="sidebar-text truncate"><?php echo $lang['soporte'] ?? 'Soporte y Ayuda'; ?></span>
        </button>
    </div>
</aside>
<?php
/**
 * includes/sidebar.php
 * -----------------------------------------------------------------------------
 * MENÚ LATERAL DEL PANEL INTERNO
 * -----------------------------------------------------------------------------
 * Carga por el bootstrap: la sesión, la base de datos y la autorización ya
 * están listas cuando este partial se incluye. Antes abría su propia sesión y
 *aba Conexión mysqli, lo que obligaba a que todas las páginas hicieran lo
 * mismo y hacía imposible tener UNA sola política de seguridad.
 *
 * La visibilidad de cada enlace la decide `Auth::tieneAcceso()`, la MISMA
 * función que protegen los endpoints: el menú y el backend nunca discrepan.
 * -----------------------------------------------------------------------------
 */
if (!class_exists('Auth')) {
    require_once dirname(__DIR__) . '/core/bootstrap.php';
}

// Mismo partial que header.php: se define una sola vez aunque se incluya dos veces.
require_once __DIR__ . '/i18n.php';
$idiomaActualSidebar = $idiomaActual;

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

// --- Sistema de acceso: la MISMA decisión que en el backend ----------------
//
// ANTES: `tiene_acceso_sb($permiso, $permisos_denegados_array)` era una lista
// de DENEGADOS calculada con una consulta propia sobre `usuario_permisos`. Eso
// tiempos que `Auth::tieneAcceso()` y, sobre todo, daba la OPPINIÓN: si el
// permiso no aparecía en la lista de denegados, el enlace se mostraba. Un
// menú lleno de enlaces visibles no es una medida de seguridad, y cualquier
// diferencia con el backend se traducía en pantallas a las que se entraba y
// luego salía con «Acceso restringido».
//
// AHORA: una sola fuente de verdad (`core/Auth.php`, mínimo privilegio) para
// el menú Y para los endpoints. Lo que no está concedido, no se muestra; y si
// alguien teclea la URL, el endpoint lo vuelve a comprobar.
$moduloActual = basename($_SERVER['PHP_SELF'], '.php');
?>

<?php /* i18n.php ya emitted data-language, SGET_LANGUAGE_URL e i18n.js */ ?>

<style>
    /* TRANSICIONES Y COLAPSO FLUIDO */
    #sidebar-menu, #main-content-wrapper { 
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; 
    }
    
    /* Estado Colapsado en Escritorio */
    body.sidebar-collapsed #sidebar-menu { 
        width: 5.5rem !important; 
    }
    @media (min-width: 1024px) {
        body.sidebar-collapsed #main-content-wrapper {
            margin-left: 7rem !important;
        }
    }
    body.sidebar-collapsed #sidebar-menu .sidebar-text { 
        opacity: 0 !important; 
        display: none !important; 
        width: 0 !important; 
    }
    body.sidebar-collapsed #sidebar-menu nav a, 
    body.sidebar-collapsed #sidebar-menu button { 
        justify-content: center !important; 
        padding-left: 0 !important; 
        padding-right: 0 !important; 
        width: 3.5rem !important; 
        margin-left: auto !important; 
        margin-right: auto !important; 
    }
    body.sidebar-collapsed #sidebar-menu nav a i, 
    body.sidebar-collapsed #sidebar-menu button i { 
        margin: 0 !important; 
        font-size: 1.15rem !important; 
    }
    
    /* Adaptación Responsiva para Pantallas Pequeñas */
    @media (max-width: 1023px) {
        #sidebar-menu { 
            transform: translateX(-110%); 
        }
        body.sidebar-open #sidebar-menu { 
            transform: translateX(0) !important; 
        }
    }
</style>

<aside id="sidebar-menu" class="w-64 fixed top-4 bottom-4 left-4 h-[calc(100vh-2rem)] z-50 rounded-[28px] shadow-2xl flex flex-col overflow-hidden border bg-white/95 dark:bg-[#0f172a]/85 border-slate-200/90 dark:border-white/10 backdrop-blur-xl transition-all duration-300">
    
    <!-- BRANDING LOGO -->
    <div class="px-4 py-6 border-b border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-[#1e293b]/20 transition-colors duration-300 flex items-center justify-center min-h-[85px]">
        <img src="../img/largo-blanco.png" alt="Logo SGET" class="h-14 w-auto max-w-full object-contain block dark:hidden">
        <img src="../img/largo-negro.png" alt="Logo SGET" class="h-14 w-auto max-w-full object-contain hidden dark:block">
    </div>

    <!-- NAVEGACIÓN PRINCIPAL -->
    <nav class="mt-4 px-3 flex-grow overflow-y-auto space-y-1.5 text-xs custom-scrollbar">
        
        <?php if ($rolUsuario == 1): // ROL ADMINISTRADOR ?>
            
            <?php if (Auth::tieneAcceso('admin')): ?>
            <a href="admin.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('admin.php', $pagina_actual); ?>">
                <i class="fas fa-chart-pie text-sm shrink-0 <?php echo verificarIconoActivo('admin.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['dashboard'] ?? 'Dashboard'; ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (Auth::tieneAcceso('usuarios')): ?>
            <a href="usuarios.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('usuarios.php', $pagina_actual); ?>">
                <i class="fas fa-users-cog text-sm shrink-0 <?php echo verificarIconoActivo('usuarios.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['usuarios'] ?? 'Usuarios & Roles'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('asignaciones')): ?>
            <a href="asignaciones.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('asignaciones.php', $pagina_actual); ?>">
                <i class="fas fa-cash-register text-sm shrink-0 <?php echo verificarIconoActivo('asignaciones.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['recaudo'] ?? 'Recaudo & Abordaje'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('rutas')): ?>
            <a href="rutas.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('rutas.php', $pagina_actual); ?>">
                <i class="fas fa-route text-sm shrink-0 <?php echo verificarIconoActivo('rutas.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['rutas'] ?? 'Gestión de Rutas'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('viajes')): ?>
            <a href="viajes.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes.php', $pagina_actual); ?>">
                <i class="fas fa-calendar-alt text-sm shrink-0 <?php echo verificarIconoActivo('viajes.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['viajes'] ?? 'Programación Viajes'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('vehiculos')): ?>
            <a href="vehiculos.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('vehiculos.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('vehiculos.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['vehiculos'] ?? 'Flota de Vehículos'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('gestion_permisos')): ?>
            <a href="gestion_permisos.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('gestion_permisos.php', $pagina_actual); ?>">
                <i class="fas fa-key text-sm shrink-0 <?php echo verificarIconoActivo('gestion_permisos.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['permisos'] ?? 'Permisos'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('ranking_conductores')): ?>
            <a href="ranking_conductores.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('ranking_conductores.php', $pagina_actual); ?>">
                <i class="fas fa-star text-sm shrink-0 <?php echo verificarIconoActivo('ranking_conductores.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['calificaciones'] ?? 'Calificaciones'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('logs')): ?>
            <a href="logs.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('logs.php', $pagina_actual); ?>">
                <i class="fas fa-file-alt text-sm shrink-0 <?php echo verificarIconoActivo('logs.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['logs'] ?? 'Logs de Auditoría'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('reportes_pasajeros')): ?>
            <a href="reportes_pasajeros.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('reportes_pasajeros.php', $pagina_actual); ?>">
                <i class="fas fa-comment-dots text-sm shrink-0 <?php echo verificarIconoActivo('reportes_pasajeros.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['reportes_pasajeros'] ?? 'Reportes de Pasajeros'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('anuncios')): ?>
            <a href="anuncios.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('anuncios.php', $pagina_actual); ?>">
                <i class="fas fa-images text-sm shrink-0 <?php echo verificarIconoActivo('anuncios.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['anuncios'] ?? 'Anuncios'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('comunicados')): ?>
            <a href="comunicados.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('comunicados.php', $pagina_actual); ?>">
                <i class="fas fa-bullhorn text-sm shrink-0 <?php echo verificarIconoActivo('comunicados.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['comunicados'] ?? 'Comunicados'; ?></span>
            </a>
            <?php endif; ?>

            <?php if (Auth::tieneAcceso('reportes')): ?>
            <a href="reportes.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('reportes.php', $pagina_actual); ?>">
                <i class="fas fa-chart-pie text-sm shrink-0 <?php echo verificarIconoActivo('reportes.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['reportes'] ?? 'Panel de Información'; ?></span>
            </a>
            <?php endif; ?>
        
        <?php elseif ($rolUsuario == 2): // ROL CONDUCTOR ?>
            
            <a href="conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva(['conductor.php', 'dashboard_conductor.php'], $pagina_actual); ?>">
                <i class="fas fa-chart-pie text-sm shrink-0 <?php echo verificarIconoActivo(['conductor.php', 'dashboard_conductor.php'], $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate"><?php echo $lang['dashboard'] ?? 'Dashboard'; ?></span>
            </a>
            
            <a href="viajes_conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes_conductor.php', $pagina_actual); ?>">
                <i class="fas fa-route text-sm shrink-0 <?php echo verificarIconoActivo('viajes_conductor.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Mis Viajes</span>
            </a>
            
            <a href="viaje_asignado.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva('viaje_asignado.php', $pagina_actual); ?>">
                <i class="fas fa-bus text-sm shrink-0 <?php echo verificarIconoActivo('viaje_asignado.php', $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Viaje Asignado</span>
            </a>
            
            <a href="resenas_conductor.php" class="sidebar-link flex items-center space-x-3.5 px-4 py-3 rounded-2xl transition-all duration-200 group <?php echo verificarClaseActiva(['resenas_conductor.php', 'reseñas_conductor.php'], $pagina_actual); ?>">
                <i class="fas fa-star text-sm shrink-0 <?php echo verificarIconoActivo(['resenas_conductor.php', 'reseñas_conductor.php'], $pagina_actual); ?>"></i>
                <span class="sidebar-text truncate">Mis Reseñas</span>
            </a>

        <?php elseif ($rolUsuario == 3): // ROL PASAJERO ?>
            
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
                <span class="sidebar-text truncate"><?php echo $lang['calificaciones'] ?? 'Calificaciones'; ?></span>
            </a>

        <?php endif; ?>
    </nav>

    <!--
        SOPORTE Y AYUDA
        El botón «?» que vivía aquí se retiró a propósito: ahora hay UN solo
        acceso global, el botón flotante «Ayudas del sistema» de la esquina
        inferior derecha (views/modals/ayuda.php), que además cambia de contenido
        según el módulo. Un «?» por pantalla obligaba a duplicar el modal y
        acababa teniendo cuatro modales de ayuda distintos con el mismo id.
    -->
</aside>

<!-- SCRIPT ROBUSTO DE CONTROL DEL SIDEBAR -->
<script>
(function() {
    function initSidebarToggle() {
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('#btnToggleSidebar');
            if (btn) {
                e.preventDefault();
                if (window.innerWidth >= 1024) {
                    document.body.classList.toggle('sidebar-collapsed');
                    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                    localStorage.setItem('sget_sidebar_collapsed', isCollapsed ? 'true' : 'false');
                } else {
                    document.body.classList.toggle('sidebar-open');
                }
            }
        });

        if (window.innerWidth >= 1024 && localStorage.getItem('sget_sidebar_collapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarToggle);
    } else {
        initSidebarToggle();
    }
})();
</script>
<?php
// Archivo: header.php
// Este archivo se incluye en la parte superior del <main> de cada página.

// Aseguramos que la variable de nombre de usuario exista
$nombreRealHeader = isset($_SESSION['nombre_usuario']) ? htmlspecialchars($_SESSION['nombre_usuario']) : "Administrador";

// Detectamos el título de la página actual basándonos en el archivo
$pagina_titulo = basename($_SERVER['PHP_SELF'], '.php');
$submodulo = "Inicio";

if ($pagina_titulo === 'admin') $submodulo = "Inicio";
else if ($pagina_titulo === 'usuarios') $submodulo = "Gestión de Usuarios";
else if ($pagina_titulo === 'asignaciones') $submodulo = "Asignaciones";
else if ($pagina_titulo === 'rutas') $submodulo = "Rutas de Transporte";
else if ($pagina_titulo === 'viajes') $submodulo = "Control de Viajes";
else if ($pagina_titulo === 'vehiculos') $submodulo = "Inventario de Vehículos";
else if ($pagina_titulo === 'ranking_conductores') $submodulo = "Calificaciones";
else if ($pagina_titulo === 'reportes') $submodulo = "Módulo de Reportes";
?>

<header class="h-16 bg-white/80 dark:bg-[#1e293b]/80 backdrop-blur-md border-b border-slate-200 dark:border-white/5 flex items-center justify-between px-8 sticky top-0 z-10 transition-colors duration-300">
    <div class="text-slate-400 dark:text-slate-500 font-medium text-sm tracking-wide">
        Dashboard &nbsp;/&nbsp; <span class="text-slate-800 dark:text-white font-semibold"><?php echo $submodulo; ?></span>
    </div>

    <div class="flex items-center space-x-6">
        <button id="themeToggle" class="text-slate-400 hover:text-amber-400 p-2 hover:bg-slate-100 dark:hover:bg-white/5 rounded-xl transition-all duration-200 text-sm" title="Cambiar Tema">
            <i id="themeIcon" class="fas fa-moon text-base"></i>
        </button>

        <div class="hidden md:block text-right">
            <p class="text-sm font-bold text-slate-800 dark:text-white"><?php echo $nombreRealHeader; ?></p>
            <p class="text-[10px] text-emerald-500 dark:text-emerald-400 font-extrabold uppercase tracking-widest flex items-center justify-end gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 inline-block animate-pulse"></span> Online
            </p>
        </div>
        
        <!-- Avatar corregido con clases nativas de Tailwind -->
        <div class="w-10 h-10 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-full flex items-center justify-center text-white font-bold text-base shadow-md shadow-blue-500/20 shrink-0">
            <?php echo strtoupper(substr($nombreRealHeader, 0, 1)); ?>
        </div>

        <a href="../assets/cerrar.php" class="flex items-center space-x-1 text-slate-400 hover:text-red-400 p-2 hover:bg-slate-100 dark:hover:bg-white/5 rounded-xl transition-all duration-200 text-sm" title="Cerrar Sesión">
            <i class="fas fa-sign-out-alt text-base"></i> 
        </a>
    </div>
</header>

<script>
    const themeToggleBtn = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    function actualizarIcono(isDark) {
        if (themeIcon) {
            if (isDark) {
                themeIcon.className = "fas fa-sun text-base text-amber-400";
            } else {
                themeIcon.className = "fas fa-moon text-base text-slate-600";
            }
        }
    }

    // Inicializar el ícono apenas se renderice el header
    actualizarIcono(document.documentElement.classList.contains('dark'));

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const esOscuro = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', esOscuro ? 'dark' : 'light');
            actualizarIcono(esOscuro);
            
            // Si existe un gráfico de Chart.js en la pantalla, actualiza el borde de la dona de forma segura
            if (typeof chartInstance !== 'undefined') {
                chartInstance.options.datasets[0].borderColor = esOscuro ? '#1e293b' : '#ffffff';
                chartInstance.update();
            }
        });
    }
</script>
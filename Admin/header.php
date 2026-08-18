<?php
// Archivo: header.php
$nombreRealHeader = isset($_SESSION['nombre_usuario']) ? htmlspecialchars($_SESSION['nombre_usuario']) : "Administrador";

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

<header class="h-16 bg-white/80 dark:bg-[#1e293b]/80 backdrop-blur-md border-b border-slate-200 dark:border-white/5 flex items-center justify-between px-6 sticky top-0 z-10 transition-colors duration-300">
    
    <!-- LADO IZQUIERDO: Botón Toggle y Breadcrumb -->
    <div class="flex items-center gap-4">
        <!-- BOTÓN TOGGLE (Label que activa el checkbox del sidebar) -->
        <label for="sidebar-toggle-checkbox" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors shadow-sm cursor-pointer" title="Ocultar/Mostrar Menú">
            <i class="fas fa-bars text-lg"></i>
        </label>

        <div class="text-slate-400 dark:text-slate-500 font-medium text-sm tracking-wide hidden sm:block">
            Dashboard &nbsp;/&nbsp; <span class="text-slate-800 dark:text-white font-semibold"><?php echo $submodulo; ?></span>
        </div>
    </div>

    <!-- LADO DERECHO: Acciones y Perfil -->
    <div class="flex items-center space-x-4 sm:space-x-6">
        <button id="themeToggle" class="text-slate-400 hover:text-amber-400 p-2 hover:bg-slate-100 dark:hover:bg-white/5 rounded-xl transition-all duration-200 text-sm" title="Cambiar Tema">
            <i id="themeIcon" class="fas fa-moon text-base"></i>
        </button>

        <div class="hidden md:block text-right">
            <p class="text-sm font-bold text-slate-800 dark:text-white"><?php echo $nombreRealHeader; ?></p>
            <p class="text-[10px] text-emerald-500 dark:text-emerald-400 font-extrabold uppercase tracking-widest flex items-center justify-end gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 inline-block animate-pulse"></span> Online
            </p>
        </div>
        
        <div class="w-10 h-10 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-full flex items-center justify-center text-white font-bold text-base shadow-md shadow-blue-500/20 shrink-0">
            <?php echo strtoupper(substr($nombreRealHeader, 0, 1)); ?>
        </div>

        <a href="../assets/cerrar.php" class="flex items-center space-x-1 text-slate-400 hover:text-red-400 p-2 hover:bg-slate-100 dark:hover:bg-white/5 rounded-xl transition-all duration-200 text-sm" title="Cerrar Sesión">
            <i class="fas fa-sign-out-alt text-base"></i> 
        </a>
    </div>
</header>

<!-- MODAL DE ADVERTENCIA POR INACTIVIDAD (Oculto por defecto) -->
<div id="inactivityModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl text-center space-y-4">
        <div class="w-14 h-14 bg-amber-100 dark:bg-amber-900/30 text-amber-500 rounded-full flex items-center justify-center mx-auto text-2xl">
            <i class="fas fa-user-clock"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-800 dark:text-white">¡Inactividad Detectada!</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Tu sesión está a punto de cerrarse por falta de actividad en el sistema.
        </p>
        <div class="text-2xl font-black text-red-500 dark:text-red-400 bg-slate-100 dark:bg-slate-900 py-3 rounded-xl">
            Cierre en: <span id="countdownTimer">30</span> segundos
        </div>
        <button id="btnContinuar" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors shadow-lg shadow-blue-600/20">
            Continuar en la sesión
        </button>
    </div>
</div>

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

    actualizarIcono(document.documentElement.classList.contains('dark'));

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const esOscuro = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', esOscuro ? 'dark' : 'light');
            actualizarIcono(esOscuro);
            
            if (typeof chartInstance !== 'undefined') {
                chartInstance.options.datasets[0].borderColor = esOscuro ? '#1e293b' : '#ffffff';
                chartInstance.update();
            }
        });
    }

    // --- LÓGICA DE INACTIVIDAD Y CUENTA REGRESIVA ---
    const TOTAL_INACTIVITY_TIME = 3 * 60 * 1000; // 3 minutos en total
    const WARNING_TIME = 30 * 1000;              // Últimos 30 segundos

    let inactivityTimer;
    let countdownInterval;
    let timeLeft = 30;

    const modal = document.getElementById('inactivityModal');
    const countdownSpan = document.getElementById('countdownTimer');
    const btnContinuar = document.getElementById('btnContinuar');

    function iniciarTemporizadorInactividad() {
        clearTimeout(inactivityTimer);
        clearInterval(countdownInterval);
        
        // Ocultar modal si estaba visible
        if (modal) modal.classList.add('hidden');
        
        // Programar la aparición de la advertencia a los 2 minutos y 30 segundos (3 min - 30 seg)
        inactivityTimer = setTimeout(() => {
            mostrarAdvertenciaCierre();
        }, TOTAL_INACTIVITY_TIME - WARNING_TIME);
    }

    function mostrarAdvertenciaCierre() {
        timeLeft = 30;
        if (countdownSpan) countdownSpan.textContent = timeLeft;
        if (modal) modal.classList.remove('hidden');

        countdownInterval = setInterval(() => {
            timeLeft--;
            if (countdownSpan) countdownSpan.textContent = timeLeft;

            if (timeLeft <= 0) {
                clearInterval(countdownInterval);
                // Redirigir al archivo que destruye la sesión
                window.location.href = "../assets/cerrar.php";
            }
        }, 1000);
    }

    // Eventos que consideran que el usuario sigue activo
    const eventosUsuario = ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart', 'click'];

    eventosUsuario.forEach(evento => {
        document.addEventListener(evento, () => {
            // Solo reiniciamos si el modal NO está visible (si está visible, debe hacer clic explícito en el botón o interactuar para salir)
            if (modal && modal.classList.contains('hidden')) {
                iniciarTemporizadorInactividad();
            }
        }, true);
    });

    // Botón de continuar sesión dentro del modal
    if (btnContinuar) {
        btnContinuar.addEventListener('click', () => {
            iniciarTemporizadorInactividad();
        });
    }

    // Arrancar el conteo al cargar el header en cualquier página
    iniciarTemporizadorInactividad();
</script>
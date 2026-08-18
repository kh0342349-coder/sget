<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nombreRealHeader = htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Conductor', ENT_QUOTES, 'UTF-8');
$inicialUsuario = !empty($nombreRealHeader) ? strtoupper(substr($nombreRealHeader, 0, 1)) : 'C';
?>

<!-- HEADER CONDUCTOR -->
<header class="h-20 bg-white/80 dark:bg-[#0b0f19]/80 backdrop-blur-md border-b border-slate-200 dark:border-white/5 flex items-center justify-between px-8 sticky top-0 z-40 transition-colors duration-300">
    
    <div class="flex items-center gap-3">
        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wider uppercase font-mono">Panel de Operaciones</span>
    </div>

    <div class="flex items-center space-x-5">
        <!-- Botón de Tema Integrado -->
        <button type="button" id="themeToggle" class="btn-theme-toggle w-10 h-10 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-white/10 rounded-xl transition-all duration-200 flex items-center justify-center cursor-pointer shadow-sm" title="Cambiar Tema">
            <i id="themeIcon" class="fas fa-moon text-base pointer-events-none"></i>
        </button>

        <div class="h-8 w-[1px] bg-slate-200 dark:bg-white/10"></div>

        <div class="hidden md:block text-right">
            <p class="text-xs font-bold text-slate-900 dark:text-white leading-tight"><?php echo $nombreRealHeader; ?></p>
            <p class="text-[10px] text-emerald-500 dark:text-emerald-400 font-extrabold uppercase tracking-widest flex items-center justify-end gap-1 mt-0.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 inline-block animate-pulse"></span> Online
            </p>
        </div>
        
        <!-- Avatar -->
        <div class="w-9 h-9 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-md shadow-blue-500/20 shrink-0">
            <?php echo $inicialUsuario; ?>
        </div>

        <!-- Cerrar Sesión -->
        <a href="../assets/cerrar.php" class="w-10 h-10 bg-slate-100 dark:bg-white/5 hover:bg-red-500/10 text-slate-500 hover:text-red-500 dark:text-slate-400 dark:hover:text-red-400 border border-slate-200 dark:border-white/10 rounded-xl transition-all duration-200 flex items-center justify-center" title="Cerrar Sesión">
            <i class="fas fa-sign-out-alt text-base pointer-events-none"></i> 
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
        <button id="btnContinuar" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors shadow-lg shadow-blue-600/25 cursor-pointer">
            Continuar en la sesión
        </button>
    </div>
</div>

<!-- SCRIPT AUTOCONTENIDO Y SINCRONIZADO (Tema + Inactividad) -->
<script>
(function() {
    // --- GESTIÓN DE TEMA ---
    function actualizarIcono(esOscuro) {
        const iconos = document.querySelectorAll('#themeIcon, .theme-icon');
        iconos.forEach(icon => {
            if (esOscuro) {
                icon.className = 'fas fa-sun text-amber-400 text-base pointer-events-none';
            } else {
                icon.className = 'fas fa-moon text-slate-600 text-base pointer-events-none';
            }
        });
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#themeToggle, #theme-toggle, .btn-theme-toggle');
        if (!btn) return;
        
        e.preventDefault();
        const esOscuroActualmente = document.documentElement.classList.contains('dark');
        const nuevoEstado = !esOscuroActualmente;

        if (nuevoEstado) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
            localStorage.setItem('color-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
            localStorage.setItem('color-theme', 'light');
        }

        actualizarIcono(nuevoEstado);
    });

    document.addEventListener('DOMContentLoaded', function() {
        actualizarIcono(document.documentElement.classList.contains('dark'));
    });

    // --- GESTIÓN DE INACTIVIDAD (3 Minutos + 30s Cuenta Regresiva) ---
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
        
        // Programar el aviso a los 2 minutos y 30 segundos
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
                // Redirigir al script de cierre de sesión
                window.location.href = "../assets/cerrar.php";
            }
        }, 1000);
    }

    // Eventos que detectan actividad del conductor
    const eventosUsuario = ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart', 'click'];

    eventosUsuario.forEach(evento => {
        document.addEventListener(evento, () => {
            // Solo reiniciar si el modal no se encuentra visible
            if (modal && modal.classList.contains('hidden')) {
                iniciarTemporizadorInactividad();
            }
        }, true);
    });

    // Botón para mantener la sesión activa
    if (btnContinuar) {
        btnContinuar.addEventListener('click', () => {
            iniciarTemporizadorInactividad();
        });
    }

    // Arrancar el temporizador al cargar el componente
    iniciarTemporizadorInactividad();
})();
</script>
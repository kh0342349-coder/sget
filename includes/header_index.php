<?php
// 1. Evitar caché del navegador para que no muestre la vista renderizada anterior
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Comprobar si realmente hay una sesión activa válida
$estaAutenticado = !empty($_SESSION['nombre_usuario']) || !empty($_SESSION['documento']);
$nombreRealHeader = $estaAutenticado ? htmlspecialchars($_SESSION['nombre_usuario']) : "";

$pagina_actual = basename($_SERVER['PHP_SELF']);
?>

<!-- HEADER A LO LARGO DE LA PANTALLA CON EFECTO DIFUMINADO -->
<header class="w-full fixed top-0 left-0 right-0 z-50 py-3 px-6 md:px-12 bg-white/60 dark:bg-slate-900/60 backdrop-blur-md border-b border-slate-200/50 dark:border-white/10 transition-colors duration-300">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
        
        <!-- LOGO (IZQUIERDA) -->
        <div class="flex items-center">
            <a href="index.php" class="flex items-center group">
                <!-- Logo Tema Claro -->
                <img id="logo-header-light" 
                     src="img/largo-blanco.png" 
                     alt="SGET Logo" 
                     class="h-9 md:h-11 w-auto object-contain block dark:hidden filter drop-shadow-sm group-hover:scale-105 transition-all duration-300">
                
                <!-- Logo Tema Oscuro -->
                <img id="logo-header-dark" 
                     src="img/largo-negro.png" 
                     alt="SGET Logo" 
                     class="h-9 md:h-11 w-auto object-contain hidden dark:block filter drop-shadow-md group-hover:scale-105 transition-all duration-300">
            </a>
        </div>

        <!-- ISLA FLOTANTE DE NAVEGACIÓN (CENTRO) -->
        <nav class="hidden lg:flex items-center gap-1 bg-slate-100/60 dark:bg-white/5 border border-slate-200/60 dark:border-white/10 p-1.5 rounded-full shadow-inner text-xs font-medium">
            <a href="index.php#inicio" class="px-5 py-2 rounded-full transition-all duration-200 bg-sky-500 text-white font-bold shadow-sm hover:bg-sky-400">
                Inicio
            </a>
            <a href="index.php#viajes-disponibles" class="px-4 py-2 rounded-full text-slate-700 dark:text-slate-200 hover:text-sky-600 dark:hover:text-white hover:bg-slate-200/50 dark:hover:bg-white/10 transition-all duration-200">
                Viaja con nosotros
            </a>
            <a href="index.php#servicios" class="px-4 py-2 rounded-full text-slate-700 dark:text-slate-200 hover:text-sky-600 dark:hover:text-white hover:bg-slate-200/50 dark:hover:bg-white/10 transition-all duration-200">
                Servicios
            </a>
            <a href="#" class="px-4 py-2 rounded-full text-slate-700 dark:text-slate-200 hover:text-sky-600 dark:hover:text-white hover:bg-slate-200/50 dark:hover:bg-white/10 transition-all duration-200">
                Nosotros
            </a>
            <a href="#" class="px-4 py-2 rounded-full text-slate-700 dark:text-slate-200 hover:text-sky-600 dark:hover:text-white hover:bg-slate-200/50 dark:hover:bg-white/10 transition-all duration-200">
                Contacto
            </a>
        </nav>

        <!-- ACCIONES (DERECHA) -->
        <div class="flex items-center gap-2.5">
            
            <!-- TOGGLE TEMA -->
            <button id="theme-toggle" type="button" class="w-10 h-10 rounded-full bg-slate-100/70 dark:bg-slate-800/70 text-slate-700 dark:text-amber-300 flex items-center justify-center transition-all cursor-pointer border border-slate-200 dark:border-slate-700 shadow-sm hover:scale-105" title="Cambiar Tema">
                <i id="theme-toggle-icon" class="fas fa-moon text-sm"></i>
            </button>

            <!-- BOTONES DE INICIO Y REGISTRO -->
            <?php if (!$estaAutenticado): ?>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="abrirPanel('panelLogin')" class="px-4 py-2 rounded-full text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-100/70 dark:bg-slate-800/70 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all border border-slate-200 dark:border-slate-700 shadow-sm cursor-pointer">
                        Iniciar Sesión
                    </button>
                    <button type="button" onclick="abrirPanel('panelRegistro')" class="px-4 py-2 rounded-full text-xs font-extrabold bg-amber-400 text-slate-950 hover:bg-amber-300 transition-all cursor-pointer shadow-md hover:shadow-lg">
                        Registrarse
                    </button>
                </div>
            <?php endif; ?>

            <!-- TARJETA DE PERFIL (SI HAY SESIÓN) -->
            <?php if ($estaAutenticado && !empty($nombreRealHeader)): ?>
                <div class="flex items-center gap-2.5 pl-2.5 py-1 pr-1 bg-slate-100/60 dark:bg-slate-800/60 rounded-full border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="text-right hidden sm:block pl-2">
                        <p class="text-xs font-bold text-slate-800 dark:text-white leading-tight"><?php echo $nombreRealHeader; ?></p>
                        <p class="text-[9px] text-emerald-500 font-extrabold uppercase tracking-widest flex items-center justify-end gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span> Online
                        </p>
                    </div>
                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-sky-500 to-blue-600 text-white font-bold text-xs flex items-center justify-center shadow-md">
                        <?php echo strtoupper(substr($nombreRealHeader, 0, 1)); ?>
                    </div>
                    <a href="../assets/cerrar.php" onclick="sessionStorage.clear();" class="p-1.5 text-slate-400 hover:text-red-500 transition-colors" title="Cerrar Sesión">
                        <i class="fas fa-sign-out-alt text-xs"></i>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</header>

<!-- SCRIPT DE MANEJO DE TEMA -->
<script>
    (function() {
        const themeToggleBtn = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-toggle-icon');

        function actualizarIcono(esOscuro) {
            if (!themeIcon) return;
            if (esOscuro) {
                themeIcon.className = 'fas fa-sun text-sm text-amber-400';
            } else {
                themeIcon.className = 'fas fa-moon text-sm text-slate-600';
            }
        }

        const esOscuroInicial = document.documentElement.classList.contains('dark');
        actualizarIcono(esOscuroInicial);

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                const esOscuro = document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', esOscuro ? 'dark' : 'light');
                actualizarIcono(esOscuro);
            });
        }
    })();
</script>
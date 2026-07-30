<?php
// 1. Evitar caché del navegador para que no muestre la vista renderizada anterior
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Comprobar si realmente hay una sesión activa válida
$estaAutenticado = !empty($_SESSION['nombre_usuario']) || !empty($_SESSION['documento']);
$nombreRealHeader = $estaAutenticado ? htmlspecialchars($_SESSION['nombre_usuario']) : "";

$pagina_actual = basename($_SERVER['PHP_SELF']);
?>

<!-- HEADER CONTENEDOR PRINCIPAL -->
<header class="w-full fixed top-0 left-0 right-0 z-50 pt-5 px-6 md:px-12 pointer-events-none">
    <div class="max-w-7xl mx-auto flex items-center justify-between pointer-events-auto">
        
        <!-- LOGO FUERA DE LA ISLA (IZQUIERDA) -->
        <div class="flex items-center">
            <a href="index.php" class="flex items-center group">
                <!-- Logo para Tema Claro -->
                <img id="logo-header-light" 
                     src="img/largo-blanco.png" 
                     alt="SGET Logo" 
                     class="h-11 md:h-14 w-auto object-contain block dark:hidden filter drop-shadow-sm group-hover:scale-105 transition-all duration-300">
                
                <!-- Logo para Tema Oscuro -->
                <img id="logo-header-dark" 
                     src="img/largo-negro.png" 
                     alt="SGET Logo" 
                     class="h-11 md:h-14 w-auto object-contain hidden dark:block filter drop-shadow-md group-hover:scale-105 transition-all duration-300">
            </a>
        </div>

        <!-- CÁPSULA / ISLA FLOTANTE DE NAVEGACIÓN (CENTRO) -->
        <nav class="hidden lg:flex items-center gap-1.5 bg-slate-900/90 dark:bg-slate-900/85 backdrop-blur-md border border-slate-700/60 dark:border-white/10 p-1.5 rounded-full shadow-2xl text-xs font-medium">
            <a href="index.php#inicio" class="px-5 py-2 rounded-full transition-all duration-200 bg-sky-500 text-slate-950 font-bold shadow-md hover:bg-sky-400">
                Inicio
            </a>
            <a href="index.php#viajes-disponibles" class="px-4 py-2 rounded-full text-slate-200 hover:text-white hover:bg-white/10 transition-all duration-200">
                Viaja con nosotros
            </a>
            <a href="index.php#servicios" class="px-4 py-2 rounded-full text-slate-200 hover:text-white hover:bg-white/10 transition-all duration-200">
                Servicios
            </a>
            <a href="index.php#rutas" class="px-4 py-2 rounded-full text-slate-200 hover:text-white hover:bg-white/10 transition-all duration-200">
                Rutas y horarios
            </a>
            <a href="#" class="px-4 py-2 rounded-full text-slate-200 hover:text-white hover:bg-white/10 transition-all duration-200">
                Nosotros
            </a>
            <a href="#" class="px-4 py-2 rounded-full text-slate-200 hover:text-white hover:bg-white/10 transition-all duration-200">
                Contacto
            </a>
        </nav>

        <!-- ACCIONES / USUARIO / MODO OSCURO (DERECHA - FUERA DE LA ISLA) -->
        <div class="flex items-center gap-3">
            
            <!-- TOGGLE TEMA -->
            <button id="theme-toggle" type="button" class="w-10 h-10 rounded-full bg-white dark:bg-slate-800 text-slate-700 dark:text-amber-300 flex items-center justify-center transition-all cursor-pointer border border-slate-200 dark:border-slate-700 shadow-md hover:scale-105" title="Cambiar Tema">
                <i id="theme-toggle-icon" class="fas fa-moon text-sm"></i>
            </button>

            <!-- BOTONES DE INICIO Y REGISTRO (SIEMPRE VISIBLES COMO EN TU DISEÑO) -->
            <div class="flex items-center gap-2">
                <button type="button" onclick="abrirPanel('panelLogin')" class="px-4 py-2 rounded-full text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all border border-slate-200 dark:border-slate-700 shadow-sm cursor-pointer">
                    Iniciar Sesión
                </button>
                <button type="button" onclick="abrirPanel('panelRegistro')" class="px-4 py-2 rounded-full text-xs font-extrabold bg-amber-400 text-slate-950 hover:bg-amber-300 transition-all cursor-pointer shadow-md hover:shadow-lg">
                    Registrarse
                </button>
            </div>

            <!-- TARJETA DE PERFIL (SOLO SI HAY SESIÓN ACTIVA Y NOMBRE VÁLIDO) -->
            <?php if ($estaAutenticado && !empty($nombreRealHeader)): ?>
                <div class="hidden sm:flex items-center gap-2.5 pl-2 border-l border-slate-300/40 dark:border-slate-700/60">
                    <div class="text-right">
                        <p class="text-xs font-bold text-slate-800 dark:text-white leading-tight drop-shadow-sm"><?php echo $nombreRealHeader; ?></p>
                        <p class="text-[9px] text-emerald-500 dark:text-emerald-400 font-extrabold uppercase tracking-widest flex items-center justify-end gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span> Online
                        </p>
                    </div>
                    <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-sky-500 to-blue-600 text-white font-bold text-xs flex items-center justify-center shadow-md border border-white/20">
                        <?php echo strtoupper(substr($nombreRealHeader, 0, 1)); ?>
                    </div>
                    <a href="../assets/cerrar.php" onclick="sessionStorage.clear();" class="p-1.5 text-slate-500 dark:text-slate-300 hover:text-red-500 transition-colors" title="Cerrar Sesión">
                        <i class="fas fa-sign-out-alt text-sm"></i>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</header>

<!-- SCRIPT DE TEMA Y LOGO -->
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
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nombreRealHeader = htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Conductor', ENT_QUOTES, 'UTF-8');
$inicialUsuario = !empty($nombreRealHeader) ? strtoupper(substr($nombreRealHeader, 0, 1)) : 'C';
?>

<!-- HEADER CORREGIDO CON MARGEN IZQUIERDO -->
<header class="w-[calc(100%-16rem)] ml-64 h-16 bg-white dark:bg-[#0f172a] border-b border-slate-200 dark:border-white/5 px-8 flex items-center justify-between shrink-0 transition-colors duration-300">
    
    <!-- LADO IZQUIERDO: MIGA DE PAN / RUTA -->
    <div class="flex items-center gap-2 text-xs font-semibold">
        <span class="text-slate-400 dark:text-slate-500">SGET</span>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-800 dark:text-slate-200 font-bold">Panel Conductor</span>
    </div>

    <!-- LADO DERECHO: CONTROLES Y PERFIL -->
    <div class="flex items-center gap-4">
        
        <!-- BOTÓN CAMBIO DE TEMA -->
        <button id="theme-toggle" type="button" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-white transition-all cursor-pointer border border-transparent hover:border-slate-200 dark:hover:border-white/10" title="Cambiar Tema">
            <i id="theme-toggle-dark-icon" class="fas fa-moon text-sm hidden"></i>
            <i id="theme-toggle-light-icon" class="fas fa-sun text-sm hidden text-amber-400"></i>
        </button>

        <div class="h-5 w-[1px] bg-slate-200 dark:bg-white/10"></div>

        <!-- INFORMACIÓN DE USUARIO -->
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-slate-900 dark:text-white leading-tight"><?php echo $nombreRealHeader; ?></p>
                <p class="text-[9px] text-emerald-500 font-extrabold uppercase tracking-widest flex items-center justify-end gap-1 mt-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span> ONLINE
                </p>
            </div>

            <!-- AVATAR -->
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-black text-xs flex items-center justify-center shadow-md shadow-blue-500/20">
                <?php echo $inicialUsuario; ?>
            </div>

            <!-- BOTÓN CERRAR SESIÓN -->
            <a href="../assets/cerrar.php" onclick="sessionStorage.clear();" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition-all ml-1" title="Cerrar Sesión">
                <i class="fas fa-sign-out-alt text-sm"></i>
            </a>
        </div>

    </div>
</header>
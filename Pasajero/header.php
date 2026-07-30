<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nombreUsuarioHeader = $_SESSION['nombre_usuario'] ?? "Usuario";
$rolUsuarioHeader = $_SESSION['rol'] ?? 3;

$textoRol = "Pasajero Activo";
if ($rolUsuarioHeader == 1) {
    $textoRol = "Administrador";
} elseif ($rolUsuarioHeader == 2) {
    $textoRol = "Conductor Activo";
}
?>

<header class="h-16 bg-white/80 dark:bg-[#1e293b]/50 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-8 sticky top-0 z-20 flex-shrink-0 transition-colors duration-300">
    <!-- Migas de pan -->
    <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <span class="font-bold tracking-wider text-slate-700 dark:text-slate-200">SGET</span>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-slate-600 dark:text-slate-300 font-medium">Panel Principal</span>
    </div>

    <!-- Controles de la derecha -->
    <div class="flex items-center gap-4">
        
        <!-- BOTÓN DE CAMBIO DE TEMA -->
        <button id="theme-toggle" type="button" class="w-10 h-10 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-yellow-400 border border-slate-200 dark:border-slate-700 rounded-xl flex items-center justify-center text-sm transition-all duration-200 hover:scale-105 cursor-pointer shadow-sm" title="Cambiar tema">
            <!-- Ícono para Modo Oscuro -->
            <svg id="theme-toggle-dark-icon" class="hidden w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
            </svg>
            <!-- Ícono para Modo Claro -->
            <svg id="theme-toggle-light-icon" class="hidden w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path>
            </svg>
        </button>

        <!-- INFORMACIÓN DE USUARIO -->
        <div class="flex items-center gap-3 pl-2 border-l border-slate-200 dark:border-slate-800">
            <div class="text-right">
                <div class="text-xs font-bold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($nombreUsuarioHeader, ENT_QUOTES, 'UTF-8'); ?></div>
                <span class="text-[9px] text-emerald-600 dark:text-emerald-400 font-bold flex items-center justify-end gap-1 uppercase tracking-tight">
                    <?php echo $textoRol; ?>
                </span>
            </div>
            <div class="h-9 w-9 rounded-xl bg-blue-600/10 dark:bg-blue-600/20 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black border border-blue-500/30 text-xs shadow-sm">
                <?php echo strtoupper(substr($nombreUsuarioHeader, 0, 1)); ?>
            </div>
        </div>

        <!-- BOTÓN CERRAR SESIÓN -->
        <div class="pl-2 border-l border-slate-200 dark:border-slate-800">
            <a href="../logout.php" class="w-10 h-10 bg-rose-500/10 hover:bg-rose-500 text-rose-500 hover:text-white border border-rose-500/20 rounded-xl flex items-center justify-center text-xs transition-all duration-200 shadow-sm" title="Cerrar Sesión">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>

    </div>
</header>
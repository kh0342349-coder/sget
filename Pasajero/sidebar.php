<?php
// Detectamos la página actual para iluminar el botón correcto
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="w-64 h-screen fixed top-0 left-0 bg-white dark:bg-[#1e293b] flex flex-col border-r border-slate-200 dark:border-slate-800 shadow-xl z-50 font-sans transition-colors duration-300">
    
    <!-- Encabezado con LOGO DINÁMICO (Modo Claro / Modo Oscuro) -->
    <div class="h-16 px-6 border-b border-slate-200 dark:border-slate-800/80 flex items-center shrink-0">
        <a href="pasajero.php" class="flex items-center group">
            <!-- Logo para Tema Claro -->
            <img id="sidebar-logo-light" 
                 src="../img/largo-blanco.png" 
                 alt="SGET Logo" 
                 class="h-9 w-auto object-contain block dark:hidden filter drop-shadow-sm group-hover:scale-105 transition-all duration-300">
            
            <!-- Logo para Tema Oscuro -->
            <img id="sidebar-logo-dark" 
                 src="../img/largo-negro.png" 
                 alt="SGET Logo" 
                 class="h-9 w-auto object-contain hidden dark:block filter drop-shadow-md group-hover:scale-105 transition-all duration-300">
        </a>
    </div>

    <!-- Menú de navegación -->
    <nav class="mt-6 px-4 flex-grow">
        <ul class="space-y-1 text-xs font-bold uppercase tracking-wider">

            <li>
                <a href="pasajero.php" 
                   class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 <?php echo ($current_page == 'pasajero.php') ? 'bg-blue-600/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 font-black' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white border border-transparent'; ?>">
                    <i class="fas fa-th-large w-5 text-sm"></i>
                    <span>Inicio</span>
                </a>
            </li> 
            
            <li>
                <a href="viajes_pasajero.php" 
                   class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 <?php echo ($current_page == 'viajes_pasajero.php') ? 'bg-blue-600/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 font-black' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white border border-transparent'; ?>">
                    <i class="fas fa-bus w-5 text-sm"></i>
                    <span>Ver Viajes</span>
                </a>
            </li>

            <!-- HISTORIAL DE RESERVAS/VIAJES -->
            <li>
                <a href="historial_pasajero.php" 
                   class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 <?php echo ($current_page == 'historial_pasajero.php') ? 'bg-blue-600/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 font-black' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white border border-transparent'; ?>">
                    <i class="fas fa-history w-5 text-sm"></i>
                    <span>Historial</span>
                </a>
            </li>

            <li>
                <a href="calificar.php" 
                   class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 <?php echo ($current_page == 'calificar.php') ? 'bg-blue-600/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 font-black' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white border border-transparent'; ?>">
                    <i class="fas fa-star w-5 text-sm"></i>
                    <span>Calificaciones</span>
                </a>
            </li>
            
        </ul>
    </nav>
</aside>
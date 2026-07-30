<!-- sidebar.php -->
<aside class="fixed top-0 left-0 w-64 h-screen bg-white dark:bg-[#0f172a] border-r border-slate-200 dark:border-white/5 flex flex-col justify-between z-50 transition-colors duration-300">
    
    <!-- Encabezado del Sidebar con LOGO DINÁMICO -->
    <div>
        <div class="h-16 flex items-center px-6 border-b border-slate-200 dark:border-white/5">
            <a href="conductor.php" class="flex items-center group">
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

        <!-- Menú de Navegación principal -->
        <nav class="p-4 space-y-1.5">
            <?php 
                $paginaActual = basename($_SERVER['PHP_SELF']); 
            ?>
            
            <a href="conductor.php" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-xs transition-all <?php echo ($paginaActual == 'conductor.php' || $paginaActual == 'dashboard_conductor.php') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-white'; ?>">
                <i class="fas fa-th-large text-sm"></i>
                <span>Dashboard</span>
            </a>

            <a href="viajes_conductor.php" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-xs transition-all <?php echo ($paginaActual == 'viajes_conductor.php') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-white'; ?>">
                <i class="fas fa-route text-sm"></i>
                <span>Mis Viajes</span>
            </a>

            <a href="viaje_asignado.php" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-xs transition-all <?php echo ($paginaActual == 'viaje_asignado.php') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-white'; ?>">
                <i class="fas fa-user text-sm"></i>
                <span>Viaje Asignado</span>
            </a>

            <a href="resenas_conductor.php" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-xs transition-all <?php echo ($paginaActual == 'resenas_conductor.php' || $paginaActual == 'reseñas_conductor.php') ? 'bg-blue-600 text-white font-bold shadow-lg shadow-blue-600/30' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-white'; ?>">
                <i class="fas fa-star text-sm"></i>
                <span>Mis Reseñas</span>
            </a>
        </nav>
    </div>
</aside>
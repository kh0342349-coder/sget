<?php
// Detectamos el nombre del archivo actual (ej: 'usuarios.php', 'admin.php', 'asignaciones.php')
$pagina_actual = basename($_SERVER['PHP_SELF']);

// Función auxiliar para generar las clases de los enlaces dinámicamente
function verificarClaseActiva($archivo, $pagina_actual) {
    if ($archivo === $pagina_actual) {
        return 'bg-slate-100 dark:bg-white/5 text-slate-900 dark:text-white border-l-2 border-blue-500 shadow-sm font-bold';
    } else {
        return 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-white/5 border-l-2 border-transparent';
    }
}

// Función auxiliar para iluminar los iconos de la página activa
function verificarIconoActivo($archivo, $pagina_actual, $clase_color = 'text-blue-500') {
    return ($archivo === $pagina_actual) ? $clase_color : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-900 group-hover:dark:text-white';
}
?>

<aside class="w-64 bg-white dark:bg-[#0b0f19] border-r border-slate-200 dark:border-white/5 flex flex-col fixed h-full shadow-xl z-20 transition-colors duration-300">
    
    <!-- CONTENEDOR DEL LOGO DINÁMICO (TAMAÑO MÁS GRANDE) -->
    <div class="px-4 py-6 border-b border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-[#1e293b]/20 transition-colors duration-300 flex items-center justify-center min-h-[85px]">
        <!-- Logo para modo claro -->
        <img src="../img/largo-blanco.png" alt="Logo SGET" class="h-14 w-auto max-w-full object-contain block dark:hidden">
        
        <!-- Logo para modo oscuro -->
        <img src="../img/largo-negro.png" alt="Logo SGET" class="h-14 w-auto max-w-full object-contain hidden dark:block">
    </div>

    <!-- NAVEGACIÓN -->
    <nav class="mt-6 px-4 flex-grow overflow-y-auto">
        <ul class="space-y-1.5 text-sm font-semibold">
            
            <!-- Dashboard -->
            <li>
                <a href="admin.php" class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 group <?php echo verificarClaseActiva('admin.php', $pagina_actual); ?>">
                    <i class="fas fa-chart-pie <?php echo verificarIconoActivo('admin.php', $pagina_actual); ?>"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Usuarios -->
            <li>
                <a href="usuarios.php" class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 group <?php echo verificarClaseActiva('usuarios.php', $pagina_actual); ?>">
                    <i class="fas fa-users-cog <?php echo verificarIconoActivo('usuarios.php', $pagina_actual); ?>"></i>
                    <span>Usuarios</span>
                </a>
            </li>
            
            <!-- Asignaciones -->
            <li>
                <a href="asignaciones.php" class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 group <?php echo verificarClaseActiva('asignaciones.php', $pagina_actual); ?>">
                    <i class="fas fa-clipboard-check <?php echo verificarIconoActivo('asignaciones.php', $pagina_actual); ?>"></i>
                    <span>Asignaciones</span>
                </a>
            </li>
            
            <!-- Rutas -->
            <li>
                <a href="rutas.php" class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 group <?php echo verificarClaseActiva('rutas.php', $pagina_actual); ?>">
                    <i class="fas fa-map-signs <?php echo verificarIconoActivo('rutas.php', $pagina_actual); ?>"></i>
                    <span>Rutas</span>
                </a>
            </li>
            
            <!-- Viajes -->
            <li>
                <a href="viajes.php" class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 group <?php echo verificarClaseActiva('viajes.php', $pagina_actual); ?>">
                    <i class="fas fa-bus <?php echo verificarIconoActivo('viajes.php', $pagina_actual); ?>"></i>
                    <span>Viajes</span>
                </a>
            </li>
            
            <!-- Vehículos -->
            <li>
                <a href="vehiculos.php" class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 group <?php echo verificarClaseActiva('vehiculos.php', $pagina_actual); ?>">
                    <i class="fas fa-car <?php echo verificarIconoActivo('vehiculos.php', $pagina_actual); ?>"></i>
                    <span>Vehículos</span>
                </a>
            </li>
            
            <!-- Calificaciones -->
            <li>
                <?php 
                    if ($pagina_actual == 'ranking_conductores.php') {
                        $claseRanking = 'bg-slate-100 dark:bg-white/5 text-slate-900 dark:text-white border-l-2 border-blue-500 shadow-sm font-bold';
                        $iconoRanking = 'text-yellow-500';
                    } else {
                        $claseRanking = 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-white/5 border-l-2 border-transparent';
                        $iconoRanking = 'text-slate-400 dark:text-slate-500 group-hover:text-yellow-500';
                    }
                ?>
                <a href="ranking_conductores.php" class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 group <?php echo $claseRanking; ?>">
                    <i class="fas fa-star-half-alt <?php echo $iconoRanking; ?>"></i>
                    <span>Calificaciones</span>
                </a>
            </li>
            
            <!-- Reportes -->
            <li>
                <a href="reportes.php" class="flex items-center space-x-3 p-3 rounded-xl transition-all duration-200 group <?php echo verificarClaseActiva('reportes.php', $pagina_actual); ?>">
                    <i class="fas fa-file-invoice-dollar <?php echo verificarIconoActivo('reportes.php', $pagina_actual); ?>"></i>
                    <span>Reportes</span>
                </a>
            </li>
            
        </ul>
    </nav>
</aside>
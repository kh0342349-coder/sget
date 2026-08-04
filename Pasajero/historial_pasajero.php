<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}

$documento = $_SESSION['documento'];

// Obtener ID del pasajero
$query_user = $conexion->query("SELECT id_usu FROM usuario WHERE num_doc_usu = '$documento'");
$id_pasajero = 0;
if ($query_user && $query_user->num_rows > 0) {
    $user_data = $query_user->fetch_assoc();
    $id_pasajero = $user_data['id_usu'];
}

// Consulta del historial de viajes
$sql_historial = "SELECT v.*, r.nom_rut, res.fech_res, res.valor_pagado, res.metodo_pago, res.estado_pago, c.id_cal, v.id_usu_via
                  FROM reserva res
                  JOIN viaje v ON res.id_via_res = v.id_via
                  JOIN rutas r ON v.id_rut_via = r.id_rut
                  LEFT JOIN calificacion c ON v.id_via = c.id_via_cal AND c.id_usu_rem = '$id_pasajero'
                  WHERE res.id_usu_res = '$id_pasajero'
                  ORDER BY res.fech_res DESC";
$historial = $conexion->query($sql_historial);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Historial de Viajes</title>
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex min-h-screen antialiased transition-colors duration-300">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 ml-64 flex flex-col min-h-screen">

        <?php include 'header.php'; ?>

        <div class="p-8 flex-1">
            
            <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl max-w-6xl transition-colors duration-300">
                
                <!-- Encabezado de la tabla y Buscador -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <h3 class="font-bold text-slate-900 dark:text-white text-lg tracking-tight flex items-center gap-2">
                        <i class="fas fa-history text-blue-500 text-sm"></i> Historial de Viajes
                    </h3>

                    <!-- Buscador -->
                    <div class="relative w-full sm:w-72">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                            <i class="fas fa-search text-xs"></i>
                        </span>
                        <input type="text" id="inputBuscador" placeholder="Buscar por ruta, estado o fecha..." 
                               class="w-full pl-9 pr-4 py-2 text-xs bg-slate-100 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-200 rounded-xl border border-slate-200 dark:border-white/10 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                    </div>
                </div>

                <!-- Tabla de Historial -->
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-white/5">
                    <table class="w-full text-sm text-left border-collapse" id="tablaViajes">
                        <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                            <tr>
                                <th class="px-6 py-3.5">Ruta</th>
                                <th class="px-6 py-3.5">Fecha / Hora</th>
                                <th class="px-6 py-3.5">Valor Pagado</th>
                                <th class="px-6 py-3.5">Estado</th>
                                <th class="px-6 py-3.5 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                            <?php if ($historial && $historial->num_rows > 0): ?>
                                <?php while($v = $historial->fetch_assoc()): 
                                    $sePuedeCalificar = ($v['est_via'] == 'Terminado' || $v['est_via'] == 'Finalizado');
                                    $yaCalificado = !is_null($v['id_cal']);
                                ?>
                                    <tr class="fila-viaje hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <!-- Ruta -->
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 rounded-lg flex items-center justify-center text-xs flex-shrink-0">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </div>
                                                <div>
                                                    <span class="font-bold text-slate-900 dark:text-white capitalize text-xs block">
                                                        <?php echo htmlspecialchars($v['nom_rut']); ?>
                                                    </span>
                                                    <span class="text-[10px] text-slate-400">Reserva #<?php echo $v['id_via']; ?></span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Fecha y Hora -->
                                        <td class="px-6 py-4 text-xs font-mono">
                                            <span class="block font-bold text-slate-800 dark:text-slate-200">
                                                <?php echo date('d/m/Y', strtotime($v['fech_res'])); ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 uppercase">
                                                <?php echo date('h:i A', strtotime($v['hor_sal_via'])); ?>
                                            </span>
                                        </td>

                                        <!-- Valor Pagado -->
                                        <td class="px-6 py-4 text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                            $<?php echo number_format($v['valor_pagado'], 2); ?>
                                        </td>

                                        <!-- Estado -->
                                        <td class="px-6 py-4">
                                            <?php if ($sePuedeCalificar): ?>
                                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 uppercase tracking-wider">
                                                    Completado
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black bg-yellow-500/10 text-yellow-600 dark:text-yellow-500 border border-yellow-500/20 uppercase tracking-wider">
                                                    <?php echo htmlspecialchars($v['est_via']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Acción -->
                                        <td class="px-6 py-4 text-center">
                                            <?php if ($yaCalificado): ?>
                                                <div class="flex items-center justify-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold text-[10px] uppercase tracking-wider">
                                                    <i class="fas fa-check-double text-xs"></i> Calificado
                                                </div>
                                            <?php elseif ($sePuedeCalificar): ?>
                                                <a href="calificar.php?id_via=<?php echo $v['id_via']; ?>&id_cond=<?php echo $v['id_usu_via']; ?>" 
                                                   class="inline-flex items-center gap-2 bg-yellow-500 hover:bg-yellow-400 text-slate-900 px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all duration-200 shadow-md shadow-yellow-500/10">
                                                    <i class="fas fa-star text-[9px]"></i> Calificar
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-tight italic">En trayecto...</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500 italic">
                                        <i class="fas fa-ghost text-slate-300 dark:text-slate-700 text-3xl mb-3 block"></i>
                                        <span class="font-bold uppercase text-[10px] tracking-widest text-slate-400 dark:text-slate-500">No tienes registros de viajes aún.</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </main>

    <!-- Script para el Filtro en Tiempo Real del Buscador -->
    <script>
        document.getElementById('inputBuscador').addEventListener('keyup', function() {
            const valorBusqueda = this.value.toLowerCase();
            const filas = document.querySelectorAll('#tablaViajes .fila-viaje');

            filas.forEach(fila => {
                const textoFila = fila.textContent.toLowerCase();
                if (textoFila.includes(valorBusqueda)) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            });
        });

        // Alternador de tema
        const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
        } else {
            if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
        }

        const themeToggleBtn = document.getElementById('theme-toggle');

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.toggle('hidden');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.toggle('hidden');

                if (localStorage.getItem('color-theme')) {
                    if (localStorage.getItem('color-theme') === 'light') {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('color-theme', 'dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('color-theme', 'light');
                    }
                } else {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('color-theme', 'light');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('color-theme', 'dark');
                    }
                }
            });
        }
    </script>
</body>
</html>
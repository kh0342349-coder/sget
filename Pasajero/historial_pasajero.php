<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}

$documento = $_SESSION['documento'];
$nombreReal = $_SESSION['nombre_usuario'] ?? "Pasajero";

// Obtener ID del pasajero
$query_user = $conexion->query("SELECT id_usu FROM usuario WHERE num_doc_usu = '$documento'");
$id_pasajero = 0;
if ($query_user && $query_user->num_rows > 0) {
    $user_data = $query_user->fetch_assoc();
    $id_pasajero = $user_data['id_usu'];
}

// 1. Contar viajes REALES del pasajero desde la tabla reserva
$res_viajes = $conexion->query("SELECT COUNT(*) as total FROM reserva WHERE id_usu_res = '$id_pasajero'"); 
$total_viajes = ($res_viajes) ? $res_viajes->fetch_assoc()['total'] : 0;

// 2. Obtener los últimos 5 viajes
$sql_historial = "SELECT v.*, r.nom_rut, res.fech_res, c.id_cal, v.id_usu_via
                  FROM reserva res
                  JOIN viaje v ON res.id_via_res = v.id_via
                  JOIN rutas r ON v.id_rut_via = r.id_rut
                  LEFT JOIN calificacion c ON v.id_via = c.id_via_cal AND c.id_usu_rem = '$id_pasajero'
                  WHERE res.id_usu_res = '$id_pasajero'
                  ORDER BY res.fech_res DESC LIMIT 5";
$historial = $conexion->query($sql_historial);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Panel Pasajero</title>
    <!-- Script para prevenir parpadeo de tema oscuro al cargar -->
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <!-- FontAwesome para Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex min-h-screen antialiased transition-colors duration-300">

    <!-- INCLUSIÓN DIRECTA DEL SIDEBAR FIJO -->
    <?php include 'sidebar.php'; ?>

    <!-- MAIN CON MARGEN IZQUIERDO (ml-64) PARA ALINEARSE AL SIDEBAR -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen">

        <!-- HEADER MODULAR -->
        <?php include 'header.php'; ?>

        <!-- CONTENIDO DEL DASHBOARD -->
        <div class="p-8 space-y-8 flex-1">
            
            <!-- Alerta de Calificación Exitosa -->
            <?php if (isset($_GET['res']) && $_GET['res'] == 'ok'): ?>
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl flex items-center justify-between shadow-lg shadow-emerald-950/20 animate-pulse max-w-6xl">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-lg"></i>
                        <span class="font-bold text-xs uppercase tracking-wider">¡Gracias! Tu calificación se guardó correctamente.</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-emerald-400 opacity-60 hover:opacity-100 transition-opacity">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Bienvenida -->
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">¡Hola, <?php echo explode(' ', $nombreReal)[0]; ?>!</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Gestiona tus reservas de transporte y califica tus trayectos.</p>
            </div>

            <!-- Grid de Tarjetas de Métricas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl">
                <!-- Tarjeta: Viajes Realizados -->
                <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl relative overflow-hidden flex items-center justify-between group hover:border-slate-300 dark:hover:border-white/10 transition-all duration-300 shadow-sm hover:shadow-md">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Viajes Realizados</p>
                        <h4 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo $total_viajes; ?></h4>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                        <i class="fas fa-route text-lg"></i>
                    </div>
                </div>
            </div>

            <!-- Tabla de Historial de Viajes -->
            <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl max-w-6xl transition-colors duration-300">
                <h3 class="font-bold text-slate-900 dark:text-white text-base mb-4 tracking-tight flex items-center gap-2">
                    <i class="fas fa-history text-slate-400 dark:text-slate-500 text-sm"></i> Mis últimos viajes
                </h3>
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-white/5">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                            <tr>
                                <th class="px-6 py-3.5">Ruta</th>
                                <th class="px-6 py-3.5">Fecha</th>
                                <th class="px-6 py-3.5">Hora</th>
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
                                    <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 rounded-lg flex items-center justify-center text-xs">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </div>
                                                <span class="font-bold text-slate-900 dark:text-white capitalize text-xs"><?php echo htmlspecialchars($v['nom_rut']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono"><?php echo date('d/m/Y', strtotime($v['fech_res'])); ?></td>
                                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono uppercase"><?php echo date('h:i A', strtotime($v['hor_sal_via'])); ?></td>
                                        <td class="px-6 py-4">
                                            <?php if ($sePuedeCalificar): ?>
                                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 uppercase tracking-wider">
                                                    Llegaste
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black bg-yellow-500/10 text-yellow-600 dark:text-yellow-500 border border-yellow-500/20 uppercase tracking-wider">
                                                    <?php echo htmlspecialchars($v['est_via']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php if ($yaCalificado): ?>
                                                <div class="flex items-center justify-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold text-[10px] uppercase tracking-wider">
                                                    <i class="fas fa-check-double text-xs"></i> Calificado
                                                </div>
                                            <?php elseif ($sePuedeCalificar): ?>
                                                <a href="calificar.php?id_via=<?php echo $v['id_via']; ?>&id_cond=<?php echo $v['id_usu_via']; ?>" 
                                                   class="inline-flex items-center gap-2 bg-yellow-500 hover:bg-yellow-400 text-slate-900 px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all duration-200 shadow-md shadow-yellow-500/10">
                                                    <i class="fas fa-star text-[9px]"></i> Calificar Servicio
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

            <!-- Banner Inferior de Acción -->
            <div class="mt-8 relative overflow-hidden bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl p-8 text-white max-w-6xl shadow-xl shadow-blue-950/20">
                <div class="relative z-10">
                    <h2 class="text-2xl font-black tracking-tight">¿A dónde quieres ir hoy?</h2>
                    <p class="text-xs text-blue-100 mt-1 max-w-md">Encuentra y reserva tus rutas de transporte de manera rápida y segura.</p>
                    <a href="viajes_pasajero.php" class="inline-flex items-center gap-2 mt-5 bg-white text-blue-700 px-8 py-3 rounded-xl font-black uppercase text-xs hover:bg-slate-100 transition-colors shadow-lg shadow-blue-900/30">
                        <i class="fas fa-search text-[10px]"></i> Buscar Rutas
                    </a>
                </div>
                <div class="absolute -right-10 -bottom-10 text-white/5 text-9xl font-black pointer-events-none transform -rotate-12">
                    <i class="fas fa-bus"></i>
                </div>
            </div>
        </div>
    </main>

    <!-- Script global de cambio de tema -->
    <script>
        const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

        // Sincronizar estado inicial de los íconos
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            themeToggleLightIcon.classList.remove('hidden');
        } else {
            themeToggleDarkIcon.classList.remove('hidden');
        }

        const themeToggleBtn = document.getElementById('theme-toggle');

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                // Alternar íconos
                themeToggleDarkIcon.classList.toggle('hidden');
                themeToggleLightIcon.classList.toggle('hidden');

                // Alternar en el almacenamiento y en la clase HTML principal
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
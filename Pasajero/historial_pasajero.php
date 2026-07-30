<?php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php';

// Validar inicio de sesión
if (!isset($_SESSION['documento'])) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Pasajero";
$documento  = $_SESSION['documento'];

$historial_data = [];

// 1. Obtener ID del pasajero
$stmt_user = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ?");
$stmt_user->bind_param("s", $documento);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user && $result_user->num_rows > 0) {
    $user_data = $result_user->fetch_assoc();
    $id_pasajero = $user_data['id_usu'];

    // 2. Consulta ajustada según la estructura exacta de tu BD
    $sql_historial = "SELECT r.id_res, r.fech_res, r.estado_pago,
                             v.id_via, v.fec_via, v.est_via, v.id_usu_via,
                             rt.des_rut,
                             ve.pla_veh, ve.mode_veh,
                             u.nom_usu AS nom_conductor
                      FROM reserva r
                      JOIN viaje v ON r.id_via_res = v.id_via
                      JOIN rutas rt ON v.id_rut_via = rt.id_rut
                      LEFT JOIN vehiculo ve ON v.id_veh = ve.id_veh
                      LEFT JOIN usuario u ON v.id_usu_via = u.id_usu
                      WHERE r.id_usu_res = ?
                      ORDER BY r.fech_res DESC";

    $stmt_historial = $conexion->prepare($sql_historial);
    $stmt_historial->bind_param("i", $id_pasajero);
    $stmt_historial->execute();
    $res_historial = $stmt_historial->get_result();

    while ($row = $res_historial->fetch_assoc()) {
        $historial_data[] = $row;
    }
    $stmt_historial->close();
}
$stmt_user->close();
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Viajes - SGET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7'
                    }
                }
            }
        }
    </script>
    <!-- SCRIPT ANTI-FLASHEO -->
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex min-h-screen antialiased transition-colors duration-300">

    <!-- Carga del Sidebar del Pasajero -->
    <?php include 'sidebar_pasajero.php'; ?>

    <!-- Contenedor Principal -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- Header del Pasajero -->
        <?php include 'header_pasajero.php'; ?>

        <!-- Contenido principal -->
        <div class="p-8 space-y-6 flex-1">
            
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Historial de Mis Viajes</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Revisa el estado de todas tus reservas realizadas en la plataforma.</p>
            </div>
            
            <!-- Tabla de Historial -->
            <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl max-w-6xl transition-colors duration-300">
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-white/5">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                            <tr>
                                <th class="px-5 py-3.5">Ruta / Destino</th>
                                <th class="px-5 py-3.5">Fecha Viaje</th>
                                <th class="px-5 py-3.5">Conductor</th>
                                <th class="px-5 py-3.5">Vehículo</th>
                                <th class="px-5 py-3.5 text-center">Estado Pago / Reserva</th>
                                <th class="px-5 py-3.5 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                            <?php if (!empty($historial_data)): ?>
                                <?php foreach ($historial_data as $h): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-5 py-4 font-bold text-slate-900 dark:text-white capitalize">
                                        <?php echo htmlspecialchars($h['des_rut'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="px-5 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono">
                                        <?php 
                                            $fecha = !empty($h['fec_via']) ? date('d/m/Y - h:i A', strtotime($h['fec_via'])) : 'N/A';
                                            echo htmlspecialchars($fecha); 
                                        ?>
                                    </td>
                                    <td class="px-5 py-4 font-medium text-slate-800 dark:text-slate-300 capitalize text-xs">
                                        <i class="fas fa-user-circle text-slate-400 mr-1.5"></i>
                                        <?php echo htmlspecialchars($h['nom_conductor'] ?? 'Por asignar', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="bg-slate-100 dark:bg-white/5 text-blue-600 dark:text-neon-azul border border-slate-200 dark:border-white/10 px-2.5 py-0.5 rounded-md text-[10px] font-black tracking-wider uppercase inline-block w-fit">
                                                <?php echo htmlspecialchars($h['pla_veh'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium px-0.5 capitalize">
                                                <?php echo htmlspecialchars($h['mode_veh'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <?php 
                                            $estado = strtolower(trim($h['estado_pago'] ?? $h['est_via'] ?? ''));
                                            if (in_array($estado, ['pagado', 'aprobado', 'completado', 'confirmado', '0', '2'])): 
                                        ?>
                                            <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg">
                                                Completado
                                            </span>
                                        <?php elseif (in_array($estado, ['cancelado', 'rechazado', '3'])): ?>
                                            <span class="bg-rose-500/10 text-rose-500 border border-rose-500/20 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg">
                                                Cancelado
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg animate-pulse">
                                                <?php echo !empty($h['estado_pago']) ? htmlspecialchars($h['estado_pago']) : 'Pendiente'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <?php if (!empty($h['id_via']) && !empty($h['id_usu_via'])): ?>
                                            <a href="calificar.php?id_via=<?php echo $h['id_via']; ?>&id_cond=<?php echo $h['id_usu_via']; ?>" class="inline-flex items-center gap-1.5 bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 border border-yellow-500/30 px-3 py-1.5 rounded-xl text-xs font-bold transition">
                                                <i class="fas fa-star text-[10px]"></i> Calificar
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-xs italic">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-400 dark:text-slate-500 italic">
                                        <i class="fas fa-history text-slate-400 mr-2"></i> No has realizado ninguna reserva o viaje aún.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Script de Cambio de Tema -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggleBtn = document.getElementById('theme-toggle');
            const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
            const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

            function sincronizarInterfaz(esOscuro) {
                if (esOscuro) {
                    document.documentElement.classList.add('dark');
                    if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
                    if (themeToggleDarkIcon) themeToggleDarkIcon.classList.add('hidden');
                } else {
                    document.documentElement.classList.remove('dark');
                    if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
                    if (themeToggleLightIcon) themeToggleLightIcon.classList.add('hidden');
                }
            }

            function obtenerEstadoGuardado() {
                const v1 = localStorage.getItem('color-theme');
                const v2 = localStorage.getItem('theme');

                if (v1 === 'dark' || v2 === 'dark') return true;
                if (v1 === 'light' || v2 === 'light') return false;

                return window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            sincronizarInterfaz(obtenerEstadoGuardado());

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    const actualmenteOscuro = document.documentElement.classList.contains('dark');
                    const nuevoEstado = !actualmenteOscuro;

                    localStorage.setItem('color-theme', nuevoEstado ? 'dark' : 'light');
                    localStorage.setItem('theme', nuevoEstado ? 'dark' : 'light');

                    sincronizarInterfaz(nuevoEstado);
                });
            }

            window.addEventListener('storage', function(e) {
                if (e.key === 'color-theme' || e.key === 'theme') {
                    sincronizarInterfaz(e.newValue === 'dark');
                }
            });
        });
    </script>
</body>
</html>
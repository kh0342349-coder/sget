<?php
// Archivo: Admin/reportes.php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php';
require_once '../helpers/AuthHelper.php';

// 1. Verificación de Seguridad (Admin = Rol 1)[cite: 7]
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// BLOQUEO DE SEGURIDAD POR RESTRICCIONES[cite: 7]
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'reportes');

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// 2. Control de Pestañas (Tabs)[cite: 7]
$tab = $_GET['tab'] ?? 'general';
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Panel de Reportes Analíticos</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- CSS MODULAR DEL PANEL (antes: style_admin.css, que no existia en esta carpeta) -->
    <link rel="stylesheet" href="../assets/css/01-base.css">
    <link rel="stylesheet" href="../assets/css/02-layout.css">
    <link rel="stylesheet" href="../assets/css/03-componentes.css">
    <link rel="stylesheet" href="../assets/css/04-modales.css">
    <link rel="stylesheet" href="../assets/css/05-tablas.css">
    <link rel="stylesheet" href="../assets/css/06-responsive.css">
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
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">
    
    <?php include '../includes/sidebar.php'; ?>

    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen flex-1 transition-all duration-300 min-w-0">
        
        <?php include '../includes/header.php'; ?>

        <main class="space-y-8 flex-grow pb-12 relative z-10 p-8 max-w-[1600px] w-auto mx-auto w-full">
            
            <!-- ENCABEZADO CON BOTÓN DE AYUDA -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/5 dark:bg-white/[0.02] p-6 rounded-3xl border border-slate-200 dark:border-white/5 backdrop-blur-md">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Panel de Inteligencia Logística</h1>
                        
                        <!-- BOTÓN DE AYUDA DEL SISTEMA -->
                        <button type="button" onclick="abrirModalAyuda()" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer" title="Ver guía del módulo">
                            <i class="fas fa-question text-[10px]"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Historial integral de operaciones, reservas, viajes programados y métricas operativas[cite: 7].</p>
                </div>
            </div>

            <!-- Navegación de Pestañas[cite: 7] -->
            <div class="flex flex-wrap gap-2 bg-white dark:bg-[#121826] p-2 rounded-2xl border border-slate-200 dark:border-white/10 w-fit shadow-md">
                <a href="reportes.php?tab=general" class="px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 <?php echo $tab == 'general' ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'; ?>">
                    <i class="fas fa-chart-pie text-xs"></i> Consolidado General
                </a>
                <a href="reportes.php?tab=viajes" class="px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 <?php echo $tab == 'viajes' ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'; ?>">
                    <i class="fas fa-route text-xs"></i> Historial de Viajes
                </a>
                <a href="reportes.php?tab=reservas" class="px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 <?php echo $tab == 'reservas' ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'; ?>">
                    <i class="fas fa-ticket-alt text-xs"></i> Reservas de Pasajeros
                </a>
                <a href="reportes.php?tab=conductores" class="px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 <?php echo $tab == 'conductores' ? 'bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'; ?>">
                    <i class="fas fa-id-card text-xs"></i> Rendimiento Conductores
                </a>
            </div>

            <!-- CONTENIDO SEGÚN LA PESTAÑA[cite: 7] -->
            <?php if ($tab == 'general'): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php
                    $tot_viajes = $conexion->query("SELECT COUNT(*) as total FROM viaje")->fetch_assoc()['total'] ?? 0;
                    $tot_usuarios = $conexion->query("SELECT COUNT(*) as total FROM usuario")->fetch_assoc()['total'] ?? 0;
                    $tot_recaudo = $conexion->query("SELECT SUM(val_via) as total FROM viaje")->fetch_assoc()['total'] ?? 0;
                    ?>
                    <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl relative overflow-hidden">
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total Viajes Registrados</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono"><?php echo number_format($tot_viajes); ?></h3>
                    </div>
                    <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl relative overflow-hidden">
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Usuarios Registrados</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono"><?php echo number_format($tot_usuarios); ?></h3>
                    </div>
                    <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl relative overflow-hidden">
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Flujo Total Estimado</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono">$<?php echo number_format($tot_recaudo); ?></h3>
                    </div>
                </div>

            <?php elseif ($tab == 'viajes'): ?>
                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl space-y-6">
                    <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-route text-sky-400"></i> Historial Completo de Viajes
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-white/5 text-[10px] text-slate-400 uppercase font-bold">
                                    <th class="pb-3 px-3">ID</th>
                                    <th class="pb-3 px-3">Ruta</th>
                                    <th class="pb-3 px-3">Conductor</th>
                                    <th class="pb-3 px-3">Vehículo (Placa)</th>
                                    <th class="pb-3 px-3">Fecha y Hora</th>
                                    <th class="pb-3 px-3">Valor</th>
                                    <th class="pb-3 px-3 text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                <?php
                                $historial_viajes = $conexion->query("SELECT v.*, r.nom_rut, u.nom_usu, veh.pla_veh FROM viaje v LEFT JOIN rutas r ON v.id_rut_via = r.id_rut LEFT JOIN usuario u ON v.id_usu_via = u.id_usu LEFT JOIN vehiculo veh ON v.id_veh = veh.id_veh ORDER BY v.id_via DESC");
                                if ($historial_viajes && $historial_viajes->num_rows > 0):
                                    while($hv = $historial_viajes->fetch_assoc()):
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="py-3.5 px-3 font-mono text-slate-400">#<?php echo $hv['id_via']; ?></td>
                                    <td class="py-3.5 px-3 font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($hv['nom_rut'] ?? 'Ruta no asignada'); ?></td>
                                    <td class="py-3.5 px-3 text-slate-300"><?php echo htmlspecialchars($hv['nom_usu'] ?? 'Sin conductor'); ?></td>
                                    <td class="py-3.5 px-3 font-mono text-sky-400"><?php echo htmlspecialchars($hv['pla_veh'] ?? 'Sin placa'); ?></td>
                                    <td class="py-3.5 px-3 text-slate-400"><?php echo $hv['fec_via'] . ' ' . $hv['hor_sal_via']; ?></td>
                                    <td class="py-3.5 px-3 font-mono text-emerald-400 font-bold">$<?php echo number_format($hv['val_via'], 0, ',', '.'); ?></td>
                                    <td class="py-3.5 px-3 text-center">
                                        <span class="px-2.5 py-1 bg-sky-500/10 text-sky-400 border border-sky-500/20 rounded-full text-[10px] font-extrabold uppercase"><?php echo $hv['est_via']; ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-400 italic">No hay registros en el historial de viajes[cite: 7].</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($tab == 'reservas'): ?>
                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl space-y-6">
                    <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-ticket-alt text-purple-400"></i> Historial de Reservas de Pasajeros
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-white/5 text-[10px] text-slate-400 uppercase font-bold">
                                    <th class="pb-3 px-3">ID Reserva</th>
                                    <th class="pb-3 px-3">Pasajero</th>
                                    <th class="pb-3 px-3">Viaje / Ruta</th>
                                    <th class="pb-3 px-3">Valor Pagado</th>
                                    <th class="pb-3 px-3 text-center">Estado de Pago</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                <?php
                                $historial_reservas = $conexion->query("SELECT r.*, u.nom_usu, rt.nom_rut FROM reserva r JOIN usuario u ON r.id_usu_res = u.id_usu JOIN viaje v ON r.id_via_res = v.id_via LEFT JOIN rutas rt ON v.id_rut_via = rt.id_rut ORDER BY r.id_res DESC");
                                if ($historial_reservas && $historial_reservas->num_rows > 0):
                                    while($hr = $historial_reservas->fetch_assoc()):
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="py-3.5 px-3 font-mono text-slate-400">#<?php echo $hr['id_res']; ?></td>
                                    <td class="py-3.5 px-3 font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($hr['nom_usu']); ?></td>
                                    <td class="py-3.5 px-3 text-slate-300"><?php echo htmlspecialchars($hr['nom_rut'] ?? 'Ruta General'); ?></td>
                                    <td class="py-3.5 px-3 font-mono text-emerald-400 font-bold">$<?php echo number_format($hr['valor_pagado'] ?? 0, 0, ',', '.'); ?></td>
                                    <td class="py-3.5 px-3 text-center">
                                        <span class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-[10px] font-extrabold uppercase"><?php echo $hr['estado_pago'] ?? 'Completado'; ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-400 italic">No hay reservas de pasajeros registradas[cite: 7].</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($tab == 'conductores'): ?>
                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl space-y-6">
                    <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-id-card text-emerald-400"></i> Rendimiento de Conductores
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-white/5 text-[10px] text-slate-400 uppercase font-bold">
                                    <th class="pb-3 px-3">Conductor</th>
                                    <th class="pb-3 px-3">Correo</th>
                                    <th class="pb-3 px-3 text-center">Total Viajes Asignados</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                <?php
                                $rendimiento_conductores = $conexion->query("SELECT u.nom_usu, u.corre_usu, COUNT(v.id_via) as total_viajes FROM usuario u LEFT JOIN viaje v ON u.id_usu = v.id_usu_via WHERE u.id_rol_usu = 2 GROUP BY u.id_usu ORDER BY total_viajes DESC");
                                if ($rendimiento_conductores && $rendimiento_conductores->num_rows > 0):
                                    while($rc = $rendimiento_conductores->fetch_assoc()):
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="py-3.5 px-3 font-bold text-slate-800 dark:text-white flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-black text-xs border border-emerald-500/20">
                                            <?php echo strtoupper(substr($rc['nom_usu'], 0, 1)); ?>
                                        </div>
                                        <?php echo htmlspecialchars($rc['nom_usu']); ?>
                                    </td>
                                    <td class="py-3.5 px-3 text-slate-400 italic"><?php echo htmlspecialchars($rc['corre_usu']); ?></td>
                                    <td class="py-3.5 px-3 text-center font-mono font-bold text-sky-400 text-sm"><?php echo $rc['total_viajes']; ?></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="3" class="py-8 text-center text-slate-400 italic">No hay datos de rendimiento de conductores[cite: 7].</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- MODAL DE AYUDA DEL MÓDULO -->
    <div id="overlayAyuda" onclick="cerrarModalAyuda()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="modalAyuda" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-4 transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <i class="fas fa-info-circle text-sky-400"></i> Guía del Panel de Reportes
                </h3>
                <button onclick="cerrarModalAyuda()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"><i class="fas fa-times text-xs"></i></button>
            </div>
            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                <li class="flex items-start gap-2">
                    <i class="fas fa-chart-pie text-sky-400 mt-0.5"></i>
                    <span><b>Consolidado General:</b> Muestra las métricas clave y totales globales del sistema de transporte (viajes, usuarios y flujo financiero).</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-route text-emerald-400 mt-0.5"></i>
                    <span><b>Historial de Viajes:</b> Detalla todas las salidas operativas, rutas asignadas, vehículos y tarifas.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-ticket-alt text-purple-400 mt-0.5"></i>
                    <span><b>Reservas de Pasajeros:</b> Monitorea las reservas realizadas por los usuarios y el estado de sus pagos.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-id-card text-amber-400 mt-0.5"></i>
                    <span><b>Rendimiento de Conductores:</b> Consulta la cantidad de asignaciones de viaje completadas por cada miembro del personal de conducción.</span>
                </li>
            </ul>
            <button onclick="cerrarModalAyuda()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer mt-2">
                Entendido
            </button>
        </div>
    </div>

    <!-- SCRIPTS DE CONTROL -->
    <script>
        function abrirModalAyuda() {
            document.getElementById('overlayAyuda').classList.remove('opacity-0', 'pointer-events-none');
            document.getElementById('overlayAyuda').classList.add('opacity-100', 'pointer-events-auto');
            document.getElementById('modalAyuda').classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
            document.getElementById('modalAyuda').classList.add('opacity-100', 'pointer-events-auto', 'scale-100');
        }

        function cerrarModalAyuda() {
            document.getElementById('modalAyuda').classList.remove('opacity-100', 'pointer-events-auto', 'scale-100');
            document.getElementById('modalAyuda').classList.add('opacity-0', 'pointer-events-none', 'scale-95');
            document.getElementById('overlayAyuda').classList.remove('opacity-100', 'pointer-events-auto');
            document.getElementById('overlayAyuda').classList.add('opacity-0', 'pointer-events-none');
        }
    </script>
</body>
</html>
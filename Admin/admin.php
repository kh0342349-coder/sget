<?php
// Archivo: Admin/admin.php
date_default_timezone_set('America/Bogota');
session_start();

// Conexión a la base de datos subiendo un nivel hacia assets/
require_once __DIR__ . '/../assets/conexion.php';
require_once '../helpers/AuthHelper.php';

// Verificación de seguridad para Administrador (Rol 1)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? 'Kevin Hernández';

// --- CONSULTAS OPERATIVAS DEL DASHBOARD ---
$mes_actual = date('m');
$anio_actual = date('Y');

// 1. Total Usuarios Registrados
$res_total_usu = $conexion->query("SELECT COUNT(*) as total FROM usuario WHERE id_rol_usu IN (2,3)");
$total_usuarios = $res_total_usu ? $res_total_usu->fetch_assoc()['total'] : 0;

$res_mes_usu = $conexion->query("SELECT COUNT(*) as mes FROM usuario WHERE id_rol_usu IN (2,3)"); 
$usuarios_mes = $res_mes_usu ? $res_mes_usu->fetch_assoc()['mes'] : 0;
$porcentaje_usu = $total_usuarios > 0 ? round(($usuarios_mes / $total_usuarios) * 100, 1) : 0;

// 2. Viajes Completados / Despachados
$res_total_via = $conexion->query("SELECT COUNT(*) as total FROM viaje");
$total_viajes = $res_total_via ? $res_total_via->fetch_assoc()['total'] : 0;

$res_mes_via = $conexion->query("SELECT COUNT(*) as mes FROM viaje WHERE MONTH(fec_via) = '$mes_actual' AND YEAR(fec_via) = '$anio_actual'");
$viajes_mes = $res_mes_via ? $res_mes_via->fetch_assoc()['mes'] : 0;
$porcentaje_via = $total_viajes > 0 ? round(($viajes_mes / $total_viajes) * 100, 1) : 0;

// 3. Flota de Vehículos (Activos e Inactivos)
$res_veh = $conexion->query("SELECT est_veh, COUNT(*) as cantidad FROM vehiculo GROUP BY est_veh");
$vehiculos = ['Activo' => 0, 'Inactivo' => 0];
if ($res_veh) {
    while($row = $res_veh->fetch_assoc()) {
        $estado = ($row['est_veh'] == 1) ? 'Activo' : 'Inactivo';
        $vehiculos[$estado] = $row['cantidad'];
    }
}

// 4. Conductores Disponibles en Línea de Espera
$conductores_disponibles = $conexion->query("
    SELECT u.id_usu, u.nom_usu, v.pla_veh 
    FROM usuario u 
    LEFT JOIN asignacion a ON u.id_usu = a.id_usu_asig 
    LEFT JOIN vehiculo v ON a.id_veh_asig = v.id_veh 
    WHERE u.id_rol_usu = 2 AND u.est_con_usu = 'Disponible' 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Dashboard Principal</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- ESTILOS UNIFICADOS -->
    <link rel="stylesheet" href="style_admin.css">

    <script>
        tailwind.config = { darkMode: 'class' };
        
        // Carga inicial del tema sin parpadeos
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>

<body class="bg-slate-50 dark:bg-[#080c14] text-slate-800 dark:text-slate-100 flex min-h-screen transition-colors duration-300 pr-4">

   <?php include '../includes/sidebar.php'; ?>

    <!-- 2. CONTENEDOR PRINCIPAL DERECHO -->
    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen flex-1 transition-all duration-300 min-w-0">
      
        <?php include '../includes/header.php'; ?>

        <!-- 3. CONTENIDO DEL DASHBOARD CON MODO OSCURO / CLARO -->
        <main class="space-y-6 flex-grow pb-8 relative z-10">
            
            <!-- BANNER PRINCIPAL CON GRADIENTE DINÁMICO -->
            <div class="card-floating p-8 bg-gradient-to-r from-sky-500/10 via-purple-500/10 to-transparent border border-slate-200/80 dark:border-white/10 rounded-[28px] bg-white dark:bg-[#121826] flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xl">
                <div class="space-y-2">
                    <span class="px-3.5 py-1 rounded-full bg-sky-500/10 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400 text-[10px] font-black uppercase tracking-wider border border-sky-500/20 dark:border-sky-500/30 inline-flex items-center gap-2">
                        <i class="fas fa-wifi text-emerald-500 dark:text-emerald-400 animate-pulse"></i> MONITOREO EN TIEMPO REAL
                    </span>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white">¡Buen día, <span class="text-transparent bg-clip-text bg-gradient-to-r from-sky-400 via-blue-500 to-purple-500"><?= htmlspecialchars(explode(' ', $nombreReal)[0]); ?></span>!</h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-medium">
                        Aquí está el resumen operacional de tu red de transporte para el día de hoy.
                    </p>
                </div>
            </div>

            <!-- TARJETAS DE MÉTRICAS OPERATIVAS (KPIS) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Tarjeta 1: Usuarios -->
                <div class="card-floating p-6 bg-white dark:bg-[#121826] border border-slate-200/80 dark:border-white/10 rounded-[28px] shadow-xl flex justify-between items-center">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider">Usuarios Registrados</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2"><?php echo $total_usuarios; ?></h3>
                        <span class="inline-block mt-3 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-3 py-1 rounded-full border border-emerald-500/20">
                            <i class="fas fa-arrow-up text-[8px] mr-1"></i>+<?php echo $porcentaje_usu; ?>% este mes
                        </span>
                    </div>
                    <div class="w-14 h-14 rounded-3xl bg-sky-500/10 text-sky-500 dark:text-sky-400 flex items-center justify-center text-2xl border border-sky-500/20 shrink-0">
                        <i class="fas fa-users"></i>
                    </div>
                </div>

                <!-- Tarjeta 2: Viajes -->
                <div class="card-floating p-6 bg-white dark:bg-[#121826] border border-slate-200/80 dark:border-white/10 rounded-[28px] shadow-xl flex justify-between items-center">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider">Viajes Completados</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2"><?php echo $total_viajes; ?></h3>
                        <span class="inline-block mt-3 text-[10px] font-bold text-purple-600 dark:text-purple-400 bg-purple-500/10 px-3 py-1 rounded-full border border-purple-500/20">
                            <i class="fas fa-arrow-up text-[8px] mr-1"></i>+<?php echo $porcentaje_via; ?>% despachados
                        </span>
                    </div>
                    <div class="w-14 h-14 rounded-3xl bg-purple-500/10 text-purple-500 dark:text-purple-400 flex items-center justify-center text-2xl border border-purple-500/20 shrink-0">
                        <i class="fas fa-route"></i>
                    </div>
                </div>

                <!-- Tarjeta 3: Vehículos -->
                <div class="card-floating p-6 bg-white dark:bg-[#121826] border border-slate-200/80 dark:border-white/10 rounded-[28px] shadow-xl flex justify-between items-center">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider">Flota de Vehículos</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2"><?php echo $vehiculos['Activo']; ?> <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Activos</span></h3>
                        <span class="inline-block mt-3 text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-3 py-1 rounded-full border border-amber-500/20">
                            <?php echo $vehiculos['Inactivo']; ?> Fuera de servicio
                        </span>
                    </div>
                    <div class="w-14 h-14 rounded-3xl bg-amber-500/10 text-amber-500 dark:text-amber-400 flex items-center justify-center text-2xl border border-amber-500/20 shrink-0">
                        <i class="fas fa-bus"></i>
                    </div>
                </div>
            </div>

            <!-- FILA INFERIOR: TABLA Y GRÁFICO DE DONA -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Tabla de Conductores Disponibles -->
                <div class="lg:col-span-2 card-floating p-6 bg-white dark:bg-[#121826] border border-slate-200/80 dark:border-white/10 rounded-[28px] shadow-xl flex flex-col justify-between">
                    <div class="flex justify-between items-center mb-5 border-b border-slate-200/80 dark:border-white/5 pb-3">
                        <h4 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                            <i class="fas fa-id-card text-sky-500 dark:text-sky-400"></i> Conductores Disponibles
                        </h4>
                        <span class="text-[10px] bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 px-3 py-1 rounded-full font-bold uppercase tracking-wider">LÍNEA DE ESPERA</span>
                    </div>
                    
                    <div class="overflow-x-auto flex-grow custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200/80 dark:border-white/5 text-[11px] font-extrabold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                                    <th class="pb-3 pl-2">ID SISTEMA</th>
                                    <th class="pb-3">NOMBRE CONDUCTOR</th>
                                    <th class="pb-3 pr-2 text-right">VEHÍCULO ASIGNADO</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5 text-xs font-medium">
                                <?php if ($conductores_disponibles && $conductores_disponibles->num_rows > 0): 
                                    while($con = $conductores_disponibles->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors duration-150">
                                        <td class="py-3.5 pl-2 font-mono text-slate-400 dark:text-slate-500">#<?php echo htmlspecialchars($con['id_usu']); ?></td>
                                        <td class="py-3.5 font-bold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($con['nom_usu']); ?></td>
                                        <td class="py-3.5 pr-2 text-right">
                                            <span class="bg-slate-100 dark:bg-white/10 text-slate-800 dark:text-slate-200 px-3 py-1 rounded-full font-mono font-bold text-[10px] border border-slate-200/80 dark:border-white/10">
                                                <?php echo $con['pla_veh'] ? htmlspecialchars($con['pla_veh']) : 'Sin Asignar'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="3" class="py-8 text-center text-slate-400 dark:text-slate-500 italic">No se encontraron conductores en estado disponible.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Gráfico de Disponibilidad de Flota -->
                <div class="card-floating p-6 bg-white dark:bg-[#121826] border border-slate-200/80 dark:border-white/10 rounded-[28px] shadow-xl flex flex-col justify-between items-center">
                    <div class="w-full text-left mb-4">
                        <h4 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                            <i class="fas fa-chart-pie text-purple-500 dark:text-purple-400"></i> Disponibilidad de Flota
                        </h4>
                    </div>
                    
                    <div class="relative w-44 h-44 flex items-center justify-center my-2">
                        <canvas id="graficoVehiculos"></canvas>
                    </div>

                    <div class="w-full grid grid-cols-2 gap-3 mt-4 text-center text-xs font-semibold">
                        <div class="p-3 bg-slate-50 dark:bg-white/5 rounded-2xl border border-slate-200/80 dark:border-white/5">
                            <p class="text-sky-600 dark:text-sky-400 font-extrabold text-base"><?php echo $vehiculos['Activo']; ?></p>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 uppercase mt-0.5">Operativos</p>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-white/5 rounded-2xl border border-slate-200/80 dark:border-white/5">
                            <p class="text-purple-600 dark:text-purple-400 font-extrabold text-base"><?php echo $vehiculos['Inactivo']; ?></p>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 uppercase mt-0.5">Inactivos</p>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <!-- FOOTER DENTRO DEL CONTENEDOR DERECHO -->
        <footer class="p-6 text-center text-slate-500 dark:text-slate-500 text-xs font-semibold border-t border-slate-200/80 dark:border-white/5">
            &copy; <?php echo date('Y'); ?> Sistema de Gestión de Transporte SGET. Todos los derechos reservados.
        </footer>
    </div>

    <!-- INICIALIZACIÓN DINÁMICA DEL GRÁFICO CHART.JS CON CAMBIO DE TEMA -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const ctx = document.getElementById('graficoVehiculos');
            if (ctx) {
                let chartInstance;

                function renderChart() {
                    const esOscuro = document.documentElement.classList.contains('dark');
                    
                    if (chartInstance) {
                        chartInstance.destroy();
                    }

                    chartInstance = new Chart(ctx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Activos', 'Inactivos'],
                            datasets: [{
                                data: [<?php echo $vehiculos['Activo']; ?>, <?php echo $vehiculos['Inactivo']; ?>],
                                backgroundColor: ['#38bdf8', '#a855f7'],
                                borderColor: esOscuro ? '#121826' : '#ffffff',
                                borderWidth: 4,
                                hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            cutout: '75%'
                        }
                    });
                }

                renderChart();

                // Observador para redibujar el gráfico si el tema cambia
                const observer = new MutationObserver(() => renderChart());
                observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            }
        });
    </script>
</body>
</html>
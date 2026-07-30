<?php
// Archivo: admin.php (Dashboard General)
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'];

// --- CONSULTAS DEL DASHBOARD CORREGIDAS ---
$mes_actual = date('m');
$anio_actual = date('Y');

// 1. Usuarios Totales (Conductores y Pasajeros usando id_rol_usu)
$res_total_usu = $conexion->query("SELECT COUNT(*) as total FROM usuario WHERE id_rol_usu IN (2,3)");
$total_usuarios = $res_total_usu ? $res_total_usu->fetch_assoc()['total'] : 0;

$res_mes_usu = $conexion->query("SELECT COUNT(*) as mes FROM usuario WHERE id_rol_usu IN (2,3)"); 
$usuarios_mes = $res_mes_usu ? $res_mes_usu->fetch_assoc()['mes'] : 0;
$porcentaje_usu = $total_usuarios > 0 ? round(($usuarios_mes / $total_usuarios) * 100, 1) : 0;

// 2. Viajes Totales (Usando fec_via de tu tabla original)
$res_total_via = $conexion->query("SELECT COUNT(*) as total FROM viaje");
$total_viajes = $res_total_via ? $res_total_via->fetch_assoc()['total'] : 0;

$res_mes_via = $conexion->query("SELECT COUNT(*) as mes FROM viaje WHERE MONTH(fec_via) = '$mes_actual' AND YEAR(fec_via) = '$anio_actual'");
$viajes_mes = $res_mes_via ? $res_mes_via->fetch_assoc()['mes'] : 0;
$porcentaje_via = $total_viajes > 0 ? round(($viajes_mes / $total_viajes) * 100, 1) : 0;

// 3. Vehículos Activos e Inactivos
$res_veh = $conexion->query("SELECT est_veh, COUNT(*) as cantidad FROM vehiculo GROUP BY est_veh");
$vehiculos = ['Activo' => 0, 'Inactivo' => 0];
if ($res_veh) {
    while($row = $res_veh->fetch_assoc()) {
        $estado = ($row['est_veh'] == 1) ? 'Activo' : 'Inactivo';
        $vehiculos[$estado] = $row['cantidad'];
    }
}
$total_vehiculos = $vehiculos['Activo'] + $vehiculos['Inactivo'];

// 4. Conductores Disponibles (AJUSTADO: Solo campos garantizados para evitar bloqueos)
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
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Dashboard Principal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'bg-principal': { DEFAULT: '#f8fafc', dark: '#0b0f19' },
                        'bg-tarjeta': { DEFAULT: '#ffffff', dark: '#1e293b' },
                        'texto-base': { DEFAULT: '#334155', dark: '#cbd5e1' },
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7',
                        'color-mutado': '#94a3b8'
                    }
                }
            }
        }
    </script>
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-bg-principal dark:bg-bg-principal-dark flex min-h-screen text-texto-base dark:text-[#cbd5e1] transition-colors duration-300">

    <!-- 1. BARRA LATERAL REUTILIZABLE -->
    <?php include 'sidebar.php'; ?>

    <!-- Contenedor del contenido derecho -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- 2. BARRA SUPERIOR REUTILIZABLE -->
        <?php include 'header.php'; ?>

        <!-- 3. PANEL DE CONTENIDO -->
        <div class="p-8 w-full mx-auto space-y-8 flex-grow">
            
            <!-- Saludo Principal -->
            <div>
                <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight leading-none">
                    ¡Buen día, <span class="bg-gradient-to-r from-neon-azul to-neon-morado bg-clip-text text-transparent"><?php echo explode(' ', $nombreReal)[0]; ?></span>!
                </h1>
                <p class="text-color-mutado mt-2 text-sm font-medium">Aquí está el estado operacional de tu red de transporte para el día de hoy.</p>
            </div>

            <!-- Fila de Tarjetas KPI -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Tarjeta Usuarios -->
                <div class="bg-bg-tarjeta dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm hover:shadow-md transition-all duration-300 relative overflow-hidden group">
                    <div class="flex justify-between items-center relative z-10">
                        <div>
                            <p class="text-xs text-color-mutado font-extrabold uppercase tracking-wider">Usuarios Registrados</p>
                            <h3 class="text-3xl font-black text-slate-800 dark:text-white mt-2"><?php echo $total_usuarios; ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-neon-azul/10 flex items-center justify-center text-neon-azul group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs relative z-10">
                        <span class="text-emerald-500 font-bold bg-emerald-500/10 px-2 py-0.5 rounded-md flex items-center gap-1">
                            <i class="fas fa-arrow-up text-[10px]"></i> +<?php echo $porcentaje_usu; ?>%
                        </span>
                        <span class="text-color-mutado ml-2 font-medium">registrados</span>
                    </div>
                </div>
  
                <!-- Tarjeta Viajes -->
                <div class="bg-bg-tarjeta dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm hover:shadow-md transition-all duration-300 relative overflow-hidden group">
                    <div class="flex justify-between items-center relative z-10">
                        <div>
                            <p class="text-xs text-color-mutado font-extrabold uppercase tracking-wider">Viajes Completados</p>
                            <h3 class="text-3xl font-black text-slate-800 dark:text-white mt-2"><?php echo $total_viajes; ?></h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-neon-morado/10 flex items-center justify-center text-neon-morado group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-route text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs relative z-10">
                        <span class="text-emerald-500 font-bold bg-emerald-500/10 px-2 py-0.5 rounded-md flex items-center gap-1">
                            <i class="fas fa-arrow-up text-[10px]"></i> +<?php echo $porcentaje_via; ?>%
                        </span>
                        <span class="text-color-mutado ml-2 font-medium">despachados este mes</span>
                    </div>
                </div>

                <!-- Tarjeta Vehículos -->
                <div class="bg-bg-tarjeta dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm hover:shadow-md transition-all duration-300 relative overflow-hidden group">
                    <div class="flex justify-between items-center relative z-10">
                        <div>
                            <p class="text-xs text-color-mutado font-extrabold uppercase tracking-wider">Flota de Vehículos</p>
                            <h3 class="text-3xl font-black text-slate-800 dark:text-white mt-2"><?php echo $vehiculos['Activo']; ?> <span class="text-sm font-semibold text-color-mutado">Activos</span></h3>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-500 group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-bus text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-xs relative z-10">
                        <span class="text-amber-500 font-bold bg-amber-500/10 px-2 py-0.5 rounded-md">
                            <?php echo $vehiculos['Inactivo']; ?> Fuera de servicio
                        </span>
                    </div>
                </div>
            </div>

            <!-- Fila Inferior: Tabla de Conductores y Gráfico Operacional -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Tabla Conductores (Estructura adaptada sin campos conflictivos) -->
                <div class="lg:col-span-2 bg-bg-tarjeta dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm flex flex-col">
                    <div class="flex justify-between items-center mb-5">
                        <h4 class="text-base font-extrabold text-slate-800 dark:text-white tracking-tight flex items-center gap-2">
                            <i class="fas fa-id-card text-neon-azul"></i> Conductores Disponibles
                        </h4>
                        <span class="text-[11px] bg-neon-azul/10 text-neon-azul px-2.5 py-1 rounded-full font-bold uppercase tracking-wider">Línea de Espera</span>
                    </div>
                    <div class="overflow-x-auto flex-grow">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-white/5 text-[11px] font-extrabold text-color-mutado uppercase tracking-wider">
                                    <th class="pb-3 pl-2">ID Sistema</th>
                                    <th class="pb-3">Nombre Conductor</th>
                                    <th class="pb-3 pr-2 text-right">Vehículo Asignado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5 text-xs font-medium">
                                <?php if ($conductores_disponibles && $conductores_disponibles->num_rows > 0): 
                                    while($con = $conductores_disponibles->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors duration-150">
                                        <td class="py-3.5 pl-2 font-mono text-color-mutado">#<?php echo htmlspecialchars($con['id_usu']); ?></td>
                                        <td class="py-3.5 font-bold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($con['nom_usu']); ?></td>
                                        <td class="py-3.5 pr-2 text-right">
                                            <span class="bg-slate-100 dark:bg-white/10 text-slate-800 dark:text-white px-2 py-0.5 rounded-md font-mono font-bold text-[10px]">
                                                <?php echo $con['pla_veh'] ? htmlspecialchars($con['pla_veh']) : 'Sin Asignar'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="3" class="py-8 text-center text-color-mutado italic font-medium">No se encontraron conductores con estado 'Disponible'</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Gráfico de Dona de Vehículos -->
                <div class="bg-bg-tarjeta dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm flex flex-col justify-between items-center">
                    <div class="w-full text-left mb-4">
                        <h4 class="text-base font-extrabold text-slate-800 dark:text-white tracking-tight flex items-center gap-2">
                            <i class="fas fa-chart-pie text-neon-morado"></i> Disponibilidad de Flota
                        </h4>
                    </div>
                    <div class="relative w-44 h-44 flex items-center justify-center">
                        <canvas id="graficoVehiculos"></canvas>
                    </div>
                    <div class="w-full grid grid-cols-2 gap-2 mt-4 text-center text-xs font-semibold">
                        <div class="p-2 bg-slate-50 dark:bg-white/5 rounded-xl border border-slate-100 dark:border-transparent">
                            <p class="text-neon-azul font-bold"><?php echo $vehiculos['Activo']; ?></p>
                            <p class="text-[10px] text-color-mutado mt-0.5">Operativos</p>
                        </div>
                        <div class="p-2 bg-slate-50 dark:bg-white/5 rounded-xl border border-slate-100 dark:border-transparent">
                            <p class="text-neon-morado font-bold"><?php echo $vehiculos['Inactivo']; ?></p>
                            <p class="text-[10px] text-color-mutado mt-0.5">Inactivos</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Pie de página integrado -->
        <footer class="p-6 text-center text-color-mutado text-xs font-semibold border-t border-slate-200 dark:border-white/5 bg-slate-50/20 dark:bg-transparent">
            &copy; <?php echo date('Y'); ?> Sistema de Gestión de Transporte SGET. Todos los derechos reservados.
        </footer>
    </main>

    <!-- INICIALIZACIÓN DEL GRÁFICO CON CHART.JS -->
    <script>
        const ctx = document.getElementById('graficoVehiculos').getContext('2d');
        const esOscuroInicial = document.documentElement.classList.contains('dark');

        const chartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Activos', 'Inactivos'],
                datasets: [{
                    data: [<?php echo $vehiculos['Activo']; ?>, <?php echo $vehiculos['Inactivo']; ?>],
                    backgroundColor: ['#38bdf8', '#a855f7'],
                    borderColor: esOscuroInicial ? '#1e293b' : '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                cutout: '75%'
            }
        });
    </script>
</body>
</html>
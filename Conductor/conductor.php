<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 2) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Conductor";
$documento  = $_SESSION['documento'];

$id_conductor = 0;
$total_viajes = 0;
$viajes_data = [];
$promedio = 0;
$total_votos = 0;

// Consulta preparada para obtener el ID del usuario
$stmt_user = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ?");
$stmt_user->bind_param("s", $documento);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user && $result_user->num_rows > 0) {
    $user_data = $result_user->fetch_assoc();
    $id_conductor = $user_data['id_usu'];

    // 1. Contar total de viajes
    $stmt_count = $conexion->prepare("SELECT COUNT(*) as total FROM viaje WHERE id_usu_via = ?");
    $stmt_count->bind_param("i", $id_conductor);
    $stmt_count->execute();
    $total_viajes = $stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();

    // 2. Obtener promedio de calificación
    $stmt_cal = $conexion->prepare("SELECT AVG(pun_cal) as promedio, COUNT(id_cal) as total FROM calificacion WHERE id_usu_des = ?");
    $stmt_cal->bind_param("i", $id_conductor);
    $stmt_cal->execute();
    $res_cal = $stmt_cal->get_result();
    if ($res_cal) {
        $datos_cal = $res_cal->fetch_assoc();
        $promedio = round($datos_cal['promedio'] ?? 0, 1);
        $total_votos = $datos_cal['total'] ?? 0;
    }
    $stmt_cal->close();

    // 3. Consulta de viajes recientes
    $sql_viajes = "SELECT v.*, r.des_rut, ve.pla_veh, ve.mode_veh,
                    (SELECT COUNT(*) FROM reserva WHERE id_via_res = v.id_via) as num_pasajeros
                    FROM viaje v 
                    JOIN rutas r ON v.id_rut_via = r.id_rut 
                    LEFT JOIN vehiculo ve ON v.id_veh = ve.id_veh 
                    WHERE v.id_usu_via = ? 
                    ORDER BY v.fec_via DESC LIMIT 5";
    
    $stmt_viajes = $conexion->prepare($sql_viajes);
    $stmt_viajes->bind_param("i", $id_conductor);
    $stmt_viajes->execute();
    $result_viajes = $stmt_viajes->get_result();
    
    while($row = $result_viajes->fetch_assoc()) {
        $viajes_data[] = $row;
    }
    $stmt_viajes->close();
}
$stmt_user->close();

$vehiculoReciente = (!empty($viajes_data)) ? $viajes_data[0] : null;
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Conductor - SGET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'bg-principal': { DEFAULT: '#f8fafc', dark: '#0b0f19' },
                        'bg-tarjeta': { DEFAULT: '#ffffff', dark: '#1e293b' },
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
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

    <!-- 1. SIDEBAR FIJO (Ancho 64) -->
    <?php include 'sidebar.php'; ?>

    <!-- 2. CONTENEDOR DERECHO FLUIDO (Margin-Left de 64 para respetar la barra lateral) -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- HEADER SUPERIOR -->
        <?php include 'header_conductor.php'; ?>

        <!-- CONTENIDO DEL DASHBOARD -->
        <main class="p-8 space-y-8 flex-1">
            
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Panel General</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Bienvenido de vuelta al sistema de gestión de rutas.</p>
            </div>
            
            <!-- TARJETAS DE MÉTRICAS -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl">
                
                <!-- Card 1: Viajes Totales -->
                <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl relative overflow-hidden flex items-center justify-between group hover:border-slate-300 dark:hover:border-white/10 transition-all duration-300 shadow-sm hover:shadow-md">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Viajes Totales</p>
                        <h4 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo $total_viajes; ?></h4>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center border border-blue-500/20">
                        <i class="fas fa-route text-lg"></i>
                    </div>
                </div>

                <!-- Card 2: Vehículo Asignado -->
                <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl relative overflow-hidden flex items-center justify-between group hover:border-slate-300 dark:hover:border-white/10 transition-all duration-300 shadow-sm hover:shadow-md">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Vehículo Asignado</p>
                        <?php if($vehiculoReciente && !empty($vehiculoReciente['pla_veh'])): ?>
                            <h4 class="text-2xl font-black text-blue-600 dark:text-neon-azul uppercase tracking-wide"><?php echo htmlspecialchars($vehiculoReciente['pla_veh'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium uppercase mt-0.5"><?php echo htmlspecialchars($vehiculoReciente['mode_veh'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php else: ?>
                            <h4 class="text-sm font-bold text-slate-400 dark:text-slate-500 italic">No asignado</h4>
                        <?php endif; ?>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                        <i class="fas fa-bus text-lg"></i>
                    </div>
                </div>

                <!-- Card 3: Reputación -->
                <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl relative overflow-hidden flex items-center justify-between group hover:border-slate-300 dark:hover:border-white/10 transition-all duration-300 shadow-sm hover:shadow-md">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Reputación</p>
                        <div class="flex items-baseline gap-1.5">
                            <h4 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo ($total_votos > 0) ? number_format($promedio, 1) : "0.0"; ?></h4>
                            <span class="text-slate-400 dark:text-slate-500 text-xs font-bold">/ 5.0</span>
                        </div>
                        <div class="flex text-amber-400 text-[10px] mt-1.5 gap-0.5 items-center">
                            <?php
                            $estrellas_enteras = floor($promedio);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $estrellas_enteras) {
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="far fa-star text-slate-300 dark:text-slate-700"></i>';
                                }
                            }
                            ?>
                            <span class="ml-2 text-slate-500 dark:text-slate-400 font-medium text-[10px]">(<?php echo $total_votos; ?> reseñas)</span>
                        </div>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-amber-500/10 text-amber-500 dark:text-amber-400 flex items-center justify-center border border-amber-500/20">
                        <i class="fas fa-star text-lg"></i>
                    </div>
                </div>
            </div>

            <!-- TABLA DE HISTORIAL DE VIAJES -->
            <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl max-w-6xl transition-colors duration-300">
                <h3 class="font-bold text-slate-900 dark:text-white text-base mb-4 tracking-tight flex items-center gap-2">
                    <i class="fas fa-history text-slate-400 dark:text-slate-500 text-sm"></i> Últimos Viajes Registrados
                </h3>
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-white/5">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                            <tr>
                                <th class="px-5 py-3.5">Destino</th>
                                <th class="px-5 py-3.5">Fecha / Hora</th>
                                <th class="px-5 py-3.5">Vehículo</th>
                                <th class="px-5 py-3.5 text-center">Pasajeros</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                            <?php if(!empty($viajes_data)): ?>
                                <?php foreach($viajes_data as $v): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-5 py-4 font-bold text-slate-900 dark:text-white capitalize"><?php echo htmlspecialchars($v['des_rut'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-5 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono">
                                        <?php echo !empty($v['fec_via']) ? date('d/m/Y - h:i A', strtotime($v['fec_via'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="bg-slate-100 dark:bg-white/5 text-blue-600 dark:text-neon-azul border border-slate-200 dark:border-white/10 px-3 py-1 rounded-md text-[10px] font-black tracking-wider uppercase font-mono">
                                            <?php echo htmlspecialchars($v['pla_veh'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-500/10 border border-emerald-500/20 rounded-md py-0.5 max-w-[50px] mx-auto text-xs">
                                            <?php echo (int)($v['num_pasajeros'] ?? 0); ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-5 py-8 text-center text-slate-400 dark:text-slate-500 italic">No se encontraron registros de viajes para este conductor.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- SCRIPT DE TEMA SINCRONIZADO GLOBALMENTE -->
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
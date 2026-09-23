<?php
// Archivo: Admin/admin.php
date_default_timezone_set('America/Bogota');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar diccionario de idioma global
$idiomaActual = $_SESSION['sget_idioma'] ?? 'es';
if ($idiomaActual === 'en') {
    @include_once __DIR__ . '/../lang/en.php';
} else {
    @include_once __DIR__ . '/../lang/es.php';
}

require_once __DIR__ . '/../assets/conexion.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';

// Verificación de seguridad para Administrador (Rol 1)
$rolSesion = $_SESSION['rol'] ?? $_SESSION['id_rol_usu'] ?? 0;
if (!isset($_SESSION['documento']) || $rolSesion != 1) {
    header("Location: ../index.php");
    exit();
}

$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'admin');

$nombreReal = $_SESSION['nombre_usuario'] ?? 'Administrador';

// --- CONSULTAS OPERATIVAS DEL DASHBOARD ---
$mes_actual = date('m');
$anio_actual = date('Y');

// 1. Estadísticas de Usuarios
$total_usuarios = 0;
$res_total_usu = $conexion->query("SELECT COUNT(*) as total FROM usuario WHERE id_rol_usu IN (2,3)");
if ($res_total_usu) {
    $row = $res_total_usu->fetch_assoc();
    $total_usuarios = $row['total'] ?? 0;
}

$usuarios_mes = 0;
$res_mes_usu = $conexion->query("SELECT COUNT(*) as mes FROM usuario WHERE id_rol_usu IN (2,3)");
if ($res_mes_usu) {
    $row = $res_mes_usu->fetch_assoc();
    $usuarios_mes = $row['mes'] ?? 0;
}
$porcentaje_usu = $total_usuarios > 0 ? round(($usuarios_mes / $total_usuarios) * 100, 1) : 0;

// 2. Estadísticas de Viajes
$total_viajes = 0;
$res_total_via = $conexion->query("SELECT COUNT(*) as total FROM viaje");
if ($res_total_via) {
    $row = $res_total_via->fetch_assoc();
    $total_viajes = $row['total'] ?? 0;
}

$viajes_mes = 0;
$res_mes_via = $conexion->query("SELECT COUNT(*) as mes FROM viaje WHERE MONTH(fec_via) = '$mes_actual' AND YEAR(fec_via) = '$anio_actual'");
if ($res_mes_via) {
    $row = $res_mes_via->fetch_assoc();
    $viajes_mes = $row['mes'] ?? 0;
}
$porcentaje_via = $total_viajes > 0 ? round(($viajes_mes / $total_viajes) * 100, 1) : 0;

// 3. Estado de la Flota de Vehículos
$vehiculos = ['Activo' => 0, 'Inactivo' => 0];
$res_veh = $conexion->query("SELECT est_veh, COUNT(*) as cantidad FROM vehiculo GROUP BY est_veh");
if ($res_veh) {
    while($row = $res_veh->fetch_assoc()) {
        $estado = ($row['est_veh'] == 1) ? 'Activo' : 'Inactivo';
        $vehiculos[$estado] = $row['cantidad'];
    }
}

// 4. Conductores Disponibles
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
<html lang="<?php echo $idiomaActual; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Dashboard Principal</title>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && true)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style_admin.css">
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">

   <?php 
   if (file_exists(__DIR__ . '/../includes/sidebar.php')) {
       include __DIR__ . '/../includes/sidebar.php';
   }
   ?>

    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen flex-1 transition-all duration-300 min-w-0">
        <?php 
        if (file_exists(__DIR__ . '/../includes/header.php')) {
            include __DIR__ . '/../includes/header.php';
        } else {
            echo "<header class='p-4 bg-slate-800 text-white'>Header SGET</header>";
        }
        ?>

        <main class="space-y-8 flex-grow pb-12 relative z-10 p-8 max-w-[1600px] w-auto mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/5 dark:bg-white/[0.02] p-6 rounded-3xl border border-slate-200 dark:border-white/5 backdrop-blur-md">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight"><?php echo $lang['bienvenido'] ?? 'Bienvenido al Panel General'; ?></h2>
                        <button type="button" onclick="abrirModalAyuda()" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer" title="Ver guía del módulo">
                            <i class="fas fa-question text-[10px]"></i>
                        </button>
                    </div>
                    <p class="text-slate-500 dark:text-slate-400 text-xs mt-1"><?php echo $lang['sub_bienvenido'] ?? 'Resumen general de operaciones logísticas, control de flota y personal de SGET.'; ?></p>
                </div>
                <div class="flex items-center gap-2 bg-blue-500/10 text-blue-500 dark:text-sky-400 px-4 py-2 rounded-xl text-xs font-bold border border-blue-500/20">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('d \d\e F, Y'); ?>
                </div>
            </div>

            <!-- TARJETAS DE MÉTRICAS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $lang['total_usuarios'] ?? 'TOTAL USUARIOS'; ?></p>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono"><?php echo number_format($total_usuarios); ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-sky-500/10 text-sky-500 rounded-2xl flex items-center justify-center text-lg border border-sky-500/20">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs text-emerald-500 font-semibold">
                        <i class="fas fa-chart-line"></i> <span><?php echo $porcentaje_usu; ?>% de participación activa</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $lang['viajes_registrados'] ?? 'VIAJES REGISTRADOS'; ?></p>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono"><?php echo number_format($total_viajes); ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-purple-500/10 text-purple-500 rounded-2xl flex items-center justify-center text-lg border border-purple-500/20">
                            <i class="fas fa-route"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs text-purple-400 font-semibold">
                        <i class="fas fa-calendar-check"></i> <span><?php echo $viajes_mes; ?> despachos este mes</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $lang['flota_disponible'] ?? 'FLOTA DISPONIBLE'; ?></p>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono"><?php echo $vehiculos['Activo']; ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-emerald-500/10 text-emerald-500 rounded-2xl flex items-center justify-center text-lg border border-emerald-500/20">
                            <i class="fas fa-bus"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs text-slate-400 font-semibold">
                        <span>Inactivos: <b class="text-red-400"><?php echo $vehiculos['Inactivo']; ?></b></span>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $lang['eficiencia_operativa'] ?? 'EFICIENCIA OPERATIVA'; ?></p>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono">98.4%</h3>
                        </div>
                        <div class="w-12 h-12 bg-amber-500/10 text-amber-500 rounded-2xl flex items-center justify-center text-lg border border-amber-500/20">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs text-emerald-500 font-semibold">
                        <i class="fas fa-check-circle"></i> <span>Sistema operando sin bloqueos</span>
                    </div>
                </div>

            </div>

            <!-- SECCIÓN INFERIOR -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fas fa-id-card text-sky-400"></i> <?php echo $lang['conductores_turno'] ?? 'Conductores Disponibles en Turno'; ?>
                        </h3>
                        <a href="ranking_conductores.php" class="text-xs text-sky-400 hover:underline font-bold"><?php echo $lang['ver_ranking'] ?? 'Ver Ranking'; ?></a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-white/5 text-[10px] text-slate-400 uppercase font-bold">
                                    <th class="pb-3"><?php echo $lang['conductor'] ?? 'Conductor'; ?></th>
                                    <th class="pb-3"><?php echo $lang['vehiculo_asignado'] ?? 'Vehículo Asignado'; ?></th>
                                    <th class="pb-3 text-center"><?php echo $lang['estado'] ?? 'Estado'; ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5 text-xs">
                                <?php if ($conductores_disponibles && $conductores_disponibles->num_rows > 0): ?>
                                    <?php while($c = $conductores_disponibles->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="py-3.5 font-bold text-slate-800 dark:text-white flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-full bg-slate-200 dark:bg-white/10 flex items-center justify-center text-xs font-black">
                                                <?php echo strtoupper(substr($c['nom_usu'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($c['nom_usu']); ?>
                                        </td>
                                        <td class="py-3.5 font-mono text-slate-500 dark:text-slate-300">
                                            <?php echo $c['pla_veh'] ? htmlspecialchars($c['pla_veh']) : '<span class="text-amber-400 italic">' . ($lang['sin_asignar'] ?? 'Sin asignar') . '</span>'; ?>
                                        </td>
                                        <td class="py-3.5 text-center">
                                            <span class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-[10px] font-extrabold uppercase"><?php echo $lang['disponible'] ?? 'Disponible'; ?></span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="py-8 text-center text-slate-400 italic">No hay conductores disponibles registrados en este momento.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl flex flex-col justify-between">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                            <i class="fas fa-bolt text-amber-400"></i> <?php echo $lang['accesos_rapidos'] ?? 'Accesos Rápidos'; ?>
                        </h3>
                        <p class="text-xs text-slate-400 mb-6 leading-relaxed"><?php echo $lang['desc_accesos'] ?? 'Utiliza los accesos directos para gestionar las tareas logísticas frecuentes de manera inmediata.'; ?></p>
                        
                        <div class="space-y-3">
                            <a href="viajes.php" class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-white/[0.03] hover:bg-slate-100 dark:hover:bg-white/[0.06] border border-slate-200 dark:border-white/5 rounded-2xl transition-all group">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2.5">
                                    <i class="fas fa-plus-circle text-sky-400"></i> <?php echo $lang['btn_despachar'] ?? 'Despachar Nuevo Viaje'; ?>
                                </span>
                                <i class="fas fa-chevron-right text-xs text-slate-400 group-hover:translate-x-1 transition-transform"></i>
                            </a>
                            <a href="asignaciones.php" class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-white/[0.03] hover:bg-slate-100 dark:hover:bg-white/[0.06] border border-slate-200 dark:border-white/5 rounded-2xl transition-all group">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2.5">
                                    <i class="fas fa-ticket-alt text-purple-400"></i> <?php echo $lang['btn_recauda'] ?? 'Registrar Reserva / Recaudo'; ?>
                                </span>
                                <i class="fas fa-chevron-right text-xs text-slate-400 group-hover:translate-x-1 transition-transform"></i>
                            </a>
                            <a href="gestion_permisos.php" class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-white/[0.03] hover:bg-slate-100 dark:hover:bg-white/[0.06] border border-slate-200 dark:border-white/5 rounded-2xl transition-all group">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2.5">
                                    <i class="fas fa-user-shield text-emerald-400"></i> <?php echo $lang['btn_restricciones'] ?? 'Configurar Restricciones'; ?>
                                </span>
                                <i class="fas fa-chevron-right text-xs text-slate-400 group-hover:translate-x-1 transition-transform"></i>
                            </a>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-white/5 text-center">
                        <span class="text-[10px] font-mono text-slate-400 uppercase tracking-widest">SGET v2.5 - Módulo Admin</span>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- MODAL DE AYUDA -->
    <div id="overlayAyuda" onclick="cerrarModalAyuda()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="modalAyuda" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-4 transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <i class="fas fa-info-circle text-sky-400"></i> Guía del Panel General (Dashboard)
                </h3>
                <button onclick="cerrarModalAyuda()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center"><i class="fas fa-times text-xs"></i></button>
            </div>
            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                <li class="flex items-start gap-2">
                    <i class="fas fa-chart-pie text-sky-400 mt-0.5"></i>
                    <span><b>Métricas Principales:</b> Visualiza en tiempo real el volumen de usuarios, viajes despachados y la disponibilidad operativa de la flota.</span>
                </li>
            </ul>
            <button onclick="cerrarModalAyuda()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer mt-2">
                Entendido
            </button>
        </div>
    </div>

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
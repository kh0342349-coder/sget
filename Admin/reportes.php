<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

// 1. Verificación de Seguridad (Admin = Rol 1)
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// 2. Control de Pestañas (Tabs)
$tab = $_GET['tab'] ?? 'general'; 
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Panel de Reportes Analíticos</title>

    <!-- PREVENCIÓN DE FLASHEO DE MODO OSCURO (Sincronizado con 'theme' y 'color-theme') -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || localStorage.getItem('color-theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

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
                        'neon-morado': '#a855f7',
                        'color-mutado': { DEFAULT: '#64748b', dark: '#94a3b8' }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] flex min-h-screen antialiased text-slate-800 dark:text-slate-100 transition-colors duration-300">

    <!-- BARRA LATERAL -->
    <?php include 'sidebar.php'; ?>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- HEADER MODULAR REUTILIZABLE -->
        <?php include 'header.php'; ?>

        <!-- SECCIÓN DE CONTENIDO -->
        <main class="p-8 flex-1 space-y-6">
            
            <!-- ENCABEZADO DE SECCIÓN -->
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Panel de Inteligencia Logística</h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Historial integral de operaciones, reservas, viajes programados y métricas operativas.</p>
            </div>

            <!-- CONTROL DE PESTAÑAS (TABS) -->
            <div class="flex flex-wrap gap-2 bg-slate-200/80 dark:bg-[#1e293b] p-1.5 rounded-xl border border-slate-300/60 dark:border-white/5 w-fit shadow-md backdrop-blur-sm">
                <a href="reportes.php?tab=general" 
                   class="px-5 py-2.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all duration-200 flex items-center gap-2 <?php echo $tab == 'general' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-white/[0.05]'; ?>">
                    <i class="fas fa-chart-pie text-xs"></i> Consolidado General
                </a>
                <a href="reportes.php?tab=viajes" 
                   class="px-5 py-2.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all duration-200 flex items-center gap-2 <?php echo $tab == 'viajes' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-white/[0.05]'; ?>">
                    <i class="fas fa-route text-xs"></i> Historial de Viajes
                </a>
                <a href="reportes.php?tab=reservas" 
                   class="px-5 py-2.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all duration-200 flex items-center gap-2 <?php echo $tab == 'reservas' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-white/[0.05]'; ?>">
                    <i class="fas fa-ticket-alt text-xs"></i> Reservas de Pasajeros
                </a>
                <a href="reportes.php?tab=conductores" 
                   class="px-5 py-2.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all duration-200 flex items-center gap-2 <?php echo $tab == 'conductores' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-white/[0.05]'; ?>">
                    <i class="fas fa-id-card text-xs"></i> Rendimiento Conductores
                </a>
            </div>

            <!-- ==========================================
                 PESTAÑA 1: CONSOLIDADO GENERAL
                 ========================================== -->
            <?php if ($tab == 'general'): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <?php
                    // Métricas globales
                    $tot_viajes = $conexion->query("SELECT COUNT(*) as total FROM viaje")->fetch_assoc()['total'] ?? 0;
                    $tot_usuarios = $conexion->query("SELECT COUNT(*) as total FROM usuario")->fetch_assoc()['total'] ?? 0;
                    $tot_recaudo = $conexion->query("SELECT SUM(val_via) as total FROM viaje")->fetch_assoc()['total'] ?? 0;
                    ?>

                    <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200/80 dark:border-white/5 shadow-lg relative overflow-hidden backdrop-blur-sm transition-colors duration-300">
                        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-blue-500 to-transparent"></div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">Total Viajes Registrados</p>
                        <h3 class="text-3xl font-extrabold text-slate-800 dark:text-white mt-2 font-mono"><?php echo number_format($tot_viajes); ?></h3>
                        <p class="text-[10px] text-blue-600 dark:text-blue-400 mt-2 font-semibold"><i class="fas fa-arrow-up mr-1"></i> Registros globales en BD</p>
                    </div>

                    <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200/80 dark:border-white/5 shadow-lg relative overflow-hidden backdrop-blur-sm transition-colors duration-300">
                        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-purple-500 to-transparent"></div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">Usuarios Registrados</p>
                        <h3 class="text-3xl font-extrabold text-slate-800 dark:text-white mt-2 font-mono"><?php echo number_format($tot_usuarios); ?></h3>
                        <p class="text-[10px] text-purple-600 dark:text-purple-400 mt-2 font-semibold"><i class="fas fa-users mr-1"></i> Admins, Conductores y Pasajeros</p>
                    </div>

                    <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200/80 dark:border-white/5 shadow-lg relative overflow-hidden backdrop-blur-sm transition-colors duration-300">
                        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-emerald-500 to-transparent"></div>
                        <p class="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">Flujo Total Estimado</p>
                        <h3 class="text-3xl font-extrabold text-slate-800 dark:text-white mt-2 font-mono">$<?php echo number_format($tot_recaudo); ?></h3>
                        <p class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-2 font-semibold"><i class="fas fa-coins mr-1"></i> Acumulado general tarifario</p>
                    </div>

                </div>
            <?php endif; ?>

            <!-- ==========================================
                 PESTAÑA 2: HISTORIAL DE VIAJES
                 ========================================== -->
            <?php if ($tab == 'viajes'): ?>
                <div class="bg-white dark:bg-[#1e293b] rounded-2xl border border-slate-200/80 dark:border-white/5 shadow-xl overflow-hidden backdrop-blur-sm transition-colors duration-300">
                    <div class="p-6 border-b border-slate-200 dark:border-white/5 flex justify-between items-center">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Trazabilidad Total de Viajes</h2>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono uppercase mt-0.5 tracking-wider">Monitoreo general de asignación de servicios logísticos</p>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-100/80 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ID / Conductor</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Ruta Programada</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fecha / Hora</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Tarifa</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                                <?php
                                $sql_viajes = "SELECT v.id_via, u.nom_usu, r.nom_rut, v.fec_via, v.val_via, v.est_via 
                                               FROM viaje v
                                               LEFT JOIN usuario u ON v.id_usu_via = u.id_usu
                                               LEFT JOIN rutas r ON v.id_rut_via = r.id_rut
                                               ORDER BY v.fec_via DESC";
                                $res_viajes = $conexion->query($sql_viajes);

                                if ($res_viajes && $res_viajes->num_rows > 0):
                                    while($row = $res_viajes->fetch_assoc()): ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-all group">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <span class="text-xs font-mono font-bold text-slate-400 dark:text-slate-500">#<?php echo $row['id_via']; ?></span>
                                                    <span class="text-sm font-semibold text-slate-800 dark:text-slate-200 group-hover:text-blue-600 dark:group-hover:text-white uppercase"><?php echo htmlspecialchars($row['nom_usu'] ?? 'Sin Asignar'); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-2.5 py-1 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/5 text-slate-700 dark:text-slate-300 rounded-lg text-xs">
                                                    <i class="fas fa-map-pin text-blue-500 dark:text-neon-azul mr-2 text-[10px]"></i> <?php echo htmlspecialchars($row['nom_rut'] ?? 'Ruta No Especificada'); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-xs font-mono text-slate-500 dark:text-slate-400">
                                                <?php echo date("d/m/Y • h:i A", strtotime($row['fec_via'])); ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="text-sm font-bold font-mono text-slate-800 dark:text-white">$<?php echo number_format($row['val_via']); ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <?php 
                                                $est = $row['est_via'];
                                                $badge_style = ($est == 'Completado') 
                                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20' 
                                                    : (($est == 'Pendiente') 
                                                        ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20' 
                                                        : 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-white/10');
                                                ?>
                                                <span class="inline-block px-3 py-1.5 <?php echo $badge_style; ?> rounded-xl text-[10px] font-extrabold uppercase tracking-widest border">
                                                    <?php echo htmlspecialchars($est); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; 
                                else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 text-xs">
                                            No hay registros de viajes registrados en el sistema.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ==========================================
                 PESTAÑA 3: RESERVAS DE PASAJEROS
                 ========================================== -->
            <?php if ($tab == 'reservas'): ?>
                <div class="bg-white dark:bg-[#1e293b] rounded-2xl border border-slate-200/80 dark:border-white/5 shadow-xl overflow-hidden backdrop-blur-sm transition-colors duration-300">
                    <div class="p-6 border-b border-slate-200 dark:border-white/5 flex justify-between items-center">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Solicitudes de Reservas</h2>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono uppercase mt-0.5 tracking-wider">Histórico de cupos solicitados por pasajeros</p>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-100/80 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pasajero</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Ruta Destino</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Fecha del Servicio</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Valor</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Estado de Reserva</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                                <?php
                                $sql_res = "SELECT v.id_via, u.nom_usu, r.nom_rut, v.fec_via, v.val_via, v.est_via 
                                            FROM viaje v
                                            INNER JOIN usuario u ON v.id_usu_via = u.id_usu
                                            INNER JOIN rutas r ON v.id_rut_via = r.id_rut
                                            WHERE u.id_rol_usu = 3
                                            ORDER BY v.fec_via DESC";
                                $res_res = $conexion->query($sql_res);

                                if ($res_res && $res_res->num_rows > 0):
                                    while($row = $res_res->fetch_assoc()): ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-all group">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-4">
                                                    <div class="w-9 h-9 bg-slate-100 dark:bg-white/5 border border-slate-200/80 dark:border-white/10 text-blue-600 dark:text-neon-azul rounded-xl flex items-center justify-center font-bold text-xs shadow-inner">
                                                        <?php echo strtoupper(substr($row['nom_usu'], 0, 1)); ?>
                                                    </div>
                                                    <span class="text-sm font-semibold text-slate-800 dark:text-slate-200 uppercase"><?php echo htmlspecialchars($row['nom_usu']); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-2.5 py-1 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/5 text-slate-700 dark:text-slate-300 rounded-lg text-xs">
                                                    <i class="fas fa-map-pin text-blue-500 dark:text-neon-azul mr-2 text-[10px]"></i> <?php echo htmlspecialchars($row['nom_rut']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center text-xs font-mono text-slate-500 dark:text-slate-400">
                                                <?php echo date("d/m/Y • h:i A", strtotime($row['fec_via'])); ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="text-sm font-bold font-mono text-slate-800 dark:text-white">$<?php echo number_format($row['val_via']); ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="inline-block px-3 py-1.5 bg-blue-500/10 border border-blue-500/20 text-blue-700 dark:text-neon-azul rounded-xl text-[10px] font-extrabold uppercase tracking-widest">
                                                    <?php echo htmlspecialchars($row['est_via']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; 
                                else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-slate-400 text-xs">
                                            No se encuentran solicitudes ni reservas activas en el sistema.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ==========================================
                 PESTAÑA 4: CONDUCTORES Y CALIFICACIÓN
                 ========================================== -->
            <?php if ($tab == 'conductores'): ?>
                <div class="bg-white dark:bg-[#1e293b] rounded-2xl border border-slate-200/80 dark:border-white/5 shadow-xl overflow-hidden backdrop-blur-sm transition-colors duration-300">
                    <div class="p-6 border-b border-slate-200 dark:border-white/5 flex justify-between items-center">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Desempeño del Operador / Conductor</h2>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono uppercase mt-0.5 tracking-wider">Promedio de estrellas y aceptación del servicio</p>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-100/80 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                                <tr>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Conductor</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Viajes Completados</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Calificación Promedio</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                                <?php
                                $sql_conductores = "SELECT u.nom_usu, u.num_doc_usu, COUNT(DISTINCT v.id_via) as total_viajes, AVG(c.pun_cal) as promedio 
                                                    FROM usuario u
                                                    LEFT JOIN viaje v ON u.id_usu = v.id_usu_via
                                                    LEFT JOIN calificacion c ON u.id_usu = c.id_usu_des
                                                    WHERE u.id_rol_usu = 2
                                                    GROUP BY u.id_usu";
                                $res_cond = $conexion->query($sql_conductores);

                                if ($res_cond && $res_cond->num_rows > 0):
                                    while($row = $res_cond->fetch_assoc()): 
                                        $prom = round($row['promedio'] ?? 0, 1);
                                    ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-all">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-9 h-9 bg-slate-100 dark:bg-white/5 border border-slate-200/80 dark:border-white/10 text-purple-600 dark:text-neon-morado rounded-xl flex items-center justify-center font-bold text-xs">
                                                        <?php echo strtoupper(substr($row['nom_usu'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 uppercase"><?php echo htmlspecialchars($row['nom_usu']); ?></p>
                                                        <p class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">CC: <?php echo $row['num_doc_usu']; ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center font-mono text-xs text-slate-600 dark:text-slate-300">
                                                <?php echo $row['total_viajes']; ?> viajes
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="text-sm font-bold text-amber-500 font-mono">
                                                    <i class="fas fa-star text-amber-400 mr-1"></i><?php echo $prom > 0 ? number_format($prom, 1) : '0.0'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; 
                                else: ?>
                                    <tr>
                                        <td colspan="3" class="px-6 py-12 text-center text-slate-400 text-xs">
                                            No hay registros de conductores en el sistema.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

</body>
</html>
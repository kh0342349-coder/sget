<?php
// Admin/logs.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Cargar conexión y componentes comunes
require_once '../assets/conexion.php'; 
require_once '../helpers/Logger.php'; 

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['id_usu'])) {
    header('Location: ../index.php');
    exit;
}

// 2. Manejo de Filtros
$buscar    = isset($_GET['buscar']) ? $conexion->real_escape_string($_GET['buscar']) : '';
$accion    = isset($_GET['accion']) ? $conexion->real_escape_string($_GET['accion']) : '';
$fecha_ini = isset($_GET['fecha_ini']) ? $conexion->real_escape_string($_GET['fecha_ini']) : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $conexion->real_escape_string($_GET['fecha_fin']) : '';

// 3. Consulta dinámica de Logs
$sql = "SELECT * FROM sget_logs_auditoria WHERE 1=1";

if (!empty($buscar)) {
    $sql .= " AND (nom_usu_log LIKE '%$buscar%' OR descripcion LIKE '%$buscar%' OR ip_origen LIKE '%$buscar%')";
}

if (!empty($accion)) {
    $sql .= " AND accion = '$accion'";
}

if (!empty($fecha_ini)) {
    $sql .= " AND DATE(fec_log) >= '$fecha_ini'";
}

if (!empty($fecha_fin)) {
    $sql .= " AND DATE(fec_log) <= '$fecha_fin'";
}

$sql .= " ORDER BY fec_log DESC LIMIT 100";

$result = $conexion->query($sql);
$logs = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

// 4. Consultas para Métricas Rápidas
$res_total = $conexion->query("SELECT COUNT(*) as total FROM sget_logs_auditoria");
$total_logs = ($res_total && $row = $res_total->fetch_assoc()) ? $row['total'] : 0;

$res_logins = $conexion->query("SELECT COUNT(*) as total FROM sget_logs_auditoria WHERE accion = 'LOGIN' AND DATE(fec_log) = CURDATE()");
$logins_hoy = ($res_logins && $row = $res_logins->fetch_assoc()) ? $row['total'] : 0;

$res_creados = $conexion->query("SELECT COUNT(*) as total FROM sget_logs_auditoria WHERE accion = 'CREAR_USUARIO' AND MONTH(fec_log) = MONTH(CURRENT_DATE()) AND YEAR(fec_log) = YEAR(CURRENT_DATE())");
$creados_mes = ($res_creados && $row = $res_creados->fetch_assoc()) ? $row['total'] : 0;
?>
<!DOCTYPE html>
<html lang="es" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Logs de Auditoría</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(156, 163, 175, 0.4); border-radius: 9999px; }
    </style>
</head>
<body class="bg-slate-100 dark:bg-[#090d16] text-slate-800 dark:text-slate-200 min-h-screen transition-colors duration-300">

    <!-- SIDEBAR -->
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- CONTENEDOR PRINCIPAL CON ESPACIADO PARA EL SIDEBAR -->
    <div id="main-content-wrapper" class="flex-1 flex flex-col min-h-screen px-4 lg:px-8 pb-8 transition-all duration-300">
        
        <!-- HEADER -->
        <?php include_once __DIR__ . '/../includes/header.php'; ?>

        <!-- CONTENIDO DEL MÓDULO -->
        <main class="mt-4 space-y-6">
            
            <!-- Encabezado del Módulo -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-history text-sky-500"></i> Registro de Auditoría y Logs
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Monitoreo de inicios de sesión, registros y acciones en SGET.</p>
                </div>
                <div>
                    <a href="admin.php" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-200/80 dark:bg-white/10 hover:bg-slate-300 dark:hover:bg-white/20 text-slate-700 dark:text-slate-200 text-xs font-extrabold rounded-2xl transition-all shadow-sm">
                        <i class="fas fa-arrow-left"></i> Volver al Panel
                    </a>
                </div>
            </div>

            <!-- Tarjetas de métricas estilo SGET -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white/80 dark:bg-[#0f172a]/80 backdrop-blur-xl p-5 rounded-[24px] border border-slate-200/90 dark:border-white/10 shadow-xl flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Total Eventos</p>
                        <p class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?= number_format($total_logs) ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center text-lg shadow-inner">
                        <i class="fas fa-list-check"></i>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-[#0f172a]/80 backdrop-blur-xl p-5 rounded-[24px] border border-slate-200/90 dark:border-white/10 shadow-xl flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Inicios de Sesión (Hoy)</p>
                        <p class="text-2xl font-black text-emerald-500 mt-1"><?= number_format($logins_hoy) ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-lg shadow-inner">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>

                <div class="bg-white/80 dark:bg-[#0f172a]/80 backdrop-blur-xl p-5 rounded-[24px] border border-slate-200/90 dark:border-white/10 shadow-xl flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Usuarios Creados (Mes)</p>
                        <p class="text-2xl font-black text-purple-500 mt-1"><?= number_format($creados_mes) ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-lg shadow-inner">
                        <i class="fas fa-user-plus"></i>
                    </div>
                </div>
            </div>

            <!-- Formulario de Filtros -->
            <div class="bg-white/80 dark:bg-[#0f172a]/80 backdrop-blur-xl p-4 rounded-[24px] border border-slate-200/90 dark:border-white/10 shadow-xl">
                <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                    
                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-600 dark:text-slate-400 mb-1">Búsqueda</label>
                        <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" placeholder="Nombre, IP, detalle..." class="w-full px-3.5 py-2 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-600 dark:text-slate-400 mb-1">Acción</label>
                        <select name="accion" class="w-full px-3.5 py-2 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 transition-all cursor-pointer">
                            <option value="">Todas</option>
                            <option value="LOGIN" <?= $accion === 'LOGIN' ? 'selected' : '' ?>>LOGIN</option>
                            <option value="LOGOUT" <?= $accion === 'LOGOUT' ? 'selected' : '' ?>>LOGOUT</option>
                            <option value="CREAR_USUARIO" <?= $accion === 'CREAR_USUARIO' ? 'selected' : '' ?>>CREAR_USUARIO</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-600 dark:text-slate-400 mb-1">Fecha Desde</label>
                        <input type="date" name="fecha_ini" value="<?= htmlspecialchars($fecha_ini) ?>" class="w-full px-3.5 py-2 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-600 dark:text-slate-400 mb-1">Fecha Hasta</label>
                        <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>" class="w-full px-3.5 py-2 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 transition-all">
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="w-full bg-sky-500 hover:bg-sky-400 text-slate-950 font-black py-2 px-3 rounded-2xl text-xs transition-all shadow-md shadow-sky-500/20 flex items-center justify-center gap-1 cursor-pointer">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="logs.php" class="bg-slate-200 dark:bg-white/5 hover:bg-slate-300 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 py-2 px-3 rounded-2xl text-xs transition-all flex items-center justify-center">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                    </div>

                </form>
            </div>

            <!-- Tabla de Auditoría -->
            <div class="bg-white/80 dark:bg-[#0f172a]/80 backdrop-blur-xl rounded-[28px] border border-slate-200/90 dark:border-white/10 shadow-2xl overflow-hidden">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50/80 dark:bg-[#1e293b]/50 border-b border-slate-200 dark:border-white/5 text-[10px] uppercase font-black tracking-wider text-slate-400">
                            <tr>
                                <th class="py-4 px-5">Fecha y Hora</th>
                                <th class="py-4 px-5">Usuario</th>
                                <th class="py-4 px-5">Rol</th>
                                <th class="py-4 px-5">Acción</th>
                                <th class="py-4 px-5">Descripción</th>
                                <th class="py-4 px-5">IP Origen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-12 text-slate-400">
                                        <div class="w-14 h-14 bg-slate-100 dark:bg-white/5 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl text-slate-400">
                                            <i class="fas fa-folder-open"></i>
                                        </div>
                                        <p class="font-semibold text-xs">No hay registros de auditoría que coincidan con la búsqueda.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-white/5 transition-all">
                                        <td class="py-3.5 px-5 font-mono text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                            <?= date('d/m/Y H:i:s', strtotime($log['fec_log'])) ?>
                                        </td>
                                        <td class="py-3.5 px-5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                            <?= htmlspecialchars($log['nom_usu_log'] ?? 'Anónimo') ?>
                                        </td>
                                        <td class="py-3.5 px-5 whitespace-nowrap">
                                            <span class="px-2.5 py-1 text-[10px] font-black rounded-xl bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-white/10">
                                                <?= htmlspecialchars($log['nom_rol_log'] ?? 'Sin Rol') ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-5 whitespace-nowrap">
                                            <?php
                                            $badge = match($log['accion']) {
                                                'LOGIN' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                                                'LOGOUT' => 'bg-amber-500/10 text-amber-500 border-amber-500/20',
                                                'CREAR_USUARIO' => 'bg-sky-500/10 text-sky-500 border-sky-500/20',
                                                default => 'bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-white/10'
                                            };
                                            ?>
                                            <span class="px-2.5 py-1 text-[10px] font-extrabold rounded-xl border <?= $badge ?>">
                                                <?= htmlspecialchars($log['accion']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-5 text-xs text-slate-700 dark:text-slate-300 max-w-xs md:max-w-md truncate">
                                            <?= htmlspecialchars($log['descripcion']) ?>
                                        </td>
                                        <td class="py-3.5 px-5 font-mono text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                            <?= htmlspecialchars($log['ip_origen']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="p-4 bg-slate-50/50 dark:bg-[#1e293b]/30 border-t border-slate-200/80 dark:border-white/5 text-[11px] font-bold text-slate-400 flex justify-between items-center">
                    <span>Mostrando <?= count($logs) ?> evento(s) reciente(s)</span>
                    <span>Módulo de Auditoría SGET</span>
                </div>
            </div>

        </main>
    </div>

    <!-- SCRIPT DE DESPLIEGUE Y ALTERNANCIA DEL SIDEBAR -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btnToggle = document.getElementById('btnToggleSidebar');
            if (btnToggle) {
                btnToggle.addEventListener('click', () => {
                    if (window.innerWidth >= 1024) {
                        document.body.classList.toggle('sidebar-collapsed');
                    } else {
                        document.body.classList.toggle('sidebar-open');
                    }
                });
            }
        });
    </script>
</body>
</html>
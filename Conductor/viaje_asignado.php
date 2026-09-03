<?php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php'; 

// 1. Verificación de seguridad
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 2) {
    header("Location: ../index.php");
    exit();
}

$documento = $_SESSION['documento'];
$nombreReal = $_SESSION['nombre_usuario'] ?? "Conductor";

// 2. Obtener el ID del conductor
$stmt_user = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ?");
$stmt_user->bind_param("s", $documento);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

$id_conductor = 0;
if ($result_user && $result_user->num_rows > 0) {
    $user_data = $result_user->fetch_assoc();
    $id_conductor = $user_data['id_usu'];
} else {
    echo "Error: Conductor no encontrado.";
    exit();
}
$stmt_user->close();

// 3. Consultar viaje asignado activo
$sql_viaje = "SELECT 
        v.id_via,
        v.fec_via,
        v.est_via,
        v.cup_tot,
        v.cup_dis,
        u.nom_usu AS conductor_nombre,
        u.num_doc_usu AS conductor_doc,
        u.tel_usu AS conductor_telefono,
        veh.pla_veh,
        veh.mode_veh,
        veh.cap_veh,
        r.ori_rut,
        r.des_rut,
        r.dis_rut,
        r.val_rut,
        r.nom_rut
    FROM viaje v
    INNER JOIN usuario u ON v.id_usu_via = u.id_usu
    LEFT JOIN vehiculo veh ON v.id_veh = veh.id_veh
    INNER JOIN rutas r ON v.id_rut_via = r.id_rut
    WHERE v.id_usu_via = ?
      AND v.est_via NOT IN ('Finalizado', 'Terminado', 'Completado', '0', '2')
    ORDER BY v.fec_via DESC
    LIMIT 1";

$stmt_v = $conexion->prepare($sql_viaje);
$stmt_v->bind_param("i", $id_conductor);
$stmt_v->execute();
$res_viaje = $stmt_v->get_result();
$viaje = $res_viaje->fetch_assoc();
$stmt_v->close();

$pasajeros = [];
if ($viaje) {
    // 4. Consultar pasajeros del viaje
    $sql_pasajeros = "SELECT 
            res.id_res,
            res.fech_res,
            res.metodo_pago,
            res.valor_pagado,
            res.estado_pago,
            pas.nom_usu AS pasajero_nombre,
            pas.tel_usu AS pasajero_telefono,
            pas.corre_usu AS pasajero_correo
        FROM reserva res
        INNER JOIN usuario pas ON res.id_usu_res = pas.id_usu
        WHERE res.id_via_res = ?";
    
    $stmt_p = $conexion->prepare($sql_pasajeros);
    $stmt_p->bind_param("i", $viaje['id_via']);
    $stmt_p->execute();
    $res_pasajeros = $stmt_p->get_result();
    
    while ($row_p = $res_pasajeros->fetch_assoc()) {
        $pasajeros[] = $row_p;
    }
    $stmt_p->close();
}

// Consultas secundarias para el Drawer (+)
$rutas_select = $conexion->query("SELECT id_rut, nom_rut, val_rut FROM rutas ORDER BY nom_rut ASC");
$vehiculos_select = $conexion->query("SELECT id_veh, pla_veh FROM vehiculo WHERE est_veh = 1 OR est_veh = 'Activo' ORDER BY pla_veh ASC");
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Viaje - SGET</title>
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
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(56, 189, 248, 0.5);
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex min-h-screen antialiased transition-colors duration-300 relative overflow-x-hidden">

    <!-- Carga Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Contenedor Principal -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen min-w-0">
        
        <!-- INCLUSIÓN DEL HEADER DEL CONDUCTOR -->
        <?php include '../includes/header.php'; ?>

        <!-- Cuerpo Principal -->
        <div class="p-8 space-y-6 flex-1 max-w-6xl min-w-0">
            
            <!-- ENCABEZADO DE PÁGINA CON TITULO, AYUDA (?) Y BOTÓN (+) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#1e293b]/50 p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Reporte de Viaje Asignado</h1>
                        
                        <!-- 1. BOTÓN Y TARJETA FLOTANTE DE AYUDA (?) -->
                        <div class="relative group">
                            <button type="button" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer">
                                <i class="fas fa-question text-[10px]"></i>
                            </button>

                            <div class="absolute left-0 top-full mt-2 w-80 bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-2xl p-4 text-xs opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                                <p class="font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-700/60 pb-2">
                                    <i class="fas fa-info-circle text-neon-azul"></i> Control del Servicio Asignado
                                </p>
                                <ul class="space-y-2 text-slate-600 dark:text-slate-300 leading-relaxed">
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-plus-circle text-blue-500 mt-0.5 shrink-0"></i>
                                        <span><b>Programar Viaje (+):</b> Abre el formulario deslizante para habilitar un nuevo turno o itinerario.</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-flag-checkered text-emerald-500 mt-0.5 shrink-0"></i>
                                        <span><b>Finalizar Viaje:</b> Cierra el trayecto actual, liberando tu estado a disponible.</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-users text-indigo-500 mt-0.5 shrink-0"></i>
                                        <span><b>Pasajeros:</b> Lista en tiempo real con datos de contacto y validación de pago.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Detalle del servicio, itinerario y listado oficial de pasajeros abonados.</p>
                </div>

                <!-- BOTÓN PRINCIPAL ACCIÓN CON MODAL DRAWER (+) -->
                <button onclick="abrirModalSolicitar()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 hover:opacity-95 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition-all cursor-pointer whitespace-nowrap self-start sm:self-auto">
                    <i class="fas fa-plus-circle text-sm"></i> Programar Viaje
                </button>
            </div>

            <?php if ($viaje): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- 1. Info Conductor & Vehículo -->
                    <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-xl space-y-4">
                        <div class="flex items-center gap-2 border-b border-slate-100 dark:border-white/5 pb-3">
                            <i class="fas fa-id-card text-blue-500 dark:text-neon-azul text-lg"></i>
                            <h2 class="font-bold text-slate-900 dark:text-white text-base">1. Conductor & Vehículo</h2>
                        </div>
                        <ul class="space-y-3 text-sm">
                            <li class="flex justify-between">
                                <span class="text-slate-400 dark:text-slate-500">Conductor:</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($viaje['conductor_nombre']) ?></span>
                            </li>
                            <li class="flex justify-between">
                                <span class="text-slate-400 dark:text-slate-500">Documento:</span>
                                <span class="font-mono text-slate-800 dark:text-slate-200"><?= htmlspecialchars($viaje['conductor_doc']) ?></span>
                            </li>
                            <li class="flex justify-between">
                                <span class="text-slate-400 dark:text-slate-500">Teléfono:</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($viaje['conductor_telefono'] ?? 'N/A') ?></span>
                            </li>
                            <li class="flex justify-between items-center">
                                <span class="text-slate-400 dark:text-slate-500">Vehículo:</span>
                                <span class="bg-blue-500/10 text-blue-600 dark:text-neon-azul border border-blue-500/20 font-black px-2.5 py-0.5 rounded-md uppercase text-xs">
                                    <?= htmlspecialchars($viaje['pla_veh'] ?? 'N/A') ?> (<?= htmlspecialchars($viaje['mode_veh'] ?? 'Modelo N/A') ?>)
                                </span>
                            </li>
                            <li class="flex justify-between">
                                <span class="text-slate-400 dark:text-slate-500">Capacidad Máxima:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($viaje['cap_veh'] ?? $viaje['cup_tot']) ?> Pasajeros</span>
                            </li>
                        </ul>
                    </div>

                    <!-- 2. Detalles del Viaje y Ruta + BOTÓN INTEGRADO CON MODAL -->
                    <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-xl flex flex-col justify-between space-y-4">
                        <div>
                            <div class="flex items-center gap-2 border-b border-slate-100 dark:border-white/5 pb-3 mb-4">
                                <i class="fas fa-route text-indigo-500 dark:text-neon-morado text-lg"></i>
                                <h2 class="font-bold text-slate-900 dark:text-white text-base">2. Detalles de Ruta</h2>
                            </div>
                            <ul class="space-y-3 text-sm">
                                <li class="flex justify-between">
                                    <span class="text-slate-400 dark:text-slate-500">Ruta:</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200 capitalize"><?= htmlspecialchars($viaje['ori_rut'] ?? 'Origen') ?> &rrarr; <?= htmlspecialchars($viaje['des_rut'] ?? 'Destino') ?></span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="text-slate-400 dark:text-slate-500">Distancia Estimada:</span>
                                    <span class="font-mono text-slate-800 dark:text-slate-200"><?= htmlspecialchars($viaje['dis_rut'] ?? '0') ?> km</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="text-slate-400 dark:text-slate-500">Fecha y Hora Programada:</span>
                                    <span class="font-mono text-slate-800 dark:text-slate-200"><?= !empty($viaje['fec_via']) ? date('d/m/Y - h:i A', strtotime($viaje['fec_via'])) : 'N/A' ?></span>
                                </li>
                                <li class="flex justify-between items-center">
                                    <span class="text-slate-400 dark:text-slate-500">Estado del Viaje:</span>
                                    <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-bold px-2.5 py-0.5 rounded-md text-xs uppercase">
                                        <?= htmlspecialchars($viaje['est_via']) ?>
                                    </span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="text-slate-400 dark:text-slate-500">Disponibilidad:</span>
                                    <span class="font-bold text-amber-500"><?= htmlspecialchars($viaje['cup_dis']) ?> cupos libres / <?= htmlspecialchars($viaje['cup_tot']) ?> totales</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Botón para detonar el modal de confirmación de finalización -->
                        <div class="pt-4 border-t border-slate-100 dark:border-white/5">
                            <button type="button" 
                                    onclick="confirmarFinalizarReporte(<?= $viaje['id_via'] ?>, '<?= htmlspecialchars($viaje['des_rut'] ?? 'Ruta', ENT_QUOTES, 'UTF-8') ?>')"
                                    class="w-full flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl text-xs uppercase tracking-wider transition-all duration-200 shadow-lg shadow-emerald-600/20 active:scale-[0.98] cursor-pointer">
                                <i class="fas fa-flag-checkered text-sm"></i>
                                Finalizar Viaje
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 3. Lista de Pasajeros -->
                <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl space-y-4">
                    <div class="flex items-center gap-2 border-b border-slate-100 dark:border-white/5 pb-3">
                        <i class="fas fa-users text-emerald-500 text-lg"></i>
                        <h2 class="font-bold text-slate-900 dark:text-white text-base">3. Pasajeros Asignados y Reservas</h2>
                    </div>

                    <?php if (count($pasajeros) > 0): ?>
                        <div class="overflow-x-auto custom-scrollbar rounded-xl border border-slate-200 dark:border-white/5 w-full">
                            <table class="w-full text-sm text-left border-collapse min-w-[650px]">
                                <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                                    <tr>
                                        <th class="px-5 py-3.5"># Reserva</th>
                                        <th class="px-5 py-3.5">Pasajero</th>
                                        <th class="px-5 py-3.5">Teléfono</th>
                                        <th class="px-5 py-3.5">Correo</th>
                                        <th class="px-5 py-3.5">Método Pago</th>
                                        <th class="px-5 py-3.5 text-center">Estado Pago</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                                    <?php foreach ($pasajeros as $p): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="px-5 py-4 font-mono font-bold text-blue-600 dark:text-neon-azul">
                                            RES-<?= str_pad($p['id_res'], 3, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <td class="px-5 py-4 font-semibold capitalize text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($p['pasajero_nombre']) ?>
                                        </td>
                                        <td class="px-5 py-4 font-mono text-xs">
                                            <?= htmlspecialchars($p['pasajero_telefono'] ?? 'Sin celular') ?>
                                        </td>
                                        <td class="px-5 py-4 text-xs text-slate-400">
                                            <?= htmlspecialchars($p['pasajero_correo'] ?? 'Sin correo') ?>
                                        </td>
                                        <td class="px-5 py-4 text-xs uppercase font-medium">
                                            <?= htmlspecialchars($p['metodo_pago'] ?? 'Efectivo') ?>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <?php if (strtolower($p['estado_pago'] ?? '') == 'pagado'): ?>
                                                <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                    <i class="fas fa-check-circle mr-1"></i> Pagado ($<?= number_format($p['valor_pagado'] ?? 0, 0) ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-rose-500/10 text-rose-500 dark:text-rose-400 border border-rose-500/20 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                    <i class="fas fa-clock mr-1"></i> Pendiente ($<?= number_format($p['valor_pagado'] ?? 0, 0) ?>)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="py-12 text-center text-slate-400 dark:text-slate-500 italic">
                            <i class="fas fa-info-circle text-2xl mb-2 text-amber-500 block"></i>
                            No hay reservas de pasajeros registradas para este viaje aún.
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="p-8 bg-amber-500/10 border border-amber-500/20 rounded-2xl text-amber-600 dark:text-amber-400 text-center space-y-2">
                    <i class="fas fa-exclamation-triangle text-3xl"></i>
                    <h3 class="font-bold text-base">Sin viajes activos asignados</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">No se encontró ningún viaje en estado activo asignado a tu cuenta de conductor.</p>
                </div>
            <?php endif; ?>
            
        </div>
    </main>

    <!-- OVERLAY GENERAL PARA MODALES Y DRAWER -->
    <div id="overlayReporte" onclick="cerrarTodosModales()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- 2. MODAL POP-UP DE CONFIRMACIÓN PARA FINALIZAR VIAJE -->
    <div id="modalConfirmarFinReporte" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#1e293b] w-full max-w-sm rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300 text-center" id="modalConfirmBoxReporte">
            <div class="w-12 h-12 bg-emerald-500/10 text-emerald-500 rounded-2xl flex items-center justify-center text-xl mx-auto border border-emerald-500/20">
                <i class="fas fa-flag-checkered"></i>
            </div>

            <div>
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base">¿Finalizar Viaje?</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" id="txtConfirmDestinoReporte"></p>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="cerrarModalConfirmar()" class="flex-1 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 font-bold text-xs rounded-xl uppercase tracking-wider cursor-pointer">
                    Cancelar
                </button>
                <a id="btnLinkFinalizarReporte" href="#" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl uppercase tracking-wider shadow-lg shadow-emerald-600/20 text-center flex items-center justify-center">
                    Sí, Finalizar
                </a>
            </div>
        </div>
    </div>

    <!-- 3. PANEL LATERAL DESLIZANTE (DRAWER (+)) DE PROGRAMACIÓN -->
    <aside id="drawerProgramarReporte" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between relative">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-neon-morado"></div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-500/10 text-blue-500 dark:text-neon-azul rounded-xl flex items-center justify-center border border-slate-100 dark:border-white/5">
                    <i class="fas fa-bus text-base"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Programar Nuevo Viaje</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Despachar ruta para tu vehículo</p>
                </div>
            </div>
            <button onclick="cerrarModalDrawer()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto space-y-5">
            <form id="formProgramarReporte" action="guardar_viaje.php" method="POST" class="space-y-4">
                <input type="hidden" name="id_usu_via" value="<?= $id_conductor ?? ''; ?>">

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Seleccionar Ruta</label>
                    <select name="id_rut_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                        <option value="">Selecciona tu ruta...</option>
                        <?php 
                        if($rutas_select) {
                            $rutas_select->data_seek(0);
                            while($r = $rutas_select->fetch_assoc()) {
                                echo '<option value="'.$r['id_rut'].'">'.htmlspecialchars($r['nom_rut']).'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Vehículo Asignado</label>
                    <select name="id_veh_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                        <option value="">Selecciona tu vehículo...</option>
                        <?php 
                        if($vehiculos_select) {
                            $vehiculos_select->data_seek(0);
                            while($v = $vehiculos_select->fetch_assoc()) {
                                echo '<option value="'.$v['id_veh'].'">Placa: '.htmlspecialchars($v['pla_veh']).'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fecha Salida</label>
                        <input type="date" name="fec_via" id="input_fec_reporte" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Hora Salida</label>
                        <input type="time" name="hor_sal_via" id="input_hor_reporte" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                    </div>
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalDrawer()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider transition-all cursor-pointer">
                Cancelar
            </button>
            <button type="submit" form="formProgramarReporte" class="flex-1 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-500/20 hover:opacity-95 transition-all cursor-pointer">
                Iniciar Despacho
            </button>
        </div>
    </aside>

    <!-- CONTROLADORES JAVASCRIPT -->
    <script>
        function abrirModalSolicitar() {
            const drawer = document.getElementById('drawerProgramarReporte');
            const overlay = document.getElementById('overlayReporte');

            const hoy = new Date();
            const fechaHoy = hoy.toISOString().split('T')[0];
            const horaHoy = hoy.toTimeString().split(' ')[0].substring(0, 5);

            document.getElementById('input_fec_reporte').value = fechaHoy;
            document.getElementById('input_fec_reporte').min = fechaHoy;
            document.getElementById('input_hor_reporte').value = horaHoy;

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
        }

        function cerrarModalDrawer() {
            const drawer = document.getElementById('drawerProgramarReporte');
            const overlay = document.getElementById('overlayReporte');

            drawer.classList.remove('translate-x-0');
            drawer.classList.add('translate-x-full');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function confirmarFinalizarReporte(idViaje, nombreRuta) {
            document.getElementById('btnLinkFinalizarReporte').href = 'finalizar_viaje.php?id=' + idViaje;
            document.getElementById('txtConfirmDestinoReporte').innerText = 'Confirma que el vehículo llegó a su destino (' + nombreRuta + ') para cambiar tu estado a disponible.';

            const overlay = document.getElementById('overlayReporte');
            const modal = document.getElementById('modalConfirmarFinReporte');
            const box = document.getElementById('modalConfirmBoxReporte');

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalConfirmar() {
            const overlay = document.getElementById('overlayReporte');
            const modal = document.getElementById('modalConfirmarFinReporte');
            const box = document.getElementById('modalConfirmBoxReporte');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function cerrarTodosModales() {
            cerrarModalDrawer();
            cerrarModalConfirmar();
        }

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
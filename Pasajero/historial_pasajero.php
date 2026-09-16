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

// Consulta del historial de viajes
$sql_historial = "SELECT v.*, r.nom_rut, res.fech_res, res.valor_pagado, res.metodo_pago, res.estado_pago, c.id_cal, v.id_usu_via, u.nom_usu as nombre_conductor
                  FROM reserva res
                  JOIN viaje v ON res.id_via_res = v.id_via
                  JOIN rutas r ON v.id_rut_via = r.id_rut
                  LEFT JOIN usuario u ON v.id_usu_via = u.id_usu
                  LEFT JOIN calificacion c ON v.id_via = c.id_via_cal AND c.id_usu_rem = '$id_pasajero'
                  WHERE res.id_usu_res = '$id_pasajero'
                  ORDER BY res.fech_res DESC";
$historial = $conexion->query($sql_historial);

// Consulta de rutas para el Drawer (+)
$rutas_disponibles = $conexion->query("SELECT id_rut, nom_rut FROM rutas ORDER BY nom_rut ASC");
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Historial de Viajes</title>
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
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
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">
    
    <?php include '../includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 flex flex-col min-h-screen min-w-0">

        <?php include '../includes/header.php'; ?>

        <div class="p-8 flex-1 min-w-0 space-y-6">
            
            <!-- ENCABEZADO DE PÁGINA CON BOTÓN GUÍA Y PROGRAMACIÓN (+) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#1e293b]/50 p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm max-w-6xl">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Historial de Viajes</h1>
                        
                        <!-- 1. BOTÓN Y TARJETA FLOTANTE DE AYUDA (?) -->
                        <div class="relative group">
                            <button type="button" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer">
                                <i class="fas fa-question text-[10px]"></i>
                            </button>

                            <div class="absolute left-0 top-full mt-2 w-80 bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-2xl p-4 text-xs opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                                <p class="font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-700/60 pb-2">
                                    <i class="fas fa-info-circle text-neon-azul"></i> Control de Historial
                                </p>
                                <ul class="space-y-2 text-slate-600 dark:text-slate-300 leading-relaxed">
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-search text-blue-500 mt-0.5 shrink-0"></i>
                                        <span><b>Buscador:</b> Filtra tus reservas al instante escribiendo la ruta, estado o fecha.</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-star text-amber-400 mt-0.5 shrink-0"></i>
                                        <span><b>Calificar:</b> Los viajes marcados como completados habilitan la reseña del servicio.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Revisa el listado completo de tus desplazamientos y pagos realizados.</p>
                </div>

                <button onclick="abrirModalReservaHistorial()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 hover:opacity-95 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition-all cursor-pointer whitespace-nowrap self-start sm:self-auto">
                    <i class="fas fa-plus-circle text-sm"></i> Buscar Rutas
                </button>
            </div>

            <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl max-w-6xl transition-colors duration-300">
                
                <!-- Encabezado de la tabla y Buscador -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <h3 class="font-bold text-slate-900 dark:text-white text-lg tracking-tight flex items-center gap-2">
                        <i class="fas fa-history text-blue-500 text-sm"></i> Registro de Reservas
                    </h3>

                    <!-- Buscador -->
                    <div class="relative w-full sm:w-72">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                            <i class="fas fa-search text-xs"></i>
                        </span>
                        <input type="text" id="inputBuscador" placeholder="Buscar por ruta, estado o fecha..." data-i18n-placeholder-es="Buscar por ruta, estado o fecha..." data-i18n-placeholder-en="Search by route, status, or date..." 
                               class="w-full pl-9 pr-4 py-2 text-xs bg-slate-100 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-200 rounded-xl border border-slate-200 dark:border-white/10 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                    </div>
                </div>

                <!-- Tabla de Historial con Scrollbar Horizontal -->
                <div class="overflow-x-auto custom-scrollbar rounded-xl border border-slate-200 dark:border-white/5 w-full">
                    <table class="w-full text-sm text-left border-collapse min-w-[650px]" id="tablaViajes">
                        <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                            <tr>
                                <th class="px-6 py-3.5">Ruta</th>
                                <th class="px-6 py-3.5">Fecha / Hora</th>
                                <th class="px-6 py-3.5">Valor Pagado</th>
                                <th class="px-6 py-3.5">Estado</th>
                                <th class="px-6 py-3.5 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                            <?php if ($historial && $historial->num_rows > 0): ?>
                                <?php while($v = $historial->fetch_assoc()): 
                                    $sePuedeCalificar = ($v['est_via'] == 'Terminado' || $v['est_via'] == 'Finalizado');
                                    $yaCalificado = !is_null($v['id_cal']);
                                    $jsonViaje = htmlspecialchars(json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr class="fila-viaje hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <!-- Ruta -->
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 rounded-lg flex items-center justify-center text-xs flex-shrink-0">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </div>
                                                <div>
                                                    <span class="font-bold text-slate-900 dark:text-white capitalize text-xs block">
                                                        <?php echo htmlspecialchars($v['nom_rut']); ?>
                                                    </span>
                                                    <span class="text-[10px] text-slate-400">Reserva #<?php echo $v['id_via']; ?></span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Fecha y Hora -->
                                        <td class="px-6 py-4 text-xs font-mono">
                                            <span class="block font-bold text-slate-800 dark:text-slate-200">
                                                <?php echo date('d/m/Y', strtotime($v['fech_res'])); ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 uppercase">
                                                <?php echo date('h:i A', strtotime($v['hor_sal_via'])); ?>
                                            </span>
                                        </td>

                                        <!-- Valor Pagado -->
                                        <td class="px-6 py-4 text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                            $<?php echo number_format($v['valor_pagado'], 2); ?>
                                        </td>

                                        <!-- Estado -->
                                        <td class="px-6 py-4">
                                            <?php if ($sePuedeCalificar): ?>
                                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 uppercase tracking-wider">
                                                    Completado
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black bg-yellow-500/10 text-yellow-600 dark:text-yellow-500 border border-yellow-500/20 uppercase tracking-wider">
                                                    <?php echo htmlspecialchars($v['est_via']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Acción -->
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <!-- Botón Ver Ficha -->
                                                <button type="button" 
                                                        data-viaje='<?php echo $jsonViaje; ?>'
                                                        onclick="verFichaViajeHistorial(this)"
                                                        class="p-2 bg-blue-500/10 text-blue-600 dark:text-neon-azul hover:bg-blue-600 hover:text-white rounded-xl transition-all shadow-sm cursor-pointer"
                                                        title="Ver Ficha">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </button>

                                                <?php if ($yaCalificado): ?>
                                                    <div class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-bold text-[10px] uppercase tracking-wider">
                                                        <i class="fas fa-check-double text-xs"></i> Calificado
                                                    </div>
                                                <?php elseif ($sePuedeCalificar): ?>
                                                    <button type="button" 
                                                            onclick="abrirModalCalificarHistorial(<?php echo $v['id_via']; ?>, <?php echo $v['id_usu_via']; ?>, '<?php echo htmlspecialchars($v['nom_rut'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($v['nombre_conductor'] ?? 'Conductor', ENT_QUOTES, 'UTF-8'); ?>')"
                                                            class="inline-flex items-center gap-1.5 bg-yellow-500 hover:bg-yellow-400 text-slate-900 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase transition-all duration-200 shadow-md shadow-yellow-500/10 cursor-pointer">
                                                        <i class="fas fa-star text-[9px]"></i> Calificar
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-tight italic">En trayecto...</span>
                                                <?php endif; ?>
                                            </div>
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
        </div>
    </main>

    <!-- OVERLAY GENERAL PARA MODALES -->
    <div id="overlayHistorial" onclick="cerrarTodosModales()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- 2. MODAL POP-UP DE FICHA DE RESERVA DE HISTORIAL -->
    <div id="modalFichaHistorial" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#1e293b] w-full max-w-sm rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300" id="modalFichaHistorialBox">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-500 dark:text-neon-azul flex items-center justify-center text-xs">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h3 id="detRutaHistorial" class="font-extrabold text-slate-900 dark:text-white text-base capitalize"></h3>
                </div>
                <button onclick="cerrarModalFichaHistorial()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            
            <div class="space-y-3.5 text-xs">
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="fas fa-user-tie text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Conductor Asignado</p>
                        <p id="detConductorHistorial" class="font-semibold text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="far fa-calendar-alt text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Fecha y Hora</p>
                        <p id="detFechaHoraHistorial" class="font-medium text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="fas fa-wallet text-emerald-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Monto Cancelado</p>
                        <p id="detValorHistorial" class="font-mono font-bold text-emerald-600 dark:text-emerald-400 mt-0.5"></p>
                    </div>
                </div>
            </div>

            <button onclick="cerrarModalFichaHistorial()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer">
                Cerrar Recibo
            </button>
        </div>
    </div>

    <!-- 3. MODAL POP-UP DE CALIFICACIÓN INTERACTIVA -->
    <div id="modalCalificarHistorial" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#1e293b] w-full max-w-sm rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300" id="modalCalificarHistorialBox">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xs">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Calificar Servicio</h3>
                </div>
                <button onclick="cerrarModalCalificarHistorial()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <form action="guardar_calificacion.php" method="POST" class="space-y-4">
                <input type="hidden" name="id_via_cal" id="modal_hist_id_via_cal">
                <input type="hidden" name="id_usu_des" id="modal_hist_id_usu_des">
                <input type="hidden" name="pun_cal" id="modal_hist_pun_cal" value="5">

                <div>
                    <p class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Ruta & Conductor</p>
                    <p id="txtRutaConductorHistorial" class="font-extrabold text-slate-800 dark:text-slate-100 text-sm mt-0.5"></p>
                </div>

                <div class="space-y-1 text-center">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tu Puntuación</label>
                    <div class="flex items-center justify-center gap-2 text-2xl text-amber-400 py-2" id="contenedorEstrellasHistorial">
                        <i class="fas fa-star cursor-pointer hover:scale-110 transition-transform" onclick="seleccionarEstrellasHistorial(1)"></i>
                        <i class="fas fa-star cursor-pointer hover:scale-110 transition-transform" onclick="seleccionarEstrellasHistorial(2)"></i>
                        <i class="fas fa-star cursor-pointer hover:scale-110 transition-transform" onclick="seleccionarEstrellasHistorial(3)"></i>
                        <i class="fas fa-star cursor-pointer hover:scale-110 transition-transform" onclick="seleccionarEstrellasHistorial(4)"></i>
                        <i class="fas fa-star cursor-pointer hover:scale-110 transition-transform" onclick="seleccionarEstrellasHistorial(5)"></i>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Comentario del Viaje</label>
                    <textarea name="com_cal" rows="3" placeholder="¿Cómo fue tu experiencia en el recorrido?" data-i18n-placeholder-es="¿Cómo fue tu experiencia en el recorrido?" data-i18n-placeholder-en="How was your experience on the trip?" class="w-full p-3 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-xs transition-all resize-none"></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="cerrarModalCalificarHistorial()" class="flex-1 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 font-bold text-xs rounded-xl uppercase tracking-wider">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-900 font-bold text-xs rounded-xl uppercase tracking-wider shadow-lg shadow-amber-500/20">
                        Enviar Opinión
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. PANEL LATERAL DESLIZANTE (DRAWER (+)) DE RESERVA Y BÚSQUEDA -->
    <aside id="drawerReservaHistorial" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between relative">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-neon-morado"></div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-500/10 text-blue-500 dark:text-neon-azul rounded-xl flex items-center justify-center border border-slate-100 dark:border-white/5">
                    <i class="fas fa-ticket-alt text-base"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Buscar Viaje</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Solicitar cupo en ruta disponible</p>
                </div>
            </div>
            <button onclick="cerrarModalDrawerHistorial()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto space-y-5">
            <form id="formReservaHistorial" action="viajes_pasajero.php" method="GET" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Destino Deseado</label>
                    <select name="ruta" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                        <option value="">Selecciona tu ruta...</option>
                        <?php 
                        if($rutas_disponibles) {
                            $rutas_disponibles->data_seek(0);
                            while($r = $rutas_disponibles->fetch_assoc()) {
                                echo '<option value="'.$r['id_rut'].'">'.htmlspecialchars($r['nom_rut']).'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fecha Preferida</label>
                    <input type="date" name="fecha" id="input_fecha_historial" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalDrawerHistorial()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider transition-all cursor-pointer">
                Cancelar
            </button>
            <button type="submit" form="formReservaHistorial" class="flex-1 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-500/20 hover:opacity-95 transition-all cursor-pointer">
                Buscar Disponibilidad
            </button>
        </div>
    </aside>

    <!-- CONTROLADORES JAVASCRIPT Y FILTRO EN TIEMPO REAL -->
    <script>
        document.getElementById('inputBuscador').addEventListener('keyup', function() {
            const valorBusqueda = this.value.toLowerCase();
            const filas = document.querySelectorAll('#tablaViajes .fila-viaje');

            filas.forEach(fila => {
                const textoFila = fila.textContent.toLowerCase();
                if (textoFila.includes(valorBusqueda)) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            });
        });

        function verFichaViajeHistorial(btn) {
            const v = JSON.parse(btn.getAttribute('data-viaje'));
            document.getElementById('detRutaHistorial').innerText = v.nom_rut || 'Sin Nombre';
            document.getElementById('detConductorHistorial').innerText = v.nombre_conductor || 'Conductor No Asignado';
            document.getElementById('detFechaHoraHistorial').innerText = (v.fech_res || '') + ' — ' + (v.hor_sal_via || '');
            document.getElementById('detValorHistorial').innerText = '$' + parseFloat(v.valor_pagado || 0).toLocaleString('es-CO') + ' COP (' + (v.metodo_pago || 'Efectivo') + ')';

            const overlay = document.getElementById('overlayHistorial');
            const modal = document.getElementById('modalFichaHistorial');
            const box = document.getElementById('modalFichaHistorialBox');

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalFichaHistorial() {
            const overlay = document.getElementById('overlayHistorial');
            const modal = document.getElementById('modalFichaHistorial');
            const box = document.getElementById('modalFichaHistorialBox');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function abrirModalCalificarHistorial(idViaje, idConductor, nombreRuta, nombreConductor) {
            document.getElementById('modal_hist_id_via_cal').value = idViaje;
            document.getElementById('modal_hist_id_usu_des').value = idConductor;
            document.getElementById('txtRutaConductorHistorial').innerText = nombreRuta + ' (Conductor: ' + nombreConductor + ')';
            
            seleccionarEstrellasHistorial(5);

            const overlay = document.getElementById('overlayHistorial');
            const modal = document.getElementById('modalCalificarHistorial');
            const box = document.getElementById('modalCalificarHistorialBox');

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function seleccionarEstrellasHistorial(cantidad) {
            document.getElementById('modal_hist_pun_cal').value = cantidad;
            const estrellas = document.querySelectorAll('#contenedorEstrellasHistorial i');
            
            estrellas.forEach((estrella, index) => {
                if (index < cantidad) {
                    estrella.className = 'fas fa-star cursor-pointer hover:scale-110 transition-transform';
                } else {
                    estrella.className = 'far fa-star cursor-pointer hover:scale-110 transition-transform text-slate-400';
                }
            });
        }

        function cerrarModalCalificarHistorial() {
            const overlay = document.getElementById('overlayHistorial');
            const modal = document.getElementById('modalCalificarHistorial');
            const box = document.getElementById('modalCalificarHistorialBox');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function abrirModalReservaHistorial() {
            const drawer = document.getElementById('drawerReservaHistorial');
            const overlay = document.getElementById('overlayHistorial');

            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('input_fecha_historial').value = hoy;
            document.getElementById('input_fecha_historial').min = hoy;

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
        }

        function cerrarModalDrawerHistorial() {
            const drawer = document.getElementById('drawerReservaHistorial');
            const overlay = document.getElementById('overlayHistorial');

            drawer.classList.remove('translate-x-0');
            drawer.classList.add('translate-x-full');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function cerrarTodosModales() {
            cerrarModalFichaHistorial();
            cerrarModalCalificarHistorial();
            cerrarModalDrawerHistorial();
        }

        // Alternador de tema
        const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
        } else {
            if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
        }

        const themeToggleBtn = document.getElementById('theme-toggle');

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.toggle('hidden');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.toggle('hidden');

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
<?php
/**
 * Pasajero/historial_pasajero.php
 * -----------------------------------------------------------------------------
 * HISTORIAL DE RESERVAS DEL PASAJERO
 * -----------------------------------------------------------------------------
 * QUÉ CAMBIA EN ESTA SEGUNDA RONDA
 *   · Las consultas concatenaban `$documento` y `$id_pasajero` en el SQL. Ahora
 *     la identidad se toma de `Auth::id()` (entero) y todo va por PDO con
 *     sentencias preparadas.
 *   · Se añade el enlace al COMPROBANTE en PDF de cada reserva, que existía
 *     (`generar_ticket.php`) pero no estaba enlazado desde ninguna pantalla.
 *   · El botón «Calificar» solo aparece en viajes FINALIZADOS, igual que ahora
 *     decide el backend.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirSesion();
Auth::requerirRol(Config::ROL_PASAJERO);

$nombreReal = Auth::nombre();
$idPasajero = Auth::id();

$historial = Database::all(
    'SELECT v.*, rt.nom_rut, res.fech_res, res.id_res, res.valor_pagado, res.metodo_pago,
            res.estado_pago, res.embarco, res.embarque_fec, res.es_temporal,
            res.punto_abordaje, res.destino_abordaje, res.cantidad_puestos, res.asientos_asignados,
            c.id_cal, v.id_usu_via, u.nom_usu AS nombre_conductor
       FROM reserva res
       INNER JOIN viaje v   ON res.id_via_res = v.id_via
       INNER JOIN rutas rt  ON v.id_rut_via  = rt.id_rut
       LEFT  JOIN usuario u ON v.id_usu_via   = u.id_usu
       LEFT  JOIN calificacion c
              ON c.id_via_cal = v.id_via AND c.id_usu_rem = ?
      WHERE res.id_usu_res = ?
      ORDER BY res.fech_res DESC, res.id_res DESC',
    [$idPasajero, $idPasajero]
);

// Rutas para el buscador de reserva.
$rutasDisponibles = Database::all('SELECT id_rut, nom_rut FROM rutas ORDER BY nom_rut ASC');
?>
<!DOCTYPE html>
<html lang="es">
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
    <!-- SISTEMA VISUAL SGET (CSS modular): tema, componentes, modales y responsive -->
    <link rel="stylesheet" href="../assets/css/01-base.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/02-layout.css?v=<?= @filemtime('../assets/css/02-layout.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/03-componentes.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/04-modales.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/05-tablas.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/06-responsive.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/07-transiciones.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <script src="../assets/js/theme-init.js?v=<?= @filemtime('../assets/js/theme-init.js') ?: '1' ?>"></script>
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
                        
                        <!--
                             BOTÓN DE AYUDA DEL MÓDULO · RETIRADO
                             Este «?» por pantalla se sustituyó por UNO SOLO global en la
                             esquina inferior derecha (views/modals/ayuda.php), que además
                             cambia de contenido según el rol y el módulo. Con estos botones
                             repartidos, cada módulo llevaba su propia copia de la guía y se
                             desincronizaban entre sí.
                        -->

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

                </div>

                <!-- Tabla de Historial con Scrollbar Horizontal -->
                <div class="overflow-x-auto custom-scrollbar rounded-xl border border-slate-200 dark:border-white/5 w-full">
                    <table class="w-full text-sm text-left border-collapse min-w-[650px]" id="tablaViajes">
                        <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                            <tr>
                                <th class="px-6 py-3.5">Ruta</th>
                                <th class="px-6 py-3.5">Fecha / Hora</th>
                                <th class="px-6 py-3.5">Valor del pasaje</th>
                                <th class="px-6 py-3.5">Estado</th>
                                <th class="px-6 py-3.5 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                            <?php if (!empty($historial)): ?>
                                <?php foreach ($historial as $v):
                                    $sePuedeCalificar = ((string)$v['est_via'] === Config::VIA_FINALIZADO);
                                    $yaCalificado = !is_null($v['id_cal']);
                                    $pagoPendienteAbordar = $v['estado_pago'] === Config::RES_PENDIENTE
                                        && stripos((string)$v['metodo_pago'], 'efectivo') !== false;
                                    $estadoPagoLabel = $v['estado_pago'] === Config::RES_CONFIRMADA ? 'PAGADO'
                                        : ($v['estado_pago'] === Config::RES_CANCELADA ? 'CANCELADA'
                                            : ($pagoPendienteAbordar ? 'PENDIENTE AL ABORDAR' : 'PENDIENTE DE PAGO'));
                                    $estadoPagoClase = $v['estado_pago'] === Config::RES_CONFIRMADA ? 'sget-badge--exito'
                                        : ($v['estado_pago'] === Config::RES_CANCELADA ? 'sget-badge--neutro' : 'sget-badge--aviso');
                                    $embarque = $v['embarco'] === null ? null : (int)$v['embarco'];
                                    $estadoEmbarqueLabel = $embarque === 1 ? 'ABORDADO' : ($embarque === 0 ? 'NO ABORDÓ' : 'NO CONFIRMADO');
                                    $estadoEmbarqueClase = $embarque === 1 ? 'sget-badge--exito'
                                        : ($embarque === 0 ? 'sget-badge--error' : 'sget-badge--neutro');
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
                                                    <?php if (!empty($v['es_temporal'])): ?>
                                                        <span class="sget-badge sget-badge--info" style="margin-top:.25rem">Abordaje en ruta</span>
                                                        <span class="sget-help block"><?= htmlspecialchars((string)$v['punto_abordaje'] . ' → ' . (string)$v['destino_abordaje'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php endif; ?>
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
                                            $<?php echo number_format((float)$v['valor_pagado'], 2, ',', '.'); ?>
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
                                            <div class="mt-1 flex flex-wrap justify-center gap-1">
                                                <span class="sget-badge <?= $estadoPagoClase ?>" data-sget-pago-reserva="<?= (int)$v['id_res'] ?>"><?= $estadoPagoLabel ?></span>
                                                <span class="sget-badge <?= $estadoEmbarqueClase ?>" data-sget-embarque-reserva="<?= (int)$v['id_res'] ?>"><?= $estadoEmbarqueLabel ?></span>
                                            </div>
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

                                                <!-- Comprobante en PDF. El backend comprueba que la
                                                     reserva pertenece a este pasajero, aunque se
                                                     edite el id del enlace. -->
                                                <a href="generar_ticket.php?id=<?= (int)$v['id_res'] ?>"
                                                   target="_blank" rel="noopener"
                                                   title="Comprobante de la reserva"
                                                   class="p-2 bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10 rounded-xl transition-all shadow-sm">
                                                    <i class="fas fa-receipt text-xs"></i>
                                                </a>

                                                <?php if ($yaCalificado): ?>
                                                    <div class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-bold text-[10px] uppercase tracking-wider">
                                                        <i class="fas fa-check-double text-xs"></i> Calificado
                                                    </div>
                                                <?php elseif ($sePuedeCalificar): ?>
                                                    <?php /* El modal es el común del sistema (views/modals/calificar.php):
                                                            guarda en la tabla `calificacion` por el API. El formulario
                                                            anterior enviaba a guardar_calificacion.php, que no existía. */ ?>
                                                    <button type="button"
                                                            data-sget-modal="modalCalificar"
                                                            data-sget-calificar-viaje="<?= (int)$v['id_via'] ?>"
                                                            data-sget-datos='<?= htmlspecialchars(json_encode([
                                                                'id_via_cal' => (int)$v['id_via'],
                                                                'viaje'      => (string)$v['nom_rut'] . ' · ' . Fecha::legible($v['fec_via'] ?? '', false) . ' ' . Fecha::soloHora($v['hor_sal_via'] ?? ''),
                                                                'conductor'  => 'Conductor: ' . ($v['nombre_conductor'] ?? 'Sin asignar'),
                                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                                                            class="inline-flex items-center gap-1.5 bg-yellow-500 hover:bg-yellow-400 text-slate-900 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase transition-all duration-200 shadow-md shadow-yellow-500/10 cursor-pointer">
                                                        <i class="fas fa-star text-[9px]"></i> Calificar
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-tight italic">En trayecto...</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
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

    <!--
         La calificación se hace en el modal común del sistema
         (views/modals/calificar.php), que se incluye al final de la página.
         Antes vivía aquí duplicado y enviaba a un archivo inexistente.
    -->

    <!-- 4. PANEL LATERAL DESLIZANTE (DRAWER (+)) DE RESERVA Y BÚSQUEDA -->
    <!-- MODAL (antes panel lateral): drawerReservaHistorial -->
<div class="sget-modal-wrap" data-sget-capa data-titulo="drawerReservaHistorial">
    <div class="sget-overlay"></div>
    <aside id="drawerReservaHistorial" class="sget-modal sget-modal--sm sget-scroll">
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
</div>

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


        }







        function abrirModalReservaHistorial() {
            const drawer = document.getElementById('drawerReservaHistorial');
            const overlay = document.getElementById('overlayHistorial');

            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('input_fecha_historial').value = hoy;
            document.getElementById('input_fecha_historial').min = hoy;




        }

        function cerrarModalDrawerHistorial() {
            const drawer = document.getElementById('drawerReservaHistorial');
            const overlay = document.getElementById('overlayHistorial');




        }

        function cerrarTodosModales() {
            cerrarModalFichaHistorial();
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

    <!-- Motor común de modales + puente de compatibilidad con el JS heredado -->
    <script src="../assets/js/sget-modal.js?v=<?= @filemtime('../assets/js/sget-modal.js') ?: '1' ?>"></script>
    <script src="../assets/js/sget-puente.js?v=<?= @filemtime('../assets/js/sget-puente.js') ?: '1' ?>"></script>
<?php /* Modal común de calificación (ver views/modals/calificar.php) */ ?>
<?php include __DIR__ . '/../views/modals/calificar.php'; ?>
</body>
</html>
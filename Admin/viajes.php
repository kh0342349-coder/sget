<?php
/**
 * Admin/viajes.php
 * -----------------------------------------------------------------------------
 * MÓDULO: DESPACHO DE VIAJES  (Admin)
 * -----------------------------------------------------------------------------
 * CORRECCIONES APLICADAS
 *   - FECHA/HORA: `fec_via` es DATE y `hor_sal_via` es TIME. Antes eran DATETIME
 *     y se llenaban con <input type="date">/type="time", por lo que MySQL
 *     guardaba hor_sal_via = '0000-00-00 00:00:00' (FECHA CERO) y rompía
 *     TIMESTAMP(fec_via, hor_sal_via). Ese era el "Data Default Fallback".
 *   - ESTADOS: `est_via` es ENUM(Programado|En curso|Finalizado|Cancelado).
 *     Ya no existe el ambiguo 'Activo' ni el cierre automático silencioso.
 *   - CANCELACIÓN: si el viaje aún no sale se EXIGE una anotación (mín. 15
 *     caracteres), se cancelan las reservas y se notifica a cada pasajero.
 *   - Se eliminó la concatenación de variables en el SQL de liberación de
 *     conductor/vehículo y el cierre automático que liberaba recursos sin
 *     transacción.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('viajes');

if (!empty($_GET['ok']))       Flash::exito((string)$_GET['ok']);
elseif (!empty($_GET['error'])) Flash::error((string)$_GET['error']);

// Mantenimiento: cierra viajes cuya salida+vencimiento ya pasó
$cerrados = ViajeService::cerrarVencidos();

$viajes = ViajeService::listar();
$total  = count($viajes);

$conteo = ['Programado' => 0, 'En curso' => 0, 'Finalizado' => 0, 'Cancelado' => 0];
foreach (Database::all("SELECT est_via, COUNT(*) n FROM viaje GROUP BY est_via") as $f) {
    $conteo[$f['est_via']] = (int)$f['n'];
}

/** Motivos de cancelación exposed al JS del diálogo. */
$motivos = json_encode(array_map(null, array_keys(ViajeService::motivosCancelacion()),
                                    array_values(ViajeService::motivosCancelacion())));

$tituloPagina = 'Despacho de Viajes';
include __DIR__ . '/../views/partials/head.php';
?>
<<<<<<< Updated upstream
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Despacho de Viajes</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style_admin.css">
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
=======
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
>>>>>>> Stashed changes

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

<<<<<<< Updated upstream
    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen flex-1 transition-all duration-300 min-w-0">
        
        <?php include '../includes/header.php'; ?>

        <main class="space-y-8 flex-grow pb-12 relative z-10 p-8 max-w-[1600px] w-auto mx-auto w-full">
            
            <!-- ENCABEZADO CON BOTÓN DE AYUDA Y ASIGNAR VIAJE -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/5 dark:bg-white/[0.02] p-6 rounded-3xl border border-slate-200 dark:border-white/5 backdrop-blur-md">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Despacho de Viajes</h1>
                        
                        <!-- BOTÓN DE AYUDA -->
                        <button type="button" onclick="abrirModalAyuda()" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer" title="Ver guía del módulo">
                            <i class="fas fa-question text-[10px]"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Monitoreo y control de bitácoras y salidas en ruta de SGET.</p>
                </div>
                
                <button onclick="abrirModalCrear()" class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-sky-500/20 hover:opacity-90 transition-all cursor-pointer whitespace-nowrap">
                    <i class="fas fa-plus-circle text-sm"></i> Asignar Viaje
=======
    <main class="sget-main">
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title"><i class="fas fa-truck-fast text-sky-500"></i> Despacho de Viajes</h1>
                <p class="sget-page-sub">
                    Programa salidas, controla la operación y <strong>cancela viajes notificando a los pasajeros</strong>.
                </p>
            </div>
            <div class="sget-page-actions">
                <button type="button" class="sget-btn sget-btn--primario" data-sget-modal="modalViaje" data-sget-nuevo="Programar Nuevo Viaje">
                    <i class="fas fa-plus"></i> Programar Viaje
>>>>>>> Stashed changes
                </button>
            </div>
        </header>

<<<<<<< Updated upstream
            <!-- Listado en Tarjetas -->
            <?php if($resultado && $resultado->num_rows > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    <?php while($v = $resultado->fetch_assoc()): ?>
                        <?php 
                            $nombreImagen = trim($v['img_rut'] ?? '');
                            $rutaImagen = !empty($nombreImagen) ? "../img/rutas/" . $nombreImagen : "";
                            $jsonViaje = htmlspecialchars(json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="relative overflow-hidden rounded-2xl h-52 border border-slate-200 dark:border-white/10 shadow-md group transition-all duration-300 hover:shadow-xl flex flex-col justify-between p-4 bg-slate-950">
                            
                            <?php if (!empty($nombreImagen) && file_exists("../img/rutas/" . $nombreImagen)): ?>
                                <img src="<?php echo htmlspecialchars($rutaImagen); ?>" 
                                    alt="<?php echo htmlspecialchars($v['nom_rut']); ?>" 
                                    class="absolute inset-0 w-full h-full object-cover object-center z-0 opacity-70 transition-transform duration-500 group-hover:scale-110">
                            <?php endif; ?>
                            
                            <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/40 to-black/60 z-0"></div>

                            <div class="relative z-10 flex items-center justify-between mb-2">
                                <span class="text-[10px] font-mono font-bold text-white/90 bg-black/60 px-2 py-0.5 rounded-md backdrop-blur-md border border-white/10">
                                    #<?php echo $v['id_via']; ?>
                                </span>
                                <span class="text-[9px] font-extrabold uppercase tracking-wider text-emerald-300 bg-emerald-900/60 px-2 py-0.5 rounded-full border border-emerald-500/40 flex items-center gap-1 backdrop-blur-md">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block animate-pulse"></span> Activo
                                </span>
                            </div>

                            <div class="relative z-10 space-y-0.5 mt-auto mb-3">
                                <span class="text-[10px] font-black uppercase tracking-widest text-amber-300 drop-shadow-md">
                                    $<?php echo number_format($v['val_via'], 0, ',', '.'); ?> COP
                                </span>
                                <h3 class="font-black text-white text-lg tracking-tight leading-tight truncate drop-shadow-lg" title="<?php echo htmlspecialchars($v['nom_rut']); ?>">
                                    <?php echo htmlspecialchars($v['nom_rut']); ?>
                                </h3>
                                <p class="text-[10px] text-slate-300 truncate"><i class="fas fa-user-tie mr-1 text-slate-400"></i> <?php echo htmlspecialchars($v['nom_usu']); ?></p>
                            </div>

                            <div class="relative z-10 flex items-center gap-2 pt-2 border-t border-white/20">
                                <a href="eliminar.php?tipo=viaje&id=<?php echo $v['id_via']; ?>" 
                                onclick="return confirm(window.SGET_I18N?.t('¿Confirma que el vehículo llegó a su destino y desea terminar/eliminar el viaje?') || 'Confirm that the vehicle reached its destination and finish the trip?')"
                                class="flex-1 text-center py-1.5 px-2 bg-red-600/90 hover:bg-red-600 text-white font-bold text-[10px] uppercase tracking-wider rounded-lg shadow-sm transition-all flex items-center justify-center gap-1 backdrop-blur-sm">
                                    <i class="fas fa-flag-checkered"></i> Terminar
                                </a>

                                <button type="button" 
                                        data-viaje='<?php echo $jsonViaje; ?>'
                                        onclick="abrirModalDetalleBtn(this)" 
                                        class="p-2 bg-amber-400 hover:bg-amber-300 text-slate-950 rounded-lg transition-all flex items-center justify-center shadow-sm cursor-pointer"
                                        title="Ver Información">
                                    <i class="fas fa-eye text-[10px]"></i>
                                </button>
                                
                                <button type="button" 
                                        data-viaje='<?php echo $jsonViaje; ?>'
                                        onclick="abrirModalEditarBtn(this)" 
                                        class="p-2 bg-black/40 hover:bg-black/60 border border-white/30 text-white rounded-lg transition-all flex items-center justify-center backdrop-blur-md cursor-pointer" 
                                        title="Editar Parámetros">
                                    <i class="fas fa-pen text-[10px]"></i>
                                </button>
                            </div>

                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center p-12 bg-white dark:bg-[#121826] rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl text-center">
                    <div class="w-16 h-16 rounded-2xl bg-sky-500/10 text-sky-400 flex items-center justify-center text-2xl mb-4">
                        <i class="fas fa-route"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">No hay viajes activos</h3>
                    <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Actualmente no existen órdenes de despacho en tránsito.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- MODAL OVERLAY -->
    <div id="overlayViaje" onclick="cerrarTodosModales()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- PANEL LATERAL DESLIZANTE (DRAWER) DESDE LA DERECHA -->
    <aside id="drawerViaje" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#121826] border-l border-slate-200 dark:border-white/15 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between">
            <h3 id="drawerTitulo" class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-route text-sky-400"></i> Asignar Nuevo Viaje
            </h3>
            <button onclick="cerrarModalViaje()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"><i class="fas fa-times text-xs"></i></button>
        </div>
        
        <div class="p-6 flex-1 overflow-y-auto space-y-4">
            <form id="formViaje" action="procesar_viaje.php" method="POST" class="space-y-4">
                <input type="hidden" name="id_via" id="input_id_via" value="">
                
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase">Ruta Programada</label>
                    <select name="id_rut_via" id="select_id_rut_via" required onchange="actualizarPrecioRuta()" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-white">
                        <option value="">Seleccione ruta...</option>
                        <?php 
                        // Guardamos los precios en atributos data para usarlos en JavaScript
                        if($rutas_select) { 
                            while($r = $rutas_select->fetch_assoc()) { 
                                echo '<option value="'.$r['id_rut'].'" data-precio="'.$r['val_rut'].'">'.htmlspecialchars($r['nom_rut']).' ($'.number_format($r['val_rut'], 0, ',', '.').')</option>'; 
                            } 
                        } 
                        ?>
                    </select>
                </div>
                
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase">Conductor Asignado</label>
                    <select name="id_usu_via" id="select_id_usu_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-white">
                        <option value="">Seleccione conductor...</option>
                        <?php 
                        if($conductores_select) { 
                            while($c = $conductores_select->fetch_assoc()) { 
                                echo '<option value="'.$c['id_usu'].'">'.htmlspecialchars($c['nom_usu']).'</option>'; 
                            } 
                        } 
                        ?>
                    </select>
                </div>
                
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase">Vehículo Asignado</label>
                    <select name="id_veh_via" id="select_id_veh_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-white">
                        <option value="">Seleccione placa...</option>
                        <?php 
                        if($vehiculos_select) { 
                            while($ve = $vehiculos_select->fetch_assoc()) { 
                                echo '<option value="'.$ve['id_veh'].'">Placa: '.htmlspecialchars($ve['pla_veh']).'</option>'; 
                            } 
                        } 
                        ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Fecha Salida</label>
                        <input type="date" name="fec_via" id="input_fec_via" required class="w-full px-3 py-2 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Hora Salida</label>
                        <input type="time" name="hor_sal_via" id="input_hor_sal_via" required class="w-full px-3 py-2 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white">
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase">Tarifa ($)</label>
                    <input type="number" name="val_via" id="input_val_via" step="0.01" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white font-mono">
                </div>
            </form>
        </div>
        
        <div class="p-6 border-t border-slate-100 dark:border-white/5 flex gap-3">
            <button type="button" onclick="cerrarModalViaje()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 text-slate-300 rounded-xl text-xs font-bold uppercase tracking-wider cursor-pointer">Cancelar</button>
            <button type="submit" form="formViaje" class="flex-1 py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-sky-500/20 hover:opacity-90 transition-all cursor-pointer">Guardar</button>
        </div>
    </aside>

    <!-- MODAL DE AYUDA DEL MÓDULO -->
    <div id="overlayAyuda" onclick="cerrarModalAyuda()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="modalAyuda" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-4 transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <i class="fas fa-info-circle text-sky-400"></i> Guía de Despacho de Viajes
                </h3>
                <button onclick="cerrarModalAyuda()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"><i class="fas fa-times text-xs"></i></button>
=======
        <?php if ($cerrados > 0): ?>
            <div class="sget-flash sget-flash--info">
                <i class="fas fa-clock-rotate-left"></i>
                <span>Mantenimiento automático: se cerraron <?= $cerrados ?> viaje(s) que superaron el plazo de operación.</span>
>>>>>>> Stashed changes
            </div>
        <?php endif; ?>

<<<<<<< Updated upstream
    <!-- SCRIPTS DE CONTROL -->
    <script>
        // Función para autocompletar el precio al cambiar la ruta
        function actualizarPrecioRuta() {
            const selectRuta = document.getElementById('select_id_rut_via');
            const inputValVia = document.getElementById('input_val_via');
            
            const selectedOption = selectRuta.options[selectRuta.selectedIndex];
            const precio = selectedOption.getAttribute('data-precio');
            
            if (precio) {
                inputValVia.value = precio;
            } else {
                inputValVia.value = '';
=======
        <?= Flash::render() ?>

        <section class="sget-grid sget-grid--kpi">
            <?php foreach ([
                ['fa-calendar-check', 'var(--sget-azul)',    'Programados', $conteo['Programado']],
                ['fa-truck-fast',    'var(--sget-ambars)',  'En curso',    $conteo['En curso']],
                ['fa-flag-checkered','var(--sget-emerald)', 'Finalizados', $conteo['Finalizado']],
                ['fa-ban',           'var(--sget-rojo)',    'Cancelados',  $conteo['Cancelado']],
            ] as $kpi): ?>
                <div class="sget-card sget-kpi">
                    <span class="sget-kpi__icono" style="background:color-mix(in srgb,<?= $kpi[1] ?> 12%,transparent);color:<?= $kpi[1] ?>">
                        <i class="fas <?= $kpi[0] ?>"></i></span>
                    <div><p class="sget-label"><?= $kpi[2] ?></p><p class="sget-kpi__valor"><?= $kpi[3] ?></p></div>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="sget-toolbar">
            <div class="sget-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" id="buscarViaje" class="sget-input" placeholder="Buscar ruta, conductor o placa… (Ctrl+K)">
            </div>
        </div>

        <?php if ($total === 0): ?>
            <div class="sget-vacio">
                <span class="sget-vacio__icono"><i class="fas fa-truck-fast"></i></span>
                <h2 class="sget-label" style="font-size:.875rem">No hay viajes programados</h2>
                <p class="sget-page-sub" style="margin:0">Programa el primer despacho asignando ruta, conductor, vehículo y hora de salida.</p>
                <button type="button" class="sget-btn sget-btn--primario" style="margin-top:1rem"
                        data-sget-modal="modalViaje" data-sget-nuevo="Programar Nuevo Viaje">
                    <i class="fas fa-plus"></i> Programar Viaje
                </button>
            </div>
        <?php else: ?>
            <section class="sget-grid sget-grid--ancho">
                <?php foreach ($viajes as $v):
                    $id        = (int)$v['id_via'];
                    $estado    = (string)$v['est_via'];
                    $instante  = Fecha::instanteSalida($v['fec_via'] ?? null, $v['hor_sal_via'] ?? null);
                    $yaSalio   = $instante !== null && strtotime($instante) <= time();
                    $reservas  = (int)($v['num_reservas'] ?? 0);
                    $trayecto  = trim(($v['nom_rut'] ?? 'Ruta') .
                                      (!empty($v['ori_rut']) ? ' (' . $v['ori_rut'] . ' → ' . ($v['des_rut'] ?? '?') . ')' : ''));
                    $datosEdicion = [
                        'id_via'      => $id,
                        'id_rut_via'  => (int)$v['id_rut_via'],
                        'id_usu_via'  => (int)$v['id_usu_via'],
                        'id_veh'      => (int)($v['id_veh'] ?? 0),
                        'fec_via'     => Fecha::soloFecha($v['fec_via'] ?? ''),
                        'hor_sal_via' => Fecha::soloHora($v['hor_sal_via'] ?? ''),
                        'hor_lleg_via'=> Fecha::soloHora($v['hor_lleg_via'] ?? ''),
                        'val_via'     => $v['val_via'],
                        'titulo'      => 'Editar Viaje #' . $id,
                    ];
                    $datosCancelar = [
                        'id'        => $id,
                        'salida'    => Fecha::legible($instante),
                        'ya_salio'  => $yaSalio ? 1 : 0,
                        'pasajeros' => $reservas,
                        'trayecto'  => $trayecto,
                        'minimo'    => Config::MIN_ANOTACION_CANCELACION,
                    ];
                ?>
                <article class="sget-card sget-fila" data-sget-fila style="display:flex;flex-direction:column;gap:.875rem">

                    <header style="display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem">
                        <div style="min-width:0">
                            <p class="sget-label"><i class="fas fa-route"></i> Viaje #<?= $id ?></p>
                            <h3 class="sget-page-title" style="font-size:1.0625rem;margin:.25rem 0">
                                <span class="sget-linea-1"><?= htmlspecialchars($trayecto, ENT_QUOTES, 'UTF-8') ?></span>
                            </h3>
                        </div>
                        <span class="sget-badge <?= ViajeService::claseEstado($estado) ?>">
                            <i class="fas <?= ViajeService::iconoEstado($estado) ?>"></i> <?= $estado ?>
                        </span>
                    </header>

                    <div class="sget-form-2col" style="gap:.75rem">
                        <div>
                            <p class="sget-label">Salida programada</p>
                            <p class="sget-mono" style="font-size:.8125rem;margin-top:.25rem">
                                <?= Fecha::legible($instante) ?>
                                <?php if ($yaSalio): ?>
                                    <span class="sget-badge sget-badge--aviso" style="margin-left:.25rem">Ya salió</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <p class="sget-label">Recursos asignados</p>
                            <p style="font-size:.8125rem;margin-top:.25rem" class="sget-truncar">
                                <i class="fas fa-user-tie"></i> <?= htmlspecialchars((string)($v['conductor'] ?? 'Sin conductor'), ENT_QUOTES, 'UTF-8') ?><br>
                                <i class="fas fa-bus"></i> <?= htmlspecialchars((string)($v['pla_veh'] ?? 'Sin placa'), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    </div>

                    <div class="sget-form-3col" style="gap:.5rem">
                        <div>
                            <p class="sget-label">Tarifa</p>
                            <p class="sget-mono" style="font-weight:800">$<?= number_format((float)$v['val_via'], 0, ',', '.') ?></p>
                        </div>
                        <div>
                            <p class="sget-label">Reservas</p>
                            <p class="sget-mono"><?= $reservas ?> pasajero(s)</p>
                        </div>
                        <div>
                            <p class="sget-label">Llegada est.</p>
                            <p class="sget-mono"><?= Fecha::soloHora($v['hor_lleg_via'] ?? '') ?: '—' ?></p>
                        </div>
                    </div>

                    <footer style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:auto;padding-top:.875rem;border-top:1px solid var(--sget-borde)">
                        <button type="button" class="sget-btn sget-btn--neutro sget-btn--sm" style="flex:1"
                                data-sget-modal="modalViaje" data-sget-nuevo="Programar Nuevo Viaje"
                                data-sget-datos='<?= htmlspecialchars(json_encode($datosEdicion, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                            <i class="fas fa-pen"></i> Editar
                        </button>

                        <?php if (!$yaSalio): ?>
                            <button type="button" class="sget-btn sget-btn--aviso sget-btn--sm" style="flex:1"
                                    data-sget-accion="enCurso"
                                    data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="fas fa-truck-fast"></i> En curso
                            </button>
                        <?php endif; ?>

                        <button type="button" class="sget-btn sget-btn--neutro sget-btn--sm" style="flex:1"
                                data-sget-accion="finalizar"
                                data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                            <i class="fas fa-flag-checkered"></i> Terminar
                        </button>

                        <button type="button" class="sget-icon-btn sget-icon-btn--peligro"
                                title="Cancelar viaje y notificar pasajeros" aria-label="Cancelar viaje"
                                data-sget-accion="cancelarViaje"
                                data-sget-dato='<?= htmlspecialchars(json_encode($datosCancelar, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                            <i class="fas fa-ban"></i>
                        </button>
                    </footer>
                </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/../views/modals/viaje.php'; ?>

<script>
    window.__MOTIVOS_VIAJE__ = <?= $motivos ?>;
    document.addEventListener('DOMContentLoaded', function () {
        SGETCRUD.atajoBusqueda('buscarViaje');
        SGETCRUD.buscar('buscarViaje', '[data-sget-fila]');

        /* Al elegir ruta: se heredan la tarifa y la hora de salida por defecto.
           Esto elimina el error de "tarifa en 0" y la hora 00:00 heredada. */
        var selRuta = document.getElementById('viaje_ruta');
        var inpTarifa = document.getElementById('viaje_tarifa');
        var inpHora   = document.getElementById('viaje_hora');
        var inpFecha  = document.getElementById('viaje_fecha');
        var resumen   = document.querySelector('[data-resumen]');

        function sincronizar() {
            var opcion = selRuta.options[selRuta.selectedIndex];
            if (!opcion || !opcion.value) { if (resumen) resumen.textContent = 'Selecciona la ruta y completa la fecha para ver el detalle.'; return; }

            var tarifa = opcion.getAttribute('data-tarifa');
            var hora   = opcion.getAttribute('data-hora');
            if (tarifa && (!inpTarifa.value || parseFloat(inpTarifa.value) === 0)) inpTarifa.value = tarifa;
            if (hora && !inpHora.value) inpHora.value = hora;
            if (inpFecha && !inpFecha.value) inpFecha.value = new Date().toISOString().slice(0, 10);

            if (resumen) {
                resumen.innerHTML = '<strong>' + opcion.textContent.trim().split('—')[0] + '</strong><br>' +
                    'Salida: ' + (inpFecha.value || '—') + ' a las ' + (inpHora.value || '—') +
                    ' · Tarifa: $' + Number(tarifa || 0).toLocaleString('es-CO');
>>>>>>> Stashed changes
            }
        }
        if (selRuta) { selRuta.addEventListener('change', sincronizar); sincronizar(); }
    });
</script>
<?php
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';

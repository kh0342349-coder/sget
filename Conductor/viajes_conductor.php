<?php
date_default_timezone_set('America/Bogota');
if (!class_exists('Auth')) {
    require_once __DIR__ . '/../core/bootstrap.php';
}
/* La guardia vive en `Auth`: una sola política de autorización para toda
   la aplicación. Antes cada página repetía su propio
   `if (!isset($_SESSION['documento']) || $_SESSION['rol'] != N)`. */
Auth::requerirSesion();
Auth::requerirRol(Config::ROL_CONDUCTOR);

include '../assets/conexion.php'; 

$nombreReal = $_SESSION['nombre_usuario'] ?? "Conductor";
$documento  = $_SESSION['documento'];

/* --------------------------------------------------------------------------
 * FINALIZAR VIAJE
 * --------------------------------------------------------------------------
 * ANTES: aquí mismo se hacía `UPDATE viaje SET est_via = 'Finalizado'` sin
 * comprobar nada más: sin token anti-CSRF (cualquier página externa podía
 * cerrarlo con un POST) y sin verificar que el viaje fuera de este conductor
 * (bastaba conocer el id). De hecho, el formulario NO llevaba el campo
 * `finalizar_viaje` que este bloque exigía, así que la ruta ni siquiera se
 * ejecutaba y el conductor no tenía forma de cerrar su viaje desde aquí.
 *
 * AHORA: token anti-CSRF + propiedad del viaje + `ViajeService::finalizar()`,
 * que además libera conductor y vehículo, avisa a los pasajeros y deja rastro.
 * -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_viaje'])) {
    $id_viaje_fin = (int)($_POST['id_viaje'] ?? 0);

    if (!Auth::validarToken((string)($_POST['_token'] ?? ''))) {
        Flash::error('La sesión del formulario caducó. Vuelve a intentarlo.');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // Permiso + propiedad: solo sobre los viajes de este conductor.
    Auth::exigirViaje('finalizar', $id_viaje_fin);

    $r = ViajeService::finalizar($id_viaje_fin);
    Flash::set(!empty($r['ok']) ? 'exito' : 'error',
        (string)($r['mensaje'] ?? 'No se pudo finalizar el viaje.'));

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$viajes_data = [];

// Obtener TODOS los viajes del conductor
$stmt_user = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ?");
$stmt_user->bind_param("s", $documento);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user && $result_user->num_rows > 0) {
    $user_data = $result_user->fetch_assoc();
    $id_conductor = $user_data['id_usu'];

    // Consulta para obtener TODO el historial de viajes
    $sql_viajes = "SELECT v.*, r.des_rut, r.nom_rut, ve.pla_veh, ve.mode_veh,
                          (SELECT COUNT(*) FROM reserva WHERE id_via_res = v.id_via) AS num_pasajeros
                   FROM viaje v 
                   JOIN rutas r ON v.id_rut_via = r.id_rut 
                   LEFT JOIN vehiculo ve ON v.id_veh = ve.id_veh 
                   WHERE v.id_usu_via = ? 
                   ORDER BY v.fec_via DESC";

    $stmt_viajes = $conexion->prepare($sql_viajes);
    $stmt_viajes->bind_param("i", $id_conductor);
    $stmt_viajes->execute();
    $result_viajes = $stmt_viajes->get_result();

    while ($row = $result_viajes->fetch_assoc()) {
        $viajes_data[] = $row;
    }

    $stmt_viajes->close();
}

// Consultas secundarias para el Drawer (+)
// hora_salida y val_rut se usan para proponer la hora y mostrar el precio:
   // el conductor no decide la tarifa, la define la ruta.
$rutas_select = $conexion->query("SELECT id_rut, nom_rut, val_rut, hora_salida FROM rutas WHERE estado = 1 ORDER BY nom_rut ASC");

$__horasRuta = [];
if ($rutas_select) {
    $rutas_select->data_seek(0);
    while ($__r = $rutas_select->fetch_assoc()) {
        if (!empty($__r['hora_salida'])) {
            $__horasRuta[(string)$__r['id_rut']] = substr((string)$__r['hora_salida'], 0, 5);
        }
    }
    $rutas_select->data_seek(0);
}
$stmt_vehiculos = $conexion->prepare("SELECT id_veh, pla_veh FROM vehiculo WHERE est_veh = ? ORDER BY pla_veh ASC");
// `bind_param` exige variables POR REFERENCIA: pasar la constante directamente
// es un error fatal (`Argument #2 cannot be passed by reference`).
$stmt_vehiculos_estado = Config::VEH_DISPONIBLE;
$stmt_vehiculos->bind_param("s", $stmt_vehiculos_estado);
$stmt_vehiculos->execute();
$vehiculos_select = $stmt_vehiculos->get_result();

$stmt_user->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Viajes - SGET</title>
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
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">
    
    <!-- Carga Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Contenedor Principal -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen min-w-0">
        
       <!-- INCLUSIÓN DEL HEADER DEL CONDUCTOR -->
        <?php include '../includes/header.php'; ?>

        <!-- Cuerpo principal -->
        <div class="p-8 space-y-6 flex-1 min-w-0">
            
            <!-- ENCABEZADO CON TITULO, AYUDA (?) Y BOTÓN (+) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#1e293b]/50 p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Historial de Viajes</h1>
                        
                        <!--
                             BOTÓN DE AYUDA DEL MÓDULO · RETIRADO
                             Este «?» por pantalla se sustituyó por UNO SOLO global en la
                             esquina inferior derecha (views/modals/ayuda.php), que además
                             cambia de contenido según el rol y el módulo. Con estos botones
                             repartidos, cada módulo llevaba su propia copia de la guía y se
                             desincronizaban entre sí.
                        -->

                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Consulta el registro completo de todos tus viajes realizados y en proceso.</p>
                </div>

            </div>
            
            <!-- Tabla con scrollbar horizontal -->
            <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl max-w-6xl transition-colors duration-300">
                <div class="overflow-x-auto custom-scrollbar rounded-xl border border-slate-200 dark:border-white/5 w-full">
                    <table class="w-full text-sm text-left border-collapse min-w-[700px]">
                        <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                            <tr>
                                <th class="px-5 py-3.5">Destino / Ruta</th>
                                <th class="px-5 py-3.5">Fecha / Hora</th>
                                <th class="px-5 py-3.5">Vehículo</th>
                                <th class="px-5 py-3.5 text-center">Pasajeros</th>
                                <th class="px-5 py-3.5 text-center">Estado</th>
                                <th class="px-5 py-3.5 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                            <?php if (!empty($viajes_data)): ?>
                                <?php foreach ($viajes_data as $v): ?>
                                <?php 
                                    $jsonViaje = htmlspecialchars(json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-5 py-4 font-bold text-slate-900 dark:text-white capitalize">
                                        <?php echo htmlspecialchars($v['des_rut'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="px-5 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono">
                                        <?php 
                                            $fecha = !empty($v['fec_via']) ? date('d/m/Y - h:i A', strtotime($v['fec_via'])) : 'N/A';
                                            echo htmlspecialchars($fecha); 
                                        ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="bg-slate-100 dark:bg-white/5 text-blue-600 dark:text-neon-azul border border-slate-200 dark:border-white/10 px-2.5 py-0.5 rounded-md text-[10px] font-black tracking-wider uppercase inline-block w-fit">
                                                <?php echo htmlspecialchars($v['pla_veh'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium px-0.5 capitalize">
                                                <?php echo htmlspecialchars($v['mode_veh'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-500/10 border border-emerald-500/20 rounded-md py-0.5 max-w-[50px] mx-auto text-xs">
                                            <?php echo (int)($v['num_pasajeros'] ?? 0); ?>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 text-center">
                                        <?php 
                                            $estado = strtolower(trim($v['est_via'] ?? ''));
                                            if (in_array($estado, ['finalizado', 'terminado', 'completado', '0', '2'])): 
                                        ?>
                                            <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg">
                                                Finalizado
                                            </span>
                                        <?php elseif (in_array($estado, ['cancelado', '3'])): ?>
                                            <span class="bg-rose-500/10 text-rose-500 border border-rose-500/20 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg">
                                                Cancelado
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-extrabold text-[10px] uppercase px-2.5 py-1 rounded-lg animate-pulse">
                                                En Curso / Activo
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-5 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button" 
                                                    data-viaje='<?php echo $jsonViaje; ?>'
                                                    onclick="verFichaViaje(this)" 
                                                    class="p-2 bg-blue-500/10 text-blue-600 dark:text-neon-azul hover:bg-blue-600 hover:text-white rounded-xl transition-all shadow-sm cursor-pointer" 
                                                    title="Ver Ficha Completa">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>

                                            <?php /* «Finalizar» solo si el viaje puede terminarse de verdad.
                                                   Antes bastaba con que no estuviera cerrado, así que un
                                                   viaje de mañana aparecía con el botón activo y el
                                                   backend lo aceptaba aunque no hubiera salido. Aquí se usa
                                                   la MISMA regla que aplica el servidor
                                                   (`ViajeService::puedeFinalizar()`), de modo que botón y
                                                   validación no puedan discrepar. */ ?>
                                            <?php [$puedeFinalizar, $motivoFinalizar] = ViajeService::puedeFinalizar($v); ?>
                                            <?php if ($puedeFinalizar): ?>
                                                <button type="button" 
                                                        onclick="confirmarFinalizarViaje(<?php echo (int)$v['id_via']; ?>, '<?php echo htmlspecialchars($v['des_rut'] ?? 'Ruta', ENT_QUOTES, 'UTF-8'); ?>')"
                                                        class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 dark:text-rose-400 border border-rose-500/30 font-bold text-xs px-3 py-1.5 rounded-xl transition duration-150 flex items-center gap-1.5">
                                                    <i class="fas fa-flag-checkered text-xs"></i> Finalizar
                                                </button>
                                            <?php elseif (!in_array(strtolower(trim((string)($v['est_via'] ?? ''))), ['finalizado', 'terminado', 'completado', 'cancelado', '0', '2', '3'])): ?>
                                                <span class="bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-white/10 font-bold text-xs px-3 py-1.5 rounded-xl flex items-center gap-1.5 cursor-not-allowed opacity-70"
                                                      title="<?php echo htmlspecialchars($motivoFinalizar, ENT_QUOTES, 'UTF-8'); ?>"
                                                      aria-disabled="true">
                                                    <i class="fas fa-flag-checkered text-xs"></i> Finalizar
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-400 dark:text-slate-500 italic">
                                        <i class="fas fa-folder-open text-slate-400 mr-2"></i> No se encontraron registros en tu historial de viajes.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- OVERLAY GENERAL PARA MODALES Y PANEL LATERAL -->
<!-- MODAL POP-UP DE FICHA DE INFORMACIÓN -->
    <div id="modalFichaViaje" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#1e293b] w-full max-w-sm rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300" id="modalFichaBox">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-500 dark:text-neon-azul flex items-center justify-center text-xs">
                        <i class="fas fa-bus"></i>
                    </div>
                    <h3 id="detRuta" class="font-extrabold text-slate-900 dark:text-white text-base capitalize"></h3>
                </div>
                <button onclick="cerrarModalFicha()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            
            <div class="space-y-3.5 text-xs">
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="fas fa-shuttle-van text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Vehículo & Placa</p>
                        <p id="detVehículo" class="font-mono font-bold text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="far fa-calendar-alt text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Fecha y Hora</p>
                        <p id="detFecha" class="font-medium text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="fas fa-users text-emerald-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Pasajeros Reservados</p>
                        <p id="detPasajeros" class="font-bold text-emerald-600 dark:text-emerald-400 mt-0.5"></p>
                    </div>
                </div>
            </div>

            <button onclick="cerrarModalFicha()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer">
                Cerrar Ficha
            </button>
        </div>
    </div>

    <!-- PANEL LATERAL DESLIZANTE (DRAWER (+)) DE PROGRAMACIÓN -->
    <!-- MODAL (antes panel lateral): drawerProgramar -->
<div class="sget-modal-wrap" data-sget-capa data-titulo="drawerProgramar">
    <div class="sget-overlay"></div>
    <aside id="drawerProgramar" class="sget-modal sget-modal--sm sget-scroll">
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
            <form id="formProgramarConductor" method="POST" class="space-y-4" data-sget-despacho>
                <!--
                    ANTES: action="guardar_viaje.php" → ese archivo NO existe, así que
                    pulsar «Iniciar Despacho» mandaba al conductor a un 404 y no se
                    programaba nada. Ahora el envío pasa por el API con su token CSRF.
                -->
                <input type="hidden" name="_token" value="<?= Auth::token() ?>">
                <input type="hidden" name="modulo" value="viaje">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id_usu_via" value="<?php echo $id_conductor ?? ''; ?>">

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Seleccionar Ruta</label>
                    <select name="id_rut_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                        <option value="">Selecciona tu ruta...</option>
                        <?php /* El precio NO se pide: lo define la ruta. */ ?>
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
                    <select name="id_veh" id="id_veh_despacho" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
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
                        <input type="date" name="fec_via" id="input_fec_conductor" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Hora Salida</label>
                        <input type="time" name="hor_sal_via" id="input_hor_conductor" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                    </div>
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalDrawer()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider transition-all">
                Cancelar
            </button>
            <button type="submit" form="formProgramarConductor" class="flex-1 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-500/20 hover:opacity-95 transition-all">
                Iniciar Despacho
            </button>
        </div>
    </aside>
</div>

    <!-- MODAL POP-UP CONFIRMACIÓN FINALIZAR VIAJE -->
    <div id="modalConfirmarFin" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#1e293b] w-full max-w-sm rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300 text-center" id="modalConfirmBox">
            <div class="w-12 h-12 bg-rose-500/10 text-rose-500 rounded-2xl flex items-center justify-center text-xl mx-auto border border-rose-500/20">
                <i class="fas fa-flag-checkered"></i>
            </div>

            <div>
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base">¿Finalizar Viaje?</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" id="txtConfirmDestino"></p>
            </div>

            <form method="POST" action="" id="formFinalizarViajeModal">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="id_viaje" id="inputFinalizarId">
                <input type="hidden" name="finalizar_viaje" value="1">

                <div class="flex gap-3">
                    <button type="button" onclick="cerrarModalConfirmar()" class="flex-1 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 font-bold text-xs rounded-xl uppercase tracking-wider">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl uppercase tracking-wider shadow-lg shadow-rose-600/20">
                        Sí, Finalizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- CONTROLADORES JAVASCRIPT -->
    <script>
        function abrirModalSolicitar() {
            const drawer = document.getElementById('drawerProgramar');
            const overlay = document.getElementById('overlayConductor');

            const hoy = new Date();
            const fechaHoy = hoy.toISOString().split('T')[0];
            const horaHoy = hoy.toTimeString().split(' ')[0].substring(0, 5);

            document.getElementById('input_fec_conductor').value = fechaHoy;
            document.getElementById('input_fec_conductor').min = fechaHoy;
            document.getElementById('input_hor_conductor').value = horaHoy;




        }

        function cerrarModalDrawer() {
            const drawer = document.getElementById('drawerProgramar');
            const overlay = document.getElementById('overlayConductor');




        }

        function verFichaViaje(btn) {
            const v = JSON.parse(btn.getAttribute('data-viaje'));
            document.getElementById('detRuta').innerText = v.des_rut || 'Sin Destino';
            document.getElementById('detVehículo').innerText = (v.pla_veh || 'N/A') + ' - ' + (v.mode_veh || '');
            document.getElementById('detFecha').innerText = v.fec_via + ' ' + (v.hor_sal_via || '');
            document.getElementById('detPasajeros').innerText = (v.num_pasajeros || 0) + ' pasajeros a bordo';

            const overlay = document.getElementById('overlayConductor');
            const modal = document.getElementById('modalFichaViaje');
            const box = document.getElementById('modalFichaBox');



            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalFicha() {
            const overlay = document.getElementById('overlayConductor');
            const modal = document.getElementById('modalFichaViaje');
            const box = document.getElementById('modalFichaBox');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');


        }

        function confirmarFinalizarViaje(idViaje, nombreRuta) {
            document.getElementById('inputFinalizarId').value = idViaje;
            document.getElementById('txtConfirmDestino').innerText = 'Confirma que el vehículo llegó a su destino (' + nombreRuta + ') para liberar la unidad.';

            const overlay = document.getElementById('overlayConductor');
            const modal = document.getElementById('modalConfirmarFin');
            const box = document.getElementById('modalConfirmBox');



            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalConfirmar() {
            const overlay = document.getElementById('overlayConductor');
            const modal = document.getElementById('modalConfirmarFin');
            const box = document.getElementById('modalConfirmBox');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');


        }

        function cerrarTodosModales() {
            cerrarModalDrawer();
            cerrarModalFicha();
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

    <!-- Motor común de modales + puente de compatibilidad con el JS heredado -->
    <script src="../assets/js/sget-modal.js?v=<?= @filemtime('../assets/js/sget-modal.js') ?: '1' ?>"></script>
    <script src="../assets/js/sget-puente.js?v=<?= @filemtime('../assets/js/sget-puente.js') ?: '1' ?>"></script>

    <script>
    /* ======================================================================
       DESPACHO DEL CONDUCTOR
       El formulario heredado vivía sin backend (guardar_viaje.php no existe).
       Ahora se envía al API, que valida disponibilidad del conductor y del
       vehículo, avisa al resto y responde con errores por campo.
       ====================================================================== */
    (function () {
        const form = document.getElementById('formProgramarConductor');
        if (!form) return;

        // Al elegir la ruta se propone la hora de salida por defecto de la ruta.
        const selRuta = form.querySelector('[name="id_rut_via"]');
        const selVeh  = form.querySelector('[name="id_veh"]');
        const fFecha  = document.getElementById('input_fec_conductor');
        const fHora   = document.getElementById('input_hor_conductor');
        const HORAS   = <?= json_encode($__horasRuta) ?>;

        if (selRuta && HORAS[selRuta.value] && fHora && !fHora.value) {
            fHora.value = HORAS[selRuta.value];
        }

        if (fFecha && !fFecha.value) fFecha.value = new Date().toISOString().slice(0, 10);
        if (fHora && !fHora.value) fHora.value = '06:00';

        if (selRuta) {
            selRuta.addEventListener('change', () => {
                if (HORAS[selRuta.value]) fHora.value = HORAS[selRuta.value];
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (!selRuta.value || !selVeh.value) {
                SGETModal.toast('Elige la ruta y el vehículo antes de despachar.', 'error');
                return;
            }

            const btn = form.parentElement.querySelector('button[form="formProgramarConductor"]');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Despachando…'; }

            const cuerpo = new FormData(form);
            fetch('../api/index.php', {
                method: 'POST',
                body: cuerpo,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(r => r.json())
                .then(j => {
                    if (j.status === 'ok') {
                        SGETModal.toast(j.mensaje, 'exito');
                        setTimeout(() => { location.href = j.redirect || 'viaje_asignado.php'; }, 900);
                    } else {
                        SGETModal.toast(j.mensaje || 'No se pudo programar el viaje.', 'error');
                        if (btn) { btn.disabled = false; btn.innerHTML = 'Iniciar Despacho'; }
                    }
                })
                .catch(() => {
                    SGETModal.toast('Error de comunicación con el servidor.', 'error');
                    if (btn) { btn.disabled = false; btn.innerHTML = 'Iniciar Despacho'; }
                });
        });
    })();
    </script>
</body>
</html>
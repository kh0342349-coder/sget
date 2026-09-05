<?php
// Archivo: Admin/viajes.php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php'; 
require_once '../helpers/AuthHelper.php';

// Verificación de seguridad (Solo Admin)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// BLOQUEO DE SEGURIDAD POR RESTRICCIONES
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'viajes');

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// 1. CIERRE AUTOMÁTICO DE VIAJES (Han pasado 24 horas o más desde su hora de salida)
$sql_cierre_tiempo = "SELECT id_via, id_usu_via, id_veh FROM viaje WHERE est_via = 'Activo' AND TIMESTAMP(fec_via, hor_sal_via) <= (NOW() - INTERVAL 24 HOUR)";
$res_cierre = $conexion->query($sql_cierre_tiempo);
if ($res_cierre && $res_cierre->num_rows > 0) {
    while($row_c = $res_cierre->fetch_assoc()) {
        $id_v_exp = $row_c['id_via'];
        $id_u_exp = $row_c['id_usu_via'];
        $id_ve_exp = $row_c['id_veh'];

        // Marcar viaje como Finalizado
        $conexion->query("UPDATE viaje SET est_via = 'Finalizado' WHERE id_via = $id_v_exp");
        // Liberar conductor
        if ($id_u_exp) {
            $conexion->query("UPDATE usuario SET est_con_usu = 1 WHERE id_usu = $id_u_exp");
        }
        // Liberar vehículo
        if ($id_ve_exp) {
            $conexion->query("UPDATE vehiculo SET est_veh = 1 WHERE id_veh = $id_ve_exp");
        }
    }
}

// Consulta de viajes activos
$query = "SELECT 
            v.id_via, 
            v.id_rut_via,
            v.id_usu_via,
            v.id_veh,
            IFNULL(r.nom_rut, 'Ruta no asignada') AS nom_rut, 
            r.img_rut,
            IFNULL(u.nom_usu, 'Sin conductor') AS nom_usu, 
            IFNULL(veh.pla_veh, 'Sin Placa') AS pla_veh,
            v.val_via, 
            v.fec_via,
            v.hor_sal_via,
            v.est_via
          FROM viaje v 
          LEFT JOIN rutas r ON v.id_rut_via = r.id_rut 
          LEFT JOIN usuario u ON v.id_usu_via = u.id_usu 
          LEFT JOIN vehiculo veh ON v.id_veh = veh.id_veh
          WHERE v.est_via = 'Activo'
          ORDER BY v.id_via DESC";

$resultado = $conexion->query($query);

// Consultas secundarias para selects del formulario (Incluimos val_rut para autocompletar la tarifa)
$rutas_select = $conexion->query("SELECT id_rut, nom_rut, val_rut FROM rutas ORDER BY nom_rut ASC");

$conductores_select = $conexion->query("SELECT id_usu, nom_usu, est_con_usu 
                                        FROM usuario 
                                        WHERE id_rol_usu = 2 
                                        AND (est_con_usu = 1 OR est_con_usu = 'Disponible') 
                                        AND id_usu NOT IN (
                                            SELECT id_usu_via FROM viaje WHERE est_via = 'Activo'
                                        ) 
                                        ORDER BY nom_usu ASC");

$vehiculos_select = $conexion->query("SELECT id_veh, pla_veh, est_veh 
                                      FROM vehiculo 
                                      WHERE (est_veh = 1 OR est_veh = 'Activo') 
                                      AND id_veh NOT IN (
                                          SELECT id_veh FROM viaje WHERE est_via = 'Activo'
                                      ) 
                                      ORDER BY pla_veh ASC");
?>
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
<body class="bg-slate-50 dark:bg-[#080c14] text-slate-800 dark:text-slate-100 flex min-h-screen transition-colors duration-300">

    <?php include '../includes/sidebar.php'; ?>

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
                </button>
            </div>

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
                                onclick="return confirm('¿Confirma que el vehículo llegó a su destino y desea terminar/eliminar el viaje?')"
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
            </div>
            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                <li class="flex items-start gap-2">
                    <i class="fas fa-plus-circle text-sky-400 mt-0.5"></i>
                    <span><b>Asignar Viaje:</b> Al seleccionar la ruta, la tarifa se completa automáticamente. Pone al conductor y vehículo en estado Ocupado.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-pen text-blue-400 mt-0.5"></i>
                    <span><b>Editar Parámetros:</b> Modifica los datos y gestiona la liberación o asignación de recursos.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-flag-checkered text-red-400 mt-0.5"></i>
                    <span><b>Terminar Viaje:</b> Concluye el viaje liberando al conductor y vehículo.</span>
                </li>
            </ul>
            <button onclick="cerrarModalAyuda()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer mt-2">
                Entendido
            </button>
        </div>
    </div>

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
            }
        }

        function abrirDrawer() {
            document.getElementById('overlayViaje').classList.remove('opacity-0', 'pointer-events-none');
            document.getElementById('overlayViaje').classList.add('opacity-100', 'pointer-events-auto');
            document.getElementById('drawerViaje').classList.remove('translate-x-full');
            document.getElementById('drawerViaje').classList.add('translate-x-0');
        }

        function cerrarModalViaje() {
            document.getElementById('drawerViaje').classList.remove('translate-x-0');
            document.getElementById('drawerViaje').classList.add('translate-x-full');
            document.getElementById('overlayViaje').classList.remove('opacity-100', 'pointer-events-auto');
            document.getElementById('overlayViaje').classList.add('opacity-0', 'pointer-events-none');
        }

        function cerrarTodosModales() { 
            cerrarModalViaje(); 
            cerrarModalAyuda();
        }

        function abrirModalCrear() {
            document.getElementById('formViaje').reset();
            document.getElementById('input_id_via').value = '';
            document.getElementById('drawerTitulo').innerHTML = '<i class="fas fa-route text-sky-400"></i> Asignar Nuevo Viaje';
            abrirDrawer();
        }

        function abrirModalEditarBtn(btn) {
            const data = JSON.parse(btn.getAttribute('data-viaje'));
            document.getElementById('input_id_via').value = data.id_via;
            document.getElementById('select_id_rut_via').value = data.id_rut_via;
            document.getElementById('select_id_usu_via').value = data.id_usu_via;
            document.getElementById('select_id_veh_via').value = data.id_veh;
            document.getElementById('input_fec_via').value = data.fec_via;
            document.getElementById('input_hor_sal_via').value = data.hor_sal_via;
            document.getElementById('input_val_via').value = data.val_via;
            
            document.getElementById('drawerTitulo').innerHTML = '<i class="fas fa-pen text-sky-400"></i> Editar Viaje #' + data.id_via;
            abrirDrawer();
        }

        function abrirModalDetalleBtn(btn) {
            abrirModalEditarBtn(btn);
        }

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
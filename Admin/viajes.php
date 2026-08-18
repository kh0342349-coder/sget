<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

// Verificación de seguridad (Solo Admin)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

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

// Consultas secundarias para selects
$rutas_select = $conexion->query("SELECT id_rut, nom_rut, val_rut FROM rutas ORDER BY nom_rut ASC");

$conductores_select = $conexion->query("SELECT id_usu, nom_usu, est_con_usu 
                                        FROM usuario 
                                        WHERE id_rol_usu = 2 AND (est_con_usu = 1 OR id_usu IN (SELECT id_usu_via FROM viaje WHERE est_via = 'Activo')) 
                                        ORDER BY nom_usu ASC");

$vehiculos_select = $conexion->query("SELECT id_veh, pla_veh, est_veh 
                                      FROM vehiculo 
                                      WHERE est_veh = 1 OR id_veh IN (SELECT id_veh FROM viaje WHERE est_via = 'Activo') 
                                      ORDER BY pla_veh ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Despacho de Viajes</title>
    
    <script>
        const userTheme = localStorage.getItem('theme') || localStorage.getItem('color-theme');
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (userTheme === 'dark' || (!userTheme && systemTheme)) {
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
<body class="bg-slate-50 dark:bg-[#0b0f19] flex min-h-screen antialiased text-slate-800 dark:text-slate-100 transition-colors duration-300 relative overflow-x-hidden">

    <!-- BARRA LATERAL (Sidebar) -->
    <?php include 'sidebar.php'; ?>

    <!-- CONTENEDOR PRINCIPAL -->
    <!-- Se mantiene el ID main-container para que el JS del header.php pueda interactuar con él -->
    <div id="main-container" class="flex-1 ml-64 flex flex-col min-h-screen transition-all duration-300 w-full">
        
        <!-- HEADER DINÁMICO -->
        <?php include 'header.php'; ?>

        <!-- ÁREA DE TRABAJO -->
        <main class="p-6 md:p-8 flex-1 space-y-6">
            
            <!-- MENSAJES DE ALERTA -->
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'success'): ?>
                    <div id="alerta" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-between backdrop-blur-md shadow-lg">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-check-circle text-lg"></i>
                            <span class="text-sm font-semibold">Operación logística ejecutada y sincronizada correctamente.</span>
                        </div>
                        <button onclick="document.getElementById('alerta').remove()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
                    </div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div id="alerta" class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 flex items-center justify-between backdrop-blur-md shadow-lg">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-triangle text-lg"></i>
                            <span class="text-sm font-semibold">Error al intentar procesar o actualizar los parámetros del viaje solicitado.</span>
                        </div>
                        <button onclick="document.getElementById('alerta').remove()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- TÍTULO Y BOTONES DE ACCIÓN -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-900/50 p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm">
                <div>
                    <h1 class="text-xl md:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Despacho de Viajes</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs mt-0.5">Monitoreo y control de bitácoras en SGET.</p>
                </div>
                
                <button onclick="abrirModalCrear()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 hover:opacity-95 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition-all cursor-pointer whitespace-nowrap">
                    <i class="fas fa-plus-circle text-sm"></i> Asignar Viaje
                </button>
            </div>

            <!-- CONTENEDOR GRID EN TARJETAS (4 Columnas y Más Compactas) -->
            <?php if($resultado && $resultado->num_rows > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    <?php while($v = $resultado->fetch_assoc()): ?>
                        <?php 
                            $nombreImagen = trim($v['img_rut'] ?? '');
                            $rutaImagen = !empty($nombreImagen) ? "../img/rutas/" . $nombreImagen : "";
                            $jsonViaje = htmlspecialchars(json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                        ?>
                        <!-- Tarjeta Compacta -->
                        <div class="relative overflow-hidden rounded-2xl h-52 border border-slate-200 dark:border-white/10 shadow-md group transition-all duration-300 hover:shadow-xl flex flex-col justify-between p-4 bg-slate-950">
                            
                            <!-- Capa 1: Imagen de fondo -->
                            <?php if (!empty($nombreImagen)): ?>
                                <img src="<?php echo htmlspecialchars($rutaImagen); ?>" 
                                    alt="<?php echo htmlspecialchars($v['nom_rut']); ?>" 
                                    onerror="this.style.display='none';"
                                    class="absolute inset-0 w-full h-full object-cover object-center z-0 opacity-70 transition-transform duration-500 group-hover:scale-110">
                            <?php endif; ?>
                            
                            <!-- Capa 2: Degradado suave -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/40 to-black/60 z-0"></div>

                            <!-- Capa 3: Header ID y Estado -->
                            <div class="relative z-10 flex items-center justify-between mb-2">
                                <span class="text-[10px] font-mono font-bold text-white/90 bg-black/60 px-2 py-0.5 rounded-md backdrop-blur-md border border-white/10">
                                    #<?php echo $v['id_via']; ?>
                                </span>
                                <span class="text-[9px] font-extrabold uppercase tracking-wider text-emerald-300 bg-emerald-900/60 px-2 py-0.5 rounded-full border border-emerald-500/40 flex items-center gap-1 backdrop-blur-md">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block animate-pulse"></span> Activo
                                </span>
                            </div>

                            <!-- Capa 4: Información de la Ruta -->
                            <div class="relative z-10 space-y-0.5 mt-auto mb-3">
                                <span class="text-[10px] font-black uppercase tracking-widest text-amber-300 drop-shadow-md">
                                    $<?php echo number_format($v['val_via'], 0, ',', '.'); ?> COP
                                </span>
                                <h3 class="font-black text-white text-lg tracking-tight leading-tight truncate drop-shadow-lg" title="<?php echo htmlspecialchars($v['nom_rut']); ?>">
                                    <?php echo htmlspecialchars($v['nom_rut']); ?>
                                </h3>
                                <p class="text-[10px] text-slate-300 truncate"><i class="fas fa-steering-wheel mr-1 text-slate-400"></i> <?php echo htmlspecialchars($v['nom_usu']); ?></p>
                            </div>

                            <!-- Capa 5: Botones de Acción -->
                            <div class="relative z-10 flex items-center gap-2 pt-2 border-t border-white/20">
                                <a href="terminar_viaje.php?id_via=<?php echo $v['id_via']; ?>&id_usu=<?php echo $v['id_usu_via']; ?>&id_veh=<?php echo $v['id_veh']; ?>" 
                                onclick="return confirm('¿Confirma que el vehículo llegó a su destino y desea terminar el viaje?')"
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
                <!-- MENSAJE SI NO HAY VIAJES ACTIVOS -->
                <div class="flex flex-col items-center justify-center p-12 bg-white dark:bg-[#1e293b] rounded-3xl border border-slate-200 dark:border-white/5 shadow-sm text-center">
                    <div class="w-16 h-16 rounded-2xl bg-blue-500/10 text-blue-500 dark:text-neon-azul flex items-center justify-center text-2xl mb-4">
                        <i class="fas fa-route"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">No hay viajes activos</h3>
                    <p class="text-slate-500 dark:text-slate-400 text-xs mt-1 max-w-sm">Actualmente no existen ordenes de despacho en transito. Haz clic en "Asignar Viaje" para iniciar una.</p>
                </div>
            <?php endif; ?>
        </main>

        <!-- FOOTER -->
        <footer class="p-5 text-center text-slate-400 dark:text-color-mutado text-xs font-semibold border-t border-slate-200 dark:border-white/5 bg-slate-50/20 dark:bg-transparent mt-auto">
            &copy; <?php echo date('Y'); ?> Sistema de Gestión de Transporte SGET. Todos los derechos reservados.
        </footer>
    </div>

    <!-- OVERLAY GENERAL -->
    <div id="overlayViaje" onclick="cerrarTodosModales()" class="fixed inset-0 bg-slate-900/60 dark:bg-black/70 backdrop-blur-sm z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- MODAL POPUP VER INFORMACIÓN -->
    <div id="modalDetalleViaje" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#1e293b] w-full max-w-sm rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300" id="modalDetalleBox">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-500 dark:text-neon-azul flex items-center justify-center text-xs">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h3 id="detNomRuta" class="font-extrabold text-slate-900 dark:text-white text-base"></h3>
                </div>
                <button onclick="cerrarModalDetalle()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            
            <div class="space-y-3.5 text-xs">
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="fas fa-id-card text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Conductor Asignado</p>
                        <p id="detConductor" class="font-semibold text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="fas fa-shuttle-van text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Vehículo / Placa</p>
                        <p id="detVehiculo" class="font-mono font-bold text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="far fa-calendar-alt text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Fecha y Hora de Salida</p>
                        <p id="detFechaHora" class="font-medium text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>
            </div>

            <button onclick="cerrarModalDetalle()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer">
                Cerrar Ventana
            </button>
        </div>
    </div>

    <!-- PANEL LATERAL DESLIZANTE (CREAR / EDITAR) -->
    <aside id="drawerViaje" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between relative">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-neon-morado"></div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-500/10 text-blue-500 dark:text-neon-azul rounded-xl flex items-center justify-center border border-slate-100 dark:border-white/5">
                    <i id="drawerIcono" class="fas fa-bus text-base"></i>
                </div>
                <div>
                    <h3 id="drawerTitulo" class="text-base font-extrabold text-slate-900 dark:text-white">Asignar Nuevo Viaje</h3>
                    <p id="drawerSubtitulo" class="text-[11px] text-slate-500 dark:text-color-mutado">Programar orden de despachos</p>
                </div>
            </div>
            <button onclick="cerrarModalViaje()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto space-y-5">
            <form id="formViaje" action="guardar_viaje.php" method="POST" class="space-y-4">
                
                <input type="hidden" name="id_via" id="input_id_via" value="">

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Ruta Programada</label>
                    <select name="id_rut_via" id="select_id_rut_via" onchange="cargarTarifaRuta(this)" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                        <option value="">Seleccione la ruta...</option>
                        <?php 
                        if($rutas_select) {
                            $rutas_select->data_seek(0);
                            while($r = $rutas_select->fetch_assoc()) {
                                echo '<option value="'.$r['id_rut'].'" data-tarifa="'.$r['val_rut'].'">'.htmlspecialchars($r['nom_rut']).'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Conductor Asignado</label>
                    <select name="id_usu_via" id="select_id_usu_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                        <option value="">Seleccione el conductor...</option>
                        <?php 
                        if($conductores_select) {
                            $conductores_select->data_seek(0);
                            while($c = $conductores_select->fetch_assoc()) {
                                $indicador = ($c['est_con_usu'] == 0) ? ' [Asignado / En Ruta]' : '';
                                echo '<option value="'.$c['id_usu'].'">'.htmlspecialchars($c['nom_usu']).$indicador.'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Vehículo Asignado</label>
                    <select name="id_veh_via" id="select_id_veh_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                        <option value="">Seleccione vehículo/placa...</option>
                        <?php 
                        if($vehiculos_select) {
                            $vehiculos_select->data_seek(0);
                            while($v = $vehiculos_select->fetch_assoc()) {
                                $indicador = ($v['est_veh'] == 0) ? ' [Asignado / En Ruta]' : '';
                                echo '<option value="'.$v['id_veh'].'">Placa: '.htmlspecialchars($v['pla_veh']).$indicador.'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Fecha Salida</label>
                        <input type="date" name="fec_via" id="input_fec_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Hora Salida</label>
                        <input type="time" name="hor_sal_via" id="input_hor_sal_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-blue-600 dark:text-neon-azul uppercase tracking-wider">Tarifa del Viaje ($)</label>
                    <input type="number" name="val_via" id="input_val_via" required placeholder="0" class="w-full px-4 py-2.5 bg-blue-50/50 dark:bg-neon-azul/5 border border-blue-200 dark:border-neon-azul/20 rounded-xl outline-none focus:border-neon-azul text-blue-600 dark:text-neon-azul font-bold text-sm transition-all">
                </div>

            </form>
        </div>

        <div class="p-6 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalViaje()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-color-mutado rounded-xl font-bold text-xs uppercase tracking-wider transition-all">
                Cancelar
            </button>
            <button type="submit" form="formViaje" id="btnGuardarDrawer" class="flex-1 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-500/20 hover:opacity-95 transition-all">
                Guardar Viaje
            </button>
        </div>
    </aside>

    <!-- CONTROLADORES JAVASCRIPT -->
    <script>
        function abrirDrawer() {
            const drawer = document.getElementById('drawerViaje');
            const overlay = document.getElementById('overlayViaje');
            
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');
            
            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
        }

        function cerrarModalViaje() {
            const drawer = document.getElementById('drawerViaje');
            const overlay = document.getElementById('overlayViaje');

            drawer.classList.remove('translate-x-0');
            drawer.classList.add('translate-x-full');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function abrirModalDetalleBtn(btn) {
            const datos = JSON.parse(btn.getAttribute('data-viaje'));
            document.getElementById('detNomRuta').innerText = datos.nom_rut;
            document.getElementById('detConductor').innerText = datos.nom_usu;
            document.getElementById('detVehiculo').innerText = datos.pla_veh;
            document.getElementById('detFechaHora').innerText = datos.fec_via + ' ' + datos.hor_sal_via;

            const overlay = document.getElementById('overlayViaje');
            const modal = document.getElementById('modalDetalleViaje');
            const box = document.getElementById('modalDetalleBox');

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalDetalle() {
            const overlay = document.getElementById('overlayViaje');
            const modal = document.getElementById('modalDetalleViaje');
            const box = document.getElementById('modalDetalleBox');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function cerrarTodosModales() {
            cerrarModalViaje();
            cerrarModalDetalle();
        }

        function cargarTarifaRuta(selectElement) {
            const opcionSeleccionada = selectElement.options[selectElement.selectedIndex];
            const tarifa = opcionSeleccionada.getAttribute('data-tarifa');
            const inputTarifa = document.getElementById('input_val_via');

            if (tarifa) {
                inputTarifa.value = tarifa;
            } else {
                inputTarifa.value = '';
            }
        }

        function abrirModalCrear() {
            document.getElementById('formViaje').action = 'guardar_viaje.php';
            document.getElementById('drawerTitulo').innerText = 'Asignar Nuevo Viaje';
            document.getElementById('drawerSubtitulo').innerText = 'Programar orden de despachos';
            document.getElementById('drawerIcono').className = 'fas fa-bus text-base';
            document.getElementById('btnGuardarDrawer').innerText = 'Guardar Viaje';

            document.getElementById('input_id_via').value = '';
            document.getElementById('formViaje').reset();

            const hoy = new Date();
            const fechaHoy = hoy.toISOString().split('T')[0];
            const horaHoy = hoy.toTimeString().split(' ')[0].substring(0, 5);

            document.getElementById('input_fec_via').value = fechaHoy;
            document.getElementById('input_fec_via').min = fechaHoy;
            document.getElementById('input_hor_sal_via').value = horaHoy;

            abrirDrawer();
        }

        function abrirModalEditarBtn(btn) {
            const datos = JSON.parse(btn.getAttribute('data-viaje'));
            document.getElementById('formViaje').action = 'actualizar_viaje.php';
            document.getElementById('drawerTitulo').innerText = 'Editar Parámetros de Viaje';
            document.getElementById('drawerSubtitulo').innerText = 'Modificar ID: #' + datos.id_via;
            document.getElementById('drawerIcono').className = 'fas fa-pen text-base';
            document.getElementById('btnGuardarDrawer').innerText = 'Actualizar Cambios';

            document.getElementById('input_fec_via').removeAttribute('min');

            document.getElementById('input_id_via').value = datos.id_via;
            document.getElementById('select_id_rut_via').value = datos.id_rut_via;
            document.getElementById('select_id_usu_via').value = datos.id_usu_via;
            document.getElementById('select_id_veh_via').value = datos.id_veh;

            document.getElementById('input_fec_via').value = datos.fec_via;
            document.getElementById('input_hor_sal_via').value = datos.hor_sal_via;
            document.getElementById('input_val_via').value = datos.val_via;

            abrirDrawer();
        }
    </script>
</body>
</html>
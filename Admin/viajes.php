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

// Consulta de viajes activos (Inscrito id_veh)
$query = "SELECT 
            v.id_via, 
            v.id_rut_via,
            v.id_usu_via,
            v.id_veh,
            IFNULL(r.nom_rut, 'Ruta no asignada') AS nom_rut, 
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

// Consultas secundarias: Incluyen recursos disponibles (est = 1) O los que ya están asignados a un viaje activo
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
    
    <!-- PREVENCIÓN DE FLASHEO Y DETECCIÓN MODO OSCURO -->
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] flex min-h-screen antialiased text-slate-800 dark:text-slate-100 transition-colors duration-300 relative overflow-x-hidden">

    <!-- BARRA LATERAL -->
    <?php include 'sidebar.php'; ?>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- HEADER DINÁMICO REUTILIZABLE -->
        <?php include 'header.php'; ?>

        <!-- ÁREA DE TRABAJO -->
        <main class="p-8 flex-1 space-y-8">
            
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

            <!-- TÍTULO Y BOTÓN DE ACCIÓN -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Despacho de Viajes</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Monitoreo de trazabilidad en tiempo real, asignaciones vehiculares y control de bitácoras.</p>
                </div>
                <button onclick="abrirModalCrear()" class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 hover:opacity-95 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition-all self-start sm:self-auto">
                    <i class="fas fa-plus-circle text-sm"></i> Asignar Nuevo Viaje
                </button>
            </div>

            <div class="flex items-center gap-2 text-slate-700 dark:text-slate-200 font-bold text-xs uppercase tracking-wider border-b border-slate-200 dark:border-white/5 pb-3">
                <i class="fas fa-bus text-blue-500 dark:text-neon-azul"></i>
                <h2>Órdenes de Viajes Activas en SGET</h2>
            </div>

            <!-- CONTENEDOR GRID EN TARJETAS -->
            <?php if($resultado && $resultado->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php while($v = $resultado->fetch_assoc()): ?>
                        <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between space-y-4 group relative">
                            
                            <!-- Header de la Card -->
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-mono font-bold text-slate-400 dark:text-slate-400">
                                    ID: #<?php echo $v['id_via']; ?>
                                </span>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 bg-emerald-50 dark:text-emerald-400 dark:bg-emerald-500/10 px-2.5 py-0.5 rounded-full border border-emerald-100 dark:border-emerald-500/10 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span> En Ruta
                                </span>
                            </div>

                            <!-- Información Principal -->
                            <div class="space-y-1.5">
                                <h3 class="font-extrabold text-slate-900 dark:text-white text-base tracking-tight group-hover:text-blue-500 dark:group-hover:text-neon-azul transition-colors">
                                    <?php echo htmlspecialchars($v['nom_rut']); ?>
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                    <i class="fas fa-id-card text-[10px] text-slate-400"></i> Conductor: <span class="font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($v['nom_usu']); ?></span>
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                    <i class="fas fa-shuttle-van text-[10px] text-slate-400"></i> Vehículo: <span class="font-mono font-bold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($v['pla_veh']); ?></span>
                                </p>
                            </div>

                            <!-- Parámetros de Operación -->
                            <div class="pt-2 border-t border-slate-100 dark:border-white/5 text-xs space-y-1.5 text-slate-600 dark:text-slate-300">
                                <div class="flex items-center gap-2">
                                    <i class="far fa-calendar-alt text-slate-400 w-4 text-center"></i>
                                    <span><?php echo htmlspecialchars($v['fec_via'] . ' ' . $v['hor_sal_via']); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-dollar-sign text-blue-500 dark:text-neon-azul w-4 text-center"></i>
                                    <span class="font-bold text-blue-600 dark:text-neon-azul">
                                        Tarifa: $<?php echo number_format($v['val_via'], 0, ',', '.'); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- ACCIONES -->
                            <div class="pt-2 flex gap-2">
                                <a href="terminar_viaje.php?id_via=<?php echo $v['id_via']; ?>&id_usu=<?php echo $v['id_usu_via']; ?>&id_veh=<?php echo $v['id_veh']; ?>" 
                                   onclick="return confirm('¿Confirma que el vehículo llegó a su destino y desea dar por TERMINADO este viaje de forma definitiva?')"
                                   class="flex-1 text-center py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5">
                                    <i class="fas fa-flag-checkered text-[10px]"></i> Terminar Viaje
                                </a>
                                
                                <button type="button" 
                                        onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($v)); ?>)" 
                                        class="p-2.5 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white rounded-xl transition-all flex items-center justify-center" 
                                        title="Editar Parámetros">
                                    <i class="fas fa-pen text-xs"></i>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Estado vacío -->
                <div class="bg-white dark:bg-[#1e293b] rounded-2xl border border-slate-200 dark:border-white/5 p-12 text-center text-slate-400 transition-colors">
                    <i class="fas fa-route text-3xl mb-3 block text-slate-300 dark:text-white/10"></i>
                    No se encuentran órdenes de viaje activas o disponibles en este momento.
                </div>
            <?php endif; ?>

        </main>

        <!-- FOOTER -->
        <footer class="p-6 text-center text-slate-400 dark:text-color-mutado text-xs font-semibold border-t border-slate-200 dark:border-white/5 bg-slate-50/20 dark:bg-transparent">
            &copy; <?php echo date('Y'); ?> Sistema de Gestión de Transporte SGET. Todos los derechos reservados.
        </footer>
    </div>

    <!-- OVERLAY PARA EL PANEL LATERAL -->
    <div id="overlayViaje" onclick="cerrarModalViaje()" class="fixed inset-0 bg-slate-900/60 dark:bg-black/70 backdrop-blur-sm z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- PANEL LATERAL DESLIZANTE -->
    <aside id="drawerViaje" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        
        <!-- Encabezado del Panel -->
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

        <!-- Cuerpo del Formulario -->
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

        <!-- Acciones del Panel -->
        <div class="p-6 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalViaje()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-color-mutado rounded-xl font-bold text-xs uppercase tracking-wider transition-all">
                Cancelar
            </button>
            <button type="submit" form="formViaje" id="btnGuardarDrawer" class="flex-1 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-500/20 hover:opacity-95 transition-all">
                Guardar Viaje
            </button>
        </div>
    </aside>

    <!-- CONTROLADOR JAVASCRIPT DEL MODAL DUAL -->
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

            // Auto-asignar Fecha y Hora Actual
            const hoy = new Date();
            const fechaHoy = hoy.toISOString().split('T')[0];
            const horaHoy = hoy.toTimeString().split(' ')[0].substring(0, 5);

            document.getElementById('input_fec_via').value = fechaHoy;
            document.getElementById('input_fec_via').min = fechaHoy; // Previene fechas pasadas
            document.getElementById('input_hor_sal_via').value = horaHoy;

            abrirDrawer();
        }

        function abrirModalEditar(datos) {
            document.getElementById('formViaje').action = 'actualizar_viaje.php';
            document.getElementById('drawerTitulo').innerText = 'Editar Parámetros de Viaje';
            document.getElementById('drawerSubtitulo').innerText = 'Modificar ID: #' + datos.id_via;
            document.getElementById('drawerIcono').className = 'fas fa-pen text-base';
            document.getElementById('btnGuardarDrawer').innerText = 'Actualizar Cambios';

            // Remover restricción de fecha mínima en edición
            document.getElementById('input_fec_via').removeAttribute('min');

            // Cargar Valores
            document.getElementById('input_id_via').value = datos.id_via;
            document.getElementById('select_id_rut_via').value = datos.id_rut_via;
            
            // Asignación por defecto de Conductor y Vehículo
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
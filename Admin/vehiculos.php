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

// Consultamos los vehículos registrados
$query = "SELECT * FROM vehiculo ORDER BY id_veh DESC";
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Control de Vehículos</title>

    <!-- Tailwind CSS & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
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

        // Script Anti-Parpadeo de Tema (Idéntico a index.php)
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] flex min-h-screen antialiased text-slate-800 dark:text-slate-100 transition-colors duration-300 relative overflow-x-hidden">

    <!-- BARRA LATERAL -->
    <?php include 'sidebar.php'; ?>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- HEADER ESTANDARIZADO -->
        <?php include 'header.php'; ?>

        <!-- ÁREA DE TRABAJO -->
        <main class="p-8 flex-1 space-y-6 pt-24">
            
            <!-- MENSAJES DE ALERTA -->
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'success'): ?>
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center gap-3 backdrop-blur-md shadow-lg">
                        <i class="fas fa-check-circle text-lg"></i>
                        <span class="text-sm font-semibold">Datos del vehículo guardados y actualizados correctamente.</span>
                    </div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 flex items-center gap-3 backdrop-blur-md shadow-lg">
                        <i class="fas fa-exclamation-triangle text-lg"></i>
                        <span class="text-sm font-semibold">Ocurrió un problema al procesar la información del vehículo.</span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- TÍTULO Y BOTÓN DE ACCIÓN -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Control de Flota Móvil</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Gestión de unidades de transporte, capacidad de pasajeros y estado operativo del parque automotor.</p>
                </div>
                <button onclick="abrirModalCrear()" class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-gradient-to-r from-sky-500 to-blue-600 hover:opacity-95 text-slate-950 font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-sky-500/10 transition-all self-start sm:self-auto cursor-pointer">
                    <i class="fas fa-plus text-sm"></i> Agregar Vehículo
                </button>
            </div>

            <!-- TABLA DE VEHÍCULOS -->
            <div class="bg-white dark:bg-[#121826] rounded-2xl border border-slate-200 dark:border-white/10 shadow-xl overflow-hidden backdrop-blur-sm transition-colors duration-300">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/10 transition-colors">
                            <tr>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Placa</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Línea / Modelo</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Capacidad</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Estado Operativo</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Gestión</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/10 text-slate-700 dark:text-slate-200">
                            <?php if($resultado && $resultado->num_rows > 0): ?>
                                <?php while($v = $resultado->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-all group">
                                    
                                    <td class="px-6 py-4 text-xs font-mono text-slate-400 dark:text-white/30">#<?php echo $v['id_veh']; ?></td>
                                    
                                    <td class="px-6 py-4">
                                        <span class="inline-block bg-slate-100 dark:bg-white/5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold text-sky-600 dark:text-sky-400 border border-slate-200 dark:border-white/10 shadow-inner tracking-wider">
                                            <i class="fas fa-bus mr-1 text-[10px] text-slate-400 dark:text-white/20"></i><?php echo htmlspecialchars($v['pla_veh']); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-sm font-medium text-slate-800 dark:text-slate-200 group-hover:text-sky-500 dark:group-hover:text-white transition-colors">
                                        <?php echo htmlspecialchars($v['mode_veh']); ?>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-sm text-center font-mono text-slate-600 dark:text-slate-300">
                                        <span class="bg-slate-100 dark:bg-white/[0.02] border border-slate-200 dark:border-white/10 px-2.5 py-1 rounded-md">
                                            <?php echo $v['cap_veh']; ?> <span class="text-[10px] font-sans text-slate-400">puestos</span>
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-center">
                                        <?php if($v['est_veh'] == 1): ?>
                                            <span class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 rounded-full text-[10px] font-extrabold uppercase tracking-wider">Disponible</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 rounded-full text-[10px] font-extrabold uppercase tracking-wider">Fuera de Servicio</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center items-center gap-1.5">
                                            <button type="button" 
                                                    onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($v)); ?>)"
                                                    class="w-8 h-8 bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 rounded-xl flex items-center justify-center hover:bg-amber-500 hover:text-white transition-all shadow-md cursor-pointer" 
                                                    title="Editar Propiedades">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            
                                            <a href="cambiar_estado_veh.php?id=<?php echo $v['id_veh']; ?>&estado=<?php echo $v['est_veh'] == 1 ? 0 : 1; ?>" 
                                               class="w-8 h-8 flex items-center justify-center rounded-xl border transition-all shadow-md <?php echo $v['est_veh'] == 1 ? 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20 hover:bg-red-500 hover:text-white' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 hover:bg-emerald-500 hover:text-white'; ?>" 
                                               title="<?php echo $v['est_veh'] == 1 ? 'Retirar de disponibilidad' : 'Habilitar para operaciones'; ?>">
                                                <i class="fas <?php echo $v['est_veh'] == 1 ? 'fa-toggle-on' : 'fa-toggle-off'; ?> text-sm"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 text-xs">
                                        <i class="fas fa-bus text-2xl mb-3 block text-slate-300 dark:text-white/10"></i>
                                        No hay vehículos registrados en la base de datos de SGET.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- FOOTER -->
        <footer class="p-6 text-center text-slate-500 dark:text-slate-400 text-xs font-semibold border-t border-slate-200 dark:border-white/10 bg-white/50 dark:bg-[#0b0f19]/50">
            <p>&copy; <?php echo date('Y'); ?> SGET - Sistema de Gestión de Transporte. Todos los derechos reservados.</p>
        </footer>
    </div>

    <!-- OVERLAY PARA EL PANEL LATERAL -->
    <div id="overlayVehiculo" onclick="cerrarModalVehiculo()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- PANEL LATERAL DESLIZANTE -->
    <aside id="drawerVehiculo" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#121826] border-l border-slate-200 dark:border-white/10 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        
        <div class="p-6 border-b border-slate-200 dark:border-white/10 flex items-center justify-between relative">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-sky-500 to-blue-600"></div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-sky-500/10 text-sky-500 rounded-xl flex items-center justify-center border border-slate-200 dark:border-white/10">
                    <i id="drawerIcono" class="fas fa-bus text-base"></i>
                </div>
                <div>
                    <h3 id="drawerTitulo" class="text-base font-extrabold text-slate-900 dark:text-white">Registrar Vehículo</h3>
                    <p id="drawerSubtitulo" class="text-[11px] text-slate-500 dark:text-slate-400">Alta de unidad en el parque automotor</p>
                </div>
            </div>
            <button onclick="cerrarModalVehiculo()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all cursor-pointer">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto space-y-5">
            <form id="formVehiculo" action="guardar_vehiculo.php" method="POST" class="space-y-4">
                
                <input type="hidden" name="id_veh" id="input_id_veh" value="">

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Placa Identificadora</label>
                    <input type="text" name="pla_veh" id="input_pla_veh" required placeholder="Ej: XYZ-123" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl outline-none focus:border-sky-500 text-slate-800 dark:text-white text-sm transition-all uppercase font-mono">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Línea / Modelo Descriptivo</label>
                    <input type="text" name="mode_veh" id="input_mode_veh" required placeholder="Ej: Chevrolet N300 2022" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl outline-none focus:border-sky-500 text-slate-800 dark:text-white text-sm transition-all">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Capacidad de Pasajeros (Puestos)</label>
                    <input type="number" name="cap_veh" id="input_cap_veh" required min="1" max="100" placeholder="Ej: 19" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl outline-none focus:border-sky-500 text-slate-800 dark:text-white text-sm transition-all font-mono">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Estado Operativo Inicial</label>
                    <select name="est_veh" id="select_est_veh" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#121826] border border-slate-300 dark:border-white/10 rounded-xl outline-none focus:border-sky-500 text-slate-800 dark:text-white text-sm transition-all">
                        <option value="1">Disponible</option>
                        <option value="0">Fuera de Servicio</option>
                    </select>
                </div>

            </form>
        </div>

        <div class="p-6 border-t border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalVehiculo()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider transition-all cursor-pointer">
                Cancelar
            </button>
            <button type="submit" form="formVehiculo" id="btnGuardarDrawer" class="flex-1 py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-slate-950 font-extrabold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-sky-500/20 hover:opacity-90 transition-all cursor-pointer">
                Guardar Vehículo
            </button>
        </div>
    </aside>

    <!-- CONTROLADORES JAVASCRIPT Y SINCRONIZADOR DE TEMA -->
    <script>
        function abrirDrawer() {
            const drawer = document.getElementById('drawerVehiculo');
            const overlay = document.getElementById('overlayVehiculo');
            
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');
            
            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
        }

        function cerrarModalVehiculo() {
            const drawer = document.getElementById('drawerVehiculo');
            const overlay = document.getElementById('overlayVehiculo');

            drawer.classList.remove('translate-x-0');
            drawer.classList.add('translate-x-full');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function abrirModalCrear() {
            document.getElementById('formVehiculo').action = 'guardar_vehiculo.php';
            document.getElementById('drawerTitulo').innerText = 'Registrar Vehículo';
            document.getElementById('drawerSubtitulo').innerText = 'Alta de unidad en el parque automotor';
            document.getElementById('drawerIcono').className = 'fas fa-bus text-base';
            document.getElementById('btnGuardarDrawer').innerText = 'Guardar Vehículo';

            document.getElementById('input_id_veh').value = '';
            document.getElementById('formVehiculo').reset();
            document.getElementById('select_est_veh').value = '1';

            abrirDrawer();
        }

        function abrirModalEditar(datos) {
            document.getElementById('formVehiculo').action = 'actualizar_vehiculo.php';
            document.getElementById('drawerTitulo').innerText = 'Editar Vehículo';
            document.getElementById('drawerSubtitulo').innerText = 'Modificar ID: #' + datos.id_veh;
            document.getElementById('drawerIcono').className = 'fas fa-edit text-base';
            document.getElementById('btnGuardarDrawer').innerText = 'Actualizar Cambios';

            document.getElementById('input_id_veh').value = datos.id_veh;
            document.getElementById('input_pla_veh').value = datos.pla_veh;
            document.getElementById('input_mode_veh').value = datos.mode_veh;
            document.getElementById('input_cap_veh').value = datos.cap_veh;
            document.getElementById('select_est_veh').value = datos.est_veh;

            abrirDrawer();
        }

        // Observador que actualiza el localStorage si el botón de header/sidebar conmuta la clase 'dark'
        const observer = new MutationObserver(() => {
            if (document.documentElement.classList.contains('dark')) {
                localStorage.setItem('theme', 'dark');
            } else {
                localStorage.setItem('theme', 'light');
            }
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>
</body>
</html>
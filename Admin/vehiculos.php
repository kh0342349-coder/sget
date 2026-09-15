<?php
// Archivo: Admin/vehiculos.php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php'; 
require_once '../helpers/AuthHelper.php';

// Verificación de seguridad (Solo Admin)[cite: 6]
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// BLOQUEO DE SEGURIDAD POR RESTRICCIONES[cite: 6]
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'vehiculos');

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// Consultamos los vehículos registrados[cite: 6]
$query = "SELECT * FROM vehiculo ORDER BY id_veh DESC";
$resultado = $conexion->query($query);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Control de Vehículos</title>

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
            
            <!-- ENCABEZADO CON BOTÓN DE AYUDA Y ACCIÓN -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/5 dark:bg-white/[0.02] p-6 rounded-3xl border border-slate-200 dark:border-white/5 backdrop-blur-md">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Control de Flota Móvil</h1>
                        
                        <!-- BOTÓN DE AYUDA DEL SISTEMA -->
                        <button type="button" onclick="abrirModalAyuda()" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer" title="Ver guía del módulo">
                            <i class="fas fa-question text-[10px]"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Gestión de unidades de transporte, capacidad de pasajeros y estado operativo.</p>
                </div>

                <button onclick="abrirModalCrear()" class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-sky-500/20 hover:opacity-90 transition-all cursor-pointer whitespace-nowrap">
                    <i class="fas fa-plus text-sm"></i> Agregar Vehículo
                </button>
            </div>

            <!-- Tabla de Vehículos[cite: 6] -->
            <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl space-y-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-white/5 text-[10px] text-slate-400 uppercase font-bold">
                                <th class="pb-3 px-3">ID</th>
                                <th class="pb-3 px-3">Placa</th>
                                <th class="pb-3 px-3">Línea / Modelo</th>
                                <th class="pb-3 px-3 text-center">Capacidad</th>
                                <th class="pb-3 px-3 text-center">Estado Operativo</th>
                                <th class="pb-3 px-3 text-center">Gestión</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            <?php if($resultado && $resultado->num_rows > 0): ?>
                                <?php while($v = $resultado->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="py-3.5 px-3 font-mono text-slate-400">#<?php echo $v['id_veh']; ?></td>
                                    <td class="py-3.5 px-3">
                                        <span class="bg-slate-100 dark:bg-white/5 px-2.5 py-1 rounded-lg font-mono font-bold text-sky-400 border border-white/10">
                                            <?php echo htmlspecialchars($v['pla_veh']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-3 font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($v['mode_veh']); ?></td>
                                    <td class="py-3.5 px-3 text-center font-mono text-slate-300"><?php echo $v['cap_veh']; ?> puestos</td>
                                    <td class="py-3.5 px-3 text-center">
                                        <?php if($v['est_veh'] == 1): ?>
                                            <span class="px-3 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-[10px] font-extrabold uppercase">Disponible</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 bg-red-500/10 text-red-400 border border-red-500/20 rounded-full text-[10px] font-extrabold uppercase">Fuera de Servicio</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-3 text-center">
                                        <div class="flex justify-center items-center gap-2">
                                            <button type="button" onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="w-7 h-7 bg-amber-500/10 text-amber-400 rounded-xl flex items-center justify-center hover:bg-amber-500 hover:text-white transition-all shadow-sm cursor-pointer" title="Editar">
                                                <i class="fas fa-edit text-[10px]"></i>
                                            </button>
                                            <a href="cambiar_estado_veh.php?id=<?php echo $v['id_veh']; ?>&estado=<?php echo $v['est_veh'] == 1 ? 0 : 1; ?>" class="w-7 h-7 flex items-center justify-center rounded-xl border transition-all shadow-sm <?php echo $v['est_veh'] == 1 ? 'bg-red-500/10 text-red-400 border-red-500/20 hover:bg-red-500 hover:text-white' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500 hover:text-white'; ?>" title="Cambiar Estado">
                                                <i class="fas <?php echo $v['est_veh'] == 1 ? 'fa-toggle-on' : 'fa-toggle-off'; ?> text-xs"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400 italic">No hay vehículos registrados en la base de datos.[cite: 6]</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Panel Lateral / Drawer[cite: 6] -->
    <div id="overlayVehículo" onclick="cerrarTodosModales()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <aside id="drawerVehículo" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#121826] border-l border-slate-200 dark:border-white/15 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between">
            <h3 id="drawerTitulo" class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-bus text-sky-400"></i> Registrar Vehículo
            </h3>
            <button onclick="cerrarModalVehículo()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div class="p-6 flex-1 overflow-y-auto space-y-4">
            <!-- Apuntamos al archivo unificado procesar_vehiculo.php -->
            <form id="formVehículo" action="procesar_vehiculo.php" method="POST" class="space-y-4">
                <input type="hidden" name="id_veh" id="input_id_veh" value="">
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase">Placa Identificadora</label>
                    <input type="text" name="pla_veh" id="input_pla_veh" required placeholder="Ej.: XYZ-123" data-i18n-placeholder-es="Ej.: XYZ-123" data-i18n-placeholder-en="e.g. XYZ-123" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white uppercase font-mono">
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase">Línea / Modelo</label>
                    <input type="text" name="mode_veh" id="input_mode_veh" required placeholder="Ej.: Chevrolet N300" data-i18n-placeholder-es="Ej.: Chevrolet N300" data-i18n-placeholder-en="e.g. Chevrolet N300" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white">
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase">Capacidad de Puestos</label>
                    <input type="number" name="cap_veh" id="input_cap_veh" required min="1" max="100" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white font-mono">
                </div>
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase">Estado Operativo</label>
                    <select name="est_veh" id="select_est_veh" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-white">
                        <option value="1">Disponible</option>
                        <option value="0">Fuera de Servicio</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="p-6 border-t border-slate-100 dark:border-white/5 flex gap-3">
            <button type="button" onclick="cerrarModalVehículo()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 text-slate-300 rounded-xl text-xs font-bold uppercase tracking-wider cursor-pointer">Cancelar</button>
            <button type="submit" form="formVehículo" id="btnGuardarDrawer" class="flex-1 py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-sky-500/20 hover:opacity-90 transition-all cursor-pointer">Guardar</button>
        </div>
    </aside>

    <!-- MODAL DE AYUDA DEL MÓDULO -->
    <div id="overlayAyuda" onclick="cerrarModalAyuda()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="modalAyuda" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-4 transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <i class="fas fa-info-circle text-sky-400"></i> Guía de Control de Flota
                </h3>
                <button onclick="cerrarModalAyuda()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"><i class="fas fa-times text-xs"></i></button>
            </div>
            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                <li class="flex items-start gap-2">
                    <i class="fas fa-plus-circle text-sky-400 mt-0.5"></i>
                    <span><b>Agregar Vehículo:</b> Despliega el panel lateral para registrar una unidad ingresando su placa, modelo, capacidad y estado.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-edit text-amber-400 mt-0.5"></i>
                    <span><b>Editar Flota:</b> Modifica los parámetros operativos de la unidad seleccionada de forma rápida.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-toggle-on text-emerald-400 mt-0.5"></i>
                    <span><b>Cambiar Estado:</b> Alterna la disponibilidad del vehículo para el despacho de viajes en el sistema.</span>
                </li>
            </ul>
            <button onclick="cerrarModalAyuda()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer mt-2">
                Entendido
            </button>
        </div>
    </div>

    <!-- SCRIPTS DE CONTROL[cite: 6] -->
    <script>
        function abrirDrawer() {
            document.getElementById('overlayVehículo').classList.remove('opacity-0', 'pointer-events-none');
            document.getElementById('overlayVehículo').classList.add('opacity-100', 'pointer-events-auto');
            document.getElementById('drawerVehículo').classList.remove('translate-x-full');
            document.getElementById('drawerVehículo').classList.add('translate-x-0');
        }

        function cerrarModalVehículo() {
            document.getElementById('drawerVehículo').classList.remove('translate-x-0');
            document.getElementById('drawerVehículo').classList.add('translate-x-full');
            document.getElementById('overlayVehículo').classList.remove('opacity-100', 'pointer-events-auto');
            document.getElementById('overlayVehículo').classList.add('opacity-0', 'pointer-events-none');
        }

        function cerrarTodosModales() {
            cerrarModalVehículo();
            cerrarModalAyuda();
        }

        function abrirModalCrear() {
            document.getElementById('drawerTitulo').innerHTML = '<i class="fas fa-bus text-sky-400"></i> Registrar Vehículo';
            document.getElementById('btnGuardarDrawer').innerText = 'Guardar Vehículo';
            document.getElementById('input_id_veh').value = '';
            document.getElementById('formVehículo').reset();
            abrirDrawer();
        }

        function abrirModalEditar(datos) {
            document.getElementById('drawerTitulo').innerHTML = '<i class="fas fa-edit text-sky-400"></i> Editar Vehículo #' + datos.id_veh;
            document.getElementById('btnGuardarDrawer').innerText = 'Actualizar Cambios';
            document.getElementById('input_id_veh').value = datos.id_veh;
            document.getElementById('input_pla_veh').value = datos.pla_veh;
            document.getElementById('input_mode_veh').value = datos.mode_veh;
            document.getElementById('input_cap_veh').value = datos.cap_veh;
            document.getElementById('select_est_veh').value = datos.est_veh;
            abrirDrawer();
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
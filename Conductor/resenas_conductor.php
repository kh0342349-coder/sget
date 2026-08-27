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
$nombreReal = $_SESSION['nombre_usuario'] ?? "Derrick Mendoza";

// 2. Obtener el ID del conductor con Prepared Statement (Seguridad)
$stmt_user = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ?");
$stmt_user->bind_param("s", $documento);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user && $result_user->num_rows > 0) {
    $user_data = $result_user->fetch_assoc();
    $id_conductor = $user_data['id_usu'];
} else {
    echo "Error: Usuario no encontrado.";
    exit();
}
$stmt_user->close();

// 3. Consulta de Reseñas segura
$sql_resenas = "SELECT c.*, u.nom_usu as pasajero, r.des_rut, r.nom_rut, v.fec_via 
                FROM calificacion c
                JOIN usuario u ON c.id_usu_rem = u.id_usu
                JOIN viaje v ON c.id_via_cal = v.id_via
                JOIN rutas r ON v.id_rut_via = r.id_rut
                WHERE c.id_usu_des = ?
                ORDER BY v.fec_via DESC";

$stmt_resenas = $conexion->prepare($sql_resenas);
$stmt_resenas->bind_param("i", $id_conductor);
$stmt_resenas->execute();
$resenas = $stmt_resenas->get_result();

// Consultas secundarias para el Drawer (+)
$rutas_select = $conexion->query("SELECT id_rut, nom_rut, val_rut FROM rutas ORDER BY nom_rut ASC");
$vehiculos_select = $conexion->query("SELECT id_veh, pla_veh FROM vehiculo WHERE est_veh = 1 OR est_veh = 'Activo' ORDER BY pla_veh ASC");
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Reseñas - SGET</title>
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
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex min-h-screen antialiased transition-colors duration-300 relative overflow-x-hidden">

    <!-- Carga Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Contenedor Principal (ml-64 para respetar sidebar fixed) -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen min-w-0">
        
       <!-- INCLUSIÓN DEL HEADER DEL CONDUCTOR -->
        <?php include 'header_conductor.php'; ?>

        <!-- Cuerpo principal -->
        <div class="p-8 space-y-6 flex-1 min-w-0">
            
            <!-- ENCABEZADO DE PÁGINA CON TITULO, AYUDA (?) Y BOTÓN (+) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#1e293b]/50 p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Feedback de Pasajeros</h1>
                        
                        <!-- 1. BOTÓN Y TARJETA FLOTANTE DE AYUDA (?) -->
                        <div class="relative group">
                            <button type="button" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer">
                                <i class="fas fa-question text-[10px]"></i>
                            </button>

                            <div class="absolute left-0 top-full mt-2 w-80 bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-2xl p-4 text-xs opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                                <p class="font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-700/60 pb-2">
                                    <i class="fas fa-info-circle text-neon-azul"></i> Guía de Calificaciones
                                </p>
                                <ul class="space-y-2 text-slate-600 dark:text-slate-300 leading-relaxed">
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-star text-amber-400 mt-0.5 shrink-0"></i>
                                        <span><b>Estrellas:</b> Puntuación máxima de 5 estrellas otorgada por los pasajeros.</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-comment text-blue-500 mt-0.5 shrink-0"></i>
                                        <span><b>Detalle:</b> Haz clic en el botón de lectura de la tarjeta para desplegar el pop-up informativo.</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-plus-circle text-emerald-500 mt-0.5 shrink-0"></i>
                                        <span><b>Programar Viaje (+):</b> Abre el formulario deslizante para registrar una nueva salida.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Lo que dicen los usuarios sobre tu servicio en la vía.</p>
                </div>

                <!-- BOTÓN PRINCIPAL ACCIÓN CON MODAL DRAWER (+) -->
                <button onclick="abrirModalSolicitar()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 hover:opacity-95 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition-all cursor-pointer whitespace-nowrap self-start sm:self-auto">
                    <i class="fas fa-plus-circle text-sm"></i> Programar Viaje
                </button>
            </div>

            <!-- CONTENEDOR GRID DE TARJETAS -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-6xl">
                <?php if ($resenas && $resenas->num_rows > 0): ?>
                    <?php while($r = $resenas->fetch_assoc()): ?>
                    <?php 
                        $jsonResena = htmlspecialchars(json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-xl hover:border-blue-500/30 transition-all duration-300 group flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h4 class="font-bold text-slate-900 dark:text-white text-base capitalize group-hover:text-blue-500 dark:group-hover:text-neon-azul transition-colors">
                                        <?php echo htmlspecialchars($r['pasajero'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h4>
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <span class="text-[9px] bg-blue-500/10 text-blue-600 dark:text-neon-azul border border-blue-500/20 font-black px-2.5 py-0.5 rounded-md uppercase tracking-wider">
                                            <?php echo htmlspecialchars($r['des_rut'] ?? $r['nom_rut'] ?? 'Ruta', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                                            <?php echo !empty($r['fec_via']) ? date("d M, Y", strtotime($r['fec_via'])) : 'Fecha N/A'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex text-amber-400 text-[10px] gap-0.5 bg-slate-100 dark:bg-[#0b0f19]/60 px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-white/5">
                                    <?php 
                                    $puntos = (int)($r['pun_cal'] ?? 5);
                                    for($i=1; $i<=5; $i++) {
                                        echo ($i <= $puntos) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-300 dark:text-slate-700"></i>';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="relative mt-2">
                                <i class="fas fa-quote-left text-slate-200 dark:text-slate-800 text-3xl absolute -top-3 -left-1 z-0 opacity-50"></i>
                                <p class="text-slate-600 dark:text-slate-300 text-xs italic leading-relaxed relative z-10 pl-5 border-l-2 border-blue-500/30 line-clamp-3">
                                    <?php echo htmlspecialchars($r['com_cal'] ?? 'Sin comentario adicional.', ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Botón Ver Ficha de la Reseña -->
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-white/5 flex justify-end">
                            <button type="button" 
                                    data-resena='<?php echo $jsonResena; ?>'
                                    onclick="verDetalleResena(this)"
                                    class="text-[11px] font-bold text-blue-600 dark:text-neon-azul hover:underline flex items-center gap-1 cursor-pointer">
                                <i class="fas fa-eye text-[10px]"></i> Leer Completa
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full py-20 bg-white dark:bg-[#1e293b] rounded-2xl text-center border border-slate-200 dark:border-white/5 shadow-xl">
                        <div class="w-16 h-16 bg-slate-100 dark:bg-[#0b0f19] text-slate-400 dark:text-slate-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl border border-slate-200 dark:border-white/5">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                        <h2 class="text-slate-700 dark:text-slate-400 font-black uppercase tracking-widest text-xs">Sin reseñas disponibles</h2>
                        <p class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase mt-1">Tu buen trabajo se reflejará aquí pronto.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- OVERLAY GENERAL PARA MODALES -->
    <div id="overlayResenas" onclick="cerrarTodosModales()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- 2. MODAL POP-UP DE LECTURA DE RESEÑA -->
    <div id="modalDetalleResena" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#1e293b] w-full max-w-sm rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300" id="modalResenaBox">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xs">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3 id="detPasajero" class="font-extrabold text-slate-900 dark:text-white text-base capitalize"></h3>
                </div>
                <button onclick="cerrarModalResena()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            
            <div class="space-y-3.5 text-xs">
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="fas fa-route text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Ruta & Fecha</p>
                        <p id="detRutaFecha" class="font-medium text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5 space-y-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Comentario del Pasajero</p>
                    <p id="detComentario" class="italic text-slate-700 dark:text-slate-200 text-xs leading-relaxed"></p>
                </div>
            </div>

            <button onclick="cerrarModalResena()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer">
                Cerrar Ventana
            </button>
        </div>
    </div>

    <!-- 3. PANEL LATERAL DESLIZANTE (DRAWER (+)) DE PROGRAMACIÓN -->
    <aside id="drawerProgramarResenas" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
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
            <form id="formProgramarResenas" action="guardar_viaje.php" method="POST" class="space-y-4">
                <input type="hidden" name="id_usu_via" value="<?php echo $id_conductor ?? ''; ?>">

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
                        <input type="date" name="fec_via" id="input_fec_resena" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Hora Salida</label>
                        <input type="time" name="hor_sal_via" id="input_hor_resena" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                    </div>
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalDrawer()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider transition-all cursor-pointer">
                Cancelar
            </button>
            <button type="submit" form="formProgramarResenas" class="flex-1 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-500/20 hover:opacity-95 transition-all cursor-pointer">
                Iniciar Despacho
            </button>
        </div>
    </aside>

    <!-- CONTROLADORES JAVASCRIPT -->
    <script>
        function abrirModalSolicitar() {
            const drawer = document.getElementById('drawerProgramarResenas');
            const overlay = document.getElementById('overlayResenas');

            const hoy = new Date();
            const fechaHoy = hoy.toISOString().split('T')[0];
            const horaHoy = hoy.toTimeString().split(' ')[0].substring(0, 5);

            document.getElementById('input_fec_resena').value = fechaHoy;
            document.getElementById('input_fec_resena').min = fechaHoy;
            document.getElementById('input_hor_resena').value = horaHoy;

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
        }

        function cerrarModalDrawer() {
            const drawer = document.getElementById('drawerProgramarResenas');
            const overlay = document.getElementById('overlayResenas');

            drawer.classList.remove('translate-x-0');
            drawer.classList.add('translate-x-full');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function verDetalleResena(btn) {
            const r = JSON.parse(btn.getAttribute('data-resena'));
            document.getElementById('detPasajero').innerText = r.pasajero;
            document.getElementById('detRutaFecha').innerText = (r.des_rut || r.nom_rut || 'Ruta') + ' — ' + (r.fec_via || '');
            document.getElementById('detComentario').innerText = r.com_cal || 'Sin comentario adicional.';

            const overlay = document.getElementById('overlayResenas');
            const modal = document.getElementById('modalDetalleResena');
            const box = document.getElementById('modalResenaBox');

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalResena() {
            const overlay = document.getElementById('overlayResenas');
            const modal = document.getElementById('modalDetalleResena');
            const box = document.getElementById('modalResenaBox');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function cerrarTodosModales() {
            cerrarModalDrawer();
            cerrarModalResena();
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
<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Pasajero";

// CÓDIGO OPTIMIZADO: 
// 1. Filtramos por est_via = 'Activo'
// 2. Calculamos los cupos en tiempo real: (capacidad del vehículo - reservas hechas)
$sql = "SELECT v.*, r.nom_rut, u.nom_usu, ve.cap_veh,
               (SELECT COUNT(*) FROM reserva WHERE id_via_res = v.id_via) as ocupados
        FROM viaje v
        LEFT JOIN rutas r ON v.id_rut_via = r.id_rut
        LEFT JOIN usuario u ON v.id_usu_via = u.id_usu
        LEFT JOIN vehiculo ve ON v.id_veh = ve.id_veh
        WHERE v.est_via = 'Activo'
        HAVING ocupados < ve.cap_veh
        ORDER BY v.fec_via ASC, v.hor_sal_via ASC";

$res = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viajes Disponibles - SGET</title>
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
                        'neon-morado': '#a855f7'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
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
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex min-h-screen antialiased transition-colors duration-300">

    <!-- INCLUSIÓN DIRECTA DEL SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- MAIN CON MARGEN IZQUIERDO (ml-64) PARA ALINEARSE AL SIDEBAR FIJO -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- HEADER ESTANDARIZADO MODULAR -->
        <?php include 'header.php'; ?>

        <!-- CONTENIDO DE VIAJES DISPONIBLES -->
        <div class="p-8 space-y-8 flex-1">
            
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Reserva tu cupo</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Selecciona una de nuestras rutas activas para iniciar tu viaje.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-7xl">
                <?php if ($res && $res->num_rows > 0): ?>
                    <?php while($v = $res->fetch_assoc()): 
                        $cupos_totales = $v['cap_veh'] ?? 0;
                        $ocupados = $v['ocupados'] ?? 0;
                        $disponibles = $cupos_totales - $ocupados;
                    ?>
                        <div class="bg-white dark:bg-[#1e293b] rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm hover:shadow-md hover:border-slate-300 dark:hover:border-white/10 transition-all duration-300 p-6 group relative overflow-hidden flex flex-col justify-between">
                            
                            <div>
                                <div class="flex justify-between items-start mb-5">
                                    <div class="w-12 h-12 bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 rounded-xl flex items-center justify-center text-xl group-hover:bg-blue-600 group-hover:text-white transition-all duration-300 shadow-sm">
                                        <i class="fas fa-route"></i>
                                    </div>
                                    <div class="flex flex-col items-end gap-1.5">
                                        <span class="text-[9px] font-black bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 px-2.5 py-0.5 rounded-md uppercase tracking-wider">
                                            Activo
                                        </span>
                                        <span class="text-[10px] font-black <?php echo ($disponibles <= 2) ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'; ?> px-2.5 py-0.5 rounded-md uppercase tracking-tight">
                                            <?php echo $disponibles; ?> Cupos libres
                                        </span>
                                    </div>
                                </div>
                                
                                <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1 leading-tight capitalize"><?php echo htmlspecialchars($v['nom_rut'] ?? 'Ruta No Definida', ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="text-slate-500 dark:text-slate-400 text-[10px] mb-5 uppercase tracking-wider font-bold flex items-center">
                                    <i class="fas fa-user-tie mr-2 text-slate-400 dark:text-slate-500 text-xs"></i> <?php echo htmlspecialchars($v['nom_usu'] ?? 'Sin conductor', ENT_QUOTES, 'UTF-8'); ?>
                                </p>

                                <div class="space-y-2 mb-6">
                                    <div class="flex items-center text-slate-700 dark:text-slate-300 text-xs font-semibold bg-slate-100 dark:bg-[#161e2e] p-2.5 rounded-xl border border-slate-200 dark:border-white/5 font-mono">
                                        <i class="fas fa-calendar-alt mr-3 text-blue-600 dark:text-blue-400 text-sm"></i>
                                        <?php echo htmlspecialchars($v['fec_via'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div class="flex items-center text-slate-700 dark:text-slate-300 text-xs font-semibold bg-slate-100 dark:bg-[#161e2e] p-2.5 rounded-xl border border-slate-200 dark:border-white/5 font-mono">
                                        <i class="fas fa-clock mr-3 text-blue-600 dark:text-blue-400 text-sm"></i>
                                        <?php echo date("h:i A", strtotime($v['hor_sal_via'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-white/5 mt-auto">
                                <div class="flex flex-col">
                                    <span class="text-slate-400 dark:text-slate-500 text-[9px] font-black uppercase tracking-widest">Pasaje</span>
                                    <span class="text-emerald-600 dark:text-emerald-400 font-black text-xl font-mono">$<?php echo number_format($v['val_via'], 0, ',', '.'); ?></span>
                                </div>
                                <button type="button" onclick="abrirPanelReserva(<?php echo $v['id_via']; ?>, '<?php echo htmlspecialchars($v['nom_rut'], ENT_QUOTES); ?>', <?php echo $v['val_via']; ?>, <?php echo $disponibles; ?>, '<?php echo $v['fec_via']; ?>', '<?php echo date("h:i A", strtotime($v['hor_sal_via'])); ?>')"
                                   class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl text-[10px] font-black tracking-widest uppercase transition-all duration-200 shadow-md shadow-blue-600/10">
                                    RESERVAR
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full bg-white dark:bg-[#1e293b] p-20 rounded-2xl text-center border border-slate-200 dark:border-white/5 shadow-xl">
                        <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl border border-slate-200 dark:border-white/5">
                            <i class="fas fa-bus-alt"></i>
                        </div>
                        <h2 class="text-slate-600 dark:text-slate-400 font-black uppercase tracking-widest text-xs">No hay viajes disponibles</h2>
                        <p class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase mt-1">Vuelve a consultar más tarde para revisar nuevas rutas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- PANEL DESLIZANTE DERECHO PARA RESERVA Y PAGO -->
    <div id="panel-reserva" class="fixed inset-0 z-50 overflow-hidden hidden">
        <!-- Fondo oscuro difuminado -->
        <div id="panel-backdrop" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" onclick="cerrarPanelReserva()"></div>

        <div class="absolute inset-y-0 right-0 max-w-full flex pl-10">
            <div id="panel-contenido" class="w-screen max-w-md bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
                
                <!-- Encabezado del Panel -->
                <div class="p-6 border-b border-slate-200 dark:border-white/5 flex items-center justify-between">
                    <div>
                        <span class="text-[9px] font-black bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 px-2.5 py-1 rounded-md uppercase tracking-wider">Confirmación</span>
                        <h2 class="text-xl font-black text-slate-900 dark:text-white mt-1">Reserva y Pago</h2>
                    </div>
                    <button onclick="cerrarPanelReserva()" class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center hover:bg-rose-500 hover:text-white transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Cuerpo del Formulario -->
                <form action="procesar_reserva.php" method="POST" class="flex-1 overflow-y-auto p-6 space-y-6 flex flex-col justify-between">
                    <input type="hidden" name="id_via" id="modal_id_via">

                    <div class="space-y-6">
                        <!-- Info del Viaje -->
                        <div class="p-4 bg-slate-100 dark:bg-[#161e2e] rounded-2xl border border-slate-200 dark:border-white/5 space-y-3">
                            <div>
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">Ruta</span>
                                <h3 id="modal_nom_rut" class="text-base font-extrabold text-slate-900 dark:text-white capitalize"></h3>
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200 dark:border-white/5 text-xs font-mono">
                                <div>
                                    <span class="text-[9px] text-slate-400 uppercase block font-sans font-bold">Fecha:</span>
                                    <span id="modal_fec_via" class="text-slate-700 dark:text-slate-300"></span>
                                </div>
                                <div>
                                    <span class="text-[9px] text-slate-400 uppercase block font-sans font-bold">Hora:</span>
                                    <span id="modal_hor_sal" class="text-slate-700 dark:text-slate-300"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Puestos -->
                        <div>
                            <label for="modal_puestos" class="block text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                                ¿Cuántos puestos necesitas?
                            </label>
                            <div class="flex items-center gap-3">
                                <input type="number" id="modal_puestos" name="puestos" min="1" value="1" required
                                       class="w-full bg-slate-100 dark:bg-[#161e2e] border border-slate-300 dark:border-white/10 rounded-xl px-4 py-3 text-slate-900 dark:text-white font-mono text-base font-bold focus:outline-none focus:border-blue-500">
                                <span id="modal_max_disponibles" class="text-[10px] font-bold text-slate-500 whitespace-nowrap"></span>
                            </div>
                        </div>

                        <!-- Resumen del Pago -->
                        <div class="p-5 bg-blue-500/5 border border-blue-500/10 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Total a Cancelar:</span>
                                <span class="text-[9px] text-slate-400 uppercase">Pago directo al abordar</span>
                            </div>
                            <div class="text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400" id="modal_total_pagar">
                                $0
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="space-y-3 pt-4 border-t border-slate-200 dark:border-white/5">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-xl text-xs font-black tracking-widest uppercase transition-all duration-200 shadow-lg shadow-blue-600/20">
                            Confirmar y Pagar
                        </button>
                        <button type="button" onclick="cerrarPanelReserva()" class="w-full py-3 rounded-xl text-xs font-black uppercase tracking-widest bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300 transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPT DE TEMA Y PANEL LATERAL -->
    <script>
        let precioUnitarioGlobal = 0;
        let maxDisponiblesGlobal = 0;

        function abrirPanelReserva(idVia, nomRut, valVia, disponibles, fecVia, horSal) {
            precioUnitarioGlobal = valVia;
            maxDisponiblesGlobal = disponibles;

            document.getElementById('modal_id_via').value = idVia;
            document.getElementById('modal_nom_rut').textContent = nomRut;
            document.getElementById('modal_fec_via').textContent = fecVia;
            document.getElementById('modal_hor_sal').textContent = horSal;
            
            const inputPuestos = document.getElementById('modal_puestos');
            inputPuestos.value = 1;
            inputPuestos.max = disponibles;
            document.getElementById('modal_max_disponibles').textContent = `Máx: ${disponibles} libres`;

            calcularTotalModal();

            const panel = document.getElementById('panel-reserva');
            const backdrop = document.getElementById('panel-backdrop');
            const contenido = document.getElementById('panel-contenido');

            panel.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                contenido.classList.remove('translate-x-full');
            }, 10);
        }

        function cerrarPanelReserva() {
            const panel = document.getElementById('panel-reserva');
            const backdrop = document.getElementById('panel-backdrop');
            const contenido = document.getElementById('panel-contenido');

            backdrop.classList.add('opacity-0');
            contenido.classList.add('translate-x-full');

            setTimeout(() => {
                panel.classList.add('hidden');
            }, 300);
        }

        function calcularTotalModal() {
            const inputPuestos = document.getElementById('modal_puestos');
            let cantidad = parseInt(inputPuestos.value) || 1;

            if (cantidad > maxDisponiblesGlobal) {
                cantidad = maxDisponiblesGlobal;
                inputPuestos.value = maxDisponiblesGlobal;
            }
            if (cantidad < 1) {
                cantidad = 1;
                inputPuestos.value = 1;
            }

            let total = cantidad * precioUnitarioGlobal;
            document.getElementById('modal_total_pagar').textContent = '$' + total.toLocaleString('es-CO');
        }

        document.getElementById('modal_puestos').addEventListener('input', calcularTotalModal);

        // Script de Sincronización de Tema
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
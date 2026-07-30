<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

// Verificación de seguridad (Solo Admin)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Consultar rutas registradas
$sql_rutas = "SELECT * FROM rutas ORDER BY id_rut DESC";
$resultado_rutas = mysqli_query($conexion, $sql_rutas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Rutas de Transporte</title>
    
    <!-- Script Anti-Parpadeo Sincronizado (Soporta múltiples llaves de Storage) -->
    <script>
        (function() {
            const tema = localStorage.getItem('theme') || localStorage.getItem('color-theme');
            const prefiereOscuro = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (tema === 'dark' || (!tema && prefiereOscuro)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
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
                        'texto-base': { DEFAULT: '#334155', dark: '#cbd5e1' },
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7',
                        'color-mutado': '#94a3b8'
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
        
        <!-- BARRA SUPERIOR -->
        <?php include 'header.php'; ?>

        <!-- ÁREA DE TRABAJO -->
        <main class="p-8 flex-1">
            
            <!-- ENCABEZADO Y BOTÓN DE ACCIÓN -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Gestión de Rutas</h1>
                    <p class="text-xs text-slate-500 dark:text-color-mutado mt-1">Administre los trayectos, origen, destino y tarifas base del sistema.</p>
                </div>
                <button onclick="abrirModalRuta()" class="px-5 py-2.5 bg-gradient-to-r from-neon-azul to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-neon-azul/20 hover:opacity-95 transition-all flex items-center gap-2">
                    <i class="fas fa-plus text-sm"></i>
                    <span>Nueva Ruta</span>
                </button>
            </div>

            <!-- TABLA DE RUTAS -->
            <div class="bg-white dark:bg-[#1e293b] rounded-2xl border border-slate-200 dark:border-white/5 shadow-xl overflow-hidden backdrop-blur-sm transition-colors duration-300">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.02] text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-color-mutado">
                                <th class="p-4 pl-6">ID</th>
                                <th class="p-4">Ruta</th>
                                <th class="p-4">Origen</th>
                                <th class="p-4">Destino</th>
                                <th class="p-4">Distancia (km)</th>
                                <th class="p-4">Valor Base</th>
                                <th class="p-4 text-center pr-6">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                            <?php if ($resultado_rutas && mysqli_num_rows($resultado_rutas) > 0): ?>
                                <?php while ($ruta = mysqli_fetch_assoc($resultado_rutas)): ?>
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="p-4 pl-6 font-mono text-xs text-slate-400">#<?php echo $ruta['id_rut']; ?></td>
                                        <td class="p-4 font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($ruta['nom_rut']); ?></td>
                                        <td class="p-4"><span class="inline-flex items-center gap-1.5"><i class="fas fa-map-marker-alt text-xs text-red-500/70"></i><?php echo htmlspecialchars($ruta['ori_rut']); ?></span></td>
                                        <td class="p-4"><span class="inline-flex items-center gap-1.5"><i class="fas fa-flag-checkered text-xs text-emerald-500/70"></i><?php echo htmlspecialchars($ruta['des_rut']); ?></span></td>
                                        <td class="p-4 font-mono text-xs"><?php echo number_format($ruta['dis_rut'], 2); ?> km</td>
                                        <td class="p-4 font-bold text-blue-600 dark:text-neon-azul font-mono">$<?php echo number_format($ruta['val_rut'], 0, ',', '.'); ?></td>
                                        <td class="p-4 pr-6 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-slate-300 flex items-center justify-center transition-all" title="Editar">
                                                    <i class="fas fa-pen text-xs"></i>
                                                </button>
                                                <button class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 text-red-600 dark:text-red-400 flex items-center justify-center transition-all" title="Eliminar">
                                                    <i class="fas fa-trash-alt text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-slate-400 dark:text-color-mutado text-xs">
                                        No hay rutas registradas actualmente.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- FOOTER -->
        <footer class="p-6 text-center text-slate-400 dark:text-color-mutado text-xs font-semibold border-t border-slate-200 dark:border-white/5 bg-slate-50/20 dark:bg-transparent">
            &copy; <?php echo date('Y'); ?> Sistema de Gestión de Transporte SGET. Todos los derechos reservados.
        </footer>
    </div>

    <!-- OVERLAY -->
    <div id="overlayRuta" onclick="cerrarModalRuta()" class="fixed inset-0 bg-slate-900/60 dark:bg-black/70 backdrop-blur-sm z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- PANEL LATERAL -->
    <aside id="drawerRuta" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between relative">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-neon-azul to-blue-600"></div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-neon-azul/10 text-neon-azul rounded-xl flex items-center justify-center border border-slate-100 dark:border-white/5">
                    <i class="fas fa-road text-base"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Registrar Nueva Ruta</h3>
                    <p class="text-[11px] text-slate-500 dark:text-color-mutado">Logística de trayecto y costos</p>
                </div>
            </div>
            <button onclick="cerrarModalRuta()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto space-y-5">
            <form id="formNuevaRuta" action="guardar_ruta.php" method="POST" class="space-y-5">
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Nombre de la Ruta</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 dark:text-white/20 text-xs">
                            <i class="fas fa-font"></i>
                        </span>
                        <input type="text" name="nom_rut" required placeholder="Ej: Ruta Fusagasugá - Bogotá"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all placeholder:text-slate-400 dark:placeholder:text-white/20">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Punto de Origen</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-red-500/60 text-xs">
                            <i class="fas fa-map-marker-alt"></i>
                        </span>
                        <input type="text" name="ori_rut" required placeholder="Ciudad o terminal de salida"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all placeholder:text-slate-400 dark:placeholder:text-white/20">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Punto de Destino</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-emerald-500/60 text-xs">
                            <i class="fas fa-flag-checkered"></i>
                        </span>
                        <input type="text" name="des_rut" required placeholder="Ciudad o terminal de llegada"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all placeholder:text-slate-400 dark:placeholder:text-white/20">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Distancia (km)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-purple-500/60 dark:text-neon-morado/60 text-xs">
                                <i class="fas fa-tachometer-alt"></i>
                            </span>
                            <input type="number" step="0.01" name="dis_rut" required placeholder="0.00"
                                   class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all placeholder:text-slate-400 dark:placeholder:text-white/20 font-mono">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-blue-600 dark:text-neon-azul uppercase tracking-wider">Precio Sugerido</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-blue-600 dark:text-neon-azul font-bold text-sm">$</span>
                            <input type="number" name="val_rut" required placeholder="0"
                                   class="w-full pl-8 pr-4 py-2.5 bg-blue-50/50 dark:bg-neon-azul/5 border border-blue-200 dark:border-neon-azul/20 rounded-xl outline-none focus:border-neon-azul text-blue-600 dark:text-neon-azul text-sm transition-all font-bold tracking-wide placeholder:text-blue-600/30 dark:placeholder:text-neon-azul/30">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalRuta()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-color-mutado hover:text-slate-900 dark:hover:text-white rounded-xl font-bold text-xs uppercase tracking-wider transition-all">
                Cancelar
            </button>
            <button type="submit" form="formNuevaRuta" class="flex-1 py-3 bg-gradient-to-r from-neon-azul to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-neon-azul/20 hover:opacity-95 transition-all">
                Guardar Ruta
            </button>
        </div>
    </aside>

    <!-- JS CONTROLADOR -->
    <script>
        function abrirModalRuta() {
            const drawer = document.getElementById('drawerRuta');
            const overlay = document.getElementById('overlayRuta');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');
            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
        }

        function cerrarModalRuta() {
            const drawer = document.getElementById('drawerRuta');
            const overlay = document.getElementById('overlayRuta');
            drawer.classList.remove('translate-x-0');
            drawer.classList.add('translate-x-full');
            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const themeToggleBtn = document.getElementById('theme-toggle');
            const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
            const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

            function obtenerTemaActual() {
                const temaGuardado = localStorage.getItem('theme') || localStorage.getItem('color-theme');
                if (temaGuardado) return temaGuardado;
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            function aplicarTema(esOscuro) {
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

            function guardarYNotificar(modo) {
                // Guarda en ambos nombres de llave para compatibilidad total con otras vistas
                localStorage.setItem('theme', modo);
                localStorage.setItem('color-theme', modo);
                
                aplicarTema(modo === 'dark');
                
                // Dispara evento personalizado para componentes incluidos como header/sidebar
                window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: modo } }));
            }

            // Aplicar el estado inicial al cargar el DOM
            aplicarTema(obtenerTemaActual() === 'dark');

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    const esActualmenteOscuro = document.documentElement.classList.contains('dark');
                    const nuevoTema = esActualmenteOscuro ? 'light' : 'dark';
                    guardarYNotificar(nuevoTema);
                });
            }

            // Escuchar cambios en otras pestañas o componentes
            window.addEventListener('storage', function(e) {
                if (e.key === 'theme' || e.key === 'color-theme') {
                    aplicarTema(e.newValue === 'dark');
                }
            });

            window.addEventListener('themeChanged', function(e) {
                aplicarTema(e.detail.theme === 'dark');
            });
        });
    </script>
</body>
</html>
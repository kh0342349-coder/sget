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
    
    <!-- Script Anti-Parpadeo Sincronizado -->
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
<body class="bg-slate-50 dark:bg-[#0b0f19] flex min-h-screen antialiased text-slate-800 dark:text-slate-100 transition-colors duration-300 relative overflow-x-hidden">

    <!-- BARRA LATERAL -->
    <?php include 'sidebar.php'; ?>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen min-w-0">
        
        <!-- BARRA SUPERIOR -->
        <?php include 'header.php'; ?>

        <!-- ÁREA DE TRABAJO -->
        <main class="p-8 flex-1 min-w-0">
            
            <!-- ENCABEZADO CON BOTÓN DE AYUDA Y BOTÓN DE NUEVA RUTA -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Gestión de Rutas</h1>
                        
                        <!-- BOTÓN CON TARJETA DE AYUDA FLOTANTE -->
                        <div class="relative group">
                            <button type="button" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer">
                                <i class="fas fa-question text-[10px]"></i>
                            </button>

                            <!-- 1. MODAL GUÍA GENERAL -->
                            <div class="absolute left-0 top-full mt-2 w-80 bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-2xl p-4 text-xs opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                                <p class="font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-700/60 pb-2">
                                    <i class="fas fa-info-circle text-neon-azul"></i> Modulo de Rutas
                                </p>
                                <ul class="space-y-2 text-slate-600 dark:text-slate-300 leading-relaxed">
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-plus-circle text-emerald-500 mt-0.5 shrink-0"></i>
                                        <span><b>Crear Ruta:</b> Permite definir el origen, destino, kilometraje, tarifa sugerida e imagen de despacho.</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-image text-neon-azul mt-0.5 shrink-0"></i>
                                        <span><b>Foto Despacho:</b> Haz clic sobre la miniatura para ver el mapa o lugar de salida ampliado.</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-edit text-purple-500 mt-0.5 shrink-0"></i>
                                        <span><b>Editar/Eliminar:</b> Modifica tarifas o la foto mediante los botones de acción.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-color-mutado mt-1">Administre los trayectos, origen, destino, tarifas base y fotos de despacho.</p>
                </div>

                <button onclick="abrirModalRuta()" class="px-5 py-2.5 bg-gradient-to-r from-neon-azul to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-neon-azul/20 hover:opacity-95 transition-all flex items-center gap-2">
                    <i class="fas fa-plus text-sm"></i>
                    <span>Nueva Ruta</span>
                </button>
            </div>

            <!-- TABLA DE RUTAS CON DESPLAZAMIENTO HORIZONTAL -->
            <div class="bg-white dark:bg-[#1e293b] rounded-2xl border border-slate-200 dark:border-white/5 shadow-xl overflow-hidden backdrop-blur-sm transition-colors duration-300">
                <div class="overflow-x-auto custom-scrollbar w-full">
                    <table class="w-full text-left border-collapse text-sm min-w-[850px]">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.02] text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-color-mutado">
                                <th class="p-4 pl-6">ID</th>
                                <th class="p-4">Imagen Despacho</th>
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
                                        <td class="p-4">
                                            <?php if (!empty($ruta['img_rut']) && file_exists("../img/rutas/" . $ruta['img_rut'])): ?>
                                                <button type="button" onclick="verDetallesRuta(<?php echo htmlspecialchars(json_encode($ruta)); ?>)" class="group/img relative">
                                                    <img src="../img/rutas/<?php echo htmlspecialchars($ruta['img_rut']); ?>" alt="Despacho" class="w-12 h-12 object-cover rounded-xl border border-slate-200 dark:border-white/10 shadow-sm group-hover/img:scale-105 transition-transform cursor-pointer">
                                                    <span class="absolute inset-0 bg-black/40 rounded-xl opacity-0 group-hover/img:opacity-100 transition-opacity flex items-center justify-center text-white text-[10px]"><i class="fas fa-search-plus"></i></span>
                                                </button>
                                            <?php else: ?>
                                                <div class="w-12 h-12 bg-slate-100 dark:bg-white/5 rounded-xl border border-dashed border-slate-300 dark:border-white/10 flex items-center justify-center text-slate-400 text-xs">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($ruta['nom_rut']); ?></td>
                                        <td class="p-4"><span class="inline-flex items-center gap-1.5"><i class="fas fa-map-marker-alt text-xs text-red-500/70"></i><?php echo htmlspecialchars($ruta['ori_rut']); ?></span></td>
                                        <td class="p-4"><span class="inline-flex items-center gap-1.5"><i class="fas fa-flag-checkered text-xs text-emerald-500/70"></i><?php echo htmlspecialchars($ruta['des_rut']); ?></span></td>
                                        <td class="p-4 font-mono text-xs"><?php echo number_format($ruta['dis_rut'], 2); ?> km</td>
                                        <td class="p-4 font-bold text-blue-600 dark:text-neon-azul font-mono">$<?php echo number_format($ruta['val_rut'], 0, ',', '.'); ?></td>
                                        <td class="p-4 pr-6 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button onclick="verDetallesRuta(<?php echo htmlspecialchars(json_encode($ruta)); ?>)" class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-500/10 hover:bg-blue-100 dark:hover:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center transition-all" title="Ver Detalles/Imagen">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </button>
                                                <button onclick="editarRuta(<?php echo htmlspecialchars(json_encode($ruta)); ?>)" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-slate-300 flex items-center justify-center transition-all" title="Editar">
                                                    <i class="fas fa-pen text-xs"></i>
                                                </button>
                                                <a href="eliminar_ruta.php?id=<?php echo $ruta['id_rut']; ?>" onclick="return confirm('¿Está seguro de eliminar esta ruta?')" class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 text-red-600 dark:text-red-400 flex items-center justify-center transition-all" title="Eliminar">
                                                    <i class="fas fa-trash-alt text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="p-8 text-center text-slate-400 dark:text-color-mutado text-xs">
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

    <!-- OVERLAY GENERAL -->
    <div id="overlayRuta" onclick="cerrarTodosLosModales()" class="fixed inset-0 bg-slate-900/60 dark:bg-black/70 backdrop-blur-sm z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- 2. MODAL DE PREVISUALIZACIÓN DE IMAGEN Y DETALLES -->
    <div id="modalVerDetalles" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/10 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform duration-300" id="cajaDetalles">
            <div class="p-5 border-b border-slate-100 dark:border-white/5 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-neon-azul/10 text-neon-azul flex items-center justify-center text-xs font-bold"><i class="fas fa-route"></i></span>
                    <h3 id="detallesTitulo" class="font-bold text-slate-900 dark:text-white text-sm">Detalles de la Ruta</h3>
                </div>
                <button onclick="cerrarModalDetalles()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg font-bold">&times;</button>
            </div>
            
            <div class="p-6 space-y-4">
                <div id="contenedorImagenAmpliada" class="w-full h-56 bg-slate-100 dark:bg-black/20 rounded-xl overflow-hidden border border-slate-200 dark:border-white/10 flex items-center justify-center">
                    <img id="imgDetalleAmpliada" src="" alt="Despacho Ampliado" class="w-full h-full object-cover">
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="p-3 bg-slate-50 dark:bg-black/20 rounded-xl border border-slate-100 dark:border-white/5">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Punto de Origen</span>
                        <span id="detOrigen" class="font-bold text-slate-800 dark:text-white"></span>
                    </div>
                    <div class="p-3 bg-slate-50 dark:bg-black/20 rounded-xl border border-slate-100 dark:border-white/5">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Punto de Destino</span>
                        <span id="detDestino" class="font-bold text-slate-800 dark:text-white"></span>
                    </div>
                    <div class="p-3 bg-slate-50 dark:bg-black/20 rounded-xl border border-slate-100 dark:border-white/5">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Distancia</span>
                        <span id="detDistancia" class="font-bold text-purple-500 font-mono"></span>
                    </div>
                    <div class="p-3 bg-slate-50 dark:bg-black/20 rounded-xl border border-slate-100 dark:border-white/5">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Tarifa Sugerida</span>
                        <span id="detPrecio" class="font-bold text-neon-azul font-mono"></span>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex justify-end">
                <button type="button" onclick="cerrarModalDetalles()" class="px-4 py-2 bg-slate-200 dark:bg-white/10 text-slate-700 dark:text-slate-200 rounded-xl font-bold text-xs">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- 3. PANEL LATERAL (DRAWER CREACIÓN/EDICIÓN) -->
    <aside id="drawerRuta" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between relative">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-neon-azul to-blue-600"></div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-neon-azul/10 text-neon-azul rounded-xl flex items-center justify-center border border-slate-100 dark:border-white/5">
                    <i class="fas fa-road text-base"></i>
                </div>
                <div>
                    <h3 id="tituloModal" class="text-base font-extrabold text-slate-900 dark:text-white">Registrar Nueva Ruta</h3>
                    <p class="text-[11px] text-slate-500 dark:text-color-mutado">Logística de trayecto, costos e imagen</p>
                </div>
            </div>
            <button onclick="cerrarModalRuta()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto space-y-5">
            <form id="formNuevaRuta" action="procesar_guardado.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                
                <!-- ID Oculto para Edición -->
                <input type="hidden" name="id_rut" id="id_rut" value="">
                <!-- Flag Oculto para Eliminar Imagen -->
                <input type="hidden" name="eliminar_imagen" id="eliminar_imagen" value="0">

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Nombre de la Ruta</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 dark:text-white/20 text-xs">
                            <i class="fas fa-font"></i>
                        </span>
                        <input type="text" name="nom_rut" id="nom_rut" required placeholder="Ej: Ruta Fusagasugá - Bogotá"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all placeholder:text-slate-400 dark:placeholder:text-white/20">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Punto de Origen</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-red-500/60 text-xs">
                            <i class="fas fa-map-marker-alt"></i>
                        </span>
                        <input type="text" name="ori_rut" id="ori_rut" required placeholder="Ciudad o terminal de salida"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all placeholder:text-slate-400 dark:placeholder:text-white/20">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Punto de Destino</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-emerald-500/60 text-xs">
                            <i class="fas fa-flag-checkered"></i>
                        </span>
                        <input type="text" name="des_rut" id="des_rut" required placeholder="Ciudad o terminal de llegada"
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
                            <input type="number" step="0.01" name="dis_rut" id="dis_rut" required placeholder="0.00"
                                   class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all placeholder:text-slate-400 dark:placeholder:text-white/20 font-mono">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-blue-600 dark:text-neon-azul uppercase tracking-wider">Precio Sugerido</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-blue-600 dark:text-neon-azul font-bold text-sm">$</span>
                            <input type="number" name="val_rut" id="val_rut" required placeholder="0"
                                   class="w-full pl-8 pr-4 py-2.5 bg-blue-50/50 dark:bg-neon-azul/5 border border-blue-200 dark:border-neon-azul/20 rounded-xl outline-none focus:border-neon-azul text-blue-600 dark:text-neon-azul text-sm transition-all font-bold tracking-wide placeholder:text-blue-600/30 dark:placeholder:text-neon-azul/30">
                        </div>
                    </div>
                </div>

                <!-- CAMPO DE IMAGEN DEL LUGAR DE DESPACHO -->
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-color-mutado uppercase tracking-wider">Imagen del Lugar de Despacho</label>
                    
                    <!-- Previsualización dinámica de la imagen existente/seleccionada -->
                    <div id="contenedorImagenActual" class="hidden items-center justify-between p-3 bg-slate-50 dark:bg-black/20 rounded-xl border border-slate-200 dark:border-white/5">
                        <div class="flex items-center gap-3 min-w-0">
                            <img id="imagenPrevia" src="" alt="Vista Previa" 
                                 class="w-12 h-12 min-w-[48px] object-cover rounded-lg border border-slate-200 dark:border-white/10"
                                 onerror="this.onerror=null; this.src='https://via.placeholder.com/150?text=No+Img';">
                            <div class="text-xs min-w-0">
                                <p id="textoEstadoImagen" class="font-semibold text-slate-700 dark:text-slate-300 truncate">Imagen Actual de la Ruta</p>
                                <p id="subtextoEstadoImagen" class="text-[10px] text-slate-400 truncate">Para cambiarla, primero elimine la actual.</p>
                            </div>
                        </div>
                        <button type="button" onclick="eliminarImagenActual()" class="px-3 py-1.5 bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 shrink-0 ml-2" title="Eliminar Imagen">
                            <i class="fas fa-trash-alt"></i>
                            <span>Eliminar</span>
                        </button>
                    </div>

                    <!-- Contenedor de Carga (Dropzone) -->
                    <div id="contenedorDropzone" class="relative flex items-center justify-center w-full">
                        <label id="labelDropArea" class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-xl cursor-pointer bg-slate-50 dark:bg-[#0b0f19]/60 border-slate-200 dark:border-white/10 hover:border-neon-azul dark:hover:border-neon-azul transition-all">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <i class="fas fa-cloud-upload-alt text-2xl text-slate-400 dark:text-white/20 mb-2"></i>
                                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                    <span class="text-neon-azul">Haz clic para subir</span> o arrastra un archivo
                                </p>
                                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">PNG, JPG o WEBP (Máx. 5MB)</p>
                            </div>
                            <input type="file" name="img_rut" id="img_rut" accept="image/*" class="hidden" onchange="mostrarNombreArchivo(this)" />
                        </label>
                    </div>

                    <p id="nombreArchivoSeleccionado" class="text-xs text-neon-azul font-medium truncate mt-1"></p>
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
            document.getElementById('formNuevaRuta').reset();
            document.getElementById('id_rut').value = '';
            document.getElementById('eliminar_imagen').value = '0';
            document.getElementById('tituloModal').innerText = 'Registrar Nueva Ruta';
            
            const contenedorImagen = document.getElementById('contenedorImagenActual');
            contenedorImagen.classList.add('hidden');
            contenedorImagen.classList.remove('flex');
            
            const contenedorDropzone = document.getElementById('contenedorDropzone');
            contenedorDropzone.classList.remove('hidden');
            document.getElementById('img_rut').disabled = false;

            document.getElementById('nombreArchivoSeleccionado').innerText = '';

            const drawer = document.getElementById('drawerRuta');
            const overlay = document.getElementById('overlayRuta');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');
            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
        }

        function editarRuta(ruta) {
            document.getElementById('id_rut').value = ruta.id_rut;
            document.getElementById('nom_rut').value = ruta.nom_rut;
            document.getElementById('ori_rut').value = ruta.ori_rut;
            document.getElementById('des_rut').value = ruta.des_rut;
            document.getElementById('dis_rut').value = ruta.dis_rut;
            document.getElementById('val_rut').value = ruta.val_rut;
            document.getElementById('eliminar_imagen').value = '0';

            document.getElementById('tituloModal').innerText = 'Editar Ruta #' + ruta.id_rut;
            document.getElementById('nombreArchivoSeleccionado').innerText = '';

            const contenedorImagen = document.getElementById('contenedorImagenActual');
            const contenedorDropzone = document.getElementById('contenedorDropzone');
            const imgPrevia = document.getElementById('imagenPrevia');
            const textoEstado = document.getElementById('textoEstadoImagen');
            const subtextoEstado = document.getElementById('subtextoEstadoImagen');

            if (ruta.img_rut && ruta.img_rut.trim() !== '') {
                imgPrevia.src = '../img/rutas/' + ruta.img_rut;
                textoEstado.innerText = "Imagen Actual de la Ruta";
                subtextoEstado.innerText = "Para cambiarla, primero elimine la actual.";
                
                contenedorImagen.classList.remove('hidden');
                contenedorImagen.classList.add('flex');
                
                contenedorDropzone.classList.add('hidden');
                document.getElementById('img_rut').disabled = true;
            } else {
                contenedorImagen.classList.add('hidden');
                contenedorImagen.classList.remove('flex');
                
                contenedorDropzone.classList.remove('hidden');
                document.getElementById('img_rut').disabled = false;
            }

            const drawer = document.getElementById('drawerRuta');
            const overlay = document.getElementById('overlayRuta');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');
            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
        }

        function verDetallesRuta(ruta) {
            document.getElementById('detallesTitulo').innerText = "Ruta: " + ruta.nom_rut;
            document.getElementById('detOrigen').innerText = ruta.ori_rut;
            document.getElementById('detDestino').innerText = ruta.des_rut;
            document.getElementById('detDistancia').innerText = ruta.dis_rut + " km";
            document.getElementById('detPrecio').innerText = "$" + strFormatNumber(ruta.val_rut);

            const imgAmpliada = document.getElementById('imgDetalleAmpliada');
            if (ruta.img_rut && ruta.img_rut.trim() !== '') {
                imgAmpliada.src = '../img/rutas/' + ruta.img_rut;
            } else {
                imgAmpliada.src = 'https://via.placeholder.com/600x300?text=Sin+Imagen+de+Despacho';
            }

            const modal = document.getElementById('modalVerDetalles');
            const caja = document.getElementById('cajaDetalles');
            const overlay = document.getElementById('overlayRuta');

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                caja.classList.remove('scale-95');
            }, 10);
        }

        function cerrarModalDetalles() {
            const modal = document.getElementById('modalVerDetalles');
            const caja = document.getElementById('cajaDetalles');
            
            caja.classList.add('scale-95');
            modal.classList.add('opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                cerrarTodosLosModales();
            }, 200);
        }

        function cerrarModalRuta() {
            const drawer = document.getElementById('drawerRuta');
            drawer.classList.remove('translate-x-0');
            drawer.classList.add('translate-x-full');
            cerrarTodosLosModales();
        }

        function cerrarTodosLosModales() {
            const overlay = document.getElementById('overlayRuta');
            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            
            const drawer = document.getElementById('drawerRuta');
            drawer.classList.add('translate-x-full');

            const modal = document.getElementById('modalVerDetalles');
            modal.classList.add('hidden', 'opacity-0');
        }

        function eliminarImagenActual() {
            document.getElementById('eliminar_imagen').value = '1';
            
            const contenedorImagen = document.getElementById('contenedorImagenActual');
            contenedorImagen.classList.add('hidden');
            contenedorImagen.classList.remove('flex');

            const fileInput = document.getElementById('img_rut');
            fileInput.value = '';
            fileInput.disabled = false;

            const contenedorDropzone = document.getElementById('contenedorDropzone');
            contenedorDropzone.classList.remove('hidden');

            document.getElementById('nombreArchivoSeleccionado').innerText = '';
        }

        function mostrarNombreArchivo(input) {
            const label = document.getElementById('nombreArchivoSeleccionado');
            const contenedorImagen = document.getElementById('contenedorImagenActual');
            const imgPrevia = document.getElementById('imagenPrevia');
            const textoEstado = document.getElementById('textoEstadoImagen');
            const subtextoEstado = document.getElementById('subtextoEstadoImagen');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                label.innerText = "Archivo seleccionado: " + file.name;

                const reader = new FileReader();
                reader.onload = function(e) {
                    imgPrevia.src = e.target.result;
                    textoEstado.innerText = "Nueva Previsualización";
                    subtextoEstado.innerText = "Imagen seleccionada lista para guardar.";
                    contenedorImagen.classList.remove('hidden');
                    contenedorImagen.classList.add('flex');
                }
                reader.readAsDataURL(file);
            } else {
                label.innerText = "";
            }
        }

        function strFormatNumber(num) {
            return new Intl.NumberFormat('es-CO').format(num);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const dropArea = document.getElementById('labelDropArea');
            const fileInput = document.getElementById('img_rut');

            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!fileInput.disabled) {
                        dropArea.classList.add('border-neon-azul', 'bg-neon-azul/10');
                    }
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropArea.classList.remove('border-neon-azul', 'bg-neon-azul/10');
                }, false);
            });

            dropArea.addEventListener('drop', (e) => {
                if (fileInput.disabled) return;
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length) {
                    fileInput.files = files;
                    mostrarNombreArchivo(fileInput);
                }
            });
        });
    </script>
</body>
</html>
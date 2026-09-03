<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 
require_once '../helpers/AuthHelper.php';

// Validación de seguridad para Admin (Rol 1)
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// Consulta Maestra: Promedio, total de viajes y datos del conductor
$sql_ranking = "SELECT 
                    u.id_usu, 
                    u.nom_usu, 
                    u.num_doc_usu,
                    COUNT(DISTINCT v.id_via) as total_viajes,
                    AVG(c.pun_cal) as promedio_estrellas,
                    COUNT(c.id_cal) as total_resenas
                FROM usuario u
                LEFT JOIN viaje v ON u.id_usu = v.id_usu_via
                LEFT JOIN calificacion c ON u.id_usu = c.id_usu_des
                WHERE u.id_rol_usu = 2
                GROUP BY u.id_usu
                ORDER BY promedio_estrellas DESC";

$ranking = $conexion->query($sql_ranking);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET Admin - Ranking de Calidad</title>
    
    <!-- Tailwind CSS & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

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

        // Script Anti-Parpadeo de Tema
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        
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
    <?php include '../includes/sidebar.php'; ?>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen min-w-0">
        
        <!-- HEADER MODULAR REUTILIZABLE -->
        <?php include '../includes/header.php'; ?>

        <!-- ÁREA DE TRABAJO -->
        <main class="p-8 flex-1 space-y-6 pt-24 min-w-0">
            
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Métricas de Desempeño</h1>
                        
                        <!-- 1. MODAL DE AYUDA GUÍA (TOOLTIP FLOTANTE) -->
                        <div class="relative group">
                            <button type="button" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer">
                                <i class="fas fa-question text-[10px]"></i>
                            </button>

                            <div class="absolute left-0 top-full mt-2 w-80 bg-white dark:bg-[#121826] border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-2xl p-4 text-xs opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                                <p class="font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-700/60 pb-2">
                                    <i class="fas fa-info-circle text-amber-400"></i> Calificación de Operadores
                                </p>
                                <ul class="space-y-2 text-slate-600 dark:text-slate-300 leading-relaxed">
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-star text-amber-400 mt-0.5 shrink-0"></i>
                                        <span><b>Promedios:</b> Puntuación media sobre 5.0 estrellas calculada a partir de la muestra de reseñas.</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-eye text-blue-500 mt-0.5 shrink-0"></i>
                                        <span><b>Ficha de Resumen:</b> Haz clic sobre el botón de acción para ver el detalle de cada conductor.</span>
                                    </li>
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-award text-emerald-500 mt-0.5 shrink-0"></i>
                                        <span><b>Estatus:</b> Clasificación automática en nivel Excelente, Regular o Crítico.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Monitoreo de control de calidad, promedios valorativos y nivel de aceptación del servicio logístico.</p>
                </div>

                <button type="button" onclick="abrirModalRangos()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 font-bold text-xs uppercase tracking-wider rounded-xl hover:bg-amber-500 hover:text-white transition-all cursor-pointer self-start sm:self-auto">
                    <i class="fas fa-info-circle"></i> Ver Escala de Calidad
                </button>
            </div>

            <!-- TABLA CON DESPLAZAMIENTO HORIZONTAL -->
            <div class="bg-white dark:bg-[#121826] rounded-2xl border border-slate-200 dark:border-white/10 shadow-2xl overflow-hidden backdrop-blur-sm transition-colors duration-300">
                <div class="overflow-x-auto custom-scrollbar w-full">
                    <table class="w-full text-left border-collapse min-w-[750px]">
                        <thead class="bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/10 transition-colors">
                            <tr>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Operador / Conductor</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Viajes Completados</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Calificación Promedio</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Muestra (Reseñas)</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Estatus</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/10 text-slate-700 dark:text-slate-200">
                            <?php if($ranking && $ranking->num_rows > 0): ?>
                                <?php while($row = $ranking->fetch_assoc()): 
                                    $prom = round($row['promedio_estrellas'], 1);
                                    $jsonConductor = htmlspecialchars(json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-all group">
                                    
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-blue-600 dark:text-neon-azul rounded-xl flex items-center justify-center font-bold shadow-inner group-hover:border-blue-500/30 dark:group-hover:border-neon-azul/30 transition-all">
                                                <?php echo strtoupper(substr($row['nom_usu'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 group-hover:text-blue-600 dark:group-hover:text-white uppercase tracking-wide transition-colors"><?php echo htmlspecialchars($row['nom_usu']); ?></p>
                                                <p class="text-[10px] text-slate-400 dark:text-slate-400 font-mono mt-0.5">CC: <?php echo $row['num_doc_usu']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-block bg-slate-100 dark:bg-white/[0.02] border border-slate-200 dark:border-white/10 px-3 py-1 rounded-lg text-xs font-mono font-medium text-slate-600 dark:text-slate-300">
                                            <?php echo $row['total_viajes']; ?> viajes
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="flex text-amber-400 text-[9px] gap-0.5 mb-1 tracking-wide">
                                                <?php 
                                                for($i=1; $i<=5; $i++) {
                                                    echo ($i <= floor($prom)) 
                                                        ? '<i class="fas fa-star text-amber-400 shadow-sm"></i>' 
                                                        : '<i class="far fa-star text-slate-300 dark:text-white/10"></i>';
                                                }
                                                ?>
                                            </div>
                                            <span class="text-base font-extrabold text-slate-800 dark:text-white font-mono tracking-tight">
                                                <?php echo ($prom > 0) ? number_format($prom, 1) : "0.0"; ?>
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-center">
                                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400 tracking-wide">
                                            <?php echo $row['total_resenas']; ?> evaluaciones
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-center">
                                        <?php if($prom >= 4.0): ?>
                                            <span class="inline-block px-3 py-1.5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 rounded-xl text-[10px] font-extrabold uppercase tracking-widest">Excelente</span>
                                        <?php elseif($prom >= 3.0): ?>
                                            <span class="inline-block px-3 py-1.5 bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 rounded-xl text-[10px] font-extrabold uppercase tracking-widest">Regular</span>
                                        <?php elseif($prom > 0): ?>
                                            <span class="inline-block px-3 py-1.5 bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-extrabold uppercase tracking-widest">Crítico</span>
                                        <?php else: ?>
                                            <span class="inline-block px-3 py-1.5 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-500 dark:text-slate-400 rounded-xl text-[10px] font-extrabold uppercase tracking-widest">Sin datos</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <button type="button" 
                                                data-conductor='<?php echo $jsonConductor; ?>'
                                                onclick="verFichaConductor(this)"
                                                class="w-8 h-8 bg-blue-500/10 text-blue-600 dark:text-neon-azul border border-blue-500/20 rounded-xl flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all shadow-md cursor-pointer mx-auto" 
                                                title="Ver Ficha de Rendimiento">
                                            <i class="fas fa-eye text-xs"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 dark:text-slate-400 text-xs">
                                        <i class="fas fa-star-half-alt text-2xl mb-3 block text-slate-300 dark:text-white/10"></i>
                                        No se registran valoraciones activas para los operadores de SGET.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- OVERLAY GENERAL PARA MODALES -->
    <div id="overlayRanking" onclick="cerrarTodosModales()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- 2. MODAL POP-UP DETALLES DE RENDIMIENTO DEL CONDUCTOR -->
    <div id="modalFichaConductor" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-sm rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300" id="modalFichaBox">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xs">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3 id="detNombre" class="font-extrabold text-slate-900 dark:text-white text-base"></h3>
                </div>
                <button onclick="cerrarModalFicha()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            
            <div class="space-y-3.5 text-xs">
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="fas fa-id-card text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Documento de Identidad</p>
                        <p id="detDoc" class="font-mono font-bold text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="fas fa-route text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total de Viajes</p>
                        <p id="detViajes" class="font-bold text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="fas fa-star text-amber-400 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Puntuación Promedio</p>
                        <p id="detPromedio" class="font-mono font-bold text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>
            </div>

            <button onclick="cerrarModalFicha()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer">
                Cerrar Ficha
            </button>
        </div>
    </div>

    <!-- 3. MODAL DE ESCALA Y CRITERIOS DE CALIDAD -->
    <div id="modalRangos" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300" id="modalRangosBox">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xs">
                        <i class="fas fa-list-ol"></i>
                    </div>
                    <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Escala de Calidad SGET</h3>
                </div>
                <button onclick="cerrarModalRangos()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            
            <div class="space-y-3 text-xs">
                <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl flex items-center justify-between">
                    <div>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider text-[10px] block">Excelente</span>
                        <span class="text-slate-600 dark:text-slate-300">Promedio de 4.0 a 5.0 estrellas</span>
                    </div>
                    <i class="fas fa-check-circle text-emerald-500 text-lg"></i>
                </div>

                <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-2xl flex items-center justify-between">
                    <div>
                        <span class="font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider text-[10px] block">Regular</span>
                        <span class="text-slate-600 dark:text-slate-300">Promedio de 3.0 a 3.9 estrellas</span>
                    </div>
                    <i class="fas fa-exclamation-circle text-amber-500 text-lg"></i>
                </div>

                <div class="p-3 bg-red-500/10 border border-red-500/20 rounded-2xl flex items-center justify-between">
                    <div>
                        <span class="font-bold text-red-600 dark:text-red-400 uppercase tracking-wider text-[10px] block">Crítico</span>
                        <span class="text-slate-600 dark:text-slate-300">Promedio menor a 3.0 estrellas</span>
                    </div>
                    <i class="fas fa-times-circle text-red-500 text-lg"></i>
                </div>
            </div>

            <button onclick="cerrarModalRangos()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer">
                Entendido
            </button>
        </div>
    </div>

    <!-- JS CONTROLADORES DE MODALES -->
    <script>
        function verFichaConductor(btn) {
            const datos = JSON.parse(btn.getAttribute('data-conductor'));
            document.getElementById('detNombre').innerText = datos.nom_usu;
            document.getElementById('detDoc').innerText = 'CC: ' + datos.num_doc_usu;
            document.getElementById('detViajes').innerText = datos.total_viajes + ' viajes registrados';
            
            const prom = parseFloat(datos.promedio_estrellas || 0).toFixed(1);
            document.getElementById('detPromedio').innerText = prom + ' / 5.0 ⭐ (' + datos.total_resenas + ' reseñas)';

            const overlay = document.getElementById('overlayRanking');
            const modal = document.getElementById('modalFichaConductor');
            const box = document.getElementById('modalFichaBox');

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalFicha() {
            const overlay = document.getElementById('overlayRanking');
            const modal = document.getElementById('modalFichaConductor');
            const box = document.getElementById('modalFichaBox');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function abrirModalRangos() {
            const overlay = document.getElementById('overlayRanking');
            const modal = document.getElementById('modalRangos');
            const box = document.getElementById('modalRangosBox');

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalRangos() {
            const overlay = document.getElementById('overlayRanking');
            const modal = document.getElementById('modalRangos');
            const box = document.getElementById('modalRangosBox');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function cerrarTodosModales() {
            cerrarModalFicha();
            cerrarModalRangos();
        }

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
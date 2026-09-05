<?php
// Archivo: Admin/ranking_conductores.php
date_default_timezone_set('America/Bogota');
session_start();

include '../assets/conexion.php';
require_once '../helpers/AuthHelper.php';

// Verificación de seguridad (Solo Admin)[cite: 7]
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// BLOQUEO DE SEGURIDAD POR RESTRICCIONES[cite: 7]
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'ranking_conductores');

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// Consulta para ranking de conductores[cite: 7]
$query_ranking = "SELECT u.id_usu, u.nom_usu, u.corre_usu, COUNT(v.id_via) as total_viajes 
                  FROM usuario u 
                  LEFT JOIN viaje v ON u.id_usu = v.id_usu_via 
                  WHERE u.id_rol_usu = 2 AND u.estado = 1 
                  GROUP BY u.id_usu, u.nom_usu, u.corre_usu 
                  ORDER BY total_viajes DESC";
$resultado_ranking = $conexion->query($query_ranking);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Ranking de Conductores</title>
    
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
            
            <!-- ENCABEZADO CON BOTÓN DE AYUDA -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/5 dark:bg-white/[0.02] p-6 rounded-3xl border border-slate-200 dark:border-white/5 backdrop-blur-md">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Ranking de Conductores</h1>
                        
                        <!-- BOTÓN DE AYUDA DEL SISTEMA -->
                        <button type="button" onclick="abrirModalAyuda()" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer" title="Ver guía del módulo">
                            <i class="fas fa-question text-[10px]"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Evaluación de desempeño, cantidad de viajes realizados y métricas del personal de conducción[cite: 7].</p>
                </div>
            </div>

            <!-- Tabla de Ranking[cite: 7] -->
            <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl space-y-6">
                <div class="flex justify-between items-center">
                    <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-trophy text-amber-400"></i> Tabla de Posiciones y Rendimiento
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-white/5 text-[10px] text-slate-400 uppercase font-bold">
                                <th class="pb-3 px-3">Posición</th>
                                <th class="pb-3 px-3">Conductor</th>
                                <th class="pb-3 px-3">Correo Electrónico</th>
                                <th class="pb-3 px-3 text-center">Viajes Realizados</th>
                                <th class="pb-3 px-3 text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            <?php if($resultado_ranking && $resultado_ranking->num_rows > 0): ?>
                                <?php $pos = 1; while($c = $resultado_ranking->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="py-3.5 px-3 font-mono font-bold text-slate-400">
                                        <?php if($pos == 1): ?><span class="text-amber-400"><i class="fas fa-medal"></i> #1</span>
                                        <?php elseif($pos == 2): ?><span class="text-slate-300"><i class="fas fa-medal"></i> #2</span>
                                        <?php elseif($pos == 3): ?><span class="text-amber-600"><i class="fas fa-medal"></i> #3</span>
                                        <?php else: echo "#" . $pos; endif; ?>
                                    </td>
                                    <td class="py-3.5 px-3 font-bold text-slate-800 dark:text-white flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-black text-xs border border-emerald-500/20">
                                            <?php echo strtoupper(substr($c['nom_usu'], 0, 1)); ?>
                                        </div>
                                        <?php echo htmlspecialchars($c['nom_usu']); ?>
                                    </td>
                                    <td class="py-3.5 px-3 text-slate-500 dark:text-slate-300 italic"><?php echo htmlspecialchars($c['corre_usu']); ?></td>
                                    <td class="py-3.5 px-3 text-center font-mono font-bold text-sky-400"><?php echo $c['total_viajes']; ?></td>
                                    <td class="py-3.5 px-3 text-center">
                                        <span class="px-3 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-[10px] font-extrabold uppercase">Activo</span>
                                    </td>
                                </tr>
                                <?php $pos++; endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-400 italic">No hay registros de conductores disponibles[cite: 7].</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- MODAL DE AYUDA DEL MÓDULO -->
    <div id="overlayAyuda" onclick="cerrarModalAyuda()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="modalAyuda" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-4 transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <i class="fas fa-info-circle text-sky-400"></i> Guía de Ranking de Conductores
                </h3>
                <button onclick="cerrarModalAyuda()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"><i class="fas fa-times text-xs"></i></button>
            </div>
            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                <li class="flex items-start gap-2">
                    <i class="fas fa-trophy text-amber-400 mt-0.5"></i>
                    <span><b>Tabla de Posiciones:</b> El sistema genera automáticamente el orden de los conductores basándose en la cantidad de viajes registrados exitosamente en el despacho.</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-medal text-sky-400 mt-0.5"></i>
                    <span><b>Top Rendimiento:</b> Destaca visualmente a los tres (3) mejores conductores con iconos de medallas (Oro, Plata y Bronce) para facilitar la lectura del desempeño.</span>
                </li>
            </ul>
            <button onclick="cerrarModalAyuda()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer mt-2">
                Entendido
            </button>
        </div>
    </div>

    <!-- SCRIPTS DE CONTROL -->
    <script>
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
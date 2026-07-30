<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

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

        // Script Anti-Parpadeo de Tema (Sincronizado con index.php y header.php)
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] flex min-h-screen antialiased text-slate-800 dark:text-slate-100 transition-colors duration-300">

    <!-- BARRA LATERAL -->
    <?php include 'sidebar.php'; ?>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- HEADER MODULAR REUTILIZABLE -->
        <?php include 'header.php'; ?>

        <!-- ÁREA DE TRABAJO -->
        <main class="p-8 flex-1 space-y-6 pt-24">
            
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Métricas de Desempeño</h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs mt-1">Monitoreo de control de calidad, promedios valorativos y nivel de aceptación del servicio logístico.</p>
            </div>

            <div class="bg-white dark:bg-[#121826] rounded-2xl border border-slate-200 dark:border-white/10 shadow-2xl overflow-hidden backdrop-blur-sm transition-colors duration-300">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/10 transition-colors">
                            <tr>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Operador / Conductor</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Viajes Completados</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Calificación Promedio</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Muestra (Reseñas)</th>
                                <th class="px-6 py-4 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Estatus de Calidad</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/10 text-slate-700 dark:text-slate-200">
                            <?php if($ranking && $ranking->num_rows > 0): ?>
                                <?php while($row = $ranking->fetch_assoc()): 
                                    $prom = round($row['promedio_estrellas'], 1);
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
                                    
                                    <td class="px-6 py-4 text-right">
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
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 dark:text-slate-400 text-xs">
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

    <!-- Escuchador dinámico sincronizado para actualizar la clave 'theme' -->
    <script>
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
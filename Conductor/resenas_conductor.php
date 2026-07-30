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
$sql_resenas = "SELECT c.*, u.nom_usu as pasajero, r.des_rut, v.fec_via 
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
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex min-h-screen antialiased transition-colors duration-300">

    <!-- Carga Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Contenedor Principal (ml-64 para respetar sidebar fixed) -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen">
        
       <!-- INCLUSIÓN DEL HEADER DEL CONDUCTOR -->
        <?php include 'header_conductor.php'; ?>
        </header>

        <!-- Cuerpo principal -->
        <div class="p-8 space-y-6 flex-1">
            
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Feedback de Pasajeros</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Lo que dicen los usuarios sobre tu servicio en la vía.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-6xl">
                <?php if ($resenas && $resenas->num_rows > 0): ?>
                    <?php while($r = $resenas->fetch_assoc()): ?>
                    <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-xl hover:border-blue-500/30 transition-all duration-300 group">
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
                            <p class="text-slate-600 dark:text-slate-300 text-xs italic leading-relaxed relative z-10 pl-5 border-l-2 border-blue-500/30">
                                <?php echo htmlspecialchars($r['com_cal'] ?? 'Sin comentario adicional.', ENT_QUOTES, 'UTF-8'); ?>
                            </p>
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

    <!-- JavaScript para Toggle de Modo Oscuro / Claro -->
    <script>
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
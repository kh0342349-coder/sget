<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}

$documento_sesion = $_SESSION['documento'];
$nombreReal = $_SESSION['nombre_usuario'] ?? "Pasajero";

// 1. OBTENER ID REAL DEL USUARIO (PASAJERO)
$query_user = $conexion->query("SELECT id_usu FROM usuario WHERE num_doc_usu = '$documento_sesion'");
$id_pasajero = 0;
if ($query_user && $query_user->num_rows > 0) {
    $user_data = $query_user->fetch_assoc();
    $id_pasajero = $user_data['id_usu'];
}

// Capturamos los datos que vienen por la URL (GET)
$id_via = $_GET['id_via'] ?? null;
$id_cond = $_GET['id_cond'] ?? null;

// Bandera para saber si los datos están listos
$parametros_validos = ($id_via && $id_cond);
$conductor = "Conductor";

if ($parametros_validos) {
    // Consultar nombre del conductor si existen los parámetros
    $stmt_cond = $conexion->prepare("SELECT nom_usu FROM usuario WHERE id_usu = ?");
    $stmt_cond->bind_param("i", $id_cond);
    $stmt_cond->execute();
    $query_cond = $stmt_cond->get_result();
    if ($query_cond && $query_cond->num_rows > 0) {
        $conductor = $query_cond->fetch_assoc()['nom_usu'];
    }
    $stmt_cond->close();
}

// ------------------------------------------------------------------
// HISTORIAL DE RESEÑAS REALIZADAS POR ESTE PASAJERO
// ------------------------------------------------------------------
$historial_resenas = [];

if ($id_pasajero > 0) {
    $sql_historial = "SELECT c.*, u.nom_usu AS nombre_conductor 
                      FROM calificacion c 
                      JOIN viaje v ON c.id_via_cal = v.id_via 
                      LEFT JOIN usuario u ON v.id_usu_via = u.id_usu 
                      WHERE c.id_usu_rem = ?
                      ORDER BY c.id_cal DESC";

    if ($stmt_hist = $conexion->prepare($sql_historial)) {
        $stmt_hist->bind_param("i", $id_pasajero);
        $stmt_hist->execute();
        $res_hist = $stmt_hist->get_result();
        while ($row = $res_hist->fetch_assoc()) {
            $historial_resenas[] = $row;
        }
        $stmt_hist->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificar Servicio - SGET</title>
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
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex min-h-screen antialiased transition-colors duration-300">

    <!-- INCLUSIÓN DIRECTA DEL SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- MAIN CON MARGEN IZQUIERDO PARA ALINEARSE AL SIDEBAR FIJO -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- HEADER ESTANDARIZADO MODULAR -->
        <?php include 'header.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="flex-1 max-w-5xl w-full mx-auto p-6 md:p-8 space-y-10">
            
            <!-- FORMULARIO DE CALIFICACIÓN / AVISO -->
            <div class="flex flex-col items-center justify-center">
                <?php if ($parametros_validos): ?>
                    <div class="max-w-md w-full bg-white dark:bg-[#1e293b] rounded-[2rem] shadow-xl p-8 border border-slate-200 dark:border-white/5 transition-colors duration-300">
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4">
                                <i class="fas fa-star"></i>
                            </div>
                            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Calificar Servicio</h1>
                            <p class="text-slate-500 dark:text-slate-400 text-xs mt-1.5">Tu opinión sobre <strong class="text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars($conductor, ENT_QUOTES, 'UTF-8'); ?></strong> es muy valiosa.</p>
                        </div>

                        <form action="../procesos/guardar_calificacion.php" method="POST" class="space-y-5">
                            <input type="hidden" name="id_via" value="<?php echo htmlspecialchars($id_via, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id_cond" value="<?php echo htmlspecialchars($id_cond, ENT_QUOTES, 'UTF-8'); ?>">

                            <div>
                                <label class="text-[10px] font-black uppercase text-slate-400 mb-2 ml-2 block tracking-widest">Puntuación</label>
                                <div class="relative">
                                    <select name="puntos" required class="w-full bg-slate-100 dark:bg-[#161e2e] border border-slate-200 dark:border-slate-800 rounded-xl px-5 py-3.5 text-xs font-bold text-slate-700 dark:text-slate-200 focus:outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition appearance-none cursor-pointer">
                                        <option value="5">⭐⭐⭐⭐⭐ Excelente</option>
                                        <option value="4">⭐⭐⭐⭐ Muy Bueno</option>
                                        <option value="3">⭐⭐⭐ Regular</option>
                                        <option value="2">⭐⭐ Malo</option>
                                        <option value="1">⭐ Pésimo</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-5 pointer-events-none text-slate-400 text-xs">
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="text-[10px] font-black uppercase text-slate-400 mb-2 ml-2 block tracking-widest">¿Algo que destacar?</label>
                                <textarea name="comentario" rows="3" placeholder="Ej: Muy puntual y amable..." class="w-full bg-slate-100 dark:bg-[#161e2e] border border-slate-200 dark:border-slate-800 rounded-xl px-5 py-3.5 text-xs font-medium text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 transition resize-none"></textarea>
                            </div>

                            <div class="flex flex-col gap-2 pt-2">
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-200 shadow-md">
                                    Enviar Calificación
                                </button>
                            </div>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="max-w-md w-full bg-white dark:bg-[#1e293b] rounded-[2rem] shadow-xl p-8 border border-slate-200 dark:border-white/5 text-center transition-colors duration-300">
                        <div class="w-16 h-16 bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight uppercase">Calificar un Servicio</h1>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mt-3 leading-relaxed">
                            Para calificar un servicio, dirígete a la sección de tu <strong class="text-slate-700 dark:text-slate-200">Historial de Viajes</strong> y haz clic en el botón <span class="text-yellow-600 dark:text-yellow-400 font-bold">"Calificar"</span> en los viajes completados.
                        </p>
                        <div class="mt-6">
                            <a href="pasajero.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition duration-200">
                                Ir a mis viajes
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SECCIÓN: HISTORIAL DE RESEÑAS -->
            <div class="w-full bg-white dark:bg-[#1e293b] rounded-[2rem] shadow-xl p-8 border border-slate-200 dark:border-white/5 transition-colors duration-300">
                <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-200 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-500/10 text-blue-500 rounded-xl flex items-center justify-center text-lg">
                            <i class="fas fa-history"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Mis Calificaciones Realizadas</h2>
                            <p class="text-slate-500 dark:text-slate-400 text-xs">Historial de las opiniones que has enviado a tus conductores</p>
                        </div>
                    </div>
                    <span class="text-xs font-bold px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-full">
                        Total: <?php echo count($historial_resenas); ?>
                    </span>
                </div>

                <?php if (empty($historial_resenas)): ?>
                    <div class="text-center py-8 text-slate-400 dark:text-slate-500">
                        <i class="fas fa-comment-slash text-3xl mb-2 block"></i>
                        <p class="text-xs font-medium">Aún no has dejado opiniones registradas en el sistema.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($historial_resenas as $resena): ?>
                            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-[#161e2e] border border-slate-200 dark:border-slate-800 flex flex-col justify-between gap-3">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                            <i class="fas fa-user-circle text-blue-500"></i>
                                            Conductor: <?php echo htmlspecialchars($resena['nombre_conductor'] ?? 'Por asignar', ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <span class="text-[10px] text-slate-400 mt-0.5 block">
                                            <?php 
                                                $fecha = !empty($resena['fech_cal']) ? date('d/m/Y - h:i A', strtotime($resena['fech_cal'])) : 'Registrado';
                                                echo $fecha;
                                            ?>
                                        </span>
                                    </div>
                                    <div class="flex text-yellow-400 text-xs">
                                        <?php 
                                            $pts = (int)($resena['puntos_cal'] ?? 5);
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $pts) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } else {
                                                    echo '<i class="far fa-star text-slate-300 dark:text-slate-700"></i>';
                                                }
                                            }
                                        ?>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-300 italic bg-white dark:bg-[#1e293b] p-3 rounded-xl border border-slate-100 dark:border-slate-800/50">
                                    "<?php echo htmlspecialchars(!empty($resena['coment_cal']) ? $resena['coment_cal'] : 'Sin comentario escrito.', ENT_QUOTES, 'UTF-8'); ?>"
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- SCRIPT TEMA GLOBAL -->
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
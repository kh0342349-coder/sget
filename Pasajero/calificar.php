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
$id_pasajero = 0;
$stmt_user = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ?");
$stmt_user->bind_param("s", $documento_sesion);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
if ($res_user && $res_user->num_rows > 0) {
    $id_pasajero = $res_user->fetch_assoc()['id_usu'];
}
$stmt_user->close();

// Capturamos los datos que vienen por la URL (GET)
$id_via = $_GET['id_via'] ?? null;
$id_cond = $_GET['id_cond'] ?? null;

// Bandera para saber si los datos vienen por parámetro
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

// 2. VIAJES PENDIENTES POR CALIFICAR
$viajes_pendientes = [];
if ($id_pasajero > 0 && !$parametros_validos) {
    // Consulta sin la columna v.fech_via para evitar errores SQL
    $sql_pendientes = "SELECT v.id_via, v.id_usu_via AS id_cond, u.nom_usu AS nombre_conductor, r.nom_rut
                       FROM reserva res
                       INNER JOIN viaje v ON res.id_via_res = v.id_via
                       INNER JOIN usuario u ON v.id_usu_via = u.id_usu
                       LEFT JOIN rutas r ON v.id_rut_via = r.id_rut
                       LEFT JOIN calificacion c ON (c.id_via_cal = v.id_via AND c.id_usu_rem = res.id_usu_res)
                       WHERE res.id_usu_res = ? 
                         AND c.id_cal IS NULL
                       ORDER BY v.id_via DESC";

    if ($stmt_pend = $conexion->prepare($sql_pendientes)) {
        $stmt_pend->bind_param("i", $id_pasajero);
        $stmt_pend->execute();
        $res_pend = $stmt_pend->get_result();
        while ($row = $res_pend->fetch_assoc()) {
            $viajes_pendientes[] = $row;
        }
        $stmt_pend->close();
    }
}

// 3. HISTORIAL DE RESEÑAS REALIZADAS POR ESTE PASAJERO
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

// Consulta de rutas para el Drawer (+)
$rutas_disponibles = $conexion->query("SELECT id_rut, nom_rut FROM rutas ORDER BY nom_rut ASC");
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
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 flex min-h-screen antialiased transition-colors duration-300 relative overflow-x-hidden">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 ml-64 flex flex-col min-h-screen min-w-0">
        <?php include 'header.php'; ?>

        <div class="flex-1 max-w-5xl w-full mx-auto p-6 md:p-8 space-y-8 min-w-0">
            
            <!-- ENCABEZADO DE LA SECCIÓN -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#1e293b]/50 p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Calificación de Servicio</h1>
                        
                        <div class="relative group">
                            <button type="button" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold cursor-pointer">
                                <i class="fas fa-question text-[10px]"></i>
                            </button>

                            <div class="absolute left-0 top-full mt-2 w-80 bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700/80 rounded-2xl shadow-2xl p-4 text-xs opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                                <p class="font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-700/60 pb-2">
                                    <i class="fas fa-info-circle text-neon-azul"></i> Guía de Calificaciones
                                </p>
                                <ul class="space-y-2 text-slate-600 dark:text-slate-300 leading-relaxed">
                                    <li class="flex items-start gap-1.5">
                                        <i class="fas fa-star text-amber-400 mt-0.5 shrink-0"></i>
                                        <span><b>Evaluación:</b> Selecciona tus estrellas y deja un comentario del trayecto.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Registra tu opinión del trayecto y consulta el historial enviado.</p>
                </div>

                <button onclick="abrirModalReservaCalificacion()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 hover:opacity-95 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition-all cursor-pointer whitespace-nowrap">
                    <i class="fas fa-plus-circle text-sm"></i> Buscar Rutas
                </button>
            </div>

            <!-- CONTENEDOR DE FORMULARIO O LISTA DE PENDIENTES -->
            <div class="w-full">
                <?php if ($parametros_validos): ?>
                    <!-- FORMULARIO DIRECTO CUANDO HAY PARAMETROS GET -->
                    <div class="max-w-md w-full mx-auto bg-white dark:bg-[#1e293b] rounded-[2rem] shadow-xl p-8 border border-slate-200 dark:border-white/5 transition-colors duration-300">
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4">
                                <i class="fas fa-star"></i>
                            </div>
                            <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Calificar Servicio</h2>
                            <p class="text-slate-500 dark:text-slate-400 text-xs mt-1.5">Tu opinión sobre <strong class="text-blue-600 dark:text-blue-400"><?php echo htmlspecialchars($conductor, ENT_QUOTES, 'UTF-8'); ?></strong> es muy valiosa.</p>
                        </div>

                        <form action="../procesos/guardar_calificacion.php" method="POST" class="space-y-5">
                            <input type="hidden" name="id_via" value="<?php echo htmlspecialchars($id_via, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id_cond" value="<?php echo htmlspecialchars($id_cond, ENT_QUOTES, 'UTF-8'); ?>">

                            <div>
                                <label class="text-[10px] font-black uppercase text-slate-400 mb-2 ml-2 block tracking-widest">Puntuación</label>
                                <div class="relative">
                                    <select name="puntos" required class="w-full bg-slate-100 dark:bg-[#161e2e] border border-slate-200 dark:border-slate-800 rounded-xl px-5 py-3.5 text-xs font-bold text-slate-700 dark:text-slate-200 focus:outline-none focus:border-blue-500 transition appearance-none cursor-pointer">
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
                                <textarea name="comentario" rows="3" placeholder="Ej: Muy puntual y amable..." class="w-full bg-slate-100 dark:bg-[#161e2e] border border-slate-200 dark:border-slate-800 rounded-xl px-5 py-3.5 text-xs font-medium text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-blue-500 transition resize-none"></textarea>
                            </div>

                            <div class="flex flex-col gap-2 pt-2">
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition duration-200 shadow-md cursor-pointer">
                                    Enviar Calificación
                                </button>
                                <a href="calificar.php" class="w-full text-center text-xs text-slate-400 hover:underline mt-2">Volver al panel</a>
                            </div>
                        </form>
                    </div>

                <?php else: ?>
                    <!-- TARJETA DE VIAJES PENDIENTES POR CALIFICAR -->
                    <div class="w-full bg-white dark:bg-[#1e293b] rounded-[2rem] shadow-xl p-8 border border-slate-200 dark:border-white/5 transition-colors duration-300">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-200 dark:border-slate-800">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-amber-500/10 text-amber-500 rounded-xl flex items-center justify-center text-lg">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Viajes Pendientes por Calificar</h2>
                                    <p class="text-slate-500 dark:text-slate-400 text-xs">Selecciona un viaje para calificar el servicio</p>
                                </div>
                            </div>
                            <span class="text-xs font-bold px-3 py-1 bg-amber-500/10 text-amber-500 rounded-full">
                                Pendientes: <?php echo count($viajes_pendientes); ?>
                            </span>
                        </div>

                        <?php if (empty($viajes_pendientes)): ?>
                            <div class="text-center py-8 text-slate-400 dark:text-slate-500">
                                <i class="fas fa-check-circle text-3xl mb-2 block text-emerald-500"></i>
                                <p class="text-xs font-medium">¡Todo en orden! No tienes viajes pendientes por calificar.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($viajes_pendientes as $vp): ?>
                                    <div class="p-5 rounded-2xl bg-slate-50 dark:bg-[#161e2e] border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">
                                                <?php echo htmlspecialchars($vp['nom_rut'] ?? 'Ruta General', ENT_QUOTES, 'UTF-8'); ?>
                                            </p>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1">
                                                <i class="fas fa-user-circle text-blue-500"></i> Conductor: <strong><?php echo htmlspecialchars($vp['nombre_conductor'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            </p>
                                            <span class="text-[10px] text-slate-400 mt-1 block">
                                                <i class="fas fa-hashtag"></i> Viaje #<?php echo $vp['id_via']; ?>
                                            </span>
                                        </div>
                                        <a href="calificar.php?id_via=<?php echo $vp['id_via']; ?>&id_cond=<?php echo $vp['id_cond']; ?>" class="px-4 py-2.5 bg-yellow-500 hover:bg-yellow-600 text-slate-950 font-black text-[10px] uppercase tracking-wider rounded-xl shadow-md transition-all whitespace-nowrap">
                                            Calificar
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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
                            <?php $jsonRes = htmlspecialchars(json_encode($resena, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>
                            <div class="p-5 rounded-2xl bg-slate-50 dark:bg-[#161e2e] border border-slate-200 dark:border-slate-800 flex flex-col justify-between gap-3">
                                <div>
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <p class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                                <i class="fas fa-user-circle text-blue-500"></i>
                                                Conductor: <?php echo htmlspecialchars($resena['nombre_conductor'] ?? 'Por asignar', ENT_QUOTES, 'UTF-8'); ?>
                                            </p>
                                            <span class="text-[10px] text-slate-400 mt-0.5 block">
                                                <?php echo !empty($resena['fech_cal']) ? date('d/m/Y - h:i A', strtotime($resena['fech_cal'])) : 'Registrado'; ?>
                                            </span>
                                        </div>
                                        <div class="flex text-yellow-400 text-xs">
                                            <?php 
                                                $pts = (int)($resena['puntos_cal'] ?? 5);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo ($i <= $pts) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-300 dark:text-slate-700"></i>';
                                                }
                                            ?>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 italic bg-white dark:bg-[#1e293b] p-3 rounded-xl border border-slate-100 dark:border-slate-800/50 line-clamp-2">
                                        "<?php echo htmlspecialchars(!empty($resena['coment_cal']) ? $resena['coment_cal'] : 'Sin comentario escrito.', ENT_QUOTES, 'UTF-8'); ?>"
                                    </p>
                                </div>

                                <div class="flex justify-end pt-1">
                                    <button type="button" 
                                            data-opinion='<?php echo $jsonRes; ?>'
                                            onclick="verDetalleOpinionCalificacion(this)"
                                            class="text-[10px] font-bold text-blue-600 dark:text-neon-azul hover:underline flex items-center gap-1 cursor-pointer">
                                        <i class="fas fa-eye text-[9px]"></i> Leer Completa
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- OVERLAY GENERAL PARA MODALES -->
    <div id="overlayCalificacion" onclick="cerrarTodosModalesCalificacion()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <!-- MODAL POP-UP DE LECTURA COMPLETA -->
    <div id="modalLecturaOpinion" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#1e293b] w-full max-w-sm rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300" id="modalOpinionBox">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xs">
                        <i class="fas fa-comment-alt"></i>
                    </div>
                    <h3 id="detConductorOpinion" class="font-extrabold text-slate-900 dark:text-white text-base capitalize"></h3>
                </div>
                <button onclick="cerrarModalOpinion()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            
            <div class="space-y-3.5 text-xs">
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5">
                    <i class="far fa-calendar-alt text-blue-500 text-base w-5 text-center"></i>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Fecha de Envío</p>
                        <p id="detFechaOpinion" class="font-medium text-slate-800 dark:text-slate-100 mt-0.5"></p>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-black/20 border border-slate-100 dark:border-white/5 space-y-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Comentario Registrado</p>
                    <p id="detComentarioOpinion" class="italic text-slate-700 dark:text-slate-200 text-xs leading-relaxed"></p>
                </div>
            </div>

            <button onclick="cerrarModalOpinion()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer">
                Cerrar Opinión
            </button>
        </div>
    </div>

    <!-- PANEL LATERAL DESLIZANTE (DRAWER) DE BÚSQUEDA Y RESERVA -->
    <aside id="drawerReservaCalificacion" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between relative">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-neon-morado"></div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-500/10 text-blue-500 dark:text-neon-azul rounded-xl flex items-center justify-center border border-slate-100 dark:border-white/5">
                    <i class="fas fa-ticket-alt text-base"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Buscar Viaje</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Solicitar cupo en ruta disponible</p>
                </div>
            </div>
            <button onclick="cerrarModalDrawerCalificacion()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto space-y-5">
            <form id="formReservaCalificacion" action="viajes_pasajero.php" method="GET" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Destino Deseado</label>
                    <select name="ruta" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                        <option value="">Selecciona tu ruta...</option>
                        <?php 
                        if($rutas_disponibles) {
                            $rutas_disponibles->data_seek(0);
                            while($r = $rutas_disponibles->fetch_assoc()) {
                                echo '<option value="'.$r['id_rut'].'">'.htmlspecialchars($r['nom_rut']).'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fecha Preferida</label>
                    <input type="date" name="fecha" id="input_fecha_calificacion" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalDrawerCalificacion()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider transition-all cursor-pointer">
                Cancelar
            </button>
            <button type="submit" form="formReservaCalificacion" class="flex-1 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-500/20 hover:opacity-95 transition-all cursor-pointer">
                Buscar Disponibilidad
            </button>
        </div>
    </aside>

    <!-- CONTROLADORES JAVASCRIPT -->
    <script>
        function verDetalleOpinionCalificacion(btn) {
            const res = JSON.parse(btn.getAttribute('data-opinion'));
            document.getElementById('detConductorOpinion').innerText = res.nombre_conductor || 'Conductor';
            document.getElementById('detFechaOpinion').innerText = res.fech_cal || 'Fecha no registrada';
            document.getElementById('detComentarioOpinion').innerText = res.coment_cal || 'Sin comentario escrito.';

            const overlay = document.getElementById('overlayCalificacion');
            const modal = document.getElementById('modalLecturaOpinion');
            const box = document.getElementById('modalOpinionBox');

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalOpinion() {
            const overlay = document.getElementById('overlayCalificacion');
            const modal = document.getElementById('modalLecturaOpinion');
            const box = document.getElementById('modalOpinionBox');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function abrirModalReservaCalificacion() {
            const drawer = document.getElementById('drawerReservaCalificacion');
            const overlay = document.getElementById('overlayCalificacion');

            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('input_fecha_calificacion').value = hoy;
            document.getElementById('input_fecha_calificacion').min = hoy;

            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100', 'pointer-events-auto');

            drawer.classList.remove('translate-x-full');
            drawer.classList.add('translate-x-0');
        }

        function cerrarModalDrawerCalificacion() {
            const drawer = document.getElementById('drawerReservaCalificacion');
            const overlay = document.getElementById('overlayCalificacion');

            drawer.classList.remove('translate-x-0');
            drawer.classList.add('translate-x-full');

            overlay.classList.remove('opacity-100', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        function cerrarTodosModalesCalificacion() {
            cerrarModalOpinion();
            cerrarModalDrawerCalificacion();
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
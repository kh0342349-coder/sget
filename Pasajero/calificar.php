<?php
/**
 * Pasajero/calificar.php
 * -----------------------------------------------------------------------------
 * CALIFICAR SERVICIO  (Pasajero)
 * -----------------------------------------------------------------------------
 * QUÉ CAMBIA EN ESTA SEGUNDA RONDA
 *   · Las tres consultas iban por mysqli con `$documento_sesion` en el SQL y
 *     repetían la búsqueda del usuario por documento. Ahora la identidad se
 *     toma de `Auth::id()` y todo se resuelve con `CalificacionService`, que
 *     además ya aplica la regla correcta: SOLO se puede calificar un viaje
 *     FINALIZADO y con reserva viva. Antes esta página listaba como «pendientes»
 *     viajes que ni siquiera habían salido, y el backend lo aceptaba.
 *   · `?id_via=…&id_cond=…` ya no permite mostrar el nombre de un conductor
 *     cualquiera: el nombre se resuelve desde el viaje REAL de la persona.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirSesion();
Auth::requerirRol(Config::ROL_PASAJERO);
Auth::requerirAcceso('calificar');

$nombreReal   = Auth::nombre();
$idPasajero   = Auth::id();

/* Los ids de la URL se normalizan a entero: nada de lo que venga por GET debe
   tocar la base de datos sin pasar por un tipo. */
$idViaje   = (int)($_GET['id_via'] ?? 0);
$idConductor = (int)($_GET['id_cond'] ?? 0);

$parametrosValidos = ($idViaje > 0 && $idConductor > 0);
$conductor = 'Conductor';

if ($parametrosValidos) {
    /* El nombre del conductor sale del viaje indicado, no del parámetro: así no
       se puede «poner» el nombre de otra persona en la pantalla. */
    $fila = Database::one(
        'SELECT u.nom_usu
           FROM viaje v
           INNER JOIN usuario u ON u.id_usu = v.id_usu_via
          WHERE v.id_via = ?',
        [$idViaje]
    );
    if ($fila) {
        $conductor = (string)$fila['nom_usu'];
    }
}

/* 2. Viajes pendientes de calificar: FINALIZADOS, con reserva viva y sin nota
      previa. Es la lista que devuelve el servicio, no una consulta local. */
$viajesPendientes = [];
foreach (CalificacionService::pendientes($idPasajero) as $fila) {
    if ($fila['pun_cal'] !== null) {
        continue;   // ya calificado
    }
    $viajesPendientes[] = [
        'id_via'          => (int)$fila['id_via'],
        'id_cond'         => (int)$fila['id_usu_via'],
        'nombre_conductor'=> (string)$fila['conductor'],
        'nom_rut'         => (string)$fila['nom_rut'],
        'fec_via'         => (string)$fila['fec_via'],
        'hor_sal_via'     => (string)$fila['hor_sal_via'],
    ];
}

/* 3. Historial de reseñas de este pasajero. */
$historialResenas = Database::all(
    'SELECT c.id_cal, c.id_via_cal, c.pun_cal, c.com_cal, c.fec_cal,
            u.nom_usu AS nombre_conductor
       FROM calificacion c
       LEFT JOIN viaje v   ON v.id_via  = c.id_via_cal
       LEFT JOIN usuario u ON u.id_usu  = v.id_usu_via
      WHERE c.id_usu_rem = ?
      ORDER BY c.fec_cal DESC, c.id_cal DESC',
    [$idPasajero]
);

// Rutas para el buscador de reserva.
$rutasDisponibles = Database::all('SELECT id_rut, nom_rut FROM rutas ORDER BY nom_rut ASC');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificar Servicio - SGET</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- SISTEMA VISUAL SGET (CSS modular): tema, componentes, modales y responsive -->
    <link rel="stylesheet" href="../assets/css/01-base.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/02-layout.css?v=<?= @filemtime('../assets/css/02-layout.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/03-componentes.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/04-modales.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/05-tablas.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/06-responsive.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/07-transiciones.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <script src="../assets/js/theme-init.js?v=<?= @filemtime('../assets/js/theme-init.js') ?: '1' ?>"></script>
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
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">
    <?php include '../includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 flex flex-col min-h-screen min-w-0">
        <?php include '../includes/header.php'; ?>

        <div class="flex-1 max-w-5xl w-full mx-auto p-6 md:p-8 space-y-8 min-w-0">
            
            <!-- ENCABEZADO DE LA SECCIÓN -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#1e293b]/50 p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Calificación de Servicio</h1>
                        
                        <!--
                             BOTÓN DE AYUDA DEL MÓDULO · RETIRADO
                             Este «?» por pantalla se sustituyó por UNO SOLO global en la
                             esquina inferior derecha (views/modals/ayuda.php), que además
                             cambia de contenido según el rol y el módulo. Con estos botones
                             repartidos, cada módulo llevaba su propia copia de la guía y se
                             desincronizaban entre sí.
                        -->

                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Registra tu opinión del trayecto y consulta el historial enviado.</p>
                </div>

                <button onclick="abrirModalReservaCalificación()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 hover:opacity-95 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition-all cursor-pointer whitespace-nowrap">
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
                            <?php /* Token anti-CSRF: sin él el endpoint devuelve
                                     «La sesión del formulario caducó» y la
                                     calificación NUNCA se guarda. */ ?>
                            <?= Auth::campoToken() ?>
                            <input type="hidden" name="id_via" value="<?= (int)$idViaje ?>">
                            <input type="hidden" name="id_cond" value="<?= (int)$idConductor ?>">

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
                                <textarea name="comentario" rows="3" placeholder="Ej.: Muy puntual y amable..." data-i18n-placeholder-es="Ej.: Muy puntual y amable..." data-i18n-placeholder-en="e.g. Very punctual and friendly..." class="w-full bg-slate-100 dark:bg-[#161e2e] border border-slate-200 dark:border-slate-800 rounded-xl px-5 py-3.5 text-xs font-medium text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-blue-500 transition resize-none"></textarea>
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
                                Pendientes: <?php echo count($viajesPendientes); ?>
                            </span>
                        </div>

                        <?php if (empty($viajesPendientes)): ?>
                            <div class="text-center py-8 text-slate-400 dark:text-slate-500">
                                <i class="fas fa-check-circle text-3xl mb-2 block text-emerald-500"></i>
                                <p class="text-xs font-medium">¡Todo en orden! No tienes viajes pendientes por calificar.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($viajesPendientes as $vp): ?>
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
                                        <button type="button"
                                                data-sget-modal="modalCalificar"
                                                data-sget-datos="<?= htmlspecialchars(json_encode([
                                                    'id_via_cal' => (int)$vp['id_via'],
                                                    'viaje'      => (string)$vp['nom_rut'] . ' · ' . Fecha::legible($vp['fec_via'], false) . ' ' . Fecha::soloHora($vp['hor_sal_via']),
                                                    'conductor'  => 'Conductor: ' . $vp['nombre_conductor'],
                                                ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
                                                class="px-4 py-2.5 bg-yellow-500 hover:bg-yellow-600 text-slate-950 font-black text-[10px] uppercase tracking-wider rounded-xl shadow-md transition-all whitespace-nowrap">
                                            Calificar
                                        </button>
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
                            <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Mis Calificaciónes Realizadas</h2>
                            <p class="text-slate-500 dark:text-slate-400 text-xs">Historial de las opiniones que has enviado a tus conductores</p>
                        </div>
                    </div>
                    <span class="text-xs font-bold px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-full">
                        Total: <?php echo count($historialResenas); ?>
                    </span>
                </div>

                <?php if (empty($historialResenas)): ?>
                    <div class="text-center py-8 text-slate-400 dark:text-slate-500">
                        <i class="fas fa-comment-slash text-3xl mb-2 block"></i>
                        <p class="text-xs font-medium">Aún no has dejado opiniones registradas en el sistema.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($historialResenas as $resena): ?>
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
                                                /* `puntos_cal` / `coment_cal` no existen: las columnas
                                                   reales son `pun_cal` y `com_cal`. Con los nombres
                                                   equivocados, todas las reseñas del historial se
                                                   veían como «5 estrellas, sin comentario». */
                                                $pts = (int)($resena['pun_cal'] ?? 0);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo ($i <= $pts) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-300 dark:text-slate-700"></i>';
                                                }
                                            ?>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 italic bg-white dark:bg-[#1e293b] p-3 rounded-xl border border-slate-100 dark:border-slate-800/50 line-clamp-2">
                                        "<?php echo htmlspecialchars(!empty($resena['com_cal']) ? $resena['com_cal'] : 'Sin comentario escrito.', ENT_QUOTES, 'UTF-8'); ?>"
                                    </p>
                                </div>

                                <div class="flex justify-end pt-1">
                                    <button type="button" 
                                            data-opinion='<?php echo $jsonRes; ?>'
                                            onclick="verDetalleOpinionCalificación(this)"
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
    <div id="overlayCalificación" onclick="cerrarTodosModalesCalificación()" class="fixed inset-0 bg-slate-950/60 backdrop-blur-md z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

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
    <!-- MODAL (antes panel lateral): drawerReservaCalificación -->
<div class="sget-modal-wrap" data-sget-capa data-titulo="drawerReservaCalificación">
    <div class="sget-overlay"></div>
    <aside id="drawerReservaCalificación" class="sget-modal sget-modal--sm sget-scroll">
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
            <button onclick="cerrarModalDrawerCalificación()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto space-y-5">
            <form id="formReservaCalificación" action="viajes_pasajero.php" method="GET" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Destino Deseado</label>
                    <select name="ruta" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                        <option value="">Selecciona tu ruta...</option>
                        <?php foreach ($rutasDisponibles as $r): ?>
                            <option value="<?= (int)$r['id_rut'] ?>"><?= htmlspecialchars((string)$r['nom_rut'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fecha Preferida</label>
                    <input type="date" name="fecha" id="input_fecha_calificacion" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalDrawerCalificación()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider transition-all cursor-pointer">
                Cancelar
            </button>
            <button type="submit" form="formReservaCalificación" class="flex-1 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-500/20 hover:opacity-95 transition-all cursor-pointer">
                Buscar Disponibilidad
            </button>
        </div>
    </aside>
</div>

    <!-- CONTROLADORES JAVASCRIPT -->
    <script>
        function verDetalleOpinionCalificación(btn) {
            const res = JSON.parse(btn.getAttribute('data-opinion'));
            document.getElementById('detConductorOpinion').innerText = res.nombre_conductor || 'Conductor';
            document.getElementById('detFechaOpinion').innerText = res.fech_cal || 'Fecha no registrada';
            document.getElementById('detComentarioOpinion').innerText = res.coment_cal || 'Sin comentario escrito.';

            const overlay = document.getElementById('overlayCalificación');
            const modal = document.getElementById('modalLecturaOpinion');
            const box = document.getElementById('modalOpinionBox');


            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalOpinion() {
            const overlay = document.getElementById('overlayCalificación');
            const modal = document.getElementById('modalLecturaOpinion');
            const box = document.getElementById('modalOpinionBox');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');

        }

        function abrirModalReservaCalificación() {
            const drawer = document.getElementById('drawerReservaCalificación');
            const overlay = document.getElementById('overlayCalificación');

            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('input_fecha_calificacion').value = hoy;
            document.getElementById('input_fecha_calificacion').min = hoy;


        }

        function cerrarModalDrawerCalificación() {
            const drawer = document.getElementById('drawerReservaCalificación');
            const overlay = document.getElementById('overlayCalificación');


        }

        function cerrarTodosModalesCalificación() {
            cerrarModalOpinion();
            cerrarModalDrawerCalificación();
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

    <?php include __DIR__ . '/../views/modals/calificar.php'; ?>

    <!-- Motor común de modales + puente de compatibilidad con el JS heredado -->
    <script src="../assets/js/sget-modal.js?v=<?= @filemtime('../assets/js/sget-modal.js') ?: '1' ?>"></script>
    <script src="../assets/js/sget-puente.js?v=<?= @filemtime('../assets/js/sget-puente.js') ?: '1' ?>"></script>
</body>
</html>
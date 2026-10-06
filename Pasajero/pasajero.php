<?php
/**
 * Pasajero/pasajero.php
 * -----------------------------------------------------------------------------
 * PANEL DEL PASAJERO
 * -----------------------------------------------------------------------------
 * QUÉ CAMBIA EN ESTA SEGUNDA RONDA
 *   · Las consultas iban por `mysqli` con `$documento` e `$id_pasajero`
 *     concatenados dentro del SQL. El valor venía de la sesión, así que no era
 *     inyección desde fuera, pero sí era SQL construido a mano con un patrón
 *     que, copiado una vez más, sí lo sería. Ahora la identidad se toma de
 *     `Auth::id()` (que es `int`) y todo va por PDO con sentencias preparadas.
 *   · Se añade el flujo que faltaba: REPORTAR. Antes la acción `reporte.crear`
 *     existía en la API pero quedaba detrás de `requerirAdmin()`, así que el
 *     pasajero no tenía ninguna forma de crear un reporte.
 *   · Se añade el enlace al COMPROBANTE en PDF de cada reserva, que existía
 *     (`Pasajero/generar_ticket.php`) pero no estaba enlazado desde ninguna
 *     página.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirSesion();
Auth::requerirRol(Config::ROL_PASAJERO);

$nombreReal   = Auth::nombre();
$idPasajero   = Auth::id();

/* 1. Reservas reales del pasajero (contadas por reserva, no por viaje). */
$totalViajes = (int) Database::scalar(
    'SELECT COUNT(*) FROM reserva WHERE id_usu_res = ?',
    [$idPasajero]
);

/* 2. Últimos 5 viajes con su calificación, para poder mostrar el botón. */
$historial = Database::all(
    'SELECT v.*, rt.nom_rut, res.fech_res, res.id_res,
            c.id_cal, v.id_usu_via, u.nom_usu AS nombre_conductor
       FROM reserva res
       INNER JOIN viaje v      ON res.id_via_res = v.id_via
       INNER JOIN rutas rt     ON v.id_rut_via  = rt.id_rut
       LEFT  JOIN usuario u    ON v.id_usu_via   = u.id_usu
       LEFT  JOIN calificacion c ON c.id_via_cal = v.id_via AND c.id_usu_rem = ?
      WHERE res.id_usu_res = ?
      ORDER BY res.fech_res DESC, res.id_res DESC
      LIMIT 5',
    [$idPasajero, $idPasajero]
);

/* 3. Rutas disponibles para el buscador de reserva. */
$rutasDisponibles = Database::all('SELECT id_rut, nom_rut FROM rutas ORDER BY nom_rut ASC');

/* 4. Viajes sobre los que este pasajero puede abrir un reporte. */
$viajesReportables = ReporteService::viajesReportables($idPasajero);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Panel Pasajero</title>
    <!-- Script para prevenir parpadeo de tema oscuro al cargar -->
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- SISTEMA VISUAL SGET (CSS modular): tema, componentes, modales y responsive -->
    <link rel="stylesheet" href="../assets/css/01-base.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/02-layout.css?v=<?= @filemtime('../assets/css/01-base.css') ?: '1' ?>">
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
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7'
                    }
                }
            }
        }
    </script>
    <!-- FontAwesome para Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Vinculación al CSS personalizado -->
    <link rel="stylesheet" href="../css/style.css">
    <style>
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
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">

    <!-- INCLUSIÓN DIRECTA DEL SIDEBAR FIJO -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- MAIN CON MARGEN IZQUIERDO (ml-64) PARA ALINEARSE AL SIDEBAR -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen min-w-0">

        <!-- HEADER MODULAR -->
        <?php include '../includes/header.php'; ?>

        <!-- CONTENIDO DEL DASHBOARD -->
        <div class="p-8 space-y-8 flex-1 min-w-0">
            
            <!-- Alerta de Calificación Exitosa -->
            <?php if (isset($_GET['res']) && $_GET['res'] == 'ok'): ?>
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl flex items-center justify-between shadow-lg shadow-emerald-950/20 animate-pulse max-w-6xl">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-lg"></i>
                        <span class="font-bold text-xs uppercase tracking-wider">¡Gracias! Tu calificación se guardó correctamente.</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-emerald-400 opacity-60 hover:opacity-100 transition-opacity">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Bienvenida con Título, Botón de Ayuda (?) y Botón Reserva (+) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#1e293b]/50 p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm max-w-6xl">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">¡Hola, <?php echo explode(' ', $nombreReal)[0]; ?>!</h1>
                        
                        <!--
                             BOTÓN DE AYUDA DEL MÓDULO · RETIRADO
                             Este «?» por pantalla se sustituyó por UNO SOLO global en la
                             esquina inferior derecha (views/modals/ayuda.php), que además
                             cambia de contenido según el rol y el módulo. Con estos botones
                             repartidos, cada módulo llevaba su propia copia de la guía y se
                             desincronizaban entre sí.
                        -->

                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Gestiona tus reservas de transporte y califica tus trayectos.</p>
                </div>

                <!-- ACCIONES PRINCIPALES DEL PANEL -->
                <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                    <button type="button" data-sget-modal="modalReporte"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-slate-300 dark:border-white/10 bg-white dark:bg-white/5 text-slate-600 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/10 font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer whitespace-nowrap">
                        <i class="fas fa-comment-dots text-sm"></i> Reportar
                    </button>
                    <button onclick="abrirModalReserva()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 hover:opacity-95 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition-all cursor-pointer whitespace-nowrap">
                        <i class="fas fa-plus-circle text-sm"></i> Reservar Viaje
                    </button>
                </div>
            </div>

            <!-- Grid de Tarjetas de Métricas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl">
                <!-- Tarjeta: Viajes Realizados -->
                <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl relative overflow-hidden flex items-center justify-between group hover:border-slate-300 dark:hover:border-white/10 transition-all duration-300 shadow-sm hover:shadow-md">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Viajes Realizados</p>
                        <h4 class="text-4xl font-black text-slate-900 dark:text-white"><?php echo $total_viajes; ?></h4>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                        <i class="fas fa-route text-lg"></i>
                    </div>
                </div>
            </div>

            <!-- Tabla de Historial de Viajes con scrollbar horizontal -->
            <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl max-w-6xl transition-colors duration-300">
                <h3 class="font-bold text-slate-900 dark:text-white text-base mb-4 tracking-tight flex items-center gap-2">
                    <i class="fas fa-history text-slate-400 dark:text-slate-500 text-sm"></i> Mis últimos viajes
                </h3>
                <div class="overflow-x-auto custom-scrollbar rounded-xl border border-slate-200 dark:border-white/5 w-full">
                    <table class="w-full text-sm text-left border-collapse min-w-[650px]">
                        <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                            <tr>
                                <th class="px-6 py-3.5">Ruta</th>
                                <th class="px-6 py-3.5">Fecha</th>
                                <th class="px-6 py-3.5">Hora</th>
                                <th class="px-6 py-3.5">Estado</th>
                                <th class="px-6 py-3.5 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                            <?php if (!empty($historial)): ?>
                                <?php foreach ($historial as $v): ?>
                                    <?php /* Solo un viaje FINALIZADO se puede calificar: la nota mide una
                                         experiencia que ya ocurrió. El backend lo vuelve a comprobar
                                         en `CalificacionService::puedeCalificar()`. */ ?>
                                    $sePuedeCalificar = ((string)$v['est_via'] === Config::VIA_FINALIZADO);
                                    $yaCalificado = !is_null($v['id_cal']); ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 rounded-lg flex items-center justify-center text-xs">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </div>
                                                <span class="font-bold text-slate-900 dark:text-white capitalize text-xs"><?php echo htmlspecialchars($v['nom_rut']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono"><?php echo date('d/m/Y', strtotime((string)$v['fech_res'])); ?></td>
                                        <td class="px-6 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono uppercase"><?php echo date('h:i A', strtotime((string)$v['hor_sal_via'])); ?></td>
                                        <td class="px-6 py-4">
                                            <?php if ($sePuedeCalificar): ?>
                                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 uppercase tracking-wider">
                                                    Llegaste
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black bg-yellow-500/10 text-yellow-600 dark:text-yellow-500 border border-yellow-500/20 uppercase tracking-wider">
                                                    <?php echo htmlspecialchars($v['est_via']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="inline-flex flex-wrap items-center justify-center gap-2">

                                                <!-- Comprobante en PDF. El backend vuelve a comprobar que
                                                     la reserva es de ESTE pasajero aunque alguien
                                                     edite el id del enlace. -->
                                                <a href="generar_ticket.php?id=<?= (int)$v['id_res'] ?>"
                                                   target="_blank" rel="noopener"
                                                   title="Comprobante de la reserva"
                                                   class="p-2 bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10 rounded-xl transition-all shadow-sm">
                                                    <i class="fas fa-receipt text-xs"></i>
                                                </a>

                                                <?php if ($yaCalificado): ?>
                                                    <div class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold text-[10px] uppercase tracking-wider">
                                                        <i class="fas fa-check-double text-xs"></i> Calificado
                                                    </div>
                                                <?php elseif ($sePuedeCalificar): ?>
                                                <button type="button"
                                                            data-sget-modal="modalCalificar"
                                                            data-sget-calificar-viaje="<?= (int)$v['id_via'] ?>"
                                                            data-sget-datos='<?= htmlspecialchars(json_encode([
                                                                'id_via_cal' => (int)$v['id_via'],
                                                                'viaje'      => (string)$v['nom_rut'] . ' · ' . Fecha::legible($v['fec_via'] ?? '', false) . ' ' . Fecha::soloHora($v['hor_sal_via'] ?? ''),
                                                                'conductor'  => 'Conductor: ' . ($v['nombre_conductor'] ?? 'Sin asignar'),
                                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                                                            class="inline-flex items-center gap-1.5 bg-yellow-500 hover:bg-yellow-400 text-slate-900 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase transition-all duration-200 shadow-md shadow-yellow-500/10 cursor-pointer">
                                                        <i class="fas fa-star text-[9px]"></i> Calificar
                                                </button>
                                                <?php else: ?>
                                                    <span class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-tight italic">En trayecto...</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500 italic">
                                        <i class="fas fa-ghost text-slate-300 dark:text-slate-700 text-3xl mb-3 block"></i>
                                        <span class="font-bold uppercase text-[10px] tracking-widest text-slate-400 dark:text-slate-500">No tienes registros de viajes aún.</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Banner Inferior de Acción -->
            <div class="mt-8 relative overflow-hidden bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl p-8 text-white max-w-6xl shadow-xl shadow-blue-950/20">
                <div class="relative z-10">
                    <h2 class="text-2xl font-black tracking-tight">¿A dónde quieres ir hoy?</h2>
                    <p class="text-xs text-blue-100 mt-1 max-w-md">Encuentra y reserva tus rutas de transporte de manera rápida y segura.</p>
                    <button onclick="abrirModalReserva()" class="inline-flex items-center gap-2 mt-5 bg-white text-blue-700 px-8 py-3 rounded-xl font-black uppercase text-xs hover:bg-slate-100 transition-colors shadow-lg shadow-blue-900/30 cursor-pointer">
                        <i class="fas fa-search text-[10px]"></i> Buscar Rutas
                    </button>
                </div>
                <div class="absolute -right-10 -bottom-10 text-white/5 text-9xl font-black pointer-events-none transform -rotate-12">
                    <i class="fas fa-bus"></i>
                </div>
            </div>
        </div>
    </main>

    <!-- OVERLAY GENERAL PARA MODALES Y DRAWER -->
<!-- 2. MODAL POP-UP DE CALIFICACIÓN INTERACTIVA -->
    <!--
         La calificación usa el modal común del sistema (views/modals/calificar.php).
         Este modal estaba duplicado aquí y enviaba a guardar_calificacion.php,
         un archivo que no existía: el sistema de reseñas no funcionaba.
    -->
<script>
        function abrirModalReserva() {
            const drawer = document.getElementById('drawerReservaPasajero');
            const overlay = document.getElementById('overlayPasajero');

            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('input_fecha_reserva').value = hoy;
            document.getElementById('input_fecha_reserva').min = hoy;




        }

        function cerrarModalDrawer() {
            const drawer = document.getElementById('drawerReservaPasajero');
            const overlay = document.getElementById('overlayPasajero');




        }







        function cerrarTodosModales() {
            cerrarModalDrawer();
        }

        // Script global de cambio de tema
        const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
        } else {
            if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
        }

        const themeToggleBtn = document.getElementById('theme-toggle');

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.toggle('hidden');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.toggle('hidden');

                if (localStorage.getItem('color-theme')) {
                    if (localStorage.getItem('color-theme') === 'light') {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('color-theme', 'dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('color-theme', 'light');
                    }
                } else {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('color-theme', 'light');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('color-theme', 'dark');
                    }
                }
            });
        }
    </script>

    <!-- Motor común de modales + puente de compatibilidad con el JS heredado -->
    <script src="../assets/js/sget-modal.js?v=<?= @filemtime('../assets/js/sget-modal.js') ?: '1' ?>"></script>
    <script src="../assets/js/sget-puente.js?v=<?= @filemtime('../assets/js/sget-puente.js') ?: '1' ?>"></script>

    <?php /* Modal COMÚN de calificación (ver views/modals/calificar.php).
             Guardar la reseña por el API es lo que hace que funcione: el
             formulario heredado enviaba a guardar_calificacion.php, inexistente. */ ?>
    <?php include __DIR__ . '/../views/modals/calificar.php'; ?>

    <?php /* Flujo de reporte del pasajero. La API valida, en el servidor, que el
             viaje sea suyo y que la descripción tenga sentido: ocultar el botón
             no es una medida de seguridad. */ ?>
    <?php
    $viajesParaReporte = [];
    foreach ($viajesReportables as $v) {
        $viajesParaReporte[] = [
            'id'       => (int)$v['id_via'],
            'etiqueta' => sprintf('%s · %s %s', (string)$v['nom_rut'],
                Fecha::legible((string)$v['fec_via'], false),
                Fecha::soloHora((string)$v['hor_sal_via'])),
            'estado'   => (string)$v['est_via'],
            'ya'       => (int)$v['ya_reportado'] > 0,
        ];
    }
    ?>
    <?php include __DIR__ . '/../views/modals/reporte.php'; ?>
</body>
</html>
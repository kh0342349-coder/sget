<?php
date_default_timezone_set('America/Bogota');
if (!class_exists('Auth')) {
    require_once __DIR__ . '/../core/bootstrap.php';
} elseif (session_status() === PHP_SESSION_NONE) {
    Auth::iniciar();
}

include '../assets/conexion.php'; 

// 1. Verificación de seguridad (Solo Conductor - Rol 2)
/* La guardia vive en `Auth`: una sola política de autorización para toda
   la aplicación. Antes cada página repetía su propio
   `if (!isset($_SESSION['documento']) || $_SESSION['rol'] != N)`. */
Auth::requerirSesion();
Auth::requerirRol(Config::ROL_CONDUCTOR);

$documento = $_SESSION['documento'];
$nombreReal = $_SESSION['nombre_usuario'] ?? "Conductor";

// 2. Obtener el ID del conductor
$stmt_user = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ?");
$stmt_user->bind_param("s", $documento);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

$id_conductor = 0;
if ($result_user && $result_user->num_rows > 0) {
    $user_data = $result_user->fetch_assoc();
    $id_conductor = $user_data['id_usu'];
} else {
    echo "Error: Conductor no encontrado.";
    exit();
}
$stmt_user->close();

// 3. Consultar viaje asignado activo
$sql_viaje = "SELECT 
        v.id_via,
        v.fec_via,
        v.est_via,
        v.cup_tot,
        v.cup_dis,
        v.val_via,
        u.nom_usu AS conductor_nombre,
        u.num_doc_usu AS conductor_doc,
        u.tel_usu AS conductor_telefono,
        veh.pla_veh,
        veh.mode_veh,
        veh.cap_veh,
        r.ori_rut,
        r.des_rut,
        r.dis_rut,
        r.val_rut,
        r.nom_rut
    FROM viaje v
    INNER JOIN usuario u ON v.id_usu_via = u.id_usu
    LEFT JOIN vehiculo veh ON v.id_veh = veh.id_veh
    INNER JOIN rutas r ON v.id_rut_via = r.id_rut
    WHERE v.id_usu_via = ?
      AND v.est_via IN (?, ?)
    /* ORDEN CORREGIDO
       Antes: `ORDER BY v.fec_via DESC` → el conductor veía el viaje MÁS ANTIGUO
       de los que tenía abiertos, casi siempre uno de hace semanas, en vez del
       que le toca ahora. Se ordena por urgencia real:
         1. el que ya está en curso,
         2. el que sale más pronto a partir de ahora,
         3. y los pasados, al final (deben cerrarse). */
    ORDER BY
      (v.est_via = ?) DESC,
      CASE WHEN v.fec_via >= CURDATE() THEN 0 ELSE 1 END,
      CASE WHEN v.fec_via >= CURDATE() THEN TIMESTAMP(v.fec_via, v.hor_sal_via) END ASC,
      v.fec_via DESC
    LIMIT 1";

$stmt_v = $conexion->prepare($sql_viaje);
// mysqli exige variables por referencia: los estados van a variables propias.
$viajeProgramado = Config::VIA_PROGRAMADO;
$viajeEnCurso    = Config::VIA_EN_CURSO;
$stmt_v->bind_param("isss", $id_conductor, $viajeProgramado, $viajeEnCurso, $viajeEnCurso);
$stmt_v->execute();
$res_viaje = $stmt_v->get_result();
$viaje = $res_viaje->fetch_assoc();
$stmt_v->close();

/*
 * Los pasajeros se piden al SERVICIO, no con SQL en la página.
 *
 * Antes esta página listaba una fila por RESERVA y pintaba el estado comparando
 * `estado_pago == 'pagado'`. Ese valor NO existe en el ENUM, que es
 * ('Pendiente','Confirmada','Cancelada'): el mismo tipo de error que el del
 * recaudo, y el efecto era que TODOS los pasajeros salían como «Pendiente»,
 * pagaran o no. Además un pasajero con 3 puestos aparecía 3 veces y el
 * conductor no tenía forma de anotar quién se quedó en casa.
 */
$manifiesto = [];
if ($viaje) {
    $manifiesto = ReservaService::manifiesto((int)$viaje['id_via']);
}
$totalPasajeros = 0;
$embarcaron     = 0;
$noSePresentaron= 0;
$sinDefinir     = 0;
foreach ($manifiesto as $m) {
    $totalPasajeros += $m['puestos'];
    $embarcaron     += $m['embarcaron'];
    $noSePresentaron+= $m['no_embarcaron'];
    $sinDefinir     += $m['sin_decidir'];
}

// Consultas secundarias para el Drawer (+)
$rutas_select = $conexion->query("SELECT id_rut, nom_rut, val_rut FROM rutas ORDER BY nom_rut ASC");
$stmt_vehiculos = $conexion->prepare("SELECT id_veh, pla_veh, mode_veh FROM vehiculo WHERE est_veh = ? ORDER BY pla_veh ASC");
// `bind_param` exige variables POR REFERENCIA: pasar la constante directamente
// es un error fatal (`Argument #2 cannot be passed by reference`).
$stmt_vehiculos_estado = Config::VEH_DISPONIBLE;
$stmt_vehiculos->bind_param("s", $stmt_vehiculos_estado);
$stmt_vehiculos->execute();
$vehiculos_select = $stmt_vehiculos->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Viaje - SGET</title>
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
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    
    <!-- Carga Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Contenedor Principal Ajustado al Sidebar -->
    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen min-w-0 transition-all duration-300">
        
        <!-- INCLUSIÓN DEL HEADER DEL CONDUCTOR -->
        <?php include '../includes/header.php'; ?>

        <!-- Cuerpo Principal -->
        <main class="p-8 space-y-6 flex-1 max-w-6xl min-w-0">
            
            <!-- ENCABEZADO DE PÁGINA CON TITULO, AYUDA (?) Y BOTÓN (+) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-[#1e293b]/50 p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Reporte de Viaje Asignado</h1>
                        
                        <!--
                             BOTÓN DE AYUDA DEL MÓDULO · RETIRADO
                             Este «?» por pantalla se sustituyó por UNO SOLO global en la
                             esquina inferior derecha (views/modals/ayuda.php), que además
                             cambia de contenido según el rol y el módulo. Con estos botones
                             repartidos, cada módulo llevaba su propia copia de la guía y se
                             desincronizaban entre sí.
                        -->

                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Detalle del servicio, itinerario y listado oficial de pasajeros abonados.</p>
                </div>

            </div>

            <?php if ($viaje): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- 1. Info Conductor & Vehículo -->
                    <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-xl space-y-4">
                        <div class="flex items-center gap-2 border-b border-slate-100 dark:border-white/5 pb-3">
                            <i class="fas fa-id-card text-blue-500 dark:text-neon-azul text-lg"></i>
                            <h2 class="font-bold text-slate-900 dark:text-white text-base">1. Conductor & Vehículo</h2>
                        </div>
                        <ul class="space-y-3 text-sm">
                            <li class="flex justify-between">
                                <span class="text-slate-400 dark:text-slate-500">Conductor:</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($viaje['conductor_nombre']) ?></span>
                            </li>
                            <li class="flex justify-between">
                                <span class="text-slate-400 dark:text-slate-500">Documento:</span>
                                <span class="font-mono text-slate-800 dark:text-slate-200"><?= htmlspecialchars($viaje['conductor_doc']) ?></span>
                            </li>
                            <li class="flex justify-between">
                                <span class="text-slate-400 dark:text-slate-500">Teléfono:</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($viaje['conductor_telefono'] ?? 'N/A') ?></span>
                            </li>
                            <li class="flex justify-between items-center">
                                <span class="text-slate-400 dark:text-slate-500">Vehículo:</span>
                                <span class="bg-blue-500/10 text-blue-600 dark:text-neon-azul border border-blue-500/20 font-black px-2.5 py-0.5 rounded-md uppercase text-xs">
                                    <?= htmlspecialchars($viaje['pla_veh'] ?? 'N/A') ?> (<?= htmlspecialchars($viaje['mode_veh'] ?? 'Modelo N/A') ?>)
                                </span>
                            </li>
                            <li class="flex justify-between">
                                <span class="text-slate-400 dark:text-slate-500">Capacidad Máxima:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($viaje['cap_veh'] ?? $viaje['cup_tot']) ?> Pasajeros</span>
                            </li>
                        </ul>
                    </div>

                    <!-- 2. Detalles del Viaje y Ruta -->
                    <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-xl flex flex-col justify-between space-y-4">
                        <div>
                            <div class="flex items-center gap-2 border-b border-slate-100 dark:border-white/5 pb-3 mb-4">
                                <i class="fas fa-route text-indigo-500 dark:text-neon-morado text-lg"></i>
                                <h2 class="font-bold text-slate-900 dark:text-white text-base">2. Detalles de Ruta</h2>
                            </div>
                            <ul class="space-y-3 text-sm">
                                <li class="flex justify-between">
                                    <span class="text-slate-400 dark:text-slate-500">Ruta:</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200 capitalize"><?= htmlspecialchars($viaje['ori_rut'] ?? 'Origen') ?> &rrarr; <?= htmlspecialchars($viaje['des_rut'] ?? 'Destino') ?></span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="text-slate-400 dark:text-slate-500">Distancia Estimada:</span>
                                    <span class="font-mono text-slate-800 dark:text-slate-200"><?= htmlspecialchars($viaje['dis_rut'] ?? '0') ?> km</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="text-slate-400 dark:text-slate-500">Fecha y Hora Programada:</span>
                                    <span class="font-mono text-slate-800 dark:text-slate-200"><?= !empty($viaje['fec_via']) ? date('d/m/Y - h:i A', strtotime($viaje['fec_via'])) : 'N/A' ?></span>
                                </li>
                                <li class="flex justify-between items-center">
                                    <span class="text-slate-400 dark:text-slate-500">Estado del Viaje:</span>
                                    <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-bold px-2.5 py-0.5 rounded-md text-xs uppercase">
                                        <?= htmlspecialchars($viaje['est_via']) ?>
                                    </span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="text-slate-400 dark:text-slate-500">Disponibilidad:</span>
                                    <span class="font-bold text-amber-500"><?= htmlspecialchars($viaje['cup_dis']) ?> cupos libres / <?= htmlspecialchars($viaje['cup_tot']) ?> totales</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Botón para finalizar viaje.
                             Solo aparece si el viaje puede terminarse de verdad; si aún no
                             ha salido, se explica por qué. La regla es la misma que aplica
                             el backend (`ViajeService::puedeFinalizar()`). -->
                        <?php [$puedeFinalizar, $motivoFinalizar] = ViajeService::puedeFinalizar($viaje); ?>
                        <div class="pt-4 border-t border-slate-100 dark:border-white/5">
                            <?php if ($puedeFinalizar): ?>
                                <button type="button" 
                                        onclick="confirmarFinalizarReporte(<?= (int)$viaje['id_via'] ?>, '<?= htmlspecialchars($viaje['des_rut'] ?? 'Ruta', ENT_QUOTES, 'UTF-8') ?>')"
                                        class="w-full flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl text-xs uppercase tracking-wider transition-all duration-200 shadow-lg shadow-emerald-600/20 active:scale-[0.98] cursor-pointer">
                                    <i class="fas fa-flag-checkered text-sm"></i>
                                    Finalizar Viaje
                                </button>
                            <?php else: ?>
                                <p class="flex items-start gap-2 text-[11px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl px-3.5 py-3">
                                    <i class="fas fa-circle-info mt-0.5 shrink-0"></i>
                                    <span><?= htmlspecialchars($motivoFinalizar, ENT_QUOTES, 'UTF-8') ?></span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 3. Lista de Pasajeros -->
                <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl space-y-4">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-white/5 pb-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-users text-emerald-500 text-lg"></i>
                            <h2 class="font-bold text-slate-900 dark:text-white text-base">3. Pasajeros de este viaje</h2>
                        </div>
                        <?php if ($viaje && (string)$viaje['est_via'] === Config::VIA_EN_CURSO): ?>
                            <button type="button" class="sget-btn sget-btn--primario sget-btn--sm"
                                    data-sget-modal="modalPasajeroTemporal">
                                <i class="fas fa-user-plus"></i> Agregar pasajero en ruta
                            </button>
                        <?php endif; ?>
                        <?php if ($totalPasajeros > 0): ?>
                            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-white/5">
                                    <?= (int)$totalPasajeros ?> puesto(s)
                                </span>
                                <?php
                                $recaudadoTotal = array_sum(array_map(static fn($m): float => (float)($m['pagados'] ?? 0) * (float)($m['debe'] ?? 0), $manifiesto));
                                ?>
                                <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    <i class="fas fa-coins mr-1"></i> Recaudado: $<?= number_format($recaudadoTotal, 0, ',', '.') ?>
                                </span>
                                <?php if ($embarcaron > 0): ?>
                                    <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        <?= (int)$embarcaron ?> embarcaron
                                    </span>
                                <?php endif; ?>
                                <?php if ($noSePresentaron > 0): ?>
                                    <span class="px-2.5 py-1 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                        <?= (int)$noSePresentaron ?> no se presentaron
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($manifiesto)): ?>
                        <p class="text-xs text-slate-500 dark:text-slate-400 flex items-start gap-2">
                            <i class="fas fa-circle-info text-sky-500 mt-0.5"></i>
                            Anota quién sube al bus. Los que no se presenten se marcan con un motivo: es lo que
                            después aparece en los informes y lo que explica por qué un pasajero no figura
                            entre los que viajaron.
                        </p>

                        <div class="overflow-x-auto custom-scrollbar rounded-xl border border-slate-200 dark:border-white/5 w-full">
                            <table class="w-full text-sm text-left border-collapse min-w-[720px]">
                                <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                                    <tr>
                                        <th class="px-5 py-3.5">Pasajero</th>
                                        <th class="px-5 py-3.5">Tel&eacute;fono</th>
                                        <th class="px-5 py-3.5 text-center">Puestos</th>
                                        <th class="px-5 py-3.5 text-center">Pago</th>
                                        <th class="px-5 py-3.5 text-center">Embarque</th>
                                        <th class="px-5 py-3.5 text-right">Acci&oacute;nes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                                    <?php foreach ($manifiesto as $m): ?>
                                        <?php
                                        $cancelado   = $m['cancelados'] > 0 && $m['pagados'] === 0 && $m['pendientes'] === 0;
                                        $todoPagado  = $m['pendientes'] === 0 && !$cancelado;
                                        $embarcado   = $m['embarcaron'] > 0 && $m['no_embarcaron'] === 0 && $m['sin_decidir'] === 0;
                                        $noVino      = $m['no_embarcaron'] > 0 && $m['embarcaron'] === 0 && $m['sin_decidir'] === 0;
                                        $pagoAlAbordar = $m['pendientes'] > 0 && $m['pendientes_al_abordar'] === $m['pendientes'];
                                        $cobrarYEmbarcar = $pagoAlAbordar && $m['sin_decidir'] > 0 && $m['no_embarcaron'] === 0;
                                        ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors<?= $cancelado ? ' opacity-50' : '' ?>">
                                            <td class="px-5 py-4">
                                                <div class="font-semibold capitalize text-slate-900 dark:text-white">
                                                    <?= htmlspecialchars($m['pasajero'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <div class="font-mono text-[10px] text-slate-400">
                                                    <?= htmlspecialchars($m['num_doc_usu'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <?php if (!empty($m['es_temporal'])): ?>
                                                    <span class="sget-badge sget-badge--info" style="margin-top:.25rem">Pasajero en ruta</span>
                                                    <?php foreach ($m['tramos_temporales'] as $tramo): ?>
                                                        <div class="sget-help">
                                                            <?= htmlspecialchars($tramo['origen'] . ' → ' . $tramo['destino'], ENT_QUOTES, 'UTF-8') ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <?php if ($m['motivo'] !== ''): ?>
                                                    <div class="text-[10px] text-rose-500 dark:text-rose-400 mt-1">
                                                        <i class="fas fa-circle-info"></i> <?= htmlspecialchars($m['motivo'], ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-4 font-mono text-xs">
                                                <?= htmlspecialchars($m['tel_usu'] !== '' ? $m['tel_usu'] : 'Sin celular', ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="px-5 py-4 text-center font-mono font-bold"><?= (int)$m['puestos'] ?></td>
                                            <td class="px-5 py-4 text-center">
                                                <?php if ($cancelado): ?>
                                                    <span class="bg-slate-500/10 text-slate-500 border border-slate-500/20 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">Cancelada</span>
                                                <?php elseif ($todoPagado): ?>
                                                    <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                        <i class="fas fa-check-circle mr-1"></i> Pagada
                                                    </span>
                                                <?php elseif ($pagoAlAbordar): ?>
                                                    <span class="bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/30 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                        <i class="fas fa-coins mr-1"></i> Pendiente al abordar
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                        <i class="fas fa-clock mr-1"></i> <?= (int)$m['pendientes'] ?> pendiente(s)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-4 text-center">
                                                <?php if ($embarcado): ?>
                                                    <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                        <i class="fas fa-user-check mr-1"></i> Embarc&oacute;
                                                    </span>
                                                <?php elseif ($noVino): ?>
                                                    <span class="bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                        <i class="fas fa-user-slash mr-1"></i> No se present&oacute;
                                                    </span>
                                                <?php elseif ($m['embarcaron'] > 0 || $m['no_embarcaron'] > 0): ?>
                                                    <span class="bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                        Embarque parcial
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-slate-100 dark:bg-white/5 text-slate-500 border border-slate-200 dark:border-white/5 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                        <i class="fas fa-question mr-1"></i> Sin definir
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-4">
                                                <?php if (!$cancelado): ?>
                                                    <div class="flex items-center justify-end gap-2">
                                                        <?php if ($cobrarYEmbarcar): ?>
                                                            <button type="button"
                                                                    class="px-3 py-2 rounded-lg text-[10px] font-black uppercase tracking-wider bg-amber-500 hover:bg-amber-400 text-slate-950 transition-colors"
                                                                    data-sget-cobrar-embarcar="1"
                                                                    data-sget-pasajero="<?= (int)$m['id_pasajero'] ?>">
                                                                <i class="fas fa-money-bill-wave mr-1"></i> Confirmar pago y abordaje
                                                            </button>
                                                        <?php elseif ($m['pendientes'] === 0 && !empty($m['ids_por_embarcar'])): ?>
                                                            <button type="button"
                                                                    class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-600 hover:bg-emerald-700 text-white transition-colors"
                                                                    data-sget-embarcar="1"
                                                                    data-sget-reserva="<?= (int)$m['ids_por_embarcar'][0] ?>"
                                                                    title="Marcar el abordaje de un puesto pagado">
                                                                <i class="fas fa-user-check"></i> Confirmar abordaje
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($m['sin_decidir'] > 0): ?>
                                                            <button type="button"
                                                                    class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-colors bg-rose-600 hover:bg-rose-700 text-white"
                                                                    data-sget-embarcar="0"
                                                                    data-sget-pasajero="<?= (int)$m['id_pasajero'] ?>"
                                                                    title="Marcar que este pasajero no se presento (pide un motivo)">
                                                                <i class="fas fa-user-slash"></i> No vino
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="py-12 text-center text-slate-400 dark:text-slate-500 italic">
                            <i class="fas fa-info-circle text-2xl mb-2 text-amber-500 block"></i>
                            No hay pasajeros reservados para este viaje a&uacute;n.
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="p-8 bg-amber-500/10 border border-amber-500/20 rounded-2xl text-amber-600 dark:text-amber-400 text-center space-y-2">
                    <i class="fas fa-exclamation-triangle text-3xl"></i>
                    <h3 class="font-bold text-base">Sin viajes activos asignados</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">No se encontró ningún viaje en estado activo asignado a tu cuenta de conductor.</p>
                </div>
            <?php endif; ?>
            
        </main>
    </div>

    <!-- OVERLAY GENERAL PARA MODALES Y DRAWER -->
<!-- MODAL POP-UP DE CONFIRMACIÓN PARA FINALIZAR VIAJE -->
    <div id="modalConfirmarFinReporte" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#1e293b] w-full max-w-sm rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-5 transform scale-95 transition-all duration-300 text-center" id="modalConfirmBoxReporte">
            <div class="w-12 h-12 bg-emerald-500/10 text-emerald-500 rounded-2xl flex items-center justify-center text-xl mx-auto border border-emerald-500/20">
                <i class="fas fa-flag-checkered"></i>
            </div>

            <div>
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base">¿Finalizar Viaje?</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" id="txtConfirmDestinoReporte"></p>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="cerrarModalConfirmar()" class="flex-1 py-2.5 bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 font-bold text-xs rounded-xl uppercase tracking-wider cursor-pointer">
                    Cancelar
                </button>
                <form method="POST" action="finalizar_viaje.php" id="formFinalizarReporte" class="flex-1 flex">
                    <?= Auth::campoToken() ?>
                    <input type="hidden" name="id" id="inputFinalizarReporteId" value="">
                    <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl uppercase tracking-wider shadow-lg shadow-emerald-600/20 text-center flex items-center justify-center cursor-pointer">
                        Sí, Finalizar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- PANEL LATERAL DESLIZANTE (DRAWER (+)) DE PROGRAMACIÓN -->
    <!-- MODAL (antes panel lateral): drawerProgramarReporte -->
<div class="sget-modal-wrap" data-sget-capa data-titulo="drawerProgramarReporte">
    <div class="sget-overlay"></div>
    <aside id="drawerProgramarReporte" class="sget-modal sget-modal--sm sget-scroll">
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between relative">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-neon-morado"></div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-500/10 text-blue-500 dark:text-neon-azul rounded-xl flex items-center justify-center border border-slate-100 dark:border-white/5">
                    <i class="fas fa-bus text-base"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Programar Nuevo Viaje</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Despachar ruta para tu vehículo</p>
                </div>
            </div>
            <button onclick="cerrarModalDrawer()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-all cursor-pointer">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="p-6 flex-1 overflow-y-auto space-y-5">
            <!-- Envío al API unificado (api/index.php). Antes apuntaba a
                 Admin/procesar_viaje.php, un adaptador ya retirado. -->
            <form id="formProgramarReporte" method="POST" class="space-y-4">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="modulo" value="viaje">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id_usu_via" id="inputUsuVia" value="<?= (int)$id_conductor ?>">

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Seleccionar Ruta</label>
                    <select name="id_rut_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all cursor-pointer">
                        <option value="">Selecciona tu ruta...</option>
                        <?php 
                        if($rutas_select) {
                            $rutas_select->data_seek(0);
                            while($r = $rutas_select->fetch_assoc()) {
                                echo '<option value="'.$r['id_rut'].'">'.htmlspecialchars($r['nom_rut']).' ($'.number_format($r['val_rut'], 0, ',', '.').')</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Vehículo Asignado</label>
                    <select name="id_veh_via" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all cursor-pointer">
                        <option value="">Selecciona tu vehículo...</option>
                        <?php 
                        if($vehiculos_select) {
                            $vehiculos_select->data_seek(0);
                            while($v = $vehiculos_select->fetch_assoc()) {
                                echo '<option value="'.$v['id_veh'].'">Placa: '.htmlspecialchars($v['pla_veh']).' ('.htmlspecialchars($v['mode_veh'] ?? 'N/A').')</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Fecha Salida</label>
                        <input type="date" name="fec_via" id="input_fec_reporte" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Hora Salida</label>
                        <input type="time" name="hor_sal_via" id="input_hor_reporte" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#0b0f19]/60 border border-slate-200 dark:border-white/5 rounded-xl outline-none focus:border-neon-azul text-slate-800 dark:text-white text-sm transition-all">
                    </div>
                </div>
            </form>
        </div>

        <div class="p-6 border-t border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/10 flex gap-3">
            <button type="button" onclick="cerrarModalDrawer()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-400 rounded-xl font-bold text-xs uppercase tracking-wider transition-all cursor-pointer">
                Cancelar
            </button>
            <button type="submit" form="formProgramarReporte" class="flex-1 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 dark:from-neon-azul dark:to-blue-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-lg shadow-blue-500/20 hover:opacity-95 transition-all cursor-pointer">
                Iniciar Despacho
            </button>
        </div>
    </aside>
</div>

    <!-- CONTROLADORES JAVASCRIPT -->
    <script>
        /* ------------------------------------------------------------------
           Envío del despacho al API (api/index.php).
           Se usa fetch para poder mostrar el error concreto por campo en vez
           de dejar al conductor frente a un JSON crudo.
           ------------------------------------------------------------------ */
        document.getElementById('formProgramarReporte').addEventListener('submit', async function (e) {
            e.preventDefault();
            const form = e.target;
            const btn  = document.querySelector('button[form="formProgramarReporte"]');
            const original = btn ? btn.innerHTML : '';

            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Enviando...'; }

            try {
                const respuesta = await fetch('../api/index.php', {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                const json = await respuesta.json();

                if (json.status === 'ok') {
                    alert(json.mensaje || 'Despacho programado correctamente.');
                    window.location.href = '../Conductor/viaje_asignado.php?ok=1';
                    return;
                }

                // Pintar el error en el campo concreto
                if (json.errores) {
                    for (const [campo, mensaje] of Object.entries(json.errores)) {
                        const input = form.querySelector('[name="' + campo + '"]');
                        if (input) {
                            input.style.borderColor = 'var(--sget-rojo)';
                            input.setAttribute('aria-invalid', 'true');
                        }
                        const caja = form.querySelector('[data-campo="' + campo + '"]');
                        if (caja) {
                            const err = document.createElement('span');
                            err.className = 'sget-error';
                            err.setAttribute('data-visible', '1');
                            err.style.display = 'flex';
                            err.innerHTML = '<i class="fas fa-circle-exclamation"></i><span>' + mensaje + '</span>';
                            caja.appendChild(err);
                        }
                    }
                }
                alert(json.mensaje || 'No se pudo programar el despacho.');
            } catch (error) {
                alert('Error de conexión con el servidor. Inténtalo de nuevo.');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = original; }
            }
        });

        function abrirModalSolicitar() {
            const drawer = document.getElementById('drawerProgramarReporte');
            const overlay = document.getElementById('overlayReporte');

            const hoy = new Date();
            const fechaHoy = hoy.toISOString().split('T')[0];
            const horaHoy = hoy.toTimeString().split(' ')[0].substring(0, 5);

            document.getElementById('input_fec_reporte').value = fechaHoy;
            document.getElementById('input_fec_reporte').min = fechaHoy;
            document.getElementById('input_hor_reporte').value = horaHoy;




        }

        function cerrarModalDrawer() {
            const drawer = document.getElementById('drawerProgramarReporte');
            const overlay = document.getElementById('overlayReporte');




        }

        function confirmarFinalizarReporte(idViaje, nombreRuta) {
            document.getElementById('inputFinalizarReporteId').value = idViaje;
            document.getElementById('txtConfirmDestinoReporte').innerText = 'Confirma que el vehículo llegó a su destino (' + nombreRuta + ') para cambiar tu estado a disponible.';

            const overlay = document.getElementById('overlayReporte');
            const modal = document.getElementById('modalConfirmarFinReporte');
            const box = document.getElementById('modalConfirmBoxReporte');



            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.classList.add('opacity-100', 'pointer-events-auto');

            box.classList.remove('scale-95');
            box.classList.add('scale-100');
        }

        function cerrarModalConfirmar() {
            const overlay = document.getElementById('overlayReporte');
            const modal = document.getElementById('modalConfirmarFinReporte');
            const box = document.getElementById('modalConfirmBoxReporte');

            box.classList.remove('scale-100');
            box.classList.add('scale-95');

            modal.classList.remove('opacity-100', 'pointer-events-auto');
            modal.classList.add('opacity-0', 'pointer-events-none');


        }

        function cerrarTodosModales() {
            cerrarModalDrawer();
            cerrarModalConfirmar();
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

            sincronizarInterfaz(window.SGETTheme.get() === 'dark');

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    const nuevoTema = window.SGETTheme.get() === 'dark' ? 'light' : 'dark';
                    window.SGETTheme.set(nuevoTema);
                    sincronizarInterfaz(nuevoTema === 'dark');
                });
            }

            window.addEventListener('storage', function(e) {
                if (e.key === 'theme' && (e.newValue === 'dark' || e.newValue === 'light')) {
                    window.SGETTheme.set(e.newValue);
                    sincronizarInterfaz(e.newValue === 'dark');
                }
            });
        });
    </script>

    <!-- Motor común de modales + puente de compatibilidad con el JS heredado -->
    <?php if ($viaje && (string)$viaje['est_via'] === Config::VIA_EN_CURSO): ?>
        <?php $viajeTemporal = [
            'id_via' => (int)$viaje['id_via'],
            'origen' => (string)($viaje['ori_rut'] ?? ''),
            'destino' => (string)($viaje['des_rut'] ?? ''),
            'tarifa' => (float)($viaje['val_via'] ?? $viaje['val_rut'] ?? 0),
        ]; ?>
        <?php include __DIR__ . '/../views/modals/pasajero-temporal.php'; ?>
    <?php endif; ?>

    <script src="../assets/js/sget-modal.js?v=<?= @filemtime('../assets/js/sget-modal.js') ?: '1' ?>"></script>
    <script src="../assets/js/sget-puente.js?v=<?= @filemtime('../assets/js/sget-puente.js') ?: '1' ?>"></script>
    <?php if ($viaje && (string)$viaje['est_via'] === Config::VIA_EN_CURSO): ?>
        <script src="../assets/js/sget-pasajero-temporal.js?v=<?= @filemtime('../assets/js/sget-pasajero-temporal.js') ?: '1' ?>"></script>
    <?php endif; ?>

    <script>
    /* MARCAJE DE EMBARQUE DEL CONDUCTOR
       ---------------------------------------------------------------
       El conductor es quien sabe en el paradero quién subió y quién no. Con esa
       información el informe puede decir "estosDEFF viajaron" y "este se quedó
       en casa porque…", y el pasajero recibe el aviso de que perdió el viaje.

       OJO con dos cosas:
         · hace falta el token anti-CSRF y esta página no lo tenía: sin él el API
           responds 419 y el botón no hacía nada en silencio;
         · el API valida que el viaje sea SUYO (ViajeService::puedeVerManifiesto),
           así que un conductor no puede marcar pasajeros de otro viaje aunque
           manipule la petición. */
    (function () {
        'use strict';
        if (!<?= $viaje ? (int)$viaje['id_via'] : 0 ?>) return;

        var ID_VIAJE = <?= $viaje ? (int)$viaje['id_via'] : 0 ?>;
        var TOKEN   = <?= json_encode(Auth::token()) ?>;

        // Motivos rápidos: el conductor elige uno y ya está escrito. Se puede
        // escribir otro, pero casi siempre es uno de estos.
        var MOTIVOS = [
            'No apareció en el paradero',
            'Llegó tarde, el bus ya había salido',
            'Presentó una justificación',
            'Canceló por teléfono y no avisó',
            'Se equivocó de viaje'
        ];

        function api(accion, extra, alTerminar) {
            var cuerpo = new FormData();
            cuerpo.append('_token', TOKEN);
            cuerpo.append('modulo', 'reserva');
            cuerpo.append('accion', accion);
            cuerpo.append('id_via', ID_VIAJE);
            extra(cuerpo);
            return fetch('../api/index.php', {
                method: 'POST', body: cuerpo, credentials: 'same-origin'
            }).then(function (r) { return r.json(); })
              .then(function (j) {
                  SGETModal.toast(j.mensaje, j.status === 'ok' ? 'exito' : 'error');
                  if (j.status === 'ok' && alTerminar) alTerminar();
              })
              .catch(function () { SGETModal.toast('No se pudo conectar con el servidor.', 'error'); });
        }
        document.addEventListener('click', function (e) {
            var cobrarYEmbarcar = e.target.closest('[data-sget-cobrar-embarcar]');
            if (cobrarYEmbarcar) {
                e.preventDefault();
                api('cobrarYEmbarcar', function (c) {
                    c.append('id_usu', cobrarYEmbarcar.dataset.sgetPasajero);
                }, function () { location.reload(); });
                return;
            }

            var btn = e.target.closest('[data-sget-embarcar]');
            if (!btn) return;
            e.preventDefault();

            var idPasajero = btn.dataset.sgetPasajero;
            var embarco    = btn.dataset.sgetEmbarcar === '1';

            // Subió: no hay nada que preguntar, se registra y se recarga.
            if (embarco) {
                api('embarcar', function (c) {
                    c.append('id', btn.dataset.sgetReserva);
                    c.append('embarco', '1');
                }, function () { location.reload(); });
                return;
            }

            // No vino: el motivo es OBLIGATORIO. Sin él el informe no puede
            // distinguir un no-show de una reserva que el pasajero canceló.
            var opciones = MOTIVOS.map(function (m) {
                return '<option value="' + m + '">' + m + '</option>';
            }).join('');

            SGETModal.confirmar({
                tipo: 'peligro',
                icono: 'fa-user-slash',
                titulo: 'Registrar no-presentación',
                cuerpo: '<p style="margin-bottom:.75rem">Explica por qué este pasajero no se presentó. '
                      + 'Queda escrito en el informe del viaje.</p>'
                      + '<select id="sgetMotivoNoPresente" class="sget-select" style="width:100%">'
                      + '<option value="">Elige un motivo…</option>' + opciones + '</select>'
                      + '<input id="sgetMotivoLibre" class="sget-input" style="width:100%;margin-top:.5rem" '
                      + 'placeholder="O escribe otro motivo…" maxlength="120">',
                textoOk: 'Registrar'
            }).then(function () {
                var sel  = document.getElementById('sgetMotivoNoPresente');
                var libre= document.getElementById('sgetMotivoLibre');
                var motivo = (libre && libre.value.trim()) || (sel ? sel.value.trim() : '');

                if (!motivo) {
                    SGETModal.toast('Elige o escribe un motivo: sin él el informe no explica nada.', 'error');
                    return;
                }
                api('noPresente', function (c) {
                    c.append('id_usu', idPasajero);
                    c.append('motivo', motivo);
                }, function () { location.reload(); });
            });
        });
    })();
    </script>
</body>
</html>
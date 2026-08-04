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
      AND v.est_via NOT IN ('Finalizado', 'Terminado', 'Completado', '0', '2')
    ORDER BY v.fec_via DESC
    LIMIT 1";

$stmt_v = $conexion->prepare($sql_viaje);
$stmt_v->bind_param("i", $id_conductor);
$stmt_v->execute();
$res_viaje = $stmt_v->get_result();
$viaje = $res_viaje->fetch_assoc();
$stmt_v->close();

$pasajeros = [];
if ($viaje) {
    // 4. Consultar pasajeros del viaje
    $sql_pasajeros = "SELECT 
            res.id_res,
            res.fech_res,
            res.metodo_pago,
            res.valor_pagado,
            res.estado_pago,
            pas.nom_usu AS pasajero_nombre,
            pas.tel_usu AS pasajero_telefono,
            pas.corre_usu AS pasajero_correo
        FROM reserva res
        INNER JOIN usuario pas ON res.id_usu_res = pas.id_usu
        WHERE res.id_via_res = ?";
    
    $stmt_p = $conexion->prepare($sql_pasajeros);
    $stmt_p->bind_param("i", $viaje['id_via']);
    $stmt_p->execute();
    $res_pasajeros = $stmt_p->get_result();
    
    while ($row_p = $res_pasajeros->fetch_assoc()) {
        $pasajeros[] = $row_p;
    }
    $stmt_p->close();
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Viaje - SGET</title>
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

    <!-- Contenedor Principal -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- INCLUSIÓN DEL HEADER DEL CONDUCTOR -->
        <?php include 'header_conductor.php'; ?>

        <!-- Cuerpo Principal -->
        <div class="p-8 space-y-6 flex-1 max-w-6xl">
            
            <!-- ENCABEZADO DE PÁGINA LIMPIO -->
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight uppercase">Reporte de Viaje Asignado</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Detalle del servicio, itinerario y listado oficial de pasajeros abonados.</p>
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

                    <!-- 2. Detalles del Viaje y Ruta + BOTÓN INTEGRADO -->
                    <div class="bg-white dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-xl flex flex-col justify-between space-y-4">
                        <div>
                            <div class="flex items-center gap-2 border-b border-slate-100 dark:border-white/5 pb-3 mb-4">
                                <i class="fas fa-route text-indigo-500 dark:text-neon-morado text-lg"></i>
                                <h2 class="font-bold text-slate-900 dark:text-white text-base">2. Detalles de Ruta</h2>
                            </div>
                            <ul class="space-y-3 text-sm">
                                <li class="flex justify-between">
                                    <span class="text-slate-400 dark:text-slate-500">Ruta:</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-200 capitalize"><?= htmlspecialchars($viaje['ori_rut'] ?? 'Origen') ?> &rarr; <?= htmlspecialchars($viaje['des_rut'] ?? 'Destino') ?></span>
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

                        <!-- Botón ubicado de forma destacada al final de la tarjeta de ruta -->
                        <div class="pt-4 border-t border-slate-100 dark:border-white/5">
                            <a href="finalizar_viaje.php?id=<?= $viaje['id_via'] ?>" 
                               onclick="return confirm('¿Estás seguro de que deseas finalizar este viaje? Tu estado cambiará a disponible.');"
                               class="w-full flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl text-xs uppercase tracking-wider transition-all duration-200 shadow-lg shadow-emerald-600/20 active:scale-[0.98]">
                                <i class="fas fa-flag-checkered text-sm"></i>
                                Finalizar Viaje
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 3. Lista de Pasajeros -->
                <div class="bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/5 p-6 rounded-2xl shadow-xl space-y-4">
                    <div class="flex items-center gap-2 border-b border-slate-100 dark:border-white/5 pb-3">
                        <i class="fas fa-users text-emerald-500 text-lg"></i>
                        <h2 class="font-bold text-slate-900 dark:text-white text-base">3. Pasajeros Asignados y Reservas</h2>
                    </div>

                    <?php if (count($pasajeros) > 0): ?>
                        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-white/5">
                            <table class="w-full text-sm text-left border-collapse">
                                <thead class="text-slate-500 dark:text-slate-400 uppercase text-[10px] font-black tracking-widest bg-slate-100/70 dark:bg-[#0b0f19]/50 border-b border-slate-200 dark:border-white/5">
                                    <tr>
                                        <th class="px-5 py-3.5"># Reserva</th>
                                        <th class="px-5 py-3.5">Pasajero</th>
                                        <th class="px-5 py-3.5">Teléfono</th>
                                        <th class="px-5 py-3.5">Correo</th>
                                        <th class="px-5 py-3.5">Método Pago</th>
                                        <th class="px-5 py-3.5 text-center">Estado Pago</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-white/5 text-slate-700 dark:text-slate-200">
                                    <?php foreach ($pasajeros as $p): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="px-5 py-4 font-mono font-bold text-blue-600 dark:text-neon-azul">
                                            RES-<?= str_pad($p['id_res'], 3, '0', STR_PAD_LEFT) ?>
                                        </td>
                                        <td class="px-5 py-4 font-semibold capitalize text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($p['pasajero_nombre']) ?>
                                        </td>
                                        <td class="px-5 py-4 font-mono text-xs">
                                            <?= htmlspecialchars($p['pasajero_telefono'] ?? 'Sin celular') ?>
                                        </td>
                                        <td class="px-5 py-4 text-xs text-slate-400">
                                            <?= htmlspecialchars($p['pasajero_correo'] ?? 'Sin correo') ?>
                                        </td>
                                        <td class="px-5 py-4 text-xs uppercase font-medium">
                                            <?= htmlspecialchars($p['metodo_pago'] ?? 'Efectivo') ?>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <?php if (strtolower($p['estado_pago'] ?? '') == 'pagado'): ?>
                                                <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                    <i class="fas fa-check-circle mr-1"></i> Pagado ($<?= number_format($p['valor_pagado'] ?? 0, 0) ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-rose-500/10 text-rose-500 dark:text-rose-400 border border-rose-500/20 font-bold px-2.5 py-1 rounded-lg text-xs inline-block">
                                                    <i class="fas fa-clock mr-1"></i> Pendiente ($<?= number_format($p['valor_pagado'] ?? 0, 0) ?>)
                                                </span>
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
                            No hay reservas de pasajeros registradas para este viaje aún.
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
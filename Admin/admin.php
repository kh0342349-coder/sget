<?php
// Archivo: Admin/admin.php
date_default_timezone_set('America/Bogota');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!class_exists('Auth')) {
    require_once __DIR__ . '/../core/bootstrap.php';
} elseif (session_status() === PHP_SESSION_NONE) {
    Auth::iniciar();
}

// Cargar diccionario de idioma global
$idiomaActual = $_SESSION['sget_idioma'] ?? 'es';
if ($idiomaActual === 'en') {
    @include_once __DIR__ . '/../lang/en.php';
} else {
    @include_once __DIR__ . '/../lang/es.php';
}


// Verificación de seguridad para Administrador (Rol 1)
/* La guardia vive en `Auth` (una sola política para toda la aplicación). */
Auth::requerirSesion();
Auth::requerirAdmin();

Auth::requerirAcceso('admin');

$idUsuarioActual = Auth::id();
$nombreReal      = Auth::nombre();

/* ---------------------------------------------------------------------------
 * CONSULTAS OPERATIVAS DEL DASHBOARD
 * ---------------------------------------------------------------------------
 * Estaban escritas con `$conexion->query()` y, dos de ellas, con el mes
 * concatenado en el SQL. Además `usuarios_mes` era una COPIA de `total_usuarios`
 * (se le había olvidado la condición de fecha), así que el «crecimiento de
 * usuarios» era siempre 100 %.
 * ------------------------------------------------------------------------- */
$mes_actual  = date('m');
$anio_actual = date('Y');

// 1. Estadísticas de usuarios (totales y altas de ESTE mes)
$total_usuarios = (int) Database::scalar(
    'SELECT COUNT(*) FROM usuario WHERE id_rol_usu IN (?, ?)',
    [Config::ROL_CONDUCTOR, Config::ROL_PASAJERO]
);

$usuarios_mes = (int) Database::scalar(
    'SELECT COUNT(*) FROM usuario
      WHERE id_rol_usu IN (?, ?) AND estado = ?
        AND MONTH(COALESCE(fecha_acepta_politica, NOW())) = ? AND YEAR(COALESCE(fecha_acepta_politica, NOW())) = ?',
    [Config::ROL_CONDUCTOR, Config::ROL_PASAJERO, Config::USU_ACTIVO, (int)$mes_actual, (int)$anio_actual]
);
$porcentaje_usu = $total_usuarios > 0 ? round(($usuarios_mes / $total_usuarios) * 100, 1) : 0;

// 2. Estadísticas de viajes
$total_viajes = (int) Database::scalar('SELECT COUNT(*) FROM viaje');

$viajes_mes = (int) Database::scalar(
    'SELECT COUNT(*) FROM viaje WHERE MONTH(fec_via) = ? AND YEAR(fec_via) = ?',
    [(int)$mes_actual, (int)$anio_actual]
);
$porcentaje_via = $total_viajes > 0 ? round(($viajes_mes / $total_viajes) * 100, 1) : 0;

// 3. Estado de la Flota de Vehículos
/* Los cuatro estados reales de la unidad, cada uno con su propia tarjeta.
   Antes se agrupaban en 'Activo'/'Inactivo', que escondía justo lo que había que
   ver: cuántas unidades están en el taller y cuántas retiradas. */
$vehiculos = [
    Config::VEH_DISPONIBLE     => 0,
    Config::VEH_ASIGNADO       => 0,
    Config::VEH_MANTENIMIENTO  => 0,
    Config::VEH_FUERA_SERVICIO => 0,
];
foreach (VehiculoService::todos() as $row) {
    $clave = VehiculoService::normalizar((string)$row['est_veh']);
    $vehiculos[$clave] = ($vehiculos[$clave] ?? 0) + 1;
}

// 4. Conductores disponibles
/* Fuente de verdad: `viaje`. Antes se leía la tabla `asignacion`, que
   duplicaba la relación conductor→vehículo y además podía contradecirla
   (si el viaje se reasignaba, `asignacion` se quedaba con el valor viejo). */
$conductores_disponibles = Database::all(
    'SELECT u.id_usu, u.nom_usu, veh.pla_veh
       FROM usuario u
       LEFT JOIN vehiculo veh
              ON veh.id_veh = (SELECT v2.id_veh FROM viaje v2
                                WHERE v2.id_usu_via = u.id_usu
                                  AND v2.est_via IN (?, ?)
                                ORDER BY v2.fec_via DESC LIMIT 1)
      WHERE u.id_rol_usu = ? AND u.est_con_usu = ? AND u.estado = ?
      LIMIT 5',
    [Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO, Config::ROL_CONDUCTOR, Config::CON_DISPONIBLE, Config::USU_ACTIVO]
);
?>
<!DOCTYPE html>
<html lang="<?php echo $idiomaActual; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Dashboard Principal</title>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && true)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/theme-init.js?v=<?= @filemtime('../assets/js/theme-init.js') ?: '1' ?>"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- CSS MODULAR DEL PANEL (antes: style_admin.css, que no existia en esta carpeta) -->
    <link rel="stylesheet" href="../assets/css/01-base.css">
    <link rel="stylesheet" href="../assets/css/02-layout.css?v=<?= @filemtime('../assets/css/02-layout.css') ?: '1' ?>">
    <link rel="stylesheet" href="../assets/css/03-componentes.css">
    <link rel="stylesheet" href="../assets/css/04-modales.css">
    <link rel="stylesheet" href="../assets/css/05-tablas.css">
    <link rel="stylesheet" href="../assets/css/06-responsive.css">
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">

   <?php 
   if (file_exists(__DIR__ . '/../includes/sidebar.php')) {
       include __DIR__ . '/../includes/sidebar.php';
   }
   ?>

    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen flex-1 transition-all duration-300 min-w-0">
        <?php 
        if (file_exists(__DIR__ . '/../includes/header.php')) {
            include __DIR__ . '/../includes/header.php';
        } else {
            echo "<header class='p-4 bg-slate-800 text-white'>Header SGET</header>";
        }
        ?>

        <main class="space-y-8 flex-grow pb-12 relative z-10 p-8 max-w-[1600px] w-auto mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/5 dark:bg-white/[0.02] p-6 rounded-3xl border border-slate-200 dark:border-white/5 backdrop-blur-md">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight"><?php echo $lang['bienvenido'] ?? 'Bienvenido al Panel General'; ?></h2>
                    </div>
                    <p class="text-slate-500 dark:text-slate-400 text-xs mt-1"><?php echo $lang['sub_bienvenido'] ?? 'Resumen general de operaciones logísticas, control de flota y personal de SGET.'; ?></p>
                </div>
                <div class="flex items-center gap-2 bg-blue-500/10 text-blue-500 dark:text-sky-400 px-4 py-2 rounded-xl text-xs font-bold border border-blue-500/20">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('d \d\e F, Y'); ?>
                </div>
            </div>

            <!-- TARJETAS DE MÉTRICAS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $lang['total_usuarios'] ?? 'TOTAL USUARIOS'; ?></p>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono"><?php echo number_format($total_usuarios); ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-sky-500/10 text-sky-500 rounded-2xl flex items-center justify-center text-lg border border-sky-500/20">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs text-emerald-500 font-semibold">
                        <i class="fas fa-chart-line"></i> <span><?php echo $porcentaje_usu; ?>% de participación activa</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $lang['viajes_registrados'] ?? 'VIAJES REGISTRADOS'; ?></p>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono"><?php echo number_format($total_viajes); ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-purple-500/10 text-purple-500 rounded-2xl flex items-center justify-center text-lg border border-purple-500/20">
                            <i class="fas fa-route"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs text-purple-400 font-semibold">
                        <i class="fas fa-calendar-check"></i> <span><?php echo $viajes_mes; ?> despachos este mes</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $lang['flota_disponible'] ?? 'FLOTA DISPONIBLE'; ?></p>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono"><?= (int)($vehiculos[Config::VEH_DISPONIBLE] ?? 0) ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-emerald-500/10 text-emerald-500 rounded-2xl flex items-center justify-center text-lg border border-emerald-500/20">
                            <i class="fas fa-bus"></i>
                        </div>
                    </div>
                    <!--
                        Los cuatro estados reales de la unidad. Antes esta línea
                        decía «Inactivos» y sumaba mantenimiento + averiadas + en
                        ruta en un único número, que no correspondía a nada que el
                        administrador pudiera actuar.
                    -->
                    <div class="mt-4 flex items-center gap-3 flex-wrap text-[11px] text-slate-500 dark:text-slate-400 font-semibold">
                        <span><i class="fas fa-truck-fast text-sky-500"></i>
                            En ruta: <b class="text-sky-500"><?= (int)($vehiculos[Config::VEH_ASIGNADO] ?? 0) ?></b></span>
                        <span><i class="fas fa-wrench text-amber-500"></i>
                            En mantenimiento: <b class="text-amber-500"><?= (int)($vehiculos[Config::VEH_MANTENIMIENTO] ?? 0) ?></b></span>
                        <span><i class="fas fa-ban text-red-400"></i>
                            Fuera de servicio: <b class="text-red-400"><?= (int)($vehiculos[Config::VEH_FUERA_SERVICIO] ?? 0) ?></b></span>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $lang['eficiencia_operativa'] ?? 'EFICIENCIA OPERATIVA'; ?></p>
                            <h3 class="text-3xl font-black text-slate-900 dark:text-white mt-2 font-mono">98.4%</h3>
                        </div>
                        <div class="w-12 h-12 bg-amber-500/10 text-amber-500 rounded-2xl flex items-center justify-center text-lg border border-amber-500/20">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs text-emerald-500 font-semibold">
                        <i class="fas fa-check-circle"></i> <span>Sistema operando sin bloqueos</span>
                    </div>
                </div>

            </div>

            <!-- SECCIÓN INFERIOR -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fas fa-id-card text-sky-400"></i> <?php echo $lang['conductores_turno'] ?? 'Conductores Disponibles en Turno'; ?>
                        </h3>
                        <a href="ranking_conductores.php" class="text-xs text-sky-400 hover:underline font-bold"><?php echo $lang['ver_ranking'] ?? 'Ver Ranking'; ?></a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-white/5 text-[10px] text-slate-400 uppercase font-bold">
                                    <th class="pb-3"><?php echo $lang['conductor'] ?? 'Conductor'; ?></th>
                                    <th class="pb-3"><?php echo $lang['vehiculo_asignado'] ?? 'Vehículo Asignado'; ?></th>
                                    <th class="pb-3 text-center"><?php echo $lang['estado'] ?? 'Estado'; ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5 text-xs">
                                <?php if (!empty($conductores_disponibles)): ?>
                                    <?php foreach ($conductores_disponibles as $c): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="py-3.5 font-bold text-slate-800 dark:text-white flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-full bg-slate-200 dark:bg-white/10 flex items-center justify-center text-xs font-black">
                                                <?php echo htmlspecialchars(mb_strtoupper(mb_substr((string)$c['nom_usu'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <?php echo htmlspecialchars((string)$c['nom_usu'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="py-3.5 font-mono text-slate-500 dark:text-slate-300">
                                            <?php if (!empty($c['pla_veh'])): ?>
                                                <?php echo htmlspecialchars((string)$c['pla_veh'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php else: ?>
                                                <span class="text-amber-400 italic"><?php echo $lang['sin_asignar'] ?? 'Sin asignar'; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 text-center">
                                            <span class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-[10px] font-extrabold uppercase"><?php echo $lang['disponible'] ?? 'Disponible'; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="py-8 text-center text-slate-400 italic">No hay conductores disponibles registrados en este momento.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/5 shadow-xl flex flex-col justify-between">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                            <i class="fas fa-bolt text-amber-400"></i> <?php echo $lang['accesos_rapidos'] ?? 'Accesos Rápidos'; ?>
                        </h3>
                        <p class="text-xs text-slate-400 mb-6 leading-relaxed"><?php echo $lang['desc_accesos'] ?? 'Utiliza los accesos directos para gestionar las tareas logísticas frecuentes de manera inmediata.'; ?></p>
                        
                        <div class="space-y-3">
                            <a href="viajes.php" class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-white/[0.03] hover:bg-slate-100 dark:hover:bg-white/[0.06] border border-slate-200 dark:border-white/5 rounded-2xl transition-all group">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2.5">
                                    <i class="fas fa-plus-circle text-sky-400"></i> <?php echo $lang['btn_despachar'] ?? 'Despachar Nuevo Viaje'; ?>
                                </span>
                                <i class="fas fa-chevron-right text-xs text-slate-400 group-hover:translate-x-1 transition-transform"></i>
                            </a>
                            <a href="asignaciones.php" class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-white/[0.03] hover:bg-slate-100 dark:hover:bg-white/[0.06] border border-slate-200 dark:border-white/5 rounded-2xl transition-all group">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2.5">
                                    <i class="fas fa-ticket-alt text-purple-400"></i> <?php echo $lang['btn_recauda'] ?? 'Registrar Reserva / Recaudo'; ?>
                                </span>
                                <i class="fas fa-chevron-right text-xs text-slate-400 group-hover:translate-x-1 transition-transform"></i>
                            </a>
                            <a href="gestion_permisos.php" class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-white/[0.03] hover:bg-slate-100 dark:hover:bg-white/[0.06] border border-slate-200 dark:border-white/5 rounded-2xl transition-all group">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2.5">
                                    <i class="fas fa-user-shield text-emerald-400"></i> <?php echo $lang['btn_restricciones'] ?? 'Configurar Restricciones'; ?>
                                </span>
                                <i class="fas fa-chevron-right text-xs text-slate-400 group-hover:translate-x-1 transition-transform"></i>
                            </a>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-white/5 text-center">
                        <span class="text-[10px] font-mono text-slate-400 uppercase tracking-widest">SGET v2.5 - Módulo Admin</span>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <script>
    </script>
</body>
</html>
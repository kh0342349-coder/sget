<?php
date_default_timezone_set('America/Bogota');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar diccionario según idioma de la sesión
$idiomaActual = $_SESSION['sget_idioma'] ?? 'es';
$archivoIdioma = __DIR__ . '/../lang/' . $idiomaActual . '.php';
if (file_exists($archivoIdioma)) {
    require_once $archivoIdioma;
} else {
    require_once __DIR__ . '/../lang/es.php';
}

include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 2) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = $_SESSION['nombre_usuario'] ?? "Conductor";
$documento  = $_SESSION['documento'];

$id_conductor = 0;
$total_viajes = 0;
$viajes_data = [];
$promedio = 0;
$total_votos = 0;
$restricciones_actuales = '';

// CONSULTA SEGURA CON MANEJO DE EXCEPCIONES PARA EVITAR EL CRASH EN MYSQLI
try {
    $stmt_user = $conexion->prepare("SELECT id_usu, restricciones FROM usuario WHERE num_doc_usu = ?");
} catch (mysqli_sql_exception $e) {
    // Si la columna 'restricciones' no existe en la tabla, consulta solo id_usu sin fallar
    $stmt_user = $conexion->prepare("SELECT id_usu FROM usuario WHERE num_doc_usu = ?");
}

if ($stmt_user) {
    $stmt_user->bind_param("s", $documento);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user && $result_user->num_rows > 0) {
        $user_data = $result_user->fetch_assoc();
        $id_conductor = $user_data['id_usu'];
        $restricciones_actuales = $user_data['restricciones'] ?? '';

        // 1. Contar total de viajes
        $stmt_count = $conexion->prepare("SELECT COUNT(*) as total FROM viaje WHERE id_usu_via = ?");
        if ($stmt_count) {
            $stmt_count->bind_param("i", $id_conductor);
            $stmt_count->execute();
            $total_viajes = $stmt_count->get_result()->fetch_assoc()['total'];
            $stmt_count->close();
        }

        // 2. Obtener promedio de calificación
        $stmt_cal = $conexion->prepare("SELECT AVG(pun_cal) as promedio, COUNT(id_cal) as total FROM calificacion WHERE id_usu_des = ?");
        if ($stmt_cal) {
            $stmt_cal->bind_param("i", $id_conductor);
            $stmt_cal->execute();
            $res_cal = $stmt_cal->get_result();
            if ($res_cal) {
                $datos_cal = $res_cal->fetch_assoc();
                $promedio = round($datos_cal['promedio'] ?? 0, 1);
                $total_votos = $datos_cal['total'] ?? 0;
            }
            $stmt_cal->close();
        }

        // 3. Consulta de viajes recientes
        $sql_viajes = "SELECT v.*, r.des_rut, ve.pla_veh, ve.mode_veh,
                        (SELECT COUNT(*) FROM reserva WHERE id_via_res = v.id_via) as num_pasajeros
                        FROM viaje v 
                        JOIN rutas r ON v.id_rut_via = r.id_rut 
                        LEFT JOIN vehiculo ve ON v.id_veh = ve.id_veh 
                        WHERE v.id_usu_via = ? 
                        ORDER BY v.fec_via DESC LIMIT 5";
        
        $stmt_viajes = $conexion->prepare($sql_viajes);
        if ($stmt_viajes) {
            $stmt_viajes->bind_param("i", $id_conductor);
            $stmt_viajes->execute();
            $result_viajes = $stmt_viajes->get_result();
            
            while($row = $result_viajes->fetch_assoc()) {
                $viajes_data[] = $row;
            }
            $stmt_viajes->close();
        }
    }
    $stmt_user->close();
}

// FUNCIÓN DE VERIFICACIÓN DE RESTRICCIONES EN TIEMPO REAL
function tiene_acceso($permiso, $cadena_restricciones) {
    if (empty($cadena_restricciones)) {
        return true;
    }
    $denegados = explode(',', $cadena_restricciones);
    return !in_array($permiso, $denegados);
}

$vehiculoReciente = (!empty($viajes_data)) ? $viajes_data[0] : null;
?>

<!DOCTYPE html>
<html lang="<?= $idiomaActual ?>" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - <?= ($idiomaActual === 'en') ? 'Driver Dashboard' : 'Panel Conductor' ?></title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">
    <!-- 1. SIDEBAR FIJO -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- 2. CONTENEDOR DERECHO FLUIDO -->
    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen flex-1 transition-all duration-300 min-w-0">
        
        <!-- HEADER SUPERIOR -->
        <?php include '../includes/header.php'; ?>

        <!-- CONTENIDO DEL DASHBOARD -->
        <main class="p-8 space-y-8 flex-grow pb-12 relative z-10 max-w-[1600px] w-full mx-auto">
            
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    <?= $lang['bienvenido'] ?? 'Bienvenido al Panel General' ?>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    <?= ($idiomaActual === 'en') ? 'Welcome back to the route management system.' : 'Bienvenido de vuelta al sistema de gestión de rutas.' ?>
                </p>
            </div>
            
            <!-- TARJETAS DE MÉTRICAS -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl">
                
                <!-- Card 1: Viajes Totales -->
                <div class="bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 p-6 rounded-3xl relative overflow-hidden flex items-center justify-between group hover:border-slate-300 dark:hover:border-white/20 transition-all duration-300 shadow-xl">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                            <?= $lang['viajes_registrados'] ?? 'Viajes Totales' ?>
                        </p>
                        <h4 class="text-4xl font-black text-slate-900 dark:text-white font-mono"><?php echo $total_viajes; ?></h4>
                    </div>
                    <div class="h-12 w-12 rounded-2xl bg-blue-500/10 text-blue-500 flex items-center justify-center border border-blue-500/20">
                        <i class="fas fa-route text-lg"></i>
                    </div>
                </div>

                <!-- Card 2: Vehículo Asignado -->
                <div class="bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 p-6 rounded-3xl relative overflow-hidden flex items-center justify-between group hover:border-slate-300 dark:hover:border-white/20 transition-all duration-300 shadow-xl">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                            <?= $lang['vehiculo_asignado'] ?? 'Vehículo Asignado' ?>
                        </p>
                        <?php if($vehiculoReciente && !empty($vehiculoReciente['pla_veh'])): ?>
                            <h4 class="text-2xl font-black text-sky-400 uppercase tracking-wide font-mono"><?php echo htmlspecialchars($vehiculoReciente['pla_veh'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <p class="text-[11px] text-slate-400 font-medium uppercase mt-0.5"><?php echo htmlspecialchars($vehiculoReciente['mode_veh'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php else: ?>
                            <h4 class="text-sm font-bold text-slate-400 dark:text-slate-500 italic"><?= $lang['sin_asignar'] ?? 'Sin asignar' ?></h4>
                        <?php endif; ?>
                    </div>
                    <div class="h-12 w-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                        <i class="fas fa-bus text-lg"></i>
                    </div>
                </div>

                <!-- Card 3: Reputación / Ranking -->
                <?php if (tiene_acceso('ver_ranking', $restricciones_actuales)): ?>
                <div class="bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 p-6 rounded-3xl relative overflow-hidden flex items-center justify-between group hover:border-slate-300 dark:hover:border-white/20 transition-all duration-300 shadow-xl">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                            <?= ($idiomaActual === 'en') ? 'Reputation' : 'Reputación' ?>
                        </p>
                        <div class="flex items-baseline gap-1.5">
                            <h4 class="text-4xl font-black text-slate-900 dark:text-white font-mono"><?php echo ($total_votos > 0) ? number_format($promedio, 1) : "0.0"; ?></h4>
                            <span class="text-slate-500 text-xs font-bold">/ 5.0</span>
                        </div>
                        <div class="flex text-amber-400 text-[10px] mt-1.5 gap-0.5 items-center">
                            <?php
                            $estrellas_enteras = floor($promedio);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $estrellas_enteras) {
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="far fa-star text-slate-700"></i>';
                                }
                            }
                            ?>
                            <span class="ml-2 text-slate-400 font-medium text-[10px]">(<?php echo $total_votos; ?> <?= ($idiomaActual === 'en') ? 'reviews' : 'reseñas' ?>)</span>
                        </div>
                    </div>
                    <div class="h-12 w-12 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center border border-amber-500/20">
                        <i class="fas fa-star text-lg"></i>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- TABLA DE HISTORIAL DE VIAJES -->
            <?php if (tiene_acceso('ver_rutas', $restricciones_actuales)): ?>
            <div class="bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 p-6 rounded-3xl shadow-xl max-w-6xl transition-colors duration-300">
                <h3 class="font-bold text-slate-900 dark:text-white text-base mb-4 tracking-tight flex items-center gap-2">
                    <i class="fas fa-history text-slate-400 text-sm"></i> <?= ($idiomaActual === 'en') ? 'Recent Trip Records' : 'Últimos Viajes Registrados' ?>
                </h3>
                <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/5">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead class="text-slate-400 uppercase text-[10px] font-bold tracking-wider border-b border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-black/20">
                            <tr>
                                <th class="px-5 py-3.5"><?= ($idiomaActual === 'en') ? 'Destination' : 'Destino' ?></th>
                                <th class="px-5 py-3.5"><?= ($idiomaActual === 'en') ? 'Date / Time' : 'Fecha / Hora' ?></th>
                                <th class="px-5 py-3.5"><?= ($idiomaActual === 'en') ? 'Vehicle' : 'Vehículo' ?></th>
                                <th class="px-5 py-3.5 text-center"><?= ($idiomaActual === 'en') ? 'Passengers' : 'Pasajeros' ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5 text-slate-800 dark:text-slate-100">
                            <?php if(!empty($viajes_data)): ?>
                                <?php foreach($viajes_data as $v): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="px-5 py-4 font-bold text-slate-900 dark:text-white capitalize"><?php echo htmlspecialchars($v['des_rut'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-5 py-4 text-slate-400 font-mono">
                                        <?php echo !empty($v['fec_via']) ? date('d/m/Y - h:i A', strtotime($v['fec_via'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="bg-slate-100 dark:bg-white/5 text-sky-400 border border-slate-200 dark:border-white/10 px-3 py-1 rounded-lg text-[10px] font-black tracking-wider uppercase font-mono">
                                            <?php echo htmlspecialchars($v['pla_veh'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-center text-emerald-400 font-bold bg-emerald-500/10 border border-emerald-500/20 rounded-lg py-0.5 max-w-[50px] mx-auto font-mono">
                                            <?php echo (int)($v['num_pasajeros'] ?? 0); ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-5 py-8 text-center text-slate-400 italic"><?= ($idiomaActual === 'en') ? 'No trip records found for this driver.' : 'No se encontraron registros de viajes para este conductor.' ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>
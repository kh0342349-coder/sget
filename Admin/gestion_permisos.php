<?php
// Archivo: Admin/gestion_permisos.php
date_default_timezone_set('America/Bogota');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CARGA DINÁMICA DEL DICCIONARIO DE IDIOMA
$idiomaActual = $_SESSION['sget_idioma'] ?? 'es';
$archivoIdioma = __DIR__ . '/../lang/' . $idiomaActual . '.php';
if (file_exists($archivoIdioma)) {
    require_once $archivoIdioma;
} else {
    require_once __DIR__ . '/../lang/es.php';
}

include '../assets/conexion.php';
require_once '../helpers/AuthHelper.php';

// Verificación de seguridad (Solo Admin)[cite: 15]
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// BLOQUEO DE SEGURIDAD POR RESTRICCIONES[cite: 15]
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'gestion_permisos');

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";

// Asegurar tabla de restricciones para administradores[cite: 15]
$conexion->query("CREATE TABLE IF NOT EXISTS restricciones (
    id_res INT AUTO_INCREMENT PRIMARY KEY,
    id_usu INT NOT NULL,
    modulo VARCHAR(50) NOT NULL
)");

// Módulos controlables por cada rol[cite: 15]
$modulosAdmin = [
    'admin' => $lang['mod_dashboard'] ?? 'Dashboard General',
    'asignaciones' => $lang['mod_asignaciones_titulo'] ?? 'Asignaciones y Recaudo',
    'gestion_permisos' => $lang['mod_permisos_titulo'] ?? 'Gestión de Permisos',
    'ranking_conductores' => $lang['mod_ranking_titulo'] ?? 'Ranking de Conductores',
    'reportes' => $lang['reportes'] ?? 'Reportes Analíticos',
    'rutas' => $lang['mod_rutas_titulo'] ?? 'Gestión de Rutas',
    'usuarios' => $lang['mod_usuarios_titulo'] ?? 'Gestión de Usuarios',
    'vehiculos' => $lang['mod_vehiculos_titulo'] ?? 'Control de Vehículos',
    'viajes' => $lang['mod_viajes_titulo'] ?? 'Despacho de Viajes'
];

$modulosConductor = [
    'ver_rutas' => $lang['mod_mis_viajes'] ?? 'Mis Viajes (Rutas asignadas)',
    'ver_ranking' => $lang['mod_mis_resenas'] ?? 'Mis Reseñas y Calificaciones'
];

$modulosPasajero = [
    'ver_viajes' => $lang['mod_ver_viajes_disp'] ?? 'Ver Viajes Disponibles',
    'historial' => $lang['mod_historial_reservas'] ?? 'Historial de Reservas',
    'calificar' => $lang['mod_calificaciones'] ?? 'Módulo de Calificaciones'
];

// PROCESAR GUARDADO DE PERMISOS DESDE EL DRAWER LATERAL[cite: 15]
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_permisos') {
    $id_usu_permiso = intval($_POST['id_usu']);
    $rol_usuario_permiso = intval($_POST['rol_usuario']);
    $filtro_rol_actual = intval($_POST['filtro_rol_actual'] ?? 1);
    $modulos_permitidos = $_POST['modulos'] ?? []; // Módulos que SÍ tienen acceso[cite: 15]

    // REGLA DE SEGURIDAD: Un admin no puede modificar sus propios permisos[cite: 15]
    if ($id_usu_permiso == $idUsuarioActual) {
        header("Location: gestion_permisos.php?rol=" . $filtro_rol_actual . "&status=self_error");
        exit();
    }

    if ($rol_usuario_permiso == 1) {
        // Administrador: se almacena en la tabla restricciones[cite: 15]
        $del = $conexion->prepare("DELETE FROM restricciones WHERE id_usu = ?");
        if ($del) {
            $del->bind_param("i", $id_usu_permiso);
            $del->execute();
        }
        $stmtIns = $conexion->prepare("INSERT INTO restricciones (id_usu, modulo) VALUES (?, ?)");
        if ($stmtIns) {
            foreach ($modulosAdmin as $keyMod => $nombreMod) {
                if (!in_array($keyMod, $modulos_permitidos)) {
                    $stmtIns->bind_param("is", $id_usu_permiso, $keyMod);
                    $stmtIns->execute();
                }
            }
        }
    } else {
        // Conductor o Pasajero: se guardan los módulos denegados separados por coma en la columna restricciones[cite: 15]
        $modulosDisponiblesRol = ($rol_usuario_permiso == 2) ? $modulosConductor : $modulosPasajero;
        $denegados = [];
        foreach ($modulosDisponiblesRol as $keyMod => $nombreMod) {
            if (!in_array($keyMod, $modulos_permitidos)) {
                $denegados[] = $keyMod;
            }
        }
        $strRestricciones = implode(',', $denegados);
        $stmtUpd = $conexion->prepare("UPDATE usuario SET restricciones = ? WHERE id_usu = ?");
        if ($stmtUpd) {
            $stmtUpd->bind_param("si", $strRestricciones, $id_usu_permiso);
            $stmtUpd->execute();
        }
    }

    header("Location: gestion_permisos.php?rol=" . $filtro_rol_actual . "&status=success");
    exit();
}

// Filtro de rol seleccionado (1: Admin, 2: Conductor, 3: Pasajero, 0: Todos)[cite: 15]
$filtro_rol = isset($_GET['rol']) ? intval($_GET['rol']) : 1;
$whereRol = ($filtro_rol > 0) ? "WHERE id_rol_usu = $filtro_rol AND estado = 1" : "WHERE estado = 1";
$query_usuarios = "SELECT id_usu, nom_usu, corre_usu, id_rol_usu, IFNULL(restricciones, '') as restricciones FROM usuario $whereRol ORDER BY id_usu DESC";
$resultado_usuarios = $conexion->query($query_usuarios);

// Obtener mapa de restricciones de administradores[cite: 15]
$restriccionesAdminMap = [];
$resRest = $conexion->query("SELECT id_usu, modulo FROM restricciones");
if ($resRest) {
    while ($row = $resRest->fetch_assoc()) {
        $restriccionesAdminMap[$row['id_usu']][] = $row['modulo'];
    }
}

// Nombres descriptivos de roles[cite: 15]
$nombresRoles = [
    1 => $lang['rol_admin'] ?? 'Administrador',
    2 => $lang['rol_conductor'] ?? 'Conductor',
    3 => $lang['rol_pasajero'] ?? 'Pasajero'
];
?>
<!DOCTYPE html>
<html lang="<?= $idiomaActual ?>" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - <?= $lang['mod_permisos_titulo'] ?? 'Gestión de Permisos y Restricciones' ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style_admin.css">
    
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
</head>
<body class="bg-slate-50 dark:bg-[#080c14] text-slate-800 dark:text-slate-100 flex min-h-screen transition-colors duration-300">

    <?php include '../includes/sidebar.php'; ?>

    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen flex-1 transition-all duration-300 min-w-0">
        
        <?php include '../includes/header.php'; ?>

        <main class="space-y-8 flex-grow pb-12 relative z-10 p-8 max-w-[1600px] w-auto mx-auto w-full">
            
            <!-- ENCABEZADO CON BOTÓN DE AYUDA -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/5 dark:bg-white/[0.02] p-6 rounded-3xl border border-slate-200 dark:border-white/5 backdrop-blur-md">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight"><?= $lang['mod_permisos_titulo'] ?? 'Gestión de Permisos y Restricciones' ?></h1>
                        
                        <!-- BOTÓN DE AYUDA DEL SISTEMA -->
                        <button type="button" onclick="abrirModalAyuda()" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer" title="Ver guía del módulo">
                            <i class="fas fa-question text-[10px]"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?= $lang['sub_permisos_desc'] ?? 'Configure los accesos y restricciones por módulo para cada cuenta de usuario en SGET.' ?></p>
                </div>
            </div>

            <!-- Alertas de estado -->
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'success'): ?>
                    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center gap-3 backdrop-blur-md shadow-lg">
                        <i class="fas fa-check-circle text-lg"></i>
                        <span class="text-xs font-semibold"><?= $lang['msj_permisos_exito'] ?? 'Los permisos y restricciones se han actualizado correctamente.' ?></span>
                    </div>
                <?php elseif ($_GET['status'] == 'self_error'): ?>
                    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 flex items-center gap-3 backdrop-blur-md shadow-lg">
                        <i class="fas fa-shield-alt text-lg"></i>
                        <span class="text-xs font-semibold"><?= $lang['msj_permisos_self_error'] ?? 'Acción no permitida: Un administrador no puede modificar sus propios permisos.' ?></span>
                    </div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 flex items-center gap-3 backdrop-blur-md shadow-lg">
                        <i class="fas fa-exclamation-triangle text-lg"></i>
                        <span class="text-xs font-semibold"><?= $lang['msj_permisos_error'] ?? 'Ocurrió un error al intentar guardar los cambios de permisos.' ?></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Panel de Administración de Permisos -->
            <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl space-y-6">
                
                <!-- Pestañas de Filtrado por Rol -->
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 dark:border-white/5 pb-5">
                    <h2 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <i class="fas fa-user-shield text-sky-400"></i> <?= $lang['tit_matriz_control'] ?? 'Matriz de Control de Acceso por Rol' ?>
                    </h2>

                    <div class="flex items-center gap-2 bg-slate-100 dark:bg-black/30 p-1.5 rounded-2xl border border-slate-200 dark:border-white/5">
                        <a href="gestion_permisos.php?rol=1" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?php echo $filtro_rol == 1 ? 'bg-sky-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'; ?>"><?= $lang['lbl_administradores'] ?? 'Administradores' ?></a>
                        <a href="gestion_permisos.php?rol=2" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?php echo $filtro_rol == 2 ? 'bg-sky-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'; ?>"><?= $lang['lbl_conductores'] ?? 'Conductores' ?></a>
                        <a href="gestion_permisos.php?rol=3" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?php echo $filtro_rol == 3 ? 'bg-sky-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'; ?>"><?= $lang['lbl_pasajeros'] ?? 'Pasajeros' ?></a>
                        <a href="gestion_permisos.php?rol=0" class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?php echo $filtro_rol == 0 ? 'bg-sky-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-white'; ?>"><?= $lang['lbl_todos'] ?? 'Todos' ?></a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-white/5 text-[10px] text-slate-400 uppercase font-bold">
                                <th class="pb-3 px-3"><?= $lang['lbl_id_usuario'] ?? 'ID Usuario' ?></th>
                                <th class="pb-3 px-3"><?= $lang['cfg_nombre'] ?? 'Nombre Completo' ?></th>
                                <th class="pb-3 px-3"><?= $lang['cfg_correo'] ?? 'Correo Electrónico' ?></th>
                                <th class="pb-3 px-3 text-center"><?= $lang['lbl_rol'] ?? 'Rol' ?></th>
                                <th class="pb-3 px-3 text-center"><?= $lang['lbl_config_restricciones'] ?? 'Configurar Restricciones' ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            <?php if($resultado_usuarios && $resultado_usuarios->num_rows > 0): ?>
                                <?php while($usr = $resultado_usuarios->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                    <td class="py-3.5 px-3 font-mono text-slate-400">#<?php echo $usr['id_usu']; ?></td>
                                    <td class="py-3.5 px-3 font-bold text-slate-800 dark:text-white flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-sky-500/10 text-sky-400 flex items-center justify-center font-black text-xs border border-sky-500/20">
                                            <?php echo strtoupper(substr($usr['nom_usu'], 0, 1)); ?>
                                        </div>
                                        <?php echo htmlspecialchars($usr['nom_usu']); ?>
                                    </td>
                                    <td class="py-3.5 px-3 text-slate-500 dark:text-slate-300 italic"><?php echo htmlspecialchars($usr['corre_usu']); ?></td>
                                    <td class="py-3.5 px-3 text-center">
                                        <span class="px-2.5 py-1 bg-black/20 text-sky-300 border border-white/5 rounded-lg text-[10px] font-mono font-bold">
                                            <?php echo $nombresRoles[$usr['id_rol_usu']] ?? 'Usuario'; ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-3 text-center">
                                        <?php if ($usr['id_usu'] == $idUsuarioActual): ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500/10 text-amber-400 font-bold text-[10px] uppercase tracking-wider rounded-xl border border-amber-500/20">
                                                <i class="fas fa-shield-alt text-xs"></i> <?= $lang['lbl_tu_cuenta_no_editable'] ?? 'Tu cuenta (No editable)' ?>
                                            </span>
                                        <?php else: ?>
                                            <button type="button" 
                                                    onclick="abrirDrawerPermisos(<?php echo $usr['id_usu']; ?>, '<?php echo htmlspecialchars($usr['nom_usu'], ENT_QUOTES); ?>', <?php echo $usr['id_rol_usu']; ?>, '<?php echo htmlspecialchars($usr['restricciones'], ENT_QUOTES); ?>')"
                                                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-sky-500/10 hover:bg-sky-500 text-sky-400 hover:text-slate-950 font-bold text-[10px] uppercase tracking-wider rounded-xl border border-sky-500/20 transition-all shadow-sm cursor-pointer">
                                                <i class="fas fa-sliders-h text-xs"></i> <?= $lang['btn_modificar_modulos'] ?? 'Modificar Módulos' ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-400 italic"><?= $lang['lbl_no_hay_usuarios_filtro'] ?? 'No hay cuentas registradas para este filtro.' ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- PANEL LATERAL DESLIZANTE (DRAWER) DE PERMISOS -->
    <div id="overlayPermisos" onclick="cerrarDrawerPermisos()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <aside id="drawerPermisos" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#121826] border-l border-slate-200 dark:border-white/15 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between">
            <h3 id="drawerTituloPermisos" class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fas fa-sliders-h text-sky-400"></i> <?= $lang['tit_configurar_permisos'] ?? 'Configurar Permisos' ?>
            </h3>
            <button onclick="cerrarDrawerPermisos()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <form action="gestion_permisos.php" method="POST" class="flex-1 flex flex-col justify-between overflow-hidden">
            <input type="hidden" name="accion" value="guardar_permisos">
            <input type="hidden" name="id_usu" id="input_id_usu_permiso" value="">
            <input type="hidden" name="rol_usuario" id="input_rol_usuario" value="">
            <input type="hidden" name="filtro_rol_actual" value="<?php echo $filtro_rol; ?>">

            <div class="p-6 flex-1 overflow-y-auto space-y-4">
                <p class="text-xs text-slate-400"><?= $lang['sub_selecciona_modulos'] ?? 'Selecciona los módulos a los cuales este usuario tendrá acceso habilitado:' ?></p>
                
                <!-- CONTENEDOR DINÁMICO DE CHECKBOXES SEGÚN EL ROL -->
                <div id="contenedorModulos" class="space-y-2.5">
                    <!-- Se llena automáticamente con JavaScript -->
                </div>
            </div>

            <div class="p-6 border-t border-slate-100 dark:border-white/5 flex gap-3">
                <button type="button" onclick="cerrarDrawerPermisos()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 text-slate-300 rounded-xl text-xs font-bold uppercase tracking-wider cursor-pointer"><?= $lang['cfg_cancelar'] ?? 'Cancelar' ?></button>
                <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold rounded-xl text-xs uppercase tracking-wider shadow-lg shadow-sky-500/20 hover:opacity-90 transition-all cursor-pointer"><?= $lang['cfg_guardar'] ?? 'Guardar Cambios' ?></button>
            </div>
        </form>
    </aside>

    <!-- MODAL DE AYUDA DEL MÓDULO -->
    <div id="overlayAyuda" onclick="cerrarModalAyuda()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="modalAyuda" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-4 transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <i class="fas fa-info-circle text-sky-400"></i> <?= $lang['tit_guia_permisos'] ?? 'Guía de Permisos y Restricciones' ?>
                </h3>
                <button onclick="cerrarModalAyuda()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer"><i class="fas fa-times text-xs"></i></button>
            </div>
            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                <li class="flex items-start gap-2">
                    <i class="fas fa-shield-alt text-amber-400 mt-0.5"></i>
                    <span><b><?= $lang['item_guia_perm_1_tit'] ?? 'Seguridad de Cuenta:' ?></b> <?= $lang['item_guia_perm_1_desc'] ?? 'Por seguridad y prevención, ningún administrador puede modificar sus propios permisos ni restricciones.' ?></span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-sliders-h text-emerald-400 mt-0.5"></i>
                    <span><b><?= $lang['btn_modificar_modulos'] ?? 'Modificar Módulos' ?>:</b> <?= $lang['item_guia_perm_2_desc'] ?? 'Gestiona los accesos de las demás cuentas de administradores, conductores y pasajeros del sistema.' ?></span>
                </li>
            </ul>
            <button onclick="cerrarModalAyuda()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer mt-2">
                <?= $lang['cfg_cancelar'] ? 'Entendido' : 'Entendido' ?>
            </button>
        </div>
    </div>

    <!-- SCRIPTS DE CONTROL -->
    <script>
        const IDIOMA_ACTUAL = "<?= $idiomaActual ?>";
        const txtPermisosPrefix = "<?= $lang['lbl_permisos_prefijo'] ?? 'Permisos:' ?>";
        const modulosAdmin = <?php echo json_encode($modulosAdmin); ?>;
        const modulosConductor = <?php echo json_encode($modulosConductor); ?>;
        const modulosPasajero = <?php echo json_encode($modulosPasajero); ?>;
        const restriccionesAdminMap = <?php echo json_encode($restriccionesAdminMap); ?>;

        function abrirDrawerPermisos(idUsuario, nombreUsuario, rolUsuario, restriccionesStr) {
            document.getElementById('input_id_usu_permiso').value = idUsuario;
            document.getElementById('input_rol_usuario').value = rolUsuario;
            document.getElementById('drawerTituloPermisos').innerHTML = '<i class="fas fa-sliders-h text-sky-400"></i> ' + txtPermisosPrefix + ' ' + nombreUsuario;

            const container = document.getElementById('contenedorModulos');
            container.innerHTML = '';

            let modulosObjetivo = {};
            let restringidosArray = [];

            if (rolUsuario == 1) {
                modulosObjetivo = modulosAdmin;
                restringidosArray = restriccionesAdminMap[idUsuario] || [];
            } else if (rolUsuario == 2) {
                modulosObjetivo = modulosConductor;
                restringidosArray = restriccionesStr ? restriccionesStr.split(',') : [];
            } else if (rolUsuario == 3) {
                modulosObjetivo = modulosPasajero;
                restringidosArray = restriccionesStr ? restriccionesStr.split(',') : [];
            }

            for (const [key, label] of Object.entries(modulosObjetivo)) {
                const estaHabilitado = !restringidosArray.includes(key);
                
                const labelEl = document.createElement('label');
                labelEl.className = 'flex items-center justify-between p-3.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl hover:border-sky-400/50 transition-all cursor-pointer';
                labelEl.innerHTML = `
                    <span class="text-xs font-bold text-slate-800 dark:text-white">${label}</span>
                    <input type="checkbox" name="modulos[]" value="${key}" ${estaHabilitado ? 'checked' : ''} class="w-4 h-4 accent-sky-500 rounded cursor-pointer">
                `;
                container.appendChild(labelEl);
            }

            document.getElementById('overlayPermisos').classList.remove('opacity-0', 'pointer-events-none');
            document.getElementById('overlayPermisos').classList.add('opacity-100', 'pointer-events-auto');
            document.getElementById('drawerPermisos').classList.remove('translate-x-full');
            document.getElementById('drawerPermisos').classList.add('translate-x-0');
        }

        function cerrarDrawerPermisos() {
            document.getElementById('drawerPermisos').classList.remove('translate-x-0');
            document.getElementById('drawerPermisos').classList.add('translate-x-full');
            document.getElementById('overlayPermisos').classList.remove('opacity-100', 'pointer-events-auto');
            document.getElementById('overlayPermisos').classList.add('opacity-0', 'pointer-events-none');
        }

        function abrirModalAyuda() {
            document.getElementById('overlayAyuda').classList.remove('opacity-0', 'pointer-events-none');
            document.getElementById('overlayAyuda').classList.add('opacity-100', 'pointer-events-auto');
            document.getElementById('modalAyuda').classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
            document.getElementById('modalAyuda').classList.add('opacity-100', 'pointer-events-auto', 'scale-100');
        }

        function cerrarModalAyuda() {
            document.getElementById('modalAyuda').classList.remove('opacity-100', 'pointer-events-auto', 'scale-100');
            document.getElementById('modalAyuda').classList.add('opacity-0', 'pointer-events-none', 'scale-95');
            document.getElementById('overlayAyuda').classList.remove('opacity-100', 'pointer-events-auto');
            document.getElementById('overlayAyuda').classList.add('opacity-0', 'pointer-events-none');
        }
    </script>
</body>
</html>
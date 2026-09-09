<?php
// Archivo: Admin/usuarios.php
date_default_timezone_set('America/Bogota');   
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar diccionario según el idioma activo en la sesión
$idiomaActual = $_SESSION['sget_idioma'] ?? 'es';
$archivoIdioma = __DIR__ . '/../lang/' . $idiomaActual . '.php';
if (file_exists($archivoIdioma)) {
    require_once $archivoIdioma;
} else {
    require_once __DIR__ . '/../lang/es.php';
}

include '../assets/conexion.php'; 
require_once '../helpers/AuthHelper.php';

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

// BLOQUEO DE SEGURIDAD POR RESTRICCIONES
$idUsuarioActual = $_SESSION['id_usu'] ?? 0;
AuthHelper::requerirAcceso($conexion, $idUsuarioActual, 'usuarios');

$nombreReal = $_SESSION['nombre_usuario'] ?? "Administrador";
$documentoSesion  = $_SESSION['documento'];

// Consulta general de usuarios
$query = "SELECT num_doc_usu, tip_doc_usu, nom_usu, corre_usu, id_rol_usu, estado FROM usuario";
$resultado = $conexion->query($query);

// Arrays para organizar la vista
$admins = []; $conductores = []; $pasajeros = []; $desactivados = []; 

if ($resultado) {
    while ($row = $resultado->fetch_assoc()) {
        if (isset($row['estado']) && $row['estado'] == 0) {
            $desactivados[] = $row;
        } else {
            if ($row['id_rol_usu'] == 1) $admins[] = $row;
            elseif ($row['id_rol_usu'] == 2) $conductores[] = $row;
            elseif ($row['id_rol_usu'] == 3) $pasajeros[] = $row;
        }
    }
}

$totalUsuarios = count($admins) + count($conductores) + count($pasajeros) + count($desactivados);
?>
<!DOCTYPE html>
<html lang="<?= $idiomaActual ?>" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - <?= $lang['mod_usuarios_titulo'] ?? 'Administración de Usuarios' ?></title>
    
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

    <!-- BARRA LATERAL -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- CONTENEDOR PRINCIPAL ALINEADO -->
    <div id="main-content-wrapper" class="ml-72 flex flex-col min-h-screen flex-1 transition-all duration-300 min-w-0">
        
        <!-- HEADER REUTILIZABLE -->
        <?php include '../includes/header.php'; ?>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="space-y-8 flex-grow pb-12 relative z-10 p-8 max-w-[1600px] w-auto mx-auto w-full">
            
            <!-- ENCABEZADO, AYUDA Y BOTÓN AGREGAR -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/5 dark:bg-white/[0.02] p-6 rounded-3xl border border-slate-200 dark:border-white/5 backdrop-blur-md">
                <div class="flex items-center gap-3">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                                <?= $lang['mod_usuarios_titulo'] ?? 'Administración de Usuarios' ?>
                            </h1>
                            
                            <!-- BOTÓN DE AYUDA DEL SISTEMA -->
                            <button type="button" onclick="abrirModalAyuda()" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer" title="Ver guía del módulo">
                                <i class="fas fa-question text-[10px]"></i>
                            </button>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            <?= ($idiomaActual === 'en') ? 'View, register, edit, and control the operational status of SGET personnel.' : 'Visualice, registre, edite y controle el estado operativo del personal en SGET.' ?>
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <!-- Buscador Rápido -->
                    <div class="relative w-full md:w-64">
                        <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="inputBuscadorLive" onkeyup="filtrarTablaLocal()" placeholder="<?= ($idiomaActual === 'en') ? 'Search document, name...' : 'Buscar documento, nombre...' ?>" 
                               class="w-full bg-white dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl pl-10 pr-4 py-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-400 transition-all shadow-sm">
                    </div>

                    <button onclick="abrirModalCrear()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-sky-500/20 hover:opacity-90 transition-all cursor-pointer whitespace-nowrap">
                        <i class="fas fa-user-plus text-sm"></i> <?= $lang['btn_nuevo_usuario'] ?? 'Nuevo Usuario' ?>
                    </button>
                </div>
            </div>

            <!-- TARJETAS MÉTRICAS RESUMEN -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="bg-white dark:bg-[#121826] p-5 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl flex items-center gap-4">
                    <div class="w-12 h-12 bg-red-500/10 text-red-400 rounded-2xl flex items-center justify-center font-bold text-lg border border-red-500/20"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider"><?= ($idiomaActual === 'en') ? 'Admins' : 'Admins' ?></p>
                        <p class="text-xl font-black text-slate-900 dark:text-white mt-0.5 font-mono"><?php echo count($admins); ?></p>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#121826] p-5 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl flex items-center gap-4">
                    <div class="w-12 h-12 bg-emerald-500/10 text-emerald-400 rounded-2xl flex items-center justify-center font-bold text-lg border border-emerald-500/20"><i class="fas fa-id-card"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider"><?= ($idiomaActual === 'en') ? 'Drivers' : 'Conductores' ?></p>
                        <p class="text-xl font-black text-slate-900 dark:text-white mt-0.5 font-mono"><?php echo count($conductores); ?></p>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#121826] p-5 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl flex items-center gap-4">
                    <div class="w-12 h-12 bg-sky-500/10 text-sky-400 rounded-2xl flex items-center justify-center font-bold text-lg border border-sky-500/20"><i class="fas fa-walking"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider"><?= ($idiomaActual === 'en') ? 'Passengers' : 'Pasajeros' ?></p>
                        <p class="text-xl font-black text-slate-900 dark:text-white mt-0.5 font-mono"><?php echo count($pasajeros); ?></p>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#121826] p-5 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl flex items-center gap-4">
                    <div class="w-12 h-12 bg-slate-500/10 text-slate-400 rounded-2xl flex items-center justify-center font-bold text-lg border border-slate-500/20"><i class="fas fa-users"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider"><?= $lang['total_usuarios'] ?? 'TOTAL USUARIOS' ?></p>
                        <p class="text-xl font-black text-slate-900 dark:text-white mt-0.5 font-mono"><?php echo $totalUsuarios; ?></p>
                    </div>
                </div>
            </div>

            <!-- SISTEMA DE PESTAÑAS (TABS) -->
            <div class="flex flex-wrap gap-2 bg-white dark:bg-[#121826] p-2 rounded-2xl border border-slate-200 dark:border-white/10 w-fit shadow-md">
                <button onclick="cambiarPestana('tab-admins')" id="btn-tab-admins" class="pestana-btn px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md">
                    <i class="fas fa-user-shield text-xs"></i> <?= ($idiomaActual === 'en') ? 'Administrators' : 'Administradores' ?> (<?php echo count($admins); ?>)
                </button>
                <button onclick="cambiarPestana('tab-conductores')" id="btn-tab-conductores" class="pestana-btn px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 text-slate-400 hover:text-white">
                    <i class="fas fa-id-card text-xs"></i> <?= ($idiomaActual === 'en') ? 'Drivers' : 'Conductores' ?> (<?php echo count($conductores); ?>)
                </button>
                <button onclick="cambiarPestana('tab-pasajeros')" id="btn-tab-pasajeros" class="pestana-btn px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 text-slate-400 hover:text-white">
                    <i class="fas fa-walking text-xs"></i> <?= ($idiomaActual === 'en') ? 'Passengers' : 'Pasajeros' ?> (<?php echo count($pasajeros); ?>)
                </button>
                <button onclick="cambiarPestana('tab-desactivados')" id="btn-tab-desactivados" class="pestana-btn px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 text-slate-400 hover:text-red-400">
                    <i class="fas fa-user-slash text-xs"></i> <?= ($idiomaActual === 'en') ? 'Disabled' : 'Desactivados' ?> (<?php echo count($desactivados); ?>)
                </button>
            </div>

            <!-- CONTENEDOR DE SECCIONES (TABS) -->
            <div id="contenedor-pestanas">
                
                <?php 
                $secciones = [
                    'tab-admins' => ['data' => $admins, 'visible' => true],
                    'tab-conductores' => ['data' => $conductores, 'visible' => false],
                    'tab-pasajeros' => ['data' => $pasajeros, 'visible' => false],
                    'tab-desactivados' => ['data' => $desactivados, 'visible' => false]
                ];

                foreach ($secciones as $idTab => $s): 
                ?>
                <section id="<?php echo $idTab; ?>" class="seccion-tab <?php echo $s['visible'] ? '' : 'hidden'; ?> space-y-6">
                    <div class="bg-white dark:bg-[#121826] p-6 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl space-y-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs tabla-datos">
                                <thead>
                                    <tr class="border-b border-slate-100 dark:border-white/5 text-[10px] text-slate-400 uppercase font-bold">
                                        <th class="pb-3 px-3"><?= ($idiomaActual === 'en') ? 'Document' : 'Documento' ?></th>
                                        <th class="pb-3 px-3"><?= $lang['cfg_nombre'] ?? 'Nombre Completo' ?></th>
                                        <th class="pb-3 px-3"><?= $lang['cfg_correo'] ?? 'Correo Electrónico' ?></th>
                                        <th class="pb-3 px-3 text-center"><?= ($idiomaActual === 'en') ? 'Status / Account' : 'Estado / Cuenta' ?></th>
                                        <th class="pb-3 px-3 text-center"><?= ($idiomaActual === 'en') ? 'Management' : 'Gestión' ?></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                    <?php if(empty($s['data'])): ?>
                                    <tr>
                                        <td colspan="5" class="py-12 text-center text-slate-400 italic"><?= ($idiomaActual === 'en') ? 'No records in this category.' : 'No hay registros en esta categoría.' ?></td>
                                    </tr>
                                    <?php else: foreach ($s['data'] as $u): ?>
                                    <tr class="fila-usuario hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="py-3.5 px-3">
                                            <span class="text-[10px] font-bold text-slate-400 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 px-2 py-0.5 rounded-md mr-1.5"><?php echo $u['tip_doc_usu']; ?></span>
                                            <span class="font-mono text-slate-800 dark:text-white font-semibold dato-buscar"><?php echo $u['num_doc_usu']; ?></span>
                                        </td>
                                        <td class="py-3.5 px-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-7 h-7 bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-white rounded-xl flex items-center justify-center text-xs font-black border border-slate-200 dark:border-white/10">
                                                    <?php echo strtoupper(substr($u['nom_usu'], 0, 1)); ?>
                                                </div>
                                                <span class="font-bold text-slate-800 dark:text-white dato-buscar"><?php echo htmlspecialchars($u['nom_usu']); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-3 text-slate-500 dark:text-slate-400 italic dato-buscar"><?php echo $u['corre_usu']; ?></td>
                                        <td class="py-3.5 px-3 text-center">
                                            <?php if($u['num_doc_usu'] == $documentoSesion): ?>
                                                <span class="px-3 py-1 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-full text-[10px] font-extrabold uppercase"><?= ($idiomaActual === 'en') ? 'Your Account' : 'Tu Cuenta' ?></span>
                                            <?php elseif(isset($u['estado']) && $u['estado'] == 0): ?>
                                                <span class="px-3 py-1 bg-red-500/10 text-red-400 border border-red-500/20 rounded-full text-[10px] font-extrabold uppercase"><?= ($idiomaActual === 'en') ? 'Inactive' : 'Inactivo' ?></span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-[10px] font-extrabold uppercase"><?= ($idiomaActual === 'en') ? 'Active' : 'Activo' ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-3 text-center">
                                            <div class="flex justify-center items-center gap-2">
                                                <!-- Botón Editar -->
                                                <button type="button" onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($u)); ?>)" class="w-7 h-7 bg-amber-500/10 text-amber-400 rounded-xl flex items-center justify-center hover:bg-amber-500 hover:text-white transition-all shadow-sm" title="Editar Usuario">
                                                    <i class="fas fa-edit text-[10px]"></i>
                                                </button>
                                                
                                                <?php if($u['num_doc_usu'] != $documentoSesion): ?>
                                                    <!-- Botón Activar / Desactivar -->
                                                    <?php $nuevoEstado = (isset($u['estado']) && $u['estado'] == 0) ? 1 : 0; ?>
                                                    <a href="cambiar_estado_usu.php?doc=<?php echo $u['num_doc_usu']; ?>&estado=<?php echo $nuevoEstado; ?>" 
                                                       class="w-7 h-7 flex items-center justify-center rounded-xl border transition-all shadow-sm <?php echo (isset($u['estado']) && $u['estado'] == 0) ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500 hover:text-white' : 'bg-red-500/10 text-red-400 border-red-500/20 hover:bg-red-500 hover:text-white'; ?>" 
                                                       title="<?php echo (isset($u['estado']) && $u['estado'] == 0) ? 'Activar cuenta' : 'Desactivar cuenta'; ?>">
                                                        <i class="fas <?php echo (isset($u['estado']) && $u['estado'] == 0) ? 'fa-toggle-off' : 'fa-toggle-on'; ?> text-xs"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <?php endforeach; ?>

            </div>

        </main>
    </div>

    <!-- MODAL DE AYUDA DEL MÓDULO -->
    <div id="overlayAyuda" onclick="cerrarModalAyuda()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 opacity-0 pointer-events-none transition-opacity duration-300"></div>
    <div id="modalAyuda" class="fixed inset-0 z-50 flex items-center justify-center pointer-events-none opacity-0 transition-all duration-300 p-4">
        <div class="bg-white dark:bg-[#121826] w-full max-w-md rounded-3xl p-6 border border-slate-200 dark:border-white/10 shadow-2xl space-y-4 transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center border-b border-slate-100 dark:border-white/5 pb-3">
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                    <i class="fas fa-info-circle text-sky-400"></i> <?= ($idiomaActual === 'en') ? 'User Management Guide' : 'Guía de Administración de Usuarios' ?>
                </h3>
                <button onclick="cerrarModalAyuda()" class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center"><i class="fas fa-times text-xs"></i></button>
            </div>
            <ul class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                <li class="flex items-start gap-2">
                    <i class="fas fa-user-plus text-sky-400 mt-0.5"></i>
                    <span><b><?= ($idiomaActual === 'en') ? 'New User:' : 'Nuevo Usuario:' ?></b> <?= ($idiomaActual === 'en') ? 'Register administrators, drivers, or passengers by assigning their role and credentials.' : 'Registra administradores, conductores o pasajeros asignando su rol y credenciales.' ?></span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-edit text-amber-400 mt-0.5"></i>
                    <span><b><?= ($idiomaActual === 'en') ? 'Editing:' : 'Edición:' ?></b> <?= ($idiomaActual === 'en') ? 'Modify personal details and email addresses of any active account.' : 'Modifica los datos personales y correos electrónicos de cualquier cuenta activa.' ?></span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-toggle-on text-emerald-400 mt-0.5"></i>
                    <span><b><?= ($idiomaActual === 'en') ? 'Operational Status:' : 'Estado Operativo:' ?></b> <?= ($idiomaActual === 'en') ? 'Enable or disable system access without losing user history.' : 'Activa o inhabilita el acceso al sistema sin perder el historial del usuario.' ?></span>
                </li>
            </ul>
            <button onclick="cerrarModalAyuda()" class="w-full py-3 bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/20 text-slate-800 dark:text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all cursor-pointer mt-2">
                <?= ($idiomaActual === 'en') ? 'Understood' : 'Entendido' ?>
            </button>
        </div>
    </div>

    <!-- PANEL LATERAL (DRAWER) PARA CREAR / EDITAR USUARIO -->
    <div id="overlayUsuario" onclick="cerrarModalUsuario()" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-40 opacity-0 pointer-events-none transition-opacity duration-300"></div>

    <aside id="drawerUsuario" class="fixed top-0 right-0 z-50 w-full max-w-md h-full bg-white dark:bg-[#121826] border-l border-slate-200 dark:border-white/15 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="p-6 border-b border-slate-100 dark:border-white/5 flex items-center justify-between">
            <h3 id="drawerTitulo" class="text-base font-extrabold text-slate-900 dark:text-white"><?= ($idiomaActual === 'en') ? 'Register New User' : 'Registrar Nuevo Usuario' ?></h3>
            <button onclick="cerrarModalUsuario()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="p-6 flex-1 overflow-y-auto space-y-4">
            <form id="formUsuario" action="procesar_usuario.php" method="POST" class="space-y-4">
                
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase"><?= ($idiomaActual === 'en') ? 'Document Type' : 'Tipo Documento' ?></label>
                        <select name="tip_doc_usu" id="input_tip_doc" required class="w-full px-3 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white">
                            <option value="CC">Cédula (CC)</option>
                            <option value="TI">Tarjeta (TI)</option>
                            <option value="CE">Extranjería (CE)</option>
                            <option value="G">Registro (G)</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase"><?= ($idiomaActual === 'en') ? 'Document Number' : 'Número de Documento' ?></label>
                        <input type="text" name="num_doc_usu" id="input_num_doc" required placeholder="Ej: 107249" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white font-mono">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase"><?= $lang['cfg_nombre'] ?? 'Nombre Completo' ?></label>
                    <input type="text" name="nom_usu" id="input_nom_usu" required placeholder="Ej: Kevin Hernández" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white">
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase"><?= $lang['cfg_correo'] ?? 'Correo Electrónico' ?></label>
                    <input type="email" name="corre_usu" id="input_corre_usu" required placeholder="correo@sget.com" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white">
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase"><?= ($idiomaActual === 'en') ? 'User Role' : 'Rol de Usuario' ?></label>
                    <select name="id_rol_usu" id="select_id_rol" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white">
                        <option value="1"><?= ($idiomaActual === 'en') ? 'Administrator' : 'Administrador' ?></option>
                        <option value="2"><?= ($idiomaActual === 'en') ? 'Driver' : 'Conductor' ?></option>
                        <option value="3"><?= ($idiomaActual === 'en') ? 'Passenger' : 'Pasajero' ?></option>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase"><?= ($idiomaActual === 'en') ? 'Password' : 'Contraseña' ?></label>
                    <input type="password" name="contra_usu" id="input_contra" placeholder="<?= ($idiomaActual === 'en') ? 'Leave blank to keep current' : 'Dejar en blanco para mantener actual' ?>" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-white">
                </div>

            </form>
        </div>

        <div class="p-6 border-t border-slate-100 dark:border-white/5 flex gap-3">
            <button type="button" onclick="cerrarModalUsuario()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 text-slate-300 rounded-xl text-xs font-bold uppercase"><?= $lang['cfg_cancelar'] ?? 'Cancelar' ?></button>
            <button type="submit" form="formUsuario" id="btnGuardarDrawer" class="flex-1 py-3 bg-sky-500 text-slate-950 rounded-xl text-xs font-extrabold uppercase shadow-lg shadow-sky-500/20"><?= $lang['cfg_guardar'] ?? 'Guardar' ?></button>
        </div>
    </aside>

    <!-- SCRIPTS DE CONTROL -->
    <script>
    const IDIOMA_ACTUAL = "<?= $idiomaActual ?>";

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

    function cambiarPestana(idTab) {
        document.querySelectorAll('.seccion-tab').forEach(seccion => {
            seccion.classList.add('hidden');
        });
        document.getElementById(idTab).classList.remove('hidden');

        document.querySelectorAll('.pestana-btn').forEach(btn => {
            btn.className = 'pestana-btn px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 text-slate-400 hover:text-white';
        });
        const activo = document.getElementById('btn-' + idTab);
        activo.className = 'pestana-btn px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md';
    }

    function filtrarTablaLocal() {
        const filtro = document.getElementById('inputBuscadorLive').value.toLowerCase();
        const filas = document.querySelectorAll('.fila-usuario');

        filas.forEach(fila => {
            const datos = fila.querySelectorAll('.dato-buscar');
            let textoFila = '';
            datos.forEach(d => textoFila += d.textContent.toLowerCase() + ' ');

            if (textoFila.includes(filtro)) {
                fila.style.display = '';
            } else {
                fila.style.display = 'none';
            }
        });
    }

    function abrirDrawer() {
        document.getElementById('overlayUsuario').classList.remove('opacity-0', 'pointer-events-none');
        document.getElementById('overlayUsuario').classList.add('opacity-100', 'pointer-events-auto');
        document.getElementById('drawerUsuario').classList.remove('translate-x-full');
        document.getElementById('drawerUsuario').classList.add('translate-x-0');
    }

    function cerrarModalUsuario() {
        document.getElementById('drawerUsuario').classList.remove('translate-x-0');
        document.getElementById('drawerUsuario').classList.add('translate-x-full');
        document.getElementById('overlayUsuario').classList.remove('opacity-100', 'pointer-events-auto');
        document.getElementById('overlayUsuario').classList.add('opacity-0', 'pointer-events-none');
    }

    function abrirModalCrear() {
        document.getElementById('formUsuario').action = 'procesar_usuario.php';
        document.getElementById('drawerTitulo').innerText = IDIOMA_ACTUAL === 'en' ? 'Register New User' : 'Registrar Nuevo Usuario';
        document.getElementById('btnGuardarDrawer').innerText = IDIOMA_ACTUAL === 'en' ? 'Save User' : 'Guardar Usuario';
        document.getElementById('formUsuario').reset();
        document.getElementById('input_num_doc').readOnly = false;
        abrirDrawer();
    }

    function abrirModalEditar(datos) {
        document.getElementById('formUsuario').action = 'procesar_usuario.php';
        document.getElementById('drawerTitulo').innerText = (IDIOMA_ACTUAL === 'en' ? 'Edit User: ' : 'Editar Usuario: ') + datos.nom_usu;
        document.getElementById('btnGuardarDrawer').innerText = IDIOMA_ACTUAL === 'en' ? 'Update Changes' : 'Actualizar Cambios';

        document.getElementById('input_tip_doc').value = datos.tip_doc_usu;
        document.getElementById('input_num_doc').value = datos.num_doc_usu;
        document.getElementById('input_num_doc').readOnly = true;
        document.getElementById('input_nom_usu').value = datos.nom_usu;
        document.getElementById('input_corre_usu').value = datos.corre_usu;
        document.getElementById('select_id_rol').value = datos.id_rol_usu;
        document.getElementById('input_contra').value = '';

        abrirDrawer();
    }
    </script>
</body>
</html>
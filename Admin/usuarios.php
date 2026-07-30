<?php
date_default_timezone_set('America/Bogota');   
session_start();
include '../assets/conexion.php'; 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$nombreReal = isset($_SESSION['nombre_usuario']) ? $_SESSION['nombre_usuario'] : "Administrador";
$documento  = $_SESSION['documento'];

// Consulta general de usuarios
$query = "SELECT num_doc_usu, tip_doc_usu, nom_usu, corre_usu, id_rol_usu, estado FROM usuario";
$resultado = $conexion->query($query);

// Arrays para organizar la vista
$admins = []; $conductores = []; $pasajeros = []; $desactivados = []; 

while ($row = $resultado->fetch_assoc()) {
    if ($row['estado'] == 0) {
        $desactivados[] = $row;
    } else {
        if ($row['id_rol_usu'] == 1) $admins[] = $row;
        elseif ($row['id_rol_usu'] == 2) $conductores[] = $row;
        elseif ($row['id_rol_usu'] == 3) $pasajeros[] = $row;
    }
}

$totalUsuarios = count($admins) + count($conductores) + count($pasajeros) + count($desactivados);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - SGET</title>
    
    <!-- Script Anti-parpadeo de Tema -->
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7',
                        'color-mutado': '#94a3b8'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-slate-100 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen flex antialiased transition-colors duration-300">

    <!-- 1. BARRA LATERAL -->
    <?php include 'sidebar.php'; ?>

    <!-- Contenedor Principal -->
    <main class="flex-1 ml-64 flex flex-col min-h-screen">
        
        <!-- 2. HEADER REUTILIZABLE -->
        <?php include 'header.php'; ?>

        <!-- 3. CONTENIDO PRINCIPAL DE LA VISTA -->
        <div class="p-8 max-w-[1600px] w-full mx-auto space-y-6 flex-grow">
            
            <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                        Administración de Usuarios
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Visualice, registre, filtre y edite la información del personal en SGET.
                    </p>
                </div>
                
                <!-- BUSCADOR RÁPIDO EN TIEMPO REAL -->
                <div class="relative w-full md:w-72">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="inputBuscadorLive" onkeyup="filtrarTablaLocal()" placeholder="Buscar documento, nombre..." 
                           class="w-full bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-white/10 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul shadow-sm">
                </div>

                <!-- ALERTAS Y MENSAJES DEL SISTEMA -->
                <div class="flex flex-col gap-2">
                    <?php if(isset($_GET['msg'])): ?>
                        <?php 
                            $msgText = "¡Operación realizada con éxito!";
                            if ($_GET['msg'] == 'desactivado') $msgText = "Usuario desactivado correctamente.";
                            if ($_GET['msg'] == 'reactivado') $msgText = "Usuario reactivado correctamente.";
                            if ($_GET['msg'] == 'creado') $msgText = "Usuario registrado exitosamente.";
                            if ($_GET['msg'] == 'actualizado') $msgText = "Información de usuario actualizada.";
                        ?>
                        <div id="alertaNotificacion" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 p-3 rounded-xl shadow-lg flex items-center justify-between gap-3 text-xs font-bold uppercase tracking-wide transition-all duration-300">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-check-circle text-sm"></i>
                                <span><?php echo $msgText; ?></span>
                            </div>
                            <button onclick="cerrarAlerta('alertaNotificacion')" class="text-emerald-600 dark:text-emerald-400 hover:opacity-75"><i class="fas fa-times"></i></button>
                        </div>
                    <?php endif; ?>

                    <?php if(isset($_GET['error'])): ?>
                        <?php 
                            $errorText = "Ocurrió un error inesperado.";
                            if ($_GET['error'] == 'auto_suspension') $errorText = "¡No puedes suspender tu propia cuenta de administrador!";
                            if ($_GET['error'] == 'no_encontrado') $errorText = "El usuario solicitado no existe.";
                            if ($_GET['error'] == 'mismo_usuario') $errorText = "Acción denegada sobre tu propio perfil.";
                            if ($_GET['error'] == 'rol_admin_bloqueado') $errorText = "No está permitido cambiar el rol de un administrador.";
                        ?>
                        <div id="alertaError" class="bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 p-3 rounded-xl shadow-lg flex items-center justify-between gap-3 text-xs font-bold uppercase tracking-wide transition-all duration-300">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-exclamation-triangle text-sm"></i>
                                <span><?php echo $errorText; ?></span>
                            </div>
                            <button onclick="cerrarAlerta('alertaError')" class="text-red-600 dark:text-red-400 hover:opacity-75"><i class="fas fa-times"></i></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TARJETAS METRICAS RESUMEN (MEJORA DEL SISTEMA) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-[#1e293b] p-4 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-500/10 text-red-500 rounded-xl flex items-center justify-center font-bold text-lg"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400">Admins</p>
                        <p class="text-lg font-black text-slate-900 dark:text-white"><?php echo count($admins); ?></p>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#1e293b] p-4 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm flex items-center gap-3">
                    <div class="w-10 h-10 bg-emerald-500/10 text-emerald-500 rounded-xl flex items-center justify-center font-bold text-lg"><i class="fas fa-id-card"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400">Conductores</p>
                        <p class="text-lg font-black text-slate-900 dark:text-white"><?php echo count($conductores); ?></p>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#1e293b] p-4 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm flex items-center gap-3">
                    <div class="w-10 h-10 bg-sky-500/10 text-sky-500 rounded-xl flex items-center justify-center font-bold text-lg"><i class="fas fa-walking"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400">Pasajeros</p>
                        <p class="text-lg font-black text-slate-900 dark:text-white"><?php echo count($pasajeros); ?></p>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#1e293b] p-4 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm flex items-center gap-3">
                    <div class="w-10 h-10 bg-slate-500/10 text-slate-400 rounded-xl flex items-center justify-center font-bold text-lg"><i class="fas fa-users"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400">Total Usuarios</p>
                        <p class="text-lg font-black text-slate-900 dark:text-white"><?php echo $totalUsuarios; ?></p>
                    </div>
                </div>
            </div>

            <!-- SISTEMA DE PESTAÑAS (TABS) -->
            <div class="border-b border-slate-200 dark:border-white/10 flex flex-wrap gap-2">
                <button onclick="cambiarPestana('tab-admins')" id="btn-tab-admins" class="pestana-btn flex items-center gap-2 px-5 py-3 text-sm font-bold border-b-2 transition-all duration-200 text-neon-azul border-neon-azul">
                    <i class="fas fa-user-shield"></i> Administradores (<?php echo count($admins); ?>)
                </button>
                <button onclick="cambiarPestana('tab-conductores')" id="btn-tab-conductores" class="pestana-btn flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all duration-200">
                    <i class="fas fa-id-card"></i> Conductores (<?php echo count($conductores); ?>)
                </button>
                <button onclick="cambiarPestana('tab-pasajeros')" id="btn-tab-pasajeros" class="pestana-btn flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all duration-200">
                    <i class="fas fa-walking"></i> Pasajeros (<?php echo count($pasajeros); ?>)
                </button>
                <button onclick="cambiarPestana('tab-desactivados')" id="btn-tab-desactivados" class="pestana-btn flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-red-500 transition-all duration-200">
                    <i class="fas fa-user-slash"></i> Desactivados (<?php echo count($desactivados); ?>)
                </button>
            </div>

            <!-- CONTENEDOR DE SECCIONES (TABS) -->
            <div id="contenedor-pestanas">
                
                <?php 
                $secciones = [
                    'tab-admins' => ['data' => $admins, 'label' => 'Admin', 'color' => 'from-red-500 to-rose-600', 'id_rol' => 1, 'visible' => true],
                    'tab-conductores' => ['data' => $conductores, 'label' => 'Conductor', 'color' => 'from-emerald-500 to-teal-600', 'id_rol' => 2, 'visible' => false],
                    'tab-pasajeros' => ['data' => $pasajeros, 'label' => 'Pasajero', 'color' => 'from-neon-azul to-blue-600', 'id_rol' => 3, 'visible' => false]
                ];

                foreach ($secciones as $idTab => $s): 
                ?>
                <!-- Pestaña Rol Activo -->
                <section id="<?php echo $idTab; ?>" class="seccion-tab <?php echo $s['visible'] ? '' : 'hidden'; ?> space-y-4">
                    <div class="flex justify-end items-center px-2">
                        <button onclick="abrirModalCrear(<?php echo $s['id_rol']; ?>)" 
                               class="text-xs font-bold bg-gradient-to-r <?php echo $s['color']; ?> text-white px-4 py-2.5 rounded-xl transition-all duration-300 shadow-md hover:opacity-90 flex items-center gap-2 tracking-wide uppercase">
                            <i class="fas fa-plus"></i> Agregar <?php echo $s['label']; ?>
                        </button>
                    </div>

                    <div class="bg-white dark:bg-[#1e293b] rounded-2xl border border-slate-200 dark:border-white/10 shadow-xl overflow-hidden transition-colors duration-300">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse whitespace-nowrap tabla-datos">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-white/[0.02] border-b border-slate-200 dark:border-white/10">
                                        <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Documento</th>
                                        <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Nombre Completo</th>
                                        <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Correo Electrónico</th>
                                        <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                                    <?php if(empty($s['data'])): ?>
                                    <tr class="fila-vacia">
                                        <td colspan="4" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400 italic text-sm">No hay registros activos en esta categoría...</td>
                                    </tr>
                                    <?php else: foreach ($s['data'] as $u): ?>
                                    <tr class="fila-usuario hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-all duration-150">
                                        <td class="px-6 py-4">
                                            <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 px-2 py-0.5 rounded-md mr-2"><?php echo $u['tip_doc_usu']; ?></span>
                                            <span class="text-sm font-mono text-slate-900 dark:text-white font-semibold dato-buscar"><?php echo $u['num_doc_usu']; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-white rounded-xl flex items-center justify-center text-xs font-black border border-slate-200 dark:border-white/10">
                                                    <?php echo strtoupper(substr($u['nom_usu'], 0, 1)); ?>
                                                </div>
                                                <span class="text-sm font-bold text-slate-900 dark:text-white dato-buscar"><?php echo htmlspecialchars($u['nom_usu']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 italic dato-buscar"><?php echo $u['corre_usu']; ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-4">
                                                <!-- BOTÓN CAMBIAR ESTADO / SUSPENDER CON VALIDACIÓN DE AUTOSUSPENSIÓN -->
                                                <?php if($u['num_doc_usu'] == $documento): ?>
                                                    <!-- Si es el propio Administrador Logueado -->
                                                    <button onclick="bloquearAutoSuspension()" 
                                                            class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-slate-400 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 cursor-not-allowed opacity-60" 
                                                            title="No puedes suspender tu propio perfil de administrador">
                                                        <i class="fas fa-lock text-xs"></i>
                                                        <span class="text-[10px] font-bold uppercase tracking-wider">Tu Cuenta</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="cambiarEstado('<?php echo $u['num_doc_usu']; ?>', 1, '<?php echo $documento; ?>')" 
                                                            class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 hover:bg-emerald-500/20 transition-all duration-250 shadow-sm" title="Desactivar">
                                                        <i class="fas fa-toggle-on text-base"></i>
                                                        <span class="text-[10px] font-bold uppercase tracking-wider">Activo</span>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <div class="flex gap-2 border-l pl-4 border-slate-200 dark:border-white/10">
                                                    <button onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($u)); ?>)" 
                                                       class="p-2 text-neon-azul hover:bg-slate-100 dark:hover:bg-white/5 rounded-xl transition-all" title="Editar datos">
                                                        <i class="fas fa-edit text-base"></i>
                                                    </button>
                                                </div>
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

                <!-- Pestaña Desactivados -->
                <section id="tab-desactivados" class="seccion-tab hidden space-y-4">
                    <div class="bg-white dark:bg-[#1e293b] rounded-2xl border border-slate-200 dark:border-white/10 shadow-xl overflow-hidden transition-colors duration-300">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse whitespace-nowrap tabla-datos">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-white/[0.02] border-b border-slate-200 dark:border-white/10">
                                        <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Documento</th>
                                        <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Nombre Completo</th>
                                        <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Correo Electrónico</th>
                                        <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-white/10">
                                    <?php if(empty($desactivados)): ?>
                                    <tr class="fila-vacia">
                                        <td colspan="4" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400 italic text-sm">No hay usuarios restringidos en el sistema.</td>
                                    </tr>
                                    <?php else: foreach ($desactivados as $u): ?>
                                    <tr class="fila-usuario opacity-60 hover:opacity-100 transition-all duration-200 bg-slate-50/50 dark:bg-black/10">
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-mono text-slate-500 dark:text-slate-400 font-semibold dato-buscar"><?php echo $u['num_doc_usu']; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-bold text-slate-700 dark:text-white/80 line-through decoration-red-500/40 dato-buscar"><?php echo htmlspecialchars($u['nom_usu']); ?></span>
                                            <span class="block text-[9px] uppercase font-bold text-slate-400 tracking-wide">Rol ID: <?php echo $u['id_rol_usu']; ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 italic dato-buscar"><?php echo $u['corre_usu']; ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <button onclick="cambiarEstado('<?php echo $u['num_doc_usu']; ?>', 0, '<?php echo $documento; ?>')" 
                                                    class="flex items-center gap-2 px-3 py-1.5 rounded-xl transition-all duration-250 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-500 dark:text-slate-400 hover:text-neon-azul hover:border-neon-azul/30 inline-flex shadow-sm">
                                                <i class="fas fa-toggle-off text-base"></i>
                                                <span class="text-[10px] font-bold uppercase tracking-wider">Reactivar</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- 4. PIE DE PÁGINA -->
        <footer class="p-6 text-center text-slate-400 text-xs font-semibold border-t border-slate-200 dark:border-white/10">
            &copy; <?php echo date('Y'); ?> Sistema de Gestión de Transporte SGET.
        </footer>
    </main>

    <!-- ==================== PANEL LATERAL DERECHO: CREACIÓN ==================== -->
    <div id="modalCrear" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex justify-end hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 w-full max-w-md h-full p-6 shadow-2xl space-y-6 overflow-y-auto transform translate-x-full transition-transform duration-300" id="panelCrearContenido">
            <div class="flex justify-between items-center border-b border-slate-200 dark:border-white/10 pb-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fas fa-user-plus text-neon-azul"></i> Registrar Usuario
                </h3>
                <button onclick="cerrarModal('modalCrear')" class="text-slate-400 hover:text-white p-2"><i class="fas fa-times text-base"></i></button>
            </div>

            <form action="guardar_usuario.php" method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="crear">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Tipo Doc.</label>
                        <select name="tip_doc_usu" required class="w-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul">
                            <option value="CC">CC - Cédula de Ciudadanía</option>
                            <option value="TI">TI - Tarjeta de Identidad</option>
                            <option value="CE">CE - Cédula de Extranjería</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">N° Documento</label>
                        <input type="text" name="num_doc_usu" required class="w-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Nombre Completo</label>
                    <input type="text" name="nom_usu" required class="w-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Correo Electrónico</label>
                    <input type="email" name="corre_usu" required class="w-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Rol</label>
                        <select name="id_rol_usu" id="crear_id_rol" required class="w-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul">
                            <option value="1">Administrador</option>
                            <option value="2">Conductor</option>
                            <option value="3">Pasajero</option>
                        </select>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400">Contraseña</label>
                            <button type="button" onclick="generarPasswordAuto()" class="text-[10px] text-neon-azul font-bold hover:underline"><i class="fas fa-magic"></i> Auto</button>
                        </div>
                        <input type="text" id="crear_clave_usu" name="clave_usu" required class="w-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul font-mono">
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-white/10">
                    <button type="button" onclick="cerrarModal('modalCrear')" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-200 dark:bg-white/10 text-slate-600 dark:text-slate-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold bg-neon-azul text-slate-900 hover:bg-sky-400">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== PANEL LATERAL DERECHO: EDICIÓN ==================== -->
    <div id="modalEditar" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex justify-end hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white dark:bg-[#1e293b] border-l border-slate-200 dark:border-white/10 w-full max-w-md h-full p-6 shadow-2xl space-y-6 overflow-y-auto transform translate-x-full transition-transform duration-300" id="panelEditarContenido">
            <div class="flex justify-between items-center border-b border-slate-200 dark:border-white/10 pb-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fas fa-edit text-neon-azul"></i> Editar Usuario
                </h3>
                <button onclick="cerrarModal('modalEditar')" class="text-slate-400 hover:text-white p-2"><i class="fas fa-times text-base"></i></button>
            </div>

            <form action="actualizar_usuario.php" method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id_rol_usu_real" id="edit_id_rol_usu_real">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Tipo Doc.</label>
                        <select name="tip_doc_usu" id="edit_tip_doc_usu" required class="w-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul">
                            <option value="CC">CC</option>
                            <option value="TI">TI</option>
                            <option value="CE">CE</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">N° Documento (Fijo)</label>
                        <input type="text" name="num_doc_usu" id="edit_num_doc_usu" readonly class="w-full bg-slate-200 dark:bg-white/10 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-500 dark:text-slate-400 cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Nombre Completo</label>
                    <input type="text" name="nom_usu" id="edit_nom_usu" required class="w-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Correo Electrónico</label>
                    <input type="email" name="corre_usu" id="edit_corre_usu" required class="w-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Rol de Usuario</label>
                    <select name="id_rol_usu" id="edit_id_rol_usu" required class="w-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-neon-azul">
                        <option value="1">Administrador</option>
                        <option value="2">Conductor</option>
                        <option value="3">Pasajero</option>
                    </select>
                    <!-- MENSAJE INFORMATIVO EN CASO DE EDITAR UN ADMIN -->
                    <p id="msg_bloqueo_rol" class="hidden text-[11px] text-amber-500 dark:text-amber-400 font-medium mt-1 flex items-center gap-1">
                        <i class="fas fa-exclamation-circle"></i> Los administradores no pueden modificar su propio rol desde esta vista.
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-white/10">
                    <button type="button" onclick="cerrarModal('modalEditar')" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-200 dark:bg-white/10 text-slate-600 dark:text-slate-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold bg-neon-azul text-slate-900 hover:bg-sky-400">Actualizar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS LÓGICOS DE LA PÁGINA -->
    <script>
    function cerrarAlerta(idElemento) {
        const elemento = document.getElementById(idElemento);
        if(elemento) {
            elemento.classList.add('opacity-0', 'scale-95');
            setTimeout(() => elemento.remove(), 300);
        }
    }

    setTimeout(() => {
        cerrarAlerta('alertaNotificacion');
        cerrarAlerta('alertaError');
    }, 5000);

    // FUNCIÓN DE AUTO-SUSPENSIÓN BLOQUEADA
    function bloquearAutoSuspension() {
        alert("⚠️ Operación Denegada:\n\nNo puedes suspender tu propia cuenta de administrador mientras mantienes una sesión activa en el sistema.");
    }

    function cambiarEstado(documentoUsuario, estadoActual, documentoLogueado) {
        const nuevoEstado = estadoActual === 1 ? 0 : 1;

        if (nuevoEstado === 0 && documentoUsuario === documentoLogueado) {
            bloquearAutoSuspension();
            return;
        }

        const accion = nuevoEstado === 1 ? "REACTIVAR" : "DESACTIVAR";
        let mensajeAdvertencia = `⚠️ ADVERTENCIA: ¿Está completamente seguro de que desea ${accion} al usuario con documento ${documentoUsuario}?\n\nEsta acción modificará el acceso del usuario en el sistema SGET.`;
        
        if (confirm(mensajeAdvertencia)) {
            window.location.href = `actualizar_estado.php?doc=${documentoUsuario}&nuevo_estado=${nuevoEstado}`;
        }
    }

    function cambiarPestana(idTab) {
        document.querySelectorAll('.seccion-tab').forEach(seccion => {
            seccion.classList.add('hidden');
        });
        
        document.querySelectorAll('.pestana-btn').forEach(btn => {
            btn.classList.remove('text-neon-azul', 'border-neon-azul', 'font-bold');
            btn.classList.add('border-transparent', 'text-slate-500', 'dark:text-slate-400', 'font-medium');
        });

        document.getElementById(idTab).classList.remove('hidden');

        const btnActivo = document.getElementById(`btn-${idTab}`);
        btnActivo.classList.add('text-neon-azul', 'border-neon-azul', 'font-bold');
        btnActivo.classList.remove('border-transparent', 'text-slate-500', 'dark:text-slate-400', 'font-medium');
        
        // Limpiar búsqueda al cambiar pestaña
        document.getElementById('inputBuscadorLive').value = '';
        filtrarTablaLocal();
    }

    // BUSCADOR DINÁMICO EN TIEMPO REAL
    function filtrarTablaLocal() {
        const query = document.getElementById('inputBuscadorLive').value.toLowerCase();
        const filas = document.querySelectorAll('.seccion-tab:not(.hidden) .fila-usuario');

        filas.forEach(fila => {
            const textoFila = fila.textContent.toLowerCase();
            if(textoFila.includes(query)) {
                fila.style.display = '';
            } else {
                fila.style.display = 'none';
            }
        });
    }

    // FUNCIONES PANELES LATERALES (MODALES)
    function abrirModalCrear(idRol) {
        document.getElementById('crear_id_rol').value = idRol;
        const modal = document.getElementById('modalCrear');
        const contenido = document.getElementById('panelCrearContenido');
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            contenido.classList.remove('translate-x-full');
        }, 10);
    }

    function abrirModalEditar(usuario) {
        document.getElementById('edit_num_doc_usu').value = usuario.num_doc_usu;
        document.getElementById('edit_tip_doc_usu').value = usuario.tip_doc_usu;
        document.getElementById('edit_nom_usu').value = usuario.nom_usu;
        document.getElementById('edit_corre_usu').value = usuario.corre_usu;
        
        const selectRol = document.getElementById('edit_id_rol_usu');
        const msgBloqueo = document.getElementById('msg_bloqueo_rol');
        const inputHiddenRol = document.getElementById('edit_id_rol_usu_real');

        selectRol.value = usuario.id_rol_usu;
        inputHiddenRol.value = usuario.id_rol_usu;

        // Si el usuario a editar es ADMINISTRADOR (ID 1), bloquear la alteración del ROL
        if (parseInt(usuario.id_rol_usu) === 1) {
            selectRol.disabled = true;
            selectRol.classList.add('opacity-50', 'cursor-not-allowed');
            msgBloqueo.classList.remove('hidden');
        } else {
            selectRol.disabled = false;
            selectRol.classList.remove('opacity-50', 'cursor-not-allowed');
            msgBloqueo.classList.add('hidden');
        }

        const modal = document.getElementById('modalEditar');
        const contenido = document.getElementById('panelEditarContenido');
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            contenido.classList.remove('translate-x-full');
        }, 10);
    }

    function cerrarModal(idModal) {
        const modal = document.getElementById(idModal);
        const contenido = idModal === 'modalCrear' ? document.getElementById('panelCrearContenido') : document.getElementById('panelEditarContenido');
        
        contenido.classList.add('translate-x-full');
        modal.classList.add('opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function generarPasswordAuto() {
        const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%";
        let pass = "";
        for (let i = 0; i < 10; i++) {
            pass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('crear_clave_usu').value = pass;
    }
    </script>
</body>
</html>
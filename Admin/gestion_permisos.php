<?php
// Archivo: Admin/gestion_permisos.php
date_default_timezone_set('America/Bogota');
session_start();

require_once '../assets/conexion.php';
require_once '../helpers/AuthHelper.php';

// Validar sesión y rol de Administrador
if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

$idAdmin = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 1;
AuthHelper::requerirPermiso($conexion, $idAdmin, 'gestionar_permisos');

// Consultas para tarjetas de métricas y listado de usuarios
$sqlUsuarios = "SELECT u.id_usu, u.num_doc_usu, u.tip_doc_usu, u.nom_usu, u.corre_usu, u.id_rol_usu, r.nom_rol 
                FROM usuario u 
                INNER JOIN rol r ON u.id_rol_usu = r.id_rol
                ORDER BY u.nom_usu ASC";

$resultado = mysqli_query($conexion, $sqlUsuarios);
$usuarios = [];
$c_admins = 0;
$c_conductores = 0;
$c_pasajeros = 0;

if ($resultado) {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $usuarios[] = $fila;
        if ($fila['id_rol_usu'] == 1) {
            $c_admins++;
        } elseif ($fila['id_rol_usu'] == 2) {
            $c_conductores++;
        } elseif ($fila['id_rol_usu'] == 3) {
            $c_pasajeros++;
        }
    }
}
$total_usuarios = count($usuarios);
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Administración de Permisos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'bg-principal': { DEFAULT: '#f8fafc', dark: '#0b0f19' },
                        'bg-tarjeta': { DEFAULT: '#ffffff', dark: '#1e293b' },
                        'texto-base': { DEFAULT: '#334155', dark: '#cbd5e1' },
                        'neon-azul': '#38bdf8',
                        'neon-morado': '#a855f7',
                        'color-mutado': '#94a3b8'
                    }
                }
            }
        }
    </script>
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-bg-principal dark:bg-bg-principal-dark flex min-h-screen text-texto-base dark:text-[#cbd5e1] transition-colors duration-300">

    <?php include '../includes/sidebar.php'; ?>

    <main class="flex-1 ml-64 flex flex-col min-h-screen">

        <?php include '../includes/header.php'; ?>

        <div class="p-8 w-full mx-auto space-y-6 flex-grow">
            
            <!-- Encabezado con Buscador -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight flex items-center gap-2">
                        Administración de Permisos <i class="fas fa-question-circle text-xs text-neon-azul cursor-pointer" title="Configura las funciones del sistema por usuario"></i>
                    </h1>
                    <p class="text-color-mutado mt-1 text-sm font-medium">Visualice y gestione los accesos y funciones dinámicas del personal en SGET.</p>
                </div>

                <!-- Buscador rápido en tiempo real -->
                <div class="relative w-full md:w-80">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-color-mutado">
                        <i class="fas fa-search text-xs"></i>
                    </span>
                    <input type="text" id="inputBuscar" onkeyup="filtrarUsuarios()" placeholder="Buscar documento, nombre..." 
                           class="w-full pl-9 pr-4 py-2 bg-bg-tarjeta dark:bg-[#1e293b] border border-slate-200 dark:border-white/10 rounded-xl text-xs text-slate-700 dark:text-slate-200 placeholder-color-mutado focus:outline-none focus:border-neon-azul transition-all">
                </div>
            </div>

            <!-- Fila de Tarjetas KPI / Métricas -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Admins -->
                <div onclick="cambiarTab('admins')" class="bg-bg-tarjeta dark:bg-[#1e293b] p-5 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm flex items-center gap-4 cursor-pointer hover:border-red-500/50 transition-all group">
                    <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500 group-hover:scale-110 transition-transform">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-color-mutado font-extrabold uppercase tracking-wider">Admins</p>
                        <h3 class="text-2xl font-black text-slate-800 dark:text-white"><?= $c_admins; ?></h3>
                    </div>
                </div>

                <!-- Conductores -->
                <div onclick="cambiarTab('conductores')" class="bg-bg-tarjeta dark:bg-[#1e293b] p-5 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm flex items-center gap-4 cursor-pointer hover:border-emerald-500/50 transition-all group">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 group-hover:scale-110 transition-transform">
                        <i class="fas fa-id-card text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-color-mutado font-extrabold uppercase tracking-wider">Conductores</p>
                        <h3 class="text-2xl font-black text-slate-800 dark:text-white"><?= $c_conductores; ?></h3>
                    </div>
                </div>

                <!-- Pasajeros -->
                <div onclick="cambiarTab('pasajeros')" class="bg-bg-tarjeta dark:bg-[#1e293b] p-5 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm flex items-center gap-4 cursor-pointer hover:border-neon-azul/50 transition-all group">
                    <div class="w-12 h-12 rounded-xl bg-neon-azul/10 flex items-center justify-center text-neon-azul group-hover:scale-110 transition-transform">
                        <i class="fas fa-walking text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-color-mutado font-extrabold uppercase tracking-wider">Pasajeros</p>
                        <h3 class="text-2xl font-black text-slate-800 dark:text-white"><?= $c_pasajeros; ?></h3>
                    </div>
                </div>

                <!-- Total -->
                <div onclick="cambiarTab('todos')" class="bg-bg-tarjeta dark:bg-[#1e293b] p-5 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm flex items-center gap-4 cursor-pointer hover:border-neon-morado/50 transition-all group">
                    <div class="w-12 h-12 rounded-xl bg-neon-morado/10 flex items-center justify-center text-neon-morado group-hover:scale-110 transition-transform">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-color-mutado font-extrabold uppercase tracking-wider">Total Usuarios</p>
                        <h3 class="text-2xl font-black text-slate-800 dark:text-white"><?= $total_usuarios; ?></h3>
                    </div>
                </div>
            </div>

            <!-- Navegación por Pestañas -->
            <div class="flex items-center space-x-6 border-b border-slate-200 dark:border-white/10 pb-1 text-xs font-extrabold">
                <button onclick="cambiarTab('admins')" id="tab-admins" class="tab-btn pb-2 border-b-2 border-transparent text-color-mutado hover:text-white transition-all flex items-center gap-2">
                    <i class="fas fa-user-shield"></i> Administradores (<?= $c_admins; ?>)
                </button>
                <button onclick="cambiarTab('conductores')" id="tab-conductores" class="tab-btn pb-2 border-b-2 border-transparent text-color-mutado hover:text-white transition-all flex items-center gap-2">
                    <i class="fas fa-id-card"></i> Conductores (<?= $c_conductores; ?>)
                </button>
                <button onclick="cambiarTab('pasajeros')" id="tab-pasajeros" class="tab-btn pb-2 border-b-2 border-transparent text-color-mutado hover:text-white transition-all flex items-center gap-2">
                    <i class="fas fa-walking"></i> Pasajeros (<?= $c_pasajeros; ?>)
                </button>
                <button onclick="cambiarTab('todos')" id="tab-todos" class="tab-btn pb-2 border-b-2 border-transparent text-color-mutado hover:text-white transition-all flex items-center gap-2">
                    <i class="fas fa-users"></i> Todos (<?= $total_usuarios; ?>)
                </button>
            </div>

            <!-- Tabla Principal -->
            <div class="bg-bg-tarjeta dark:bg-[#1e293b] p-6 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-white/5 text-[11px] font-extrabold text-color-mutado uppercase tracking-wider">
                                <th class="pb-3 pl-2">Documento</th>
                                <th class="pb-3">Nombre Completo</th>
                                <th class="pb-3">Correo Electrónico</th>
                                <th class="pb-3 pr-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-usuarios-body" class="divide-y divide-slate-100 dark:divide-white/5 text-xs font-medium">
                            <!-- Se renderiza vía Javascript -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <footer class="p-6 text-center text-color-mutado text-xs font-semibold border-t border-slate-200 dark:border-white/5 bg-slate-50/20 dark:bg-transparent">
            &copy; <?php echo date('Y'); ?> Sistema de Gestión de Transporte SGET. Todos los derechos reservados.
        </footer>
    </main>

    <!-- Modal de Permisos -->
    <div id="modalGestionPermisos" class="fixed inset-0 z-[999] hidden flex items-center justify-center bg-black/70 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="bg-[#1e293b] border border-white/10 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden transform transition-all">
            
            <div class="p-5 border-b border-white/5 flex justify-between items-center bg-white/5">
                <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i class="fas fa-sliders-h text-neon-azul"></i> Permisos de: <span id="modalNombreUsuario" class="text-neon-azul"></span>
                </h3>
                <button type="button" onclick="cerrarModalPermisos()" class="text-color-mutado hover:text-white transition-colors p-1">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <form id="formGuardarPermisos">
                <input type="hidden" id="modalIdUsuario" name="id_usu">

                <div class="p-6 max-h-[65vh] overflow-y-auto space-y-4">
                    <div id="loaderPermisos" class="py-10 text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-neon-azul"></i>
                        <p class="mt-3 text-sm text-color-mutado font-medium">Cargando funciones asignadas...</p>
                    </div>

                    <div id="contenedorPermisos" class="hidden space-y-4"></div>
                </div>

                <div class="p-4 border-t border-white/5 bg-white/5 flex justify-end gap-3">
                    <button type="button" onclick="cerrarModalPermisos()" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-slate-200 rounded-xl text-xs font-bold transition-all">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 bg-gradient-to-r from-neon-azul to-neon-morado text-white rounded-xl text-xs font-bold shadow-md hover:opacity-90 transition-all flex items-center gap-2">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Script de Lógica de Pestañas, Búsqueda y Modal -->
    <script>
        const listaUsuarios = <?= json_encode($usuarios); ?>;
        const idAdminActual = <?= $idAdmin; ?>;
        let rolActual = 'admins';

        function cambiarTab(rol) {
            rolActual = rol;
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('border-neon-azul', 'text-neon-azul');
                b.classList.add('border-transparent', 'text-color-mutado');
            });

            const btnActivo = document.getElementById(`tab-${rol}`);
            if (btnActivo) {
                btnActivo.classList.add('border-neon-azul', 'text-neon-azul');
                btnActivo.classList.remove('border-transparent', 'text-color-mutado');
            }

            filtrarUsuarios();
        }

        function filtrarUsuarios() {
            const texto = document.getElementById('inputBuscar').value.toLowerCase();
            
            let filtrados = listaUsuarios.filter(u => {
                const coincideRol = (rolActual === 'todos') ? true : 
                                   (rolActual === 'admins' && u.id_rol_usu == 1) ||
                                   (rolActual === 'conductores' && u.id_rol_usu == 2) ||
                                   (rolActual === 'pasajeros' && u.id_rol_usu == 3);
                
                const doc = (u.num_doc_usu || '').toLowerCase();
                const nom = (u.nom_usu || '').toLowerCase();
                const correo = (u.corre_usu || '').toLowerCase();

                const coincideTexto = doc.includes(texto) || nom.includes(texto) || correo.includes(texto);

                return coincideRol && coincideTexto;
            });

            renderizarTabla(filtrados);
        }

        function renderizarTabla(lista) {
            const tbody = document.getElementById('tabla-usuarios-body');
            tbody.innerHTML = '';

            if (lista.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="py-8 text-center text-color-mutado italic">No se encontraron usuarios en esta categoría.</td></tr>`;
                return;
            }

            lista.forEach(usu => {
                const tipoDoc = usu.tip_doc_usu || 'CC';
                const numDoc = usu.num_doc_usu || 'Sin documento';
                const inicial = usu.nom_usu ? usu.nom_usu.charAt(0).toUpperCase() : 'U';
                const esCuentaPropia = (usu.id_usu == idAdminActual);

                let botonAccion = `
                    <button onclick="abrirModalPermisos(${usu.id_usu}, '${usu.nom_usu.replace(/'/g, "\\'")}')" 
                            class="bg-neon-azul/10 hover:bg-neon-azul/20 text-neon-azul p-2 rounded-xl font-bold transition-all inline-flex items-center gap-1.5" title="Editar Permisos">
                        <i class="fas fa-edit text-sm"></i>
                    </button>
                `;

                if (esCuentaPropia) {
                    botonAccion = `
                        <span class="bg-white/5 text-color-mutado px-3 py-1 rounded-xl text-[11px] font-bold inline-flex items-center gap-1">
                            <i class="fas fa-lock text-[10px]"></i> TU CUENTA
                        </span>
                    `;
                }

                tbody.innerHTML += `
                    <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors duration-150">
                        <td class="py-3.5 pl-2">
                            <span class="bg-white/5 text-color-mutado px-2 py-0.5 rounded text-[10px] font-bold font-mono mr-1">${tipoDoc}</span>
                            <span class="font-bold text-slate-700 dark:text-slate-200 font-mono">${numDoc}</span>
                        </td>
                        <td class="py-3.5 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center font-bold text-neon-azul text-xs">
                                ${inicial}
                            </div>
                            <span class="font-bold text-slate-700 dark:text-slate-200">${usu.nom_usu}</span>
                        </td>
                        <td class="py-3.5 text-color-mutado italic">${usu.corre_usu}</td>
                        <td class="py-3.5 pr-2 text-right">
                            ${botonAccion}
                        </td>
                    </tr>
                `;
            });
        }

        document.addEventListener('DOMContentLoaded', () => cambiarTab('admins'));
    </script>

    <script src="../js/permisos.js"></script>
</body>
</html>
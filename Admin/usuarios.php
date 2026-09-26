<?php
/**
 * Admin/usuarios.php
 * -----------------------------------------------------------------------------
 * MÓDULO: ADMINISTRACIÓN DE USUARIOS  (Admin)
 * -----------------------------------------------------------------------------
 * CORRECCIONES APLICADAS
 *   - `cambiar_estado_usu.php` NO EXISTÍA (la vista lo enlazaba → 404).
 *     Ahora suspender/reactivar pasa por el API con confirmación y auditoría.
 *   - `estado` es NOT NULL: un usuario con NULL ya no puede quedar invisible.
 *   - Las contraseñas se guardan con password_hash() (antes en texto plano).
 *   - Paginación por pestañas real (con roles y estado de conductor).
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('usuarios');

if (!empty($_GET['ok']))       Flash::exito((string)$_GET['ok']);
elseif (!empty($_GET['error'])) Flash::error((string)$_GET['error']);

$grupos  = UsuarioService::agrupar();
$totales = [
    'admins'       => count($grupos['tab-admins']),
    'conductores'  => count($grupos['tab-conductores']),
    'pasajeros'    => count($grupos['tab-pasajeros']),
    'inactivos'    => count($grupos['tab-inactivos']),
];
$totalUsuarios = array_sum($totales);
$miId          = Auth::id();

<<<<<<< Updated upstream
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
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Administración de Usuarios</title>
    
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
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen antialiased">
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
                            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Administración de Usuarios</h1>
                            
                            <!-- BOTÓN DE AYUDA DEL SISTEMA -->
                            <button type="button" onclick="abrirModalAyuda()" class="w-6 h-6 rounded-full bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center text-xs font-bold shadow-xs cursor-pointer" title="Ver guía del módulo">
                                <i class="fas fa-question text-[10px]"></i>
                            </button>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Visualice, registre, edite y controle el estado operativo del personal en SGET.</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <!-- Buscador Rápido -->
                    <div class="relative w-full md:w-64">
                        <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="inputBuscadorLive" onkeyup="filtrarTablaLocal()" placeholder="Buscar documento, nombre..." data-i18n-placeholder-es="Buscar documento, nombre..." data-i18n-placeholder-en="Search by document or name..." 
                               class="w-full bg-white dark:bg-black/20 border border-slate-200 dark:border-white/10 rounded-2xl pl-10 pr-4 py-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-400 transition-all shadow-sm">
                    </div>

                    <button onclick="abrirModalCrear()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-sky-500/20 hover:opacity-90 transition-all cursor-pointer whitespace-nowrap">
                        <i class="fas fa-user-plus text-sm"></i> Nuevo Usuario
                    </button>
                </div>
            </div>

            <!-- TARJETAS MÉTRICAS RESUMEN -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="bg-white dark:bg-[#121826] p-5 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl flex items-center gap-4">
                    <div class="w-12 h-12 bg-red-500/10 text-red-400 rounded-2xl flex items-center justify-center font-bold text-lg border border-red-500/20"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Admins</p>
                        <p class="text-xl font-black text-slate-900 dark:text-white mt-0.5 font-mono"><?php echo count($admins); ?></p>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#121826] p-5 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl flex items-center gap-4">
                    <div class="w-12 h-12 bg-emerald-500/10 text-emerald-400 rounded-2xl flex items-center justify-center font-bold text-lg border border-emerald-500/20"><i class="fas fa-id-card"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Conductores</p>
                        <p class="text-xl font-black text-slate-900 dark:text-white mt-0.5 font-mono"><?php echo count($conductores); ?></p>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#121826] p-5 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl flex items-center gap-4">
                    <div class="w-12 h-12 bg-sky-500/10 text-sky-400 rounded-2xl flex items-center justify-center font-bold text-lg border border-sky-500/20"><i class="fas fa-walking"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Pasajeros</p>
                        <p class="text-xl font-black text-slate-900 dark:text-white mt-0.5 font-mono"><?php echo count($pasajeros); ?></p>
                    </div>
                </div>
                <div class="bg-white dark:bg-[#121826] p-5 rounded-3xl border border-slate-200 dark:border-white/10 shadow-xl flex items-center gap-4">
                    <div class="w-12 h-12 bg-slate-500/10 text-slate-400 rounded-2xl flex items-center justify-center font-bold text-lg border border-slate-500/20"><i class="fas fa-users"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Total Usuarios</p>
                        <p class="text-xl font-black text-slate-900 dark:text-white mt-0.5 font-mono"><?php echo $totalUsuarios; ?></p>
                    </div>
                </div>
            </div>

            <!-- SISTEMA DE PESTAÑAS (TABS) -->
            <div class="flex flex-wrap gap-2 bg-white dark:bg-[#121826] p-2 rounded-2xl border border-slate-200 dark:border-white/10 w-fit shadow-md">
                <button onclick="cambiarPestana('tab-admins')" id="btn-tab-admins" class="pestana-btn px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-md">
                    <i class="fas fa-user-shield text-xs"></i> Administradores (<?php echo count($admins); ?>)
                </button>
                <button onclick="cambiarPestana('tab-conductores')" id="btn-tab-conductores" class="pestana-btn px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 text-slate-400 hover:text-white">
                    <i class="fas fa-id-card text-xs"></i> Conductores (<?php echo count($conductores); ?>)
                </button>
                <button onclick="cambiarPestana('tab-pasajeros')" id="btn-tab-pasajeros" class="pestana-btn px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 text-slate-400 hover:text-white">
                    <i class="fas fa-walking text-xs"></i> Pasajeros (<?php echo count($pasajeros); ?>)
                </button>
                <button onclick="cambiarPestana('tab-desactivados')" id="btn-tab-desactivados" class="pestana-btn px-5 py-2.5 rounded-xl text-[11px] font-bold uppercase tracking-wider transition-all flex items-center gap-2 text-slate-400 hover:text-red-400">
                    <i class="fas fa-user-slash text-xs"></i> Desactivados (<?php echo count($desactivados); ?>)
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
=======
/** Dibuja una tabla de usuarios para una sección. */
$tablaUsuarios = function (array $usuarios, bool $mostrarEstadoConductor = false) use ($miId): void {
    if (empty($usuarios)): ?>
        <p class="sget-sin-resultados">No hay registros en esta categoría.</p>
    <?php else: ?>
        <div class="sget-table-wrap">
            <table class="sget-table">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Nombre</th>
                        <th class="sget-hide-mobile">Correo</th>
                        <?php if ($mostrarEstadoConductor): ?>
                            <th class="sget-centro sget-hide-mobile">Disponibilidad</th>
                        <?php endif; ?>
                        <th class="sget-centro">Rol</th>
                        <th class="sget-centro">Estado</th>
                        <th class="acciones">Gestión</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u):
                    $id      = (int)$u['id_usu'];
                    $activo  = (int)$u['estado'] === Config::USU_ACTIVO;
                    $esYo    = $id === $miId;
                    $rol     = (int)$u['id_rol_usu'];
                    $datos   = [
                        'id_usu'      => $id,
                        'tip_doc_usu' => $u['tip_doc_usu'],
                        'num_doc_usu' => $u['num_doc_usu'],
                        'nom_usu'     => $u['nom_usu'],
                        'corre_usu'   => $u['corre_usu'],
                        'tel_usu'     => $u['tel_usu'],
                        'id_rol_usu'  => $rol,
                        'estado'      => (int)$u['estado'],
                        'titulo'      => 'Editar: ' . $u['nom_usu'],
                    ];
>>>>>>> Stashed changes
                ?>
                    <tr data-sget-fila>
                        <td data-label="Documento">
                            <span class="sget-badge sget-badge--neutro"><?= htmlspecialchars((string)$u['tip_doc_usu'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="sget-mono"><?= htmlspecialchars((string)$u['num_doc_usu'], ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td data-label="Nombre">
                            <div style="display:flex;align-items:center;gap:.625rem">
                                <span class="sget-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr((string)$u['nom_usu'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="sget-truncar"><?= htmlspecialchars((string)$u['nom_usu'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </td>
                        <td data-label="Correo" class="sget-suave sget-truncar sget-hide-mobile"><?= htmlspecialchars((string)$u['corre_usu'], ENT_QUOTES, 'UTF-8') ?></td>
                        <?php if ($mostrarEstadoConductor): ?>
                            <td data-label="Disponibilidad" class="sget-centro sget-hide-mobile">
                                <span class="sget-badge <?= (int)($u['est_con_usu'] ?? 0) === Config::CON_DISPONIBLE ? 'sget-badge--exito' : 'sget-badge--aviso' ?>">
                                    <?= (int)($u['est_con_usu'] ?? 0) === Config::CON_DISPONIBLE ? 'Disponible' : 'Ocupado' ?>
                                </span>
                            </td>
                        <?php endif; ?>
                        <td data-label="Rol" class="sget-centro">
                            <span class="sget-badge <?= UsuarioService::claseRol($rol) ?>"><?= UsuarioService::ETIQUETA_ROL[$rol] ?? '—' ?></span>
                        </td>
                        <td data-label="Estado" class="sget-centro">
                            <?php if ($esYo): ?>
                                <span class="sget-badge sget-badge--info">Tu cuenta</span>
                            <?php else: ?>
                                <span class="sget-badge <?= UsuarioService::claseEstado($u['estado']) ?>"><?= UsuarioService::etiquetaEstado($u['estado']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="acciones" data-label="Acciones">
                            <button type="button" class="sget-icon-btn sget-icon-btn--editar" title="Editar" aria-label="Editar usuario"
                                    data-sget-modal="modalUsuario" data-sget-nuevo="Registrar Nuevo Usuario"
                                    data-sget-datos='<?= htmlspecialchars(json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="fas fa-pen"></i>
                            </button>

                            <?php if (!$esYo): ?>
                                <button type="button" class="sget-icon-btn <?= $activo ? 'sget-icon-btn--peligro' : 'sget-icon-btn--exito' ?>"
                                        title="<?= $activo ? 'Suspender cuenta' : 'Reactivar cuenta' ?>"
                                        aria-label="<?= $activo ? 'Suspender cuenta' : 'Reactivar cuenta' ?>"
                                        data-sget-accion="suspender"
                                        data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id, 'estado' => $activo ? Config::USU_INACTIVO : Config::USU_ACTIVO, 'nombre' => $u['nom_usu']], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                    <i class="fas <?= $activo ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif;
};

$tituloPagina = 'Administración de Usuarios';
include __DIR__ . '/../views/partials/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="sget-main">
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title"><i class="fas fa-users-gear text-sky-500"></i> Administración de Usuarios</h1>
                <p class="sget-page-sub">Registra, edita y controla el acceso de administradores, conductores y pasajeros.</p>
            </div>
            <div class="sget-page-actions">
                <div class="sget-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" id="buscarUsuario" class="sget-input" placeholder="Buscar documento, nombre o correo… (Ctrl+K)">
                </div>
                <button type="button" class="sget-btn sget-btn--primario" data-sget-modal="modalUsuario" data-sget-nuevo="Registrar Nuevo Usuario">
                    <i class="fas fa-user-plus"></i> Nuevo Usuario
                </button>
            </div>
        </header>

        <?= Flash::render() ?>

        <section class="sget-grid sget-grid--kpi">
            <?php foreach ([
                ['fa-user-shield', 'var(--sget-rojo)',      'Administradores', $totales['admins']],
                ['fa-id-card',     'var(--sget-emerald)',  'Conductores',     $totales['conductores']],
                ['fa-walking',     'var(--sget-azul)',     'Pasajeros',       $totales['pasajeros']],
                ['fa-user-slash',  'var(--sget-ambars)',   'Suspendidos',     $totales['inactivos']],
                ['fa-users',       'var(--sget-morado)',   'Total usuarios',  $totalUsuarios],
            ] as $kpi): ?>
                <div class="sget-card sget-kpi">
                    <span class="sget-kpi__icono" style="background:color-mix(in srgb,<?= $kpi[1] ?> 12%,transparent);color:<?= $kpi[1] ?>">
                        <i class="fas <?= $kpi[0] ?>"></i></span>
                    <div><p class="sget-label"><?= $kpi[2] ?></p><p class="sget-kpi__valor"><?= $kpi[3] ?></p></div>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="sget-tabs" data-sget-tabgrupo role="tablist" aria-label="Filtrar por rol">
            <button type="button" class="sget-tab" role="tab" aria-selected="true"  data-sget-tab="tab-admins">
                <i class="fas fa-user-shield"></i> Administradores (<?= $totales['admins'] ?>)
            </button>
            <button type="button" class="sget-tab" role="tab" aria-selected="false" data-sget-tab="tab-conductores">
                <i class="fas fa-id-card"></i> Conductores (<?= $totales['conductores'] ?>)
            </button>
            <button type="button" class="sget-tab" role="tab" aria-selected="false" data-sget-tab="tab-pasajeros">
                <i class="fas fa-walking"></i> Pasajeros (<?= $totales['pasajeros'] ?>)
            </button>
            <button type="button" class="sget-tab" role="tab" aria-selected="false" data-sget-tab="tab-inactivos">
                <i class="fas fa-user-slash"></i> Suspendidos (<?= $totales['inactivos'] ?>)
            </button>
        </div>

        <?php foreach ([
            'tab-admins'      => ['usuarios' => $grupos['tab-admins'],       'conductor' => false],
            'tab-conductores' => ['usuarios' => $grupos['tab-conductores'],  'conductor' => true],
            'tab-pasajeros'   => ['usuarios' => $grupos['tab-pasajeros'],    'conductor' => false],
            'tab-inactivos'   => ['usuarios' => $grupos['tab-inactivos'],    'conductor' => false],
        ] as $panel => $cfg): ?>
            <section class="sget-table-box" data-sget-panel-id="<?= $panel ?>" <?= $panel !== 'tab-admins' ? 'hidden' : '' ?>>
                <?php $tablaUsuarios($cfg['usuarios'], $cfg['conductor']); ?>
            </section>
        <?php endforeach; ?>
    </main>
</div>

<?php include __DIR__ . '/../views/modals/usuario.php'; ?>

<script>
    window.__MOTIVOS_VIAJE__ = window.__MOTIVOS_VIAJE__ || [];
    document.addEventListener('DOMContentLoaded', function () {
        SGETCRUD.atajoBusqueda('buscarUsuario');
        // La búsqueda recorre TODAS las pestañas, no solo la visible
        SGETCRUD.buscar('buscarUsuario', '[data-sget-fila]');
    });
</script>
<?php
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';

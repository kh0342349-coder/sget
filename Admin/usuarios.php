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
                    <input type="search" id="buscarUsuario" class="sget-input" placeholder="Buscar documento, nombre o correo… (Ctrl+K)" autocomplete="off" spellcheck="false">
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
    document.addEventListener('DOMContentLoaded', function () {
        SGETCRUD.atajoBusqueda('buscarUsuario');
        // La búsqueda recorre TODAS las pestañas, no solo la visible
        SGETCRUD.buscar('buscarUsuario', '[data-sget-fila]');
    });
</script>
<?php
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';

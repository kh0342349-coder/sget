<?php
/**
 * Admin/gestion_permisos.php
 * -----------------------------------------------------------------------------
 * MATRIZ DE PERMISOS DEL SISTEMA
 * -----------------------------------------------------------------------------
 * QUÉ CAMBIA EN ESTA SEGUNDA RONDA
 *
 *   Esta página era el ÚNICO consumidor vivo del sistema de autorización
 *   heredado. Mantenía dos estructuras que nadie más leía:
 *
 *       · tabla `restricciones`           (módulos denegados a administradores)
 *       · columna `usuario.restricciones`  (CSV de módulos denegados, para
 *                                           conductores y pasajeros)
 *
 *   Y, peor todavía: escribía en ellas desde un `<form method="POST">` propio,
 *   SIN token anti-CSRF, con SQL escrito a mano. Es decir, el segundo sistema
 *   de permisos era además el único editable por POST sin protección.
 *
 *   AHORA hay UN SOLO sistema activo:
 *
 *       permisos · rol_permiso · usuario_permisos
 *
 *   La página solo LEE usuarios y delega la escritura en
 *   `api/guardar_permisos.php`, que sí exige token CSRF, permiso
 *   `gestionar_permisos` y comprueba que el administrador no se quede sin sus
 *   propios permisos críticos.
 *
 *   `restricciones`, `usuario.restricciones` y `usuario_permiso_denegado`
 *   quedan retiradas en la migración 010.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirSesion();
Auth::requerirAdmin();
/* Permiso real, sin atajo por rol: este módulo existe precisamente para poder
   revocar la gestión de permisos a un administrador concreto. */
Auth::requerirAcceso('gestion_permisos');

if (!empty($_GET['ok']))        Flash::exito((string)$_GET['ok']);
elseif (!empty($_GET['error'])) Flash::error((string)$_GET['error']);

/* Filtro de rol: 1 admin · 2 conductor · 3 pasajero · 0 todos. Se valida contra
   la lista blanca: nunca se interpola el parámetro en el SQL. */
$rolesPermitidos = [0, Config::ROL_ADMIN, Config::ROL_CONDUCTOR, Config::ROL_PASAJERO];
$filtroRol = (int)($_GET['rol'] ?? Config::ROL_ADMIN);
if (!in_array($filtroRol, $rolesPermitidos, true)) {
    $filtroRol = Config::ROL_ADMIN;
}

$usuarios = $filtroRol > 0
    ? Database::all(
        'SELECT id_usu, nom_usu, corre_usu, num_doc_usu, id_rol_usu
           FROM usuario
          WHERE id_rol_usu = ? AND estado = ?
          ORDER BY nom_usu ASC',
        [$filtroRol, Config::USU_ACTIVO]
    )
    : Database::all(
        'SELECT id_usu, nom_usu, corre_usu, num_doc_usu, id_rol_usu
           FROM usuario
          WHERE estado = ?
          ORDER BY id_rol_usu ASC, nom_usu ASC',
        [Config::USU_ACTIVO]
    );

$nombresRoles = [
    Config::ROL_ADMIN     => 'Administrador',
    Config::ROL_CONDUCTOR => 'Conductor',
    Config::ROL_PASAJERO  => 'Pasajero',
];

$resumenUsuarios = Database::all(
    'SELECT id_rol_usu, COUNT(*) AS n FROM usuario WHERE estado = ? GROUP BY id_rol_usu',
    [Config::USU_ACTIVO]
);
$porRol = array_fill_keys(array_values($nombresRoles), 0);
foreach ($resumenUsuarios as $fila) {
    $porRol[(int)$fila['id_rol_usu']] = (int)$fila['n'];
}

$tituloPagina = 'Gestión de Permisos';
include __DIR__ . '/../views/partials/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="sget-main">
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title">
                    <i class="fas fa-user-shield text-sky-400"></i> Gestión de Permisos
                </h1>
                <p class="sget-page-sub">
                    Decide qué módulos puede usar cada cuenta. Lo que no marques aquí lo decide
                    el <strong>rol</strong>; lo que marques tiene prioridad sobre el rol.
                </p>
            </div>
        </header>

        <?= Flash::render() ?>

        <section class="sget-card" style="padding:1rem">
            <nav class="sget-tabs" aria-label="Filtrar por rol" role="tablist">
                <?php foreach ([
                    Config::ROL_ADMIN     => 'Administradores (' . $porRol[Config::ROL_ADMIN] . ')',
                    Config::ROL_CONDUCTOR => 'Conductores (' . $porRol[Config::ROL_CONDUCTOR] . ')',
                    Config::ROL_PASAJERO  => 'Pasajeros (' . $porRol[Config::ROL_PASAJERO] . ')',
                    0                     => 'Todos (' . array_sum($porRol) . ')',
                ] as $valor => $etiqueta): ?>
                    <a class="sget-tab" role="tab" aria-selected="<?= $filtroRol === $valor ? 'true' : 'false' ?>"
                       href="gestion_permisos.php?rol=<?= $valor ?>"><?= $etiqueta ?></a>
                <?php endforeach; ?>
            </nav>
        </section>

        <section class="sget-card">
            <div class="sget-table-wrap">
            <table class="sget-table">
                <thead>
                    <tr>
                        <th>Cuenta</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th class="acciones">Permisos</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($usuarios === []): ?>
                    <tr><td colspan="4" class="sget-sin-resultados">No hay cuentas activas para este filtro.</td></tr>
                <?php endif; ?>

                <?php foreach ($usuarios as $usr):
                    $esPropia = (int)$usr['id_usu'] === Auth::id(); ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:.6rem">
                                <span class="sget-avatar" aria-hidden="true">
                                    <?= htmlspecialchars(mb_strtoupper(mb_substr((string)$usr['nom_usu'], 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span style="min-width:0">
                                    <strong style="display:block"><?= htmlspecialchars((string)$usr['nom_usu'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="sget-help">Doc. <?= htmlspecialchars((string)$usr['num_doc_usu'], ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            </div>
                        </td>
                        <td class="sget-mono"><?= htmlspecialchars((string)$usr['corre_usu'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="sget-badge sget-badge--neutro">
                                <?= $nombresRoles[(int)$usr['id_rol_usu']] ?? 'Usuario' ?>
                            </span>
                        </td>
                        <td class="acciones">
                            <?php if ($esPropia): ?>
                                <span class="sget-help" style="display:inline-flex;align-items:center;gap:.35rem">
                                    <i class="fas fa-shield-halved"></i> Tu cuenta
                                </span>
                            <?php else: ?>
                                <button type="button"
                                        class="sget-btn sget-btn--neutro sget-btn--sm"
                                        data-sget-accion="abrirPermisos"
                                        data-sget-usuario="<?= (int)$usr['id_usu'] ?>"
                                        data-sget-nombre="<?= htmlspecialchars((string)$usr['nom_usu'], ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fas fa-sliders"></i> Configurar
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>

        <p class="sget-help" style="margin-top:1rem">
            <i class="fas fa-circle-info"></i>
            Un permiso marcado «Concedido por su rol» no se puede quitar desde aquí: también
            habría que quitarlo al rol entero. Y por seguridad, un administrador no puede
            quitarse a sí mismo los permisos críticos.
        </p>
    </main>
</div>

<?php include __DIR__ . '/../views/modals/permisos.php'; ?>
<?php include __DIR__ . '/../views/partials/foot.php'; ?>

<?php
/**
 * Admin/reportes_pasajeros.php
 * -----------------------------------------------------------------------------
 * MÓDULO: REPORTES Y QUEJAS DE LOS PASAJEROS  (Admin)
 * -----------------------------------------------------------------------------
 * QUÉ CAMBIÓ
 *   La página existía pero estaba muerta por partida triple:
 *     · su formulario enviaba a `actualizar_reporte.php`, un archivo inexistente;
 *     · no estaba enlazada desde el menú lateral;
 *     · y el SQL estaba escrito a mano, con estados en minúsculas que no
 *       coincidían con los de la tabla.
 *
 *   Ahora el flujo funciona de verdad: el pasajero reporta desde su panel, el
 *   administrador lo ve aquí, lo asigna a un viaje, lo resuelve y el pasajero
 *   recibe el avance en su buzón (services/ReporteService.php).
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('reportes_pasajeros');

if (!empty($_GET['ok']))       Flash::exito((string)$_GET['ok']);
elseif (!empty($_GET['error'])) Flash::error((string)$_GET['error']);

$reportes = ReporteService::todos();
$resumen  = ReporteService::resumen();
$viajes   = Database::all(
    "SELECT v.id_via, v.fec_via, v.hor_sal_via, v.id_usu_via, r.nom_rut, u.nom_usu AS conductor
       FROM viaje v
       LEFT JOIN rutas r    ON r.id_rut = v.id_rut_via
       LEFT JOIN usuario u ON u.id_usu = v.id_usu_via
      ORDER BY v.fec_via DESC, v.hor_sal_via DESC
      LIMIT 120"
);

$tituloPagina = 'Reportes de pasajeros';
include __DIR__ . '/../views/partials/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="sget-main">
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title">
                    <i class="fas fa-comment-dots text-amber-500"></i> Reportes y quejas
                </h1>
                <p class="sget-page-sub">
                    Incidencias que reportan los pasajeros sobre sus viajes. Al cambiar el estado,
                    el pasajero recibe el aviso en su buzón.
                </p>
            </div>
        </header>

        <?= Flash::render() ?>

        <section class="sget-grid sget-grid--kpi">
            <?php
            $kpis = [
                ['fa-inbox',        'var(--sget-azul)',    'Pendientes',  (int)$resumen['pendientes'], 'sget-badge--error'],
                ['fa-user-check',   'var(--sget-ambars)',  'Asignados',   (int)$resumen['asignados'],  'sget-badge--aviso'],
                ['fa-circle-check', 'var(--sget-emerald)', 'Resueltos',   (int)$resumen['completados'], 'sget-badge--exito'],
                ['fa-box-archive',  'var(--sget-morado)',  'Cerrados',    (int)$resumen['cerrados'],   'sget-badge--neutro'],
            ];
            foreach ($kpis as [$icono, $color, $titulo, $valor, $tono]): ?>
                <div class="sget-card sget-kpi">
                    <span class="sget-kpi__icono"
                          style="background:color-mix(in srgb,<?= $color ?> 14%,transparent);color:<?= $color ?>">
                        <i class="fas <?= $icono ?>"></i></span>
                    <div style="min-width:0">
                        <p class="sget-label"><?= $titulo ?></p>
                        <p class="sget-kpi__valor" style="font-size:1.25rem"><?= $valor ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <?php if (empty($reportes)): ?>
            <div class="sget-vacio">
                <span class="sget-vacio__icono"><i class="fas fa-comment-dots"></i></span>
                <h2 class="sget-label" style="font-size:.875rem">No hay reportes registrados</h2>
                <p class="sget-page-sub" style="margin:0">
                    Cuando un pasajero reporte una incidencia desde su panel, aparecerá aquí.
                </p>
            </div>
        <?php else: ?>

            <div class="sget-toolbar">
                <button type="button" class="sget-btn sget-btn--fantasma sget-btn--sm" data-sget-filtro="*">Todos</button>
                <?php foreach (ReporteService::ESTADOS as $estado): ?>
                    <button type="button" class="sget-btn sget-btn--fantasma sget-btn--sm" data-sget-filtro="<?= $estado ?>">
                        <?= ReporteService::etiquetaEstado($estado) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="sget-table-box">
                <div class="sget-table-wrap">
                    <table class="sget-table sget-table--compacta">
                        <thead><tr>
                            <th>Folio</th><th>Fecha</th><th>Pasajero</th><th>Motivo</th>
                            <th>Viaje</th><th class="sget-centro">Estado</th>
                            <th class="acciones">Acciones</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($reportes as $r):
                            $estado = (string)$r['estado'];
                            $id     = (int)$r['id_rep'];
                        ?>
                            <tr data-sget-fila data-estado="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>">
                                <td data-label="Folio" class="sget-mono">#<?= $id ?></td>
                                <td data-label="Fecha" class="sget-mono sget-suave sget-nowrap">
                                    <?= htmlspecialchars((string)($r['fecha_legible'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td data-label="Pasajero" class="sget-truncar">
                                    <?= htmlspecialchars((string)($r['pasajero'] ?? 'Desconocido'), ENT_QUOTES, 'UTF-8') ?>
                                    <br><span class="sget-help sget-mono">
                                        <?= htmlspecialchars((string)($r['num_doc_usu'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td data-label="Motivo" class="sget-truncar" style="max-width:22rem">
                                    <?= htmlspecialchars((string)$r['descripcion'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td data-label="Viaje" class="sget-truncar">
                                    <?php if (!empty($r['id_via_rep'])): ?>
                                        #<?= (int)$r['id_via_rep'] ?>
                                        <span class="sget-help sget-linea-1">
                                            <?= htmlspecialchars((string)($r['nom_via'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="sget-help">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Estado" class="sget-centro">
                                    <span class="sget-badge <?= ReporteService::claseEstado($estado) ?>">
                                        <?= ReporteService::etiquetaEstado($estado) ?>
                                    </span>
                                </td>
                                <td class="acciones" data-label="Acciones">
                                    <div style="display:flex;gap:.375rem;justify-content:flex-end">
                                        <select class="sget-select" style="width:auto;min-width:8.5rem"
                                                data-sget-estado-reporte="<?= $id ?>">
                                            <?php foreach (ReporteService::ESTADOS as $opcion): ?>
                                                <option value="<?= $opcion ?>" <?= $estado === $opcion ? 'selected' : '' ?>>
                                                    <?= ReporteService::etiquetaEstado($opcion) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <select class="sget-select" style="width:auto;min-width:9rem"
                                                data-sget-viaje-reporte="<?= $id ?>">
                                            <option value="0">Sin asignar</option>
                                            <?php foreach ($viajes as $v):
                                                $sel = (int)($r['id_via_rep'] ?? 0) === (int)$v['id_via']; ?>
                                                <option value="<?= (int)$v['id_via'] ?>" <?= $sel ? 'selected' : '' ?>>
                                                    #<?= (int)$v['id_via'] ?> · <?= htmlspecialchars(mb_substr((string)$v['nom_rut'], 0, 22), ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="button" class="sget-icon-btn sget-icon-btn--exito"
                                                title="Guardar los cambios del reporte" aria-label="Guardar"
                                                data-sget-accion="guardarReporte" data-sget-modulo="reporte"
                                                data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                            <i class="fas fa-floppy-disk"></i>
                                        </button>

                                        <button type="button" class="sget-icon-btn sget-icon-btn--peligro"
                                                title="Eliminar reporte" aria-label="Eliminar reporte"
                                                data-sget-accion="eliminarReporte" data-sget-modulo="reporte"
                                                data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                                                data-sget-titulo="Eliminar reporte"
                                                data-sget-texto='Se eliminará el reporte <strong>#<?= $id ?></strong> de forma permanente.'
                                                data-sget-ok="Sí, eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="sget-sin-resultados" data-sget-sin-resultados hidden>Ningún reporte coincide.</p>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php
// El buscador y los filtros los cablea assets/js/sget-page.js a partir de
// [data-sget-buscar] / [data-sget-filtro] / [data-sget-fila]. El guardado de
// cada fila lo hace la acción `guardarReporte` del mismo archivo, para que no
// haya dos manejadores enviando el mismo formulario dos veces.
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';

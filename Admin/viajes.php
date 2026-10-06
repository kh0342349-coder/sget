<?php
/**
 * Admin/viajes.php
 * -----------------------------------------------------------------------------
 * MÓDULO: DESPACHO DE VIAJES  (Admin)
 * -----------------------------------------------------------------------------
 * CORRECCIONES APLICADAS
 *   - FECHA/HORA: `fec_via` es DATE y `hor_sal_via` es TIME. Antes eran DATETIME
 *     y se llenaban con <input type="date">/type="time", por lo que MySQL
 *     guardaba hor_sal_via = '0000-00-00 00:00:00' (FECHA CERO) y rompía
 *     TIMESTAMP(fec_via, hor_sal_via). Ese era el "Data Default Fallback".
 *   - ESTADOS: `est_via` es ENUM(Programado|En curso|Finalizado|Cancelado).
 *     Ya no existe el ambiguo 'Activo' ni el cierre automático silencioso.
 *   - CANCELACIÓN: si el viaje aún no sale se EXIGE una anotación (mín. 15
 *     caracteres), se cancelan las reservas y se notifica a cada pasajero.
 *   - Se eliminó la concatenación de variables en el SQL de liberación de
 *     conductor/vehículo y el cierre automático que liberaba recursos sin
 *     transacción.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('viajes');

if (!empty($_GET['ok']))       Flash::exito((string)$_GET['ok']);
elseif (!empty($_GET['error'])) Flash::error((string)$_GET['error']);

// Mantenimiento: sincroniza el reloj con los viajes abiertos.
//   · los que ya pasaron su hora de salida pasan a «En curso»
//   · los que cumplieron salida + DURACIÓN se cierran como «Finalizados»
$sync = ViajeService::sincronizarEstado(true);

$viajes = ViajeService::listar();
$total  = count($viajes);

$conteo = ['Programado' => 0, 'En curso' => 0, 'Finalizado' => 0, 'Cancelado' => 0];
foreach (Database::all("SELECT est_via, COUNT(*) n FROM viaje GROUP BY est_via") as $f) {
    $conteo[$f['est_via']] = (int)$f['n'];
}

$tituloPagina = 'Despacho de Viajes';

/* Cuántos puestos embarcaron en cada viaje.
   Se calcula en UNA consulta para todas las tarjetas y no una por viaje: son
   datos de lectura que solo sirven para el «x de y» de la tarjeta. `embarco` va
   aparte del estado de pago porque se puede haber pagado al abordar y sí subir. */
$embarques = [];
foreach (Database::all(
    "SELECT id_via_res, COUNT(*) AS n FROM reserva
      WHERE estado_pago <> ? AND embarco = 1
      GROUP BY id_via_res",
    [Config::RES_CANCELADA]
) as $_f) {
    $embarques[(int)$_f['id_via_res']] = (int)$_f['n'];
}
include __DIR__ . '/../views/partials/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="sget-main">
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title"><i class="fas fa-truck-fast text-sky-500"></i> Despacho de Viajes</h1>
                <p class="sget-page-sub">
                    Programa salidas, controla la operación y <strong>cancela viajes notificando a los pasajeros</strong>.
                </p>
            </div>
            <div class="sget-page-actions">
                <button type="button" class="sget-btn sget-btn--primario" data-sget-modal="modalViaje" data-sget-nuevo="Programar Nuevo Viaje">
                    <i class="fas fa-plus"></i> Programar Viaje
                </button>
            </div>
        </header>

        <?php if ($sync['cerrar'] > 0 || $sync['marcar'] > 0): ?>
            <div class="sget-flash sget-flash--info">
                <i class="fas fa-clock-rotate-left"></i>
                <span>
                    Sincronización automática:
                    <?php if ($sync['marcar'] > 0): ?>
                        <strong><?= $sync['marcar'] ?></strong> viaje(s) ya salieron y pasaron a «En curso».
                    <?php endif; ?>
                    <?php if ($sync['cerrar'] > 0): ?>
                        <strong><?= $sync['cerrar'] ?></strong> viaje(s) cumplieron su duración y se cerraron automáticamente.
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <?= Flash::render() ?>

        <section class="sget-grid sget-grid--kpi">
            <?php foreach ([
                ['fa-calendar-check', 'var(--sget-azul)',    'Programados', $conteo['Programado']],
                ['fa-truck-fast',    'var(--sget-emerald)',  'En curso',    $conteo['En curso']],
                ['fa-flag-checkered','var(--sget-ambars)',  'Finalizados', $conteo['Finalizado']],
                ['fa-ban',           'var(--sget-rojo)',    'Cancelados',  $conteo['Cancelado']],
            ] as $kpi): ?>
                <div class="sget-card sget-kpi">
                    <span class="sget-kpi__icono" style="background:color-mix(in srgb,<?= $kpi[1] ?> 12%,transparent);color:<?= $kpi[1] ?>">
                        <i class="fas <?= $kpi[0] ?>"></i></span>
                    <div><p class="sget-label"><?= $kpi[2] ?></p><p class="sget-kpi__valor"><?= $kpi[3] ?></p></div>
                </div>
            <?php endforeach; ?>
        </section>

        <?php if ($total === 0): ?>
            <div class="sget-vacio">
                <span class="sget-vacio__icono"><i class="fas fa-truck-fast"></i></span>
                <h2 class="sget-label" style="font-size:.875rem">No hay viajes programados</h2>
                <p class="sget-page-sub" style="margin:0">Programa el primer despacho asignando ruta, conductor, vehículo y hora de salida.</p>
                <button type="button" class="sget-btn sget-btn--primario" style="margin-top:1rem"
                        data-sget-modal="modalViaje" data-sget-nuevo="Programar Nuevo Viaje">
                    <i class="fas fa-plus"></i> Programar Viaje
                </button>
            </div>
        <?php else: ?>
            <section class="sget-grid sget-grid--ancho">
                <?php foreach ($viajes as $v):
                    $id        = (int)$v['id_via'];
                    $estado    = (string)$v['est_via'];
                    $instante  = Fecha::instanteSalida($v['fec_via'] ?? null, $v['hor_sal_via'] ?? null);
                    $duracion  = ViajeService::duracionMin($v);
                    $vence     = ViajeService::instanteVencimiento($v);
                    $fase      = ViajeService::fase($v);
                    $yaSalio   = $fase['clave'] !== 'programado' && $fase['clave'] !== 'sin_fecha';
                    $vencido   = $fase['clave'] === 'vencido';
                    $reservas  = (int)($v['num_reservas'] ?? 0);
                    $trayecto  = trim(($v['nom_rut'] ?? 'Ruta') .
                                      (!empty($v['ori_rut']) ? ' (' . $v['ori_rut'] . ' → ' . ($v['des_rut'] ?? '?') . ')' : ''));
                    $datosEdicion = [
                        'id_via'      => $id,
                        'id_rut_via'  => (int)$v['id_rut_via'],
                        'id_usu_via'  => (int)$v['id_usu_via'],
                        'id_veh'      => (int)($v['id_veh'] ?? 0),
                        'fec_via'     => Fecha::soloFecha($v['fec_via'] ?? ''),
                        'hor_sal_via' => Fecha::soloHora($v['hor_sal_via'] ?? ''),
                        'hor_lleg_via'=> Fecha::soloHora($v['hor_lleg_via'] ?? ''),
                        'val_via'     => $v['val_via'],
                        'titulo'      => 'Editar Viaje #' . $id,
                    ];
                    $datosCancelar = [
                        'id'        => $id,
                        'salida'    => Fecha::legible($instante),
                        'vence'     => Fecha::legible($vence),
                        'duracion'  => RutaService::duracionLegible($duracion),
                        'ya_salio'  => $yaSalio ? 1 : 0,
                        'vencido'   => $vencido ? 1 : 0,
                        'pasajeros' => $reservas,
                        'trayecto'  => $trayecto,
                        'conductor' => (string)($v['conductor'] ?? 'Sin asignar'),
                        'placa'     => (string)($v['pla_veh'] ?? 'Sin placa'),
                        'minimo'    => Config::MIN_ANOTACION_CANCELACION,
                    ];
                ?>
                <article class="sget-card sget-fila" data-sget-fila style="display:flex;flex-direction:column;gap:.875rem">

                    <header style="display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem">
                        <div style="min-width:0">
                            <p class="sget-label"><i class="fas fa-route"></i> Viaje #<?= $id ?></p>
                            <h3 class="sget-page-title" style="font-size:1.0625rem;margin:.25rem 0">
                                <span class="sget-linea-1"><?= htmlspecialchars($trayecto, ENT_QUOTES, 'UTF-8') ?></span>
                            </h3>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:.25rem;align-items:flex-end">
                            <span class="sget-badge <?= ViajeService::claseEstado($estado) ?>">
                                <i class="fas <?= ViajeService::iconoEstado($estado) ?>"></i> <?= $estado ?>
                            </span>
                            <span class="sget-badge <?= $fase['clase'] ?>">
                                <i class="fas <?= $fase['icono'] ?>"></i> <?= $fase['etiqueta'] ?>
                            </span>
                        </div>
                    </header>

                    <div class="sget-form-2col" style="gap:.75rem">
                        <div>
                            <p class="sget-label">Salida programada</p>
                            <p class="sget-mono" style="font-size:.8125rem;margin-top:.25rem">
                                <?= Fecha::legible($instante) ?>
                            </p>
                        </div>
                        <div>
                            <p class="sget-label">Termina previsto</p>
                            <p class="sget-mono" style="font-size:.8125rem;margin-top:.25rem">
                                <?= Fecha::legible($vence) ?>
                                <span class="sget-badge sget-badge--neutro" style="margin-left:.25rem">
                                    <?= RutaService::duracionLegible($duracion) ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="sget-form-3col" style="gap:.5rem">
                        <div>
                            <p class="sget-label">Recurso</p>
                            <p style="font-size:.75rem" class="sget-truncar">
                                <i class="fas fa-user-tie"></i> <?= htmlspecialchars((string)($v['conductor'] ?? 'Sin conductor'), ENT_QUOTES, 'UTF-8') ?><br>
                                <i class="fas fa-bus"></i> <?= htmlspecialchars((string)($v['pla_veh'] ?? 'Sin placa'), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                        <div>
                            <p class="sget-label">Tarifa</p>
                            <p class="sget-mono" style="font-weight:800">$<?= number_format((float)$v['val_via'], 0, ',', '.') ?></p>
                        </div>
                        <div>
                            <p class="sget-label">Reservas</p>
                            <p class="sget-mono"><?= $reservas ?> pasajero(s)</p>
                        </div>
                        <div>
                            <p class="sget-label">Embarcaron</p>
                            <?php $emb = (int) ($embarques[$id] ?? 0); ?>
                            <p class="sget-mono"><?= $emb ?> de <?= $reservas ?></p>
                        </div>
                    </div>

                    <footer style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:auto;padding-top:.875rem;border-top:1px solid var(--sget-borde)">
                        <a class="sget-btn sget-btn--neutro sget-btn--sm" style="flex:1"
                           href="reportes.php?tab=pasajeros&amp;viaje=<?= $id ?>">
                            <i class="fas fa-users"></i> Pasajeros
                        </a>

                        <button type="button" class="sget-btn sget-btn--neutro sget-btn--sm" style="flex:1"
                                data-sget-modal="modalViaje" data-sget-nuevo="Programar Nuevo Viaje"
                                data-sget-datos='<?= htmlspecialchars(json_encode($datosEdicion, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                            <i class="fas fa-pen"></i> Editar
                        </button>

                        <?php if ($yaSalio && !$vencido): ?>
                            <button type="button" class="sget-btn sget-btn--aviso sget-btn--sm" style="flex:1"
                                    data-sget-accion="enCurso"
                                    data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="fas fa-truck-fast"></i> En curso
                            </button>
                        <?php endif; ?>

                        <?php /* «Terminar» solo si el viaje REALMENTE puede terminarse.
                               La misma regla (`ViajeService::puedeFinalizar`) la
                               aplica el backend al recibir la petición, así que
                               el botón y la validación no pueden discrepar.

                               Antes el botón salía SIEMPRE y el backend solo
                               miraba que el viaje no estuviera cerrado: un viaje
                               programado para mañana se podía «finalizar» hoy
                               sin que hubiera salido nunca. Cuando no se puede,
                               no se esconde: se explica por qué, que es lo que
                               evita que el administrador piense que la pantalla
                               está rota. */ ?>
                        <?php [$puedeTerminar, $motivoTerminar] = ViajeService::puedeFinalizar($v); ?>
                        <?php if ($puedeTerminar): ?>
                            <button type="button" class="sget-btn sget-btn--neutro sget-btn--sm" style="flex:1"
                                    data-sget-accion="finalizar"
                                    data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="fas fa-flag-checkered"></i> Terminar
                            </button>
                        <?php else: ?>
                            <span class="sget-btn sget-btn--neutro sget-btn--sm"
                                  style="flex:1;opacity:.5;cursor:not-allowed"
                                  title="<?= htmlspecialchars($motivoTerminar, ENT_QUOTES, 'UTF-8') ?>"
                                  aria-disabled="true">
                                <i class="fas fa-flag-checkered"></i> Terminar
                            </span>
                        <?php endif; ?>

                        <button type="button" class="sget-icon-btn <?= $vencido ? '' : 'sget-icon-btn--peligro' ?>"
                                title="<?= $vencido ? 'Ver por qué no se puede cancelar' : 'Cancelar viaje y notificar pasajeros' ?>"
                                aria-label="Cancelar viaje"
                                data-sget-accion="cancelarViaje"
                                data-sget-dato='<?= htmlspecialchars(json_encode($datosCancelar, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                            <i class="fas <?= $vencido ? 'fa-hourglass-end' : 'fa-ban' ?>"></i>
                        </button>
                    </footer>
                </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/../views/modals/viaje.php'; ?>
<?php include __DIR__ . '/../views/modals/cancelar-viaje.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        /* Al elegir ruta: se heredan la tarifa y la hora de salida por defecto.
           Esto elimina el error de "tarifa en 0" y la hora 00:00 heredada. */
        var selRuta = document.getElementById('viaje_ruta');
        var inpTarifa = document.getElementById('viaje_tarifa');
        var inpHora   = document.getElementById('viaje_hora');
        var inpFecha  = document.getElementById('viaje_fecha');
        var resumen   = document.querySelector('[data-resumen]');

        function sincronizar() {
            var opcion = selRuta.options[selRuta.selectedIndex];
            if (!opcion || !opcion.value) { if (resumen) resumen.textContent = 'Selecciona la ruta y completa la fecha para ver el detalle.'; return; }

            var tarifa = opcion.getAttribute('data-tarifa');
            var hora   = opcion.getAttribute('data-hora');
            if (tarifa && (!inpTarifa.value || parseFloat(inpTarifa.value) === 0)) inpTarifa.value = tarifa;
            if (hora && !inpHora.value) inpHora.value = hora;
            if (inpFecha && !inpFecha.value) inpFecha.value = new Date().toISOString().slice(0, 10);

            if (resumen) {
                resumen.innerHTML = '<strong>' + opcion.textContent.trim().split('—')[0] + '</strong><br>' +
                    'Salida: ' + (inpFecha.value || '—') + ' a las ' + (inpHora.value || '—') +
                    ' · Tarifa: $' + Number(tarifa || 0).toLocaleString('es-CO');
            }
        }
        if (selRuta) { selRuta.addEventListener('change', sincronizar); sincronizar(); }
        document.addEventListener('sget:modal-abierto', function (e) {
            if (!e.detail || e.detail.id !== 'modalViaje') return;
            sincronizar();
        });
    });
</script>
<?php
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';

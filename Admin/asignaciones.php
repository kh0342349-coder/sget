<?php
/**
 * Admin/asignaciones.php
 * -----------------------------------------------------------------------------
 * MÓDULO: RECAUDO Y ABORDAJE  (Admin)
 * -----------------------------------------------------------------------------
 * POR QUÉ SE REESCRIBIÓ ENTERO
 *   El módulo tenía un fallo que hacía que NUNCA funcionara: insertaba las
 *   reservas con `estado_pago = 'Completado'`, un valor que no existe en el
 *   ENUM de la tabla (`Pendiente`, `Confirmada`, `Cancelada`). El INSERT
 *   fallaba, el código no comprobaba el resultado y aun así pintaba
 *   "¡Asignación registrada con éxito!" con el ticket de la reserva 0.
 *
 *   Como además todo lo demás quedó en 'Pendiente', en la base no había ni una
 *   sola reserva 'Confirmada'… y por eso el módulo de Ganancias mostraba $0
 *   siempre, independientemente de la caja real.
 *
 * AHORA
 *   · Toda la lógica de reserva vive en services/ReservaService.php: capacidad,
 *     cobro, cancelación y avisos al pasajero. Esta página solo dibuja.
 *   · El cobro deja la reserva en 'Confirmada' con su fecha, o en 'Pendiente'
 *     si se cobra en el punto de embarque (que es lo habitual en transporte).
 *   · Se puede COBRAR una reserva pendiente, y CANCELARLA devolviendo el cupo.
 *   · Cada acción deja el rastro en la auditoría y avisa al pasajero.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('asignaciones');

if (!empty($_GET['ok']))       Flash::exito((string)$_GET['ok']);
elseif (!empty($_GET['error'])) Flash::error((string)$_GET['error']);

// Cierre automático de viajes vencidos (mantenimiento transversal)
ViajeService::cerrarVencidos();

/* -------------------------------------------------------------------------- */
/* Datos                                                                      */
/* -------------------------------------------------------------------------- */
$hoy = date('Y-m-d');

$viajes = Database::all(
    "SELECT v.id_via, v.fec_via, v.hor_sal_via, v.val_via, v.cup_tot, v.cup_dis, v.est_via,
            r.nom_rut, r.ori_rut, r.des_rut, r.img_rut, r.val_rut,
            u.nom_usu AS conductor, veh.pla_veh,
            (SELECT COUNT(*) FROM reserva res
              WHERE res.id_via_res = v.id_via AND res.estado_pago = ?) AS vendidos,
            (SELECT COALESCE(SUM(res.valor_pagado), 0) FROM reserva res
              WHERE res.id_via_res = v.id_via AND res.estado_pago = ?) AS recaudo
       FROM viaje v
       LEFT JOIN rutas r     ON r.id_rut   = v.id_rut_via
       LEFT JOIN usuario u  ON u.id_usu   = v.id_usu_via
       LEFT JOIN vehiculo veh ON veh.id_veh = v.id_veh
      WHERE v.est_via IN (?, ?)
      ORDER BY v.fec_via ASC, v.hor_sal_via ASC",
    [Config::RES_CONFIRMADA, Config::RES_CONFIRMADA, Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO]
);

// Resumen del día (caja): solo reservas confirmadas, que es dinero real
$resumen = [
    'cupos_vendidos' => (int) Database::scalar(
        "SELECT COUNT(*) FROM reserva res INNER JOIN viaje v ON v.id_via = res.id_via_res
          WHERE res.estado_pago = ? AND v.fec_via = ?",
        [Config::RES_CONFIRMADA, $hoy]),
    'recaudo'        => (float) Database::scalar(
        "SELECT COALESCE(SUM(res.valor_pagado), 0) FROM reserva res INNER JOIN viaje v ON v.id_via = res.id_via_res
          WHERE res.estado_pago = ? AND v.fec_via = ?",
        [Config::RES_CONFIRMADA, $hoy]),
    'pendientes'     => (int) Database::scalar(
        "SELECT COUNT(*) FROM reserva res INNER JOIN viaje v ON v.id_via = res.id_via_res
          WHERE res.estado_pago = ? AND v.fec_via = ?",
        [Config::RES_PENDIENTE, $hoy]),
    'por_cobrar'     => (float) Database::scalar(
        "SELECT COALESCE(SUM(res.valor_pagado), 0) FROM reserva res
          WHERE res.estado_pago = ? AND res.fecha_pago IS NULL",
        [Config::RES_PENDIENTE]),
];

$pasajeros = Database::all(
    "SELECT id_usu, nom_usu, num_doc_usu, corre_usu
       FROM usuario WHERE id_rol_usu = ? AND estado = ?
      ORDER BY nom_usu ASC",
    [Config::ROL_PASAJERO, Config::USU_ACTIVO]
);

$tituloPagina = 'Recaudo y abordaje';
include __DIR__ . '/../views/partials/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="sget-main">
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title">
                    <i class="fas fa-cash-register text-emerald-500"></i> Recaudo y abordaje
                </h1>
                <p class="sget-page-sub">
                    Registra el cobro de cada puesto, deja el recaudo pendiente cuando se paga al embarkar
                    y libera cupos cuando un pasajero cancela.
                </p>
            </div>
        </header>

        <?= Flash::render() ?>

        <!-- ============================== CAJA ============================== -->
        <section class="sget-grid sget-grid--kpi">
            <?php
            $kpis = [
                ['fa-sack-dollar', 'var(--sget-emerald)', 'Recaudo de hoy',    InformacionService::money($resumen['recaudo'])],
                ['fa-ticket',      'var(--sget-azul)',    'Puestos vendidos',  InformacionService::numero($resumen['cupos_vendidos'])],
                ['fa-clock',       'var(--sget-ambars)',  'Reservas por cobrar', InformacionService::numero($resumen['pendientes'])],
                ['fa-money-bill-wave', 'var(--sget-rojo)', 'Importe por cobrar', InformacionService::money($resumen['por_cobrar'])],
            ];
            foreach ($kpis as [$icono, $color, $titulo, $valor]): ?>
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

        <p class="sget-help">
            <i class="fas fa-circle-info"></i>
            Solo cuentan como dinero las reservas <strong>confirmadas</strong>. Las que quedan
            <strong>pendientes</strong> son puestos apartados que todavía no se han cobrado.
        </p>

        <!-- ============================= VIAJES ============================= -->
        <?php if (empty($viajes)): ?>
            <div class="sget-vacio">
                <span class="sget-vacio__icono"><i class="fas fa-bus"></i></span>
                <h2 class="sget-label" style="font-size:.875rem">No hay viajes activos</h2>
                <p class="sget-page-sub" style="margin:0">Programa un viaje para poder registrar el recaudo.</p>
                <a class="sget-btn sget-btn--primario" style="margin-top:1rem" href="viajes.php">
                    <i class="fas fa-plus"></i> Ir a Programación de Viajes
                </a>
            </div>
        <?php else: ?>

            <div class="sget-toolbar">
                <div class="sget-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" id="buscarViajeRecaudo" class="sget-input"
                           placeholder="Buscar por ruta, conductor o placa… (Ctrl+K)">
                </div>
            </div>

            <section class="sget-grid sget-grid--ancho">
                <?php foreach ($viajes as $v):
                    $id       = (int)$v['id_via'];
                    $libres   = (int)($v['cup_dis'] ?? 0);
                    $totales  = (int)($v['cup_tot'] ?? 0);
                    $vendidos = (int)$v['vendidos'];
                    $ocup     = $totales > 0 ? min(100, (int)round(($vendidos / $totales) * 100)) : 0;
                    $img      = trim((string)($v['img_rut'] ?? ''));
                ?>
                    <article class="sget-card sget-fila" data-sget-fila
                             data-viaje="<?= $id ?>"
                             style="display:flex;gap:1.25rem;flex-wrap:wrap">

                        <!-- Imagen de la ruta -->
                        <div style="flex:0 0 12rem;min-height:8rem;border-radius:var(--sget-radio);
                                    overflow:hidden;position:relative;background:var(--sget-superficie-2)">
                            <?php if ($img !== '' && file_exists(Config::raiz('img/rutas/' . $img))): ?>
                                <img src="../img/rutas/<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars((string)$v['nom_rut'], ENT_QUOTES, 'UTF-8') ?>"
                                     loading="lazy" style="width:100%;height:100%;object-fit:cover">
                            <?php else: ?>
                                <span style="display:grid;place-items:center;height:100%;font-size:1.75rem;color:var(--sget-texto-tenue)">
                                    <i class="fas fa-route"></i>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Datos del viaje -->
                        <div style="flex:1 1 16rem;min-width:0">
                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                                <span class="sget-badge sget-badge--neutro">#<?= $id ?></span>
                                <span class="sget-badge <?= ViajeService::claseEstado((string)$v['est_via']) ?>">
                                    <?= htmlspecialchars((string)$v['est_via'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if ($libres === 0): ?>
                                    <span class="sget-badge sget-badge--error">Agotado</span>
                                <?php elseif ($ocup >= 80): ?>
                                    <span class="sget-badge sget-badge--aviso">Casi lleno</span>
                                <?php endif; ?>
                            </div>

                            <h3 style="margin:.5rem 0 .125rem;font-weight:800;font-size:1rem">
                                <?= htmlspecialchars((string)$v['nom_rut'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <p class="sget-help" style="margin:0">
                                <?= htmlspecialchars(trim(($v['ori_rut'] ?? '') . ' → ' . ($v['des_rut'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <div class="sget-form-3col" style="margin-top:.875rem;gap:.5rem">
                                <div>
                                    <p class="sget-label">Salida</p>
                                    <p class="sget-mono" style="font-size:.8125rem">
                                        <?= Fecha::legible($v['fec_via'], false) ?><br>
                                        <span class="sget-suave"><?= Fecha::soloHora($v['hor_sal_via']) ?></span>
                                    </p>
                                </div>
                                <div>
                                    <p class="sget-label">Conductor</p>
                                    <p class="sget-truncar" style="font-size:.8125rem">
                                        <?= htmlspecialchars((string)($v['conductor'] ?: 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?>
                                        <br><span class="sget-help sget-mono"><?= htmlspecialchars((string)($v['pla_veh'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </p>
                                </div>
                                <div>
                                    <p class="sget-label">Ocupación</p>
                                    <p class="sget-mono" style="font-size:.8125rem;font-weight:800">
                                        <?= $vendidos ?>/<?= $totales ?>
                                        <span class="sget-badge <?= $ocup >= 80 ? 'sget-badge--exito' : ($ocup >= 40 ? 'sget-badge--aviso' : 'sget-badge--neutro') ?>">
                                            <?= $ocup ?>%
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Caja del viaje + acciones -->
                        <div style="flex:0 1 15rem;display:flex;flex-direction:column;gap:.625rem;min-width:0">
                            <div>
                                <p class="sget-label">Recaudo del viaje</p>
                                <p class="sget-mono" style="font-size:1.125rem;font-weight:800">
                                    <?= InformacionService::money((float)$v['recaudo']) ?>
                                </p>
                                <p class="sget-help">
                                    Tarifa <?= InformacionService::money((float)$v['val_via']) ?> por puesto
                                </p>
                            </div>

                            <div style="display:flex;gap:.5rem;margin-top:auto">
                                <button type="button" class="sget-btn sget-btn--primario sget-btn--sm" style="flex:1"
                                        data-sget-accion="abrirRecaudo" data-sget-viaje="<?= $id ?>"
                                        <?= $libres === 0 ? 'disabled title="Sin cupos libres"' : '' ?>>
                                    <i class="fas fa-cash-register"></i> Cobrar
                                </button>
                                <button type="button" class="sget-btn sget-btn--neutro sget-btn--sm"
                                        data-sget-accion="verReservas" data-sget-viaje="<?= $id ?>"
                                        title="Ver y gestionar las reservas de este viaje">
                                    <i class="fas fa-list-check"></i>
                                    <?= InformacionService::numero($vendidos) ?>
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</div>

<!-- ============================ MODAL DE RECAUDO ============================ -->
<div class="sget-modal-wrap" id="modalRecaudo" data-sget-capa data-titulo="Registrar recaudo">
    <div class="sget-overlay"></div>

    <form class="sget-modal" data-sget-panel novalidate id="formRecaudo"
          role="dialog" aria-modal="true" aria-labelledby="tituloModalRecaudo">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloModalRecaudo">
                    <span class="sget-modal__icono"
                          style="background:color-mix(in srgb,var(--sget-emerald) 14%,transparent);color:var(--sget-emerald)">
                        <i class="fas fa-cash-register"></i>
                    </span>
                    <span>Registrar cobro</span>
                </h2>
                <p class="sget-modal__sub" data-sget-texto="viaje">Viaje</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="modulo" value="reserva">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="id_via" data-sget-campo="id_via" value="0">
            <input type="hidden" name="id_usu" data-sget-campo="id_usu" value="0">

            <!-- TIPO DE PASAJERO -->
            <div class="sget-field">
                <label class="sget-label">Pasajero</label>
                <div style="display:grid;gap:.375rem;margin-top:.25rem">
                    <label class="sget-check" for="tipoRegistrado">
                        <input type="radio" id="tipoRegistrado" name="tipo_pasajero" value="registrado" checked>
                        <span>Pasajero registrado</span>
                    </label>
                    <label class="sget-check" for="tipoOcasional">
                        <input type="radio" id="tipoOcasional" name="tipo_pasajero" value="ocasional">
                        <span>Sin registro (ocasional)</span>
                    </label>
                </div>
            </div>

            <!-- PASAJERO REGISTRADO -->
            <div data-sget-bloque="registrado" style="margin-top:1rem">
                <div class="sget-field" data-campo="pasajero">
                    <label class="sget-label" for="selPasajero">
                        Buscar pasajero <span class="sget-label__req">*</span>
                    </label>
                    <select id="selPasajero" name="pasajero_registrado" class="sget-select">
                        <option value="">Selecciona un pasajero…</option>
                        <?php foreach ($pasajeros as $p): ?>
                            <option value="<?= (int)$p['id_usu'] ?>"
                                    data-nombre="<?= htmlspecialchars((string)$p['nom_usu'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-tarifa="<?= (float)0 ?>">
                                <?= htmlspecialchars(trim($p['nom_usu'] . ' · ' . $p['num_doc_usu']), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>
            </div>

            <!-- PASAJERO OCASIONAL -->
            <div data-sget-bloque="ocasional" style="margin-top:1rem;display:none">
                <div class="sget-field" data-campo="nombre_ocasional">
                    <label class="sget-label" for="nombreOcasional">
                        Nombre completo <span class="sget-label__req">*</span>
                    </label>
                    <input type="text" id="nombreOcasional" name="nombre_ocasional" class="sget-input" maxlength="100"
                           placeholder="Nombre de quien viaja">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <div class="sget-form-2col" style="margin-top:1rem">
                    <div class="sget-field" data-campo="doc_ocasional">
                        <label class="sget-label" for="docOcasional">Documento (opcional)</label>
                        <input type="text" id="docOcasional" name="doc_ocasional" class="sget-input" maxlength="20"
                               inputmode="numeric" placeholder="Si lo tiene, se registra la cuenta">
                    </div>
                    <div class="sget-field" data-campo="tel_ocasional">
                        <label class="sget-label" for="telOcasional">Teléfono (opcional)</label>
                        <input type="text" id="telOcasional" name="tel_ocasional" class="sget-input" maxlength="20"
                               placeholder="Para avisarle por WhatsApp">
                    </div>
                </div>
            </div>

            <!-- PUESTOS Y VALOR -->
            <div class="sget-form-2col" style="margin-top:1rem">
                <div class="sget-field" data-campo="puestos">
                    <label class="sget-label" for="inputPuestos">
                        Puestos <span class="sget-label__req">*</span>
                    </label>
                    <input type="number" id="inputPuestos" name="puestos" class="sget-input" min="1" max="60" value="1"
                           data-sget-autofocus>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    <span class="sget-help" data-sget-texto="cupos">Cupos libres: —</span>
                </div>

                <div class="sget-field" data-campo="metodo_pago">
                    <label class="sget-label" for="selectMetodo">Método de pago</label>
                    <select id="selectMetodo" name="metodo_pago" class="sget-select">
                        <?php foreach (ReservaService::metodosPago() as $m): ?>
                            <option value="<?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="sget-field" style="margin-top:1rem" data-campo="valor_pagado">
                <label class="sget-label" for="inputValor">
                    Valor a cobrar <span class="sget-label__req">*</span>
                </label>
                <input type="number" id="inputValor" name="valor_pagado" class="sget-input sget-input--mono"
                       min="0" step="50" value="0">
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                <p class="sget-help">
                    Se calcula con la tarifa del viaje × puestos. Puedes ajustarlo si hubo descuento.
                </p>
            </div>

            <label class="sget-check" for="inputConfirmar" style="margin-top:1rem">
                <input type="checkbox" id="inputConfirmar" name="confirmar" value="1" checked>
                <span>
                    <strong>El pago se recibe ahora</strong><br>
                    <span class="sget-help">Desmárcalo si se cobra al embarkar: la reserva queda pendiente
                    y el cupo queda apartado igual.</span>
                </span>
            </label>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
            <button type="submit" class="sget-btn sget-btn--primario">
                <i class="fas fa-receipt"></i> Registrar cobro
            </button>
        </footer>
    </form>
</div>

<!-- ========================= MODAL DE RESERVAS ========================= -->
<div class="sget-modal-wrap" id="modalReservasViaje" data-sget-capa data-titulo="Reservas del viaje">
    <div class="sget-overlay"></div>
    <div class="sget-modal sget-modal--lg" data-sget-panel role="dialog" aria-modal="true" aria-labelledby="tituloModalReservas">
        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloModalReservas">
                    <span class="sget-modal__icono"><i class="fas fa-ticket"></i></span>
                    <span>Reservas del viaje</span>
                </h2>
                <p class="sget-modal__sub" data-sget-texto="viaje">Viaje</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll" data-sget-lista-reservas>
            <p class="sget-help">Cargando…</p>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cerrar</button>
        </footer>
    </div>
</div>

<script>
/* ==========================================================================
   RECAUDO EN TERMINAL
   --------------------------------------------------------------------------
   El cálculo del valor (tarifa × puestos) se hace en el navegador para que el
   cajero vea el total mientras teclea, pero el importe que se guarda es el que
   llega al servidor: nunca se confía en un total calculado en el cliente.
   ========================================================================== */
(function () {
    'use strict';

    var VIAJES = <?= json_encode(array_map(static function (array $v): array {
        return [
            'id'       => (int)$v['id_via'],
            'etiqueta' => (string)$v['nom_rut'] . ' · ' . Fecha::legible($v['fec_via'], false) . ' ' . Fecha::soloHora($v['hor_sal_via']),
            'tarifa'   => (float)$v['val_via'],
            'cupos'    => (int)$v['cup_dis'],
        ];
    }, $viajes), JSON_UNESCAPED_UNICODE) ?>;

    var form = document.getElementById('formRecaudo');
    if (!form) return;

    // Viaje cuyas reservas se están listando. Se guarda aparte del formulario de
    // cobro porque ese se reinicia al abrirse: usar su id recargaba la lista del
    // viaje 0 y el cajero veía «este viaje no tiene reservas».
    var viajeEnPantalla = 0;

    var selPasajero = form.querySelector('#selPasajero');
    var inputPuestos = form.querySelector('#inputPuestos');
    var inputValor   = form.querySelector('#inputValor');
    var bloqueReg    = form.querySelector('[data-sget-bloque="registrado"]');
    var bloqueOcas   = form.querySelector('[data-sget-bloque="ocasional"]');
    var valorTocado  = false;

    function viajeActual() {
        return VIAJES.find(function (v) { return v.id === parseInt(form.id_via.value, 10); }) || null;
    }

    function recalcular() {
        var v = viajeActual();
        if (!v) return;

        var max = Math.max(1, v.cupos);
        if (parseInt(inputPuestos.value, 10) > max) inputPuestos.value = max;
        if (parseInt(inputPuestos.value, 10) < 1) inputPuestos.value = 1;

        form.querySelector('[data-sget-texto="cupos"]').textContent =
            v.cupos > 0 ? ('Cupos libres: ' + v.cupos) : 'Este viaje no tiene cupos libres';

        if (!valorTocado) {
            inputValor.value = Math.round(v.tarifa * parseInt(inputPuestos.value, 10));
        }
    }

    /* --- Tipo de pasajero --- */
    form.querySelectorAll('input[name="tipo_pasajero"]').forEach(function (r) {
        r.addEventListener('change', function () {
            var ocasional = r.value === 'ocasional' && r.checked;
            bloqueReg.style.display = ocasional ? 'none' : '';
            bloqueOcas.style.display = ocasional ? '' : 'none';
            form.id_usu.value = ocasional ? 0 : (selPasajero.value || 0);
        });
    });

    selPasajero.addEventListener('change', function () { form.id_usu.value = selPasajero.value || 0; });
    inputPuestos.addEventListener('input', recalcular);
    inputValor.addEventListener('input', function () { valorTocado = true; });

    /* --- Apertura --- */
    window.SGETRecaudo = {
        abrir: function (idViaje) {
            var v = viajeActual.call(null) || VIAJES.find(function (x) { return x.id === idViaje; });
            if (!v) return;

            form.reset();
            SGETModal.limpiarErrores(form);
            valorTocado = false;

            form.id_via.value = v.id;
            form.id_usu.value = 0;
            bloqueReg.style.display = '';
            bloqueOcas.style.display = 'none';

            modal.querySelector('[data-sget-texto="viaje"]').textContent = 'Viaje #' + v.id + ' · ' + v.etiqueta;

            SGETModal.abrir('modalRecaudo');
            recalcular();
        },

        verReservas: function (idViaje) {
            viajeEnPantalla = idViaje;
            var v = VIAJES.find(function (x) { return x.id === idViaje; });
            var cont = document.querySelector('[data-sget-lista-reservas]');
            document.querySelector('#modalReservasViaje [data-sget-texto="viaje"]').textContent =
                v ? ('Viaje #' + v.id + ' · ' + v.etiqueta) : ('Viaje #' + idViaje);

            SGETModal.abrir('modalReservasViaje');
            cont.innerHTML = '<p class="sget-help">Cargando…</p>';
            cargarReservas(idViaje, cont);
        }
    };

    /* --- Listado de reservas del viaje (cobrar / cancelar) --- */
    function cargarReservas(idViaje, cont) {
        var cuerpo = new FormData();
        cuerpo.append('_token', SGETModal.__token);
        cuerpo.append('modulo', 'reserva');
        cuerpo.append('accion', 'porViaje');
        cuerpo.append('id', idViaje);

        fetch('../api/index.php', {
            method: 'POST', body: cuerpo,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var lista = (j.datos && j.datos.reservas) || [];
                if (!lista.length) {
                    cont.innerHTML = '<div class="sget-vacio" style="border:none;background:transparent">' +
                        '<span class="sget-vacio__icono"><i class="fas fa-ticket"></i></span>' +
                        '<p class="sget-help">Este viaje todavía no tiene reservas.</p></div>';
                    return;
                }

                var confirmada = '<?= Config::RES_CONFIRMADA ?>';
                var cancelada  = '<?= Config::RES_CANCELADA ?>';

                cont.innerHTML = '<div class="sget-table-wrap"><table class="sget-table sget-table--compacta">' +
                    '<thead><tr><th>Pasajero</th><th>Documento</th><th>Hora</th>' +
                    '<th>Método</th><th class="acciones">Valor</th><th class="sget-centro">Estado</th><th></th></tr></thead><tbody>' +
                    lista.map(function (r) {
                        var pagada = r.estado_pago === confirmada;
                        var anulada = r.estado_pago === cancelada;
                        var acciones =
                            (anulada ? '' :
                                (pagada
                                    ? '<button type="button" class="sget-icon-btn sget-icon-btn--peligro" title="Cancelar la reserva" ' +
                                      'data-sget-cancelar-reserva="' + r.id_res + '"><i class="fas fa-ban"></i></button>'
                                    : '<button type="button" class="sget-icon-btn sget-icon-btn--exito" title="Registrar el cobro" ' +
                                      'data-sget-cobrar-reserva="' + r.id_res + '" data-valor="' + (r.valor_pagado || 0) + '">' +
                                      '<i class="fas fa-cash-register"></i></button>'));

                        return '<tr' + (anulada ? ' style="opacity:.55"' : '') + '>' +
                            '<td data-label="Pasajero" class="sget-truncar">' + esc(r.pasajero) + '</td>' +
                            '<td data-label="Documento" class="sget-mono sget-suave">' + esc(r.num_doc_usu) + '</td>' +
                            '<td data-label="Hora" class="sget-mono sget-suave sget-nowrap">' + esc(r.fech_res) + '</td>' +
                            '<td data-label="Método">' + esc(r.metodo_pago) + '</td>' +
                            '<td class="acciones" data-label="Valor"><span class="sget-mono" style="font-weight:800">' +
                                (r.valor_pagado ? '$' + Number(r.valor_pagado).toLocaleString('es-CO') : '—') + '</span></td>' +
                            '<td data-label="Estado" class="sget-centro">' +
                                '<span class="sget-badge ' + (pagada ? 'sget-badge--exito' : (anulada ? 'sget-badge--error' : 'sget-badge--aviso')) + '">' +
                                esc(r.estado_pago) + '</span></td>' +
                            '<td class="acciones">' + acciones + '</td>' +
                        '</tr>';
                    }).join('') +
                    '</tbody></table></div>';
            })
            .catch(function () { cont.innerHTML = '<p class="sget-help">No se pudo cargar el listado.</p>'; });
    }

    function esc(texto) {
        var d = document.createElement('div');
        d.textContent = texto == null ? '' : String(texto);
        return d.innerHTML;
    }

    /* --- Cobrar / cancelar una reserva puntual --- */
    document.addEventListener('click', function (e) {
        var cobrar = e.target.closest('[data-sget-cobrar-reserva]');
        if (cobrar) {
            e.preventDefault();
            var cuerpo = new FormData();
            cuerpo.append('_token', SGETModal.__token);
            cuerpo.append('modulo', 'reserva');
            cuerpo.append('accion', 'cobrar');
            cuerpo.append('id', cobrar.dataset.sgetCobrarReserva);
            cuerpo.append('valor', cobrar.dataset.valor || 0);
            cuerpo.append('metodo', document.getElementById('selectMetodo')?.value || 'Efectivo');

            fetch('../api/index.php', {
                method: 'POST', body: cuerpo,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    SGETModal.toast(j.mensaje, j.status === 'ok' ? 'exito' : 'error');
                    if (j.status === 'ok') {
                        cargarReservas(viajeEnPantalla, document.querySelector('[data-sget-lista-reservas]'));
                    }
                });
            return;
        }

        var cancelar = e.target.closest('[data-sget-cancelar-reserva]');
        if (cancelar) {
            e.preventDefault();
            SGETModal.confirmar({
                tipo: 'peligro',
                icono: 'fa-ban',
                titulo: 'Cancelar la reserva',
                cuerpo: 'Se cancelará el puesto, volverá al viaje y el pasajero recibirá un aviso. ' +
                        'Si el pago ya estaba confirmado, el reembolso se gestiona fuera del sistema.',
                textoOk: 'Sí, cancelar'
            }).then(function () {
                var cuerpo = new FormData();
                cuerpo.append('_token', SGETModal.__token);
                cuerpo.append('modulo', 'reserva');
                cuerpo.append('accion', 'cancelar');
                cuerpo.append('id', cancelar.dataset.sgetCancelarReserva);
                cuerpo.append('motivo', 'Cancelación en terminal');

                fetch('../api/index.php', {
                    method: 'POST', body: cuerpo,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        SGETModal.toast(j.mensaje, j.status === 'ok' ? 'exito' : 'error');
                        if (j.status === 'ok') {
                            cargarReservas(viajeEnPantalla, document.querySelector('[data-sget-lista-reservas]'));
                        }
                    });
            });
        }
    });

    /* --- Abrir el modal de cobro desde las tarjetas de viaje --- */
    document.addEventListener('click', function (e) {
        var boton = e.target.closest('[data-sget-accion="abrirRecaudo"], [data-sget-accion="verReservas"]');
        if (!boton) return;
        e.preventDefault();

        var id = parseInt(boton.dataset.sgetViaje, 10);
        if (boton.dataset.sgetAccion === 'abrirRecaudo') window.SGETRecaudo.abrir(id);
        else window.SGETRecaudo.verReservas(id);
    });

    var modal = document.getElementById('modalRecaudo');

    /* --- Envío --- */
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var datos = new FormData(form);
        var v = viajeActual();
        var ocasional = form.querySelector('input[name="tipo_pasajero"]:checked').value === 'ocasional';

        var errores = {};
        if (!ocasional && !datos.get('id_usu')) {
            errores.pasajero = 'Selecciona el pasajero que va a viajar.';
        }
        if (ocasional && !String(datos.get('nombre_ocasional') || '').trim()) {
            errores.nombre_ocasional = 'Escribe el nombre del pasajero.';
        }
        if (parseInt(datos.get('puestos'), 10) < 1) errores.puestos = 'Debe ser al menos un puesto.';
        if (v && parseInt(datos.get('puestos'), 10) > v.cupos) {
            errores.puestos = 'Solo quedan ' + v.cupos + ' cupos libres en este viaje.';
        }
        if (parseFloat(datos.get('valor_pagado')) < 0) errores.valor_pagado = 'El valor no puede ser negativo.';

        if (Object.keys(errores).length) {
            SGETModal.errores(errores, form);
            return;
        }

        if (ocasional) {
            // El usuario occasional se crea en el servidor con su propio servicio.
            datos.append('accion', 'ocasional');
        }

        var btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Registrando…';

        fetch('../api/index.php', {
            method: 'POST', body: datos,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.status === 'ok') {
                    SGETModal.toast(j.mensaje, 'exito');
                    SGETModal.cerrar('modalRecaudo');
                    setTimeout(function () { location.reload(); }, 900);
                } else {
                    SGETModal.toast(j.mensaje || 'No se pudo registrar el cobro.', 'error');
                    if (j.errores) SGETModal.errores(j.errores, form);
                }
            })
            .catch(function () { SGETModal.toast('Error de comunicación con el servidor.', 'error'); })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-receipt"></i> Registrar cobro';
            });
    });
})();
</script>

<?php
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';

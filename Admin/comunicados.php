<?php
/**
 * Admin/comunicados.php
 * -----------------------------------------------------------------------------
 * MÓDULO: COMUNICADOS Y AVISOS  (Admin)
 * -----------------------------------------------------------------------------
 * QUÉ RESUELVE
 *   El buzón de notificaciones solo se alimentaba de eventos automáticos (una
 *   cancelación, una reserva). Faltaba lo más básico de una operación real:
 *   poder AVISAR a todos los conductores de un cambio de última hora, o a
 *   todos los pasajeros de una suspensión general por lluvia, sin tener que
 *   cancelar 40 viajes uno por uno.
 *
 *   Aquí el administrador escribe el comunicado, elige a quién va
 *   (conductores / pasajeros / ambos / usuarios concretos), y cada persona lo
 *   recibe en su campanita. Queda registro de todo lo enviado.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('comunicados');

if (!empty($_GET['ok']))      Flash::exito((string)$_GET['ok']);
elseif (!empty($_GET['error'])) Flash::error((string)$_GET['error']);

ViajeService::cerrarVencidos();

/* Destinatarios disponibles (para el contador en vivo del formulario). */
$destinos = [
    'conductores' => (int) Database::scalar(
        "SELECT COUNT(*) FROM usuario WHERE id_rol_usu = ? AND estado = ?",
        [Config::ROL_CONDUCTOR, Config::USU_ACTIVO]),
    'pasajeros'   => (int) Database::scalar(
        "SELECT COUNT(*) FROM usuario WHERE id_rol_usu = ? AND estado = ?",
        [Config::ROL_PASAJERO, Config::USU_ACTIVO]),
    'total'       => (int) Database::scalar("SELECT COUNT(*) FROM usuario WHERE estado = ?", [Config::USU_ACTIVO]),
];

/* Histórico de comunicados leídos de la auditoría. */
$historico = Database::all(
    "SELECT * FROM sget_logs_auditoria
      WHERE accion = 'DIFUSION_AVISO'
      ORDER BY id_log DESC
      LIMIT 10"
);

$tituloPagina = 'Comunicados';
include __DIR__ . '/../views/partials/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="sget-main">
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title">
                    <i class="fas fa-bullhorn text-sky-500"></i> Comunicados y avisos
                </h1>
                <p class="sget-page-sub">
                    Envía un aviso al buzón de <strong>conductores</strong>, <strong>pasajeros</strong> o a
                    usuarios concretos. Todos lo verán en su campanita, sin salir del sistema.
                </p>
            </div>
        </header>

        <?= Flash::render() ?>

        <div class="sget-grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,24rem),1fr))">

            <!-- ================= FORMULARIO ================= -->
            <section class="sget-card">
                <p class="sget-label" style="margin-bottom:1rem">
                    <i class="fas fa-pen-to-square"></i> Redactar comunicado
                </p>

                <form id="formComunicado" class="sget-form-2col" novalidate
                      data-comunicado-form autocomplete="off">
                    <?= Auth::campoToken() ?>

                    <div class="sget-field" data-campo="titulo" style="grid-column:1/-1">
                        <label class="sget-label" for="com_titulo">
                            Asunto <span class="sget-label__req">*</span>
                        </label>
                        <input type="text" id="com_titulo" name="titulo" class="sget-input" maxlength="150" required
                               data-sget-autofocus
                               placeholder="Ej.: Suspensión de salidas por lluvia">
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>

                    <div class="sget-field" data-campo="cuerpo" style="grid-column:1/-1">
                        <label class="sget-label" for="com_cuerpo">
                            Mensaje <span class="sget-label__req">*</span>
                        </label>
                        <textarea id="com_cuerpo" name="cuerpo" class="sget-textarea" rows="5" required maxlength="1200"
                                  data-sget-contador="#com_cuerpo_contador"
                                  placeholder="Explica qué pasa, desde cuándo y qué deben hacer."></textarea>
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                        <p class="sget-help"><span id="com_cuerpo_contador">0</span>/1200 caracteres</p>
                    </div>

                    <div class="sget-field" data-campo="rol" style="grid-column:1/-1">
                        <label class="sget-label">¿A quién va dirigido?</label>
                        <div style="display:grid;gap:.5rem;margin-top:.25rem">
                            <label class="sget-check" for="dest_conductores">
                                <input type="checkbox" id="dest_conductores" name="destinos[]" value="2"
                                       data-sget-destino="2" checked>
                                <span>Conductores <span class="sget-help">(<?= (int)$destinos['conductores'] ?> activos)</span></span>
                            </label>
                            <label class="sget-check" for="dest_pasajeros">
                                <input type="checkbox" id="dest_pasajeros" name="destinos[]" value="3"
                                       data-sget-destino="3" checked>
                                <span>Pasajeros <span class="sget-help">(<?= (int)$destinos['pasajeros'] ?> activos)</span></span>
                            </label>
                        </div>
                        <p class="sget-help" style="margin-top:.5rem">
                            Destinatarios seleccionados: <strong data-sget-destino-total>—</strong>
                            (no incluye tu propia cuenta).
                        </p>
                    </div>

                    <div class="sget-field" data-campo="prioridad" style="grid-column:1/-1">
                        <label class="sget-check" for="com_prioridad">
                            <input type="checkbox" id="com_prioridad" name="prioritario" value="1">
                            <span>
                                <strong>Mensaje prioritario</strong><br>
                                <span class="sget-help">Se emphasise en el buzón con el título en rojo y
                                un aviso flotante al abrir el sistema.</span>
                            </span>
                        </label>
                    </div>

                    <div style="grid-column:1/-1;display:flex;gap:.5rem;flex-wrap:wrap">
                        <button type="submit" class="sget-btn sget-btn--primario" style="flex:1">
                            <i class="fas fa-paper-plane"></i> Enviar comunicado
                        </button>
                        <button type="button" class="sget-btn sget-btn--neutro" data-sget-borrar>
                            <i class="fas fa-eraser"></i> Limpiar
                        </button>
                    </div>
                </form>
            </section>

            <div style="display:grid;gap:1.25rem;align-content:start">

                <!-- ================= CÓMO FUNCIONA ================= -->
                <section class="sget-card">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-circle-info"></i> Qué ve quien lo recibe
                    </p>
                    <ul style="display:grid;gap:.625rem;margin:0;padding:0;list-style:none;font-size:.8125rem">
                        <li style="display:flex;gap:.625rem;align-items:flex-start">
                            <i class="fas fa-bell" style="color:var(--sget-azul);margin-top:.2rem"></i>
                            <span>La campanita de su cabecera marca el comunicado como <strong>sin leer</strong>.</span>
                        </li>
                        <li style="display:flex;gap:.625rem;align-items:flex-start">
                            <i class="fas fa-gift" style="color:var(--sget-ambars);margin-top:.2rem"></i>
                            <span>Si tiene la página abierta, aparece un <strong>aviso flotante</strong> sin
                                necesidad de recargar.</span>
                        </li>
                        <li style="display:flex;gap:.625rem;align-items:flex-start">
                            <i class="fas fa-folder-open" style="color:var(--sget-emerald);margin-top:.2rem"></i>
                            <span>El mensaje queda archivado en su buzón, con la opción de eliminarlo cuando ya
                                lo haya leído.</span>
                        </li>
                    </ul>
                </section>

                <!-- ================= HISTÓRICO ================= -->
                <section class="sget-card">
                    <p class="sget-label" style="margin-bottom:.75rem">
                        <i class="fas fa-clock-rotate-left"></i> Últimos comunicados enviados
                    </p>
                    <?php if (empty($historico)): ?>
                        <p class="sget-help">Todavía no has enviado ningún comunicado.</p>
                    <?php else: ?>
                        <ul style="display:grid;gap:.5rem;margin:0;padding:0;list-style:none">
                            <?php foreach ($historico as $h): ?>
                                <li style="padding:.625rem .75rem;border:1px solid var(--sget-borde);
                                           border-radius:var(--sget-radio-sm);background:var(--sget-superficie-2)">
                                    <p class="sget-linea-2" style="font-size:.75rem;font-weight:800">
                                        <?= htmlspecialchars((string)$h['descripcion'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p class="sget-help sget-mono" style="margin-top:.25rem;font-size:.625rem">
                                        <?= htmlspecialchars(Fecha::legible($h['fec_log']), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
</div>

<script>
/* ==========================================================================
   Envío del comunicado
   --------------------------------------------------------------------------
   Se manda por fetch al API (módulo `notificacion`, acción `difundir`) para
   poder mostrar el resultado sin recargar, y con contador de destinatarios en
   vivo para que el admin vea a quién va a llegar antes de darle enviar.
   ========================================================================== */
(function () {
    'use strict';

    var TOTALES = <?= json_encode([
        '2' => (int)$destinos['conductores'],
        '3' => (int)$destinos['pasajeros'],
    ]) ?>;

    var form = document.getElementById('formComunicado');
    if (!form) return;

    var cuerpo   = document.getElementById('com_cuerpo');
    var contador = document.getElementById('com_cuerpo_contador');
    var totalEl  = form.querySelector('[data-sget-destino-total]');

    function recalcularDestinatarios() {
        var total = 0;
        form.querySelectorAll('[data-sget-destino]').forEach(function (c) {
            if (c.checked) total += TOTALES[c.dataset.sgetDestino] || 0;
        });
        if (totalEl) totalEl.textContent = total;
    }
    recalcularDestinatarios();
    form.querySelectorAll('[data-sget-destino]').forEach(function (c) {
        c.addEventListener('change', recalcularDestinatarios);
    });

    if (cuerpo && contador) {
        var avisar = function () { contador.textContent = cuerpo.value.length; };
        cuerpo.addEventListener('input', avisar);
        avisar();
    }

    document.querySelector('[data-sget-borrar]')?.addEventListener('click', function () {
        form.reset();
        SGETModal.limpiarErrores(form);
        if (contador) contador.textContent = '0';
        recalcularDestinatarios();
        cuerpo.focus();
    });

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();

        var errores = {};
        if (!form.titulo.value.trim()) errores.titulo = 'Escribe el asunto del comunicado.';
        if (cuerpo.value.trim().length < 15) errores.cuerpo = 'El mensaje debe tener al menos 15 caracteres para que se entienda.';
        if (!form.querySelector('[data-sget-destino]:checked')) {
            SGETModal.toast('Selecciona al menos un destinatario.', 'error');
            return;
        }

        if (Object.keys(errores).length) {
            SGETModal.errores(errores, form);
            return;
        }

        var btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Enviando…';

        var datos = new FormData();
        datos.append('_token', SGETModal.__token);
        datos.append('modulo', 'notificacion');
        datos.append('accion', 'difundir');
        datos.append('titulo', form.titulo.value.trim());
        datos.append('cuerpo', cuerpo.value.trim());
        form.querySelectorAll('[data-sget-destino]:checked').forEach(function (c) {
            datos.append('roles[]', c.value);
        });

        fetch('../api/index.php', {
            method: 'POST',
            body: datos,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.status === 'ok') {
                    SGETModal.toast(j.mensaje, 'exito');
                    form.reset();
                    SGETModal.limpiarErrores(form);
                    if (contador) contador.textContent = '0';
                    recalcularDestinatarios();
                    setTimeout(function () { location.reload(); }, 1200);
                } else {
                    if (j.errores) SGETModal.errores(j.errores, form);
                    SGETModal.toast(j.mensaje || 'No se pudo enviar el comunicado.', 'error');
                }
            })
            .catch(function () { SGETModal.toast('Error de comunicación con el servidor.', 'error'); })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar comunicado';
            });
    });
})();
</script>

<?php
$jsExtra = [];
include __DIR__ . '/../views/partials/foot.php';

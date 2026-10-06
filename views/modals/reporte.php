<?php
/**
 * views/modals/reporte.php
 * -----------------------------------------------------------------------------
 * REPORTAR UNA INCIDENCIA  (Pasajero)
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   La acción `reporte.crear` estaba en la API desde hacía tiempo, pero el
 *   `case 'reporte'` empezaba con `Auth::requerirAdmin()`: el pasajero NUNCA
 *   podía llegar a ella. Es decir, el flujo
 *
 *       Pasajero → crea reporte → Administrador lo revisa
 *
 *   estaba cortado en el primer eslabón: el módulo de reportes del administrador
 *   no tenía ninguna entrada.
 *
 * CÓMO SE ABRE
 *   Con el motor común del sistema:
 *       <button data-sget-modal="modalReporte">Reportar</button>
 *
 *   Los viajes que puede reportar los pasa la página:
 *       $viajesParaReporte = [['id' => 1, 'etiqueta' => '…', 'estado' => '…', 'ya' => false], …]
 *
 * SEGURIDAD
 *   La lista de la interfaz es solo comodidad. El backend vuelve a comprobar,
 *   en `ReporteService::crear()`, que el viaje sea del pasajero y que la
 *   descripción tenga sentido. Enviar el POST a mano con otro `id_via` no sirve
 *   de nada.
 * -----------------------------------------------------------------------------
 */
$v = static fn($valor): string => htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');

$viajesModal = (isset($viajesParaReporte) && is_array($viajesParaReporte)) ? $viajesParaReporte : [];
$hayViajes   = $viajesModal !== [];
?>
<div class="sget-modal-wrap" id="modalReporte" data-sget-capa data-titulo="Reportar">
    <div class="sget-overlay"></div>

    <form class="sget-modal sget-modal--sm" data-sget-panel novalidate
          data-sget-cerrar-al-guardar="modalReporte"
          role="dialog" aria-modal="true" aria-labelledby="tituloModalReporte">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloModalReporte">
                    <span class="sget-modal__icono"
                          style="background:color-mix(in srgb,var(--sget-azul) 16%,transparent);color:var(--sget-azul)">
                        <i class="fas fa-comment-dots"></i>
                    </span>
                    <span>Reportar una incidencia</span>
                </h2>
                <p class="sget-modal__sub">La administración revisa tu reporte y te avisa por el buzón.</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?= Auth::campoToken() ?>

            <?php if (!$hayViajes): ?>
                <div class="sget-sin-resultados">
                    <i class="fas fa-route"></i>
                    <p style="margin-top:.75rem">
                        Todavía no tienes ningún viaje reservado, así que no hay nada que reportar.
                    </p>
                </div>
            <?php else: ?>

                <div class="sget-field" data-campo="id_via">
                    <label class="sget-label" for="rep_viaje">
                        <i class="fas fa-route"></i> Viaje <span class="sget-label__req">*</span>
                    </label>
                    <select id="rep_viaje" name="id_via" class="sget-select" required>
                        <option value="">Selecciona el viaje…</option>
                        <?php foreach ($viajesModal as $opcion): ?>
                            <option value="<?= (int)$opcion['id'] ?>"
                                    <?= !empty($opcion['ya']) ? 'disabled' : '' ?>>
                                <?= $v($opcion['etiqueta']) ?><?= !empty($opcion['ya']) ? ' · ya reportado' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    <span class="sget-help">Solo puedes reportar sobre viajes en los que tengas reserva.</span>
                </div>

                <div class="sget-field" style="margin-top:1.25rem" data-campo="descripcion">
                    <label class="sget-label" for="rep_descripcion">
                        <i class="fas fa-pen"></i> ¿Qué ocurrió? <span class="sget-label__req">*</span>
                    </label>
                    <textarea id="rep_descripcion" name="descripcion" class="sget-textarea" rows="5"
                              maxlength="500" required
                              placeholder="Describe el problema con detalle: retraso, vehículo, trato del conductor, cobro…"></textarea>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    <span class="sget-help">Mínimo 15 caracteres, máximo 500.</span>
                </div>

            <?php endif; ?>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
            <?php if ($hayViajes): ?>
            <button type="submit" class="sget-btn sget-btn--primario">
                <i class="fas fa-paper-plane"></i> Enviar reporte
            </button>
            <?php else: ?>
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Entendido</button>
            <?php endif; ?>
        </footer>
    </form>
</div>

<script>
(function () {
    'use strict';
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();

    function iniciar() {
        var modal = document.getElementById('modalReporte');
        if (!modal) return;
        var form = modal.querySelector('[data-sget-panel]');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var cuerpo = new FormData(form);
            cuerpo.append('_token', window.SGETModal.__token);
            cuerpo.append('modulo', 'reporte');
            cuerpo.append('accion', 'crear');

            var boton = form.querySelector('button[type="submit"]');
            if (boton) { boton.disabled = true; boton.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Enviando…'; }

            fetch('../api/index.php', {
                method: 'POST',
                body: cuerpo,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    window.SGETModal.toast(j.mensaje || 'Reporte enviado.', j.status === 'ok' ? 'exito' : 'error');
                    if (j.status === 'ok') {
                        window.SGETModal.cerrar('modalReporte');
                        form.reset();
                        window.SGETModal.limpiarErrores(form);
                    }
                })
                .catch(function () { window.SGETModal.toast('Error de comunicación con el servidor.', 'error'); })
                .finally(function () {
                    if (boton) { boton.disabled = false; boton.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar reporte'; }
                });
        });
    }
})();
</script>

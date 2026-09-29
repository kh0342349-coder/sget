<?php
/**
 * views/modals/calificar.php
 * -----------------------------------------------------------------------------
 * CALIFICAR UN VIAJE  (Pasajero)
 * -----------------------------------------------------------------------------
 * POR QUÉ ES UN MODAL COMÚN
 *   La calificación aparecía duplicada en dos páginas del pasajero
 *   (historial_pasajero.php y pasajero.php) y en las dos enviaba a
 *   `guardar_calificacion.php`, un archivo inexistente: el sistema de reseñas
 *   estaba muerto. Aquí hay UN solo formulario, enviado por el API, y las
 *   páginas lo abren con `data-sget-modal="modalCalificar"` pasando el viaje
 *   por `data-sget-datos`.
 *
 *   Al enviar se registra en la tabla `calificacion` y el conductor recibe un
 *   aviso en su buzón (services/CalificacionService.php).
 * -----------------------------------------------------------------------------
 */
$v = fn($k, $d = '') => htmlspecialchars((string)($d), ENT_QUOTES, 'UTF-8');
?>
<div class="sget-modal-wrap" id="modalCalificar" data-sget-capa data-titulo="Calificar servicio">
    <div class="sget-overlay"></div>

    <form class="sget-modal sget-modal--sm" data-sget-panel novalidate
          data-sget-calificar data-sget-cerrar-al-guardar="modalCalificar"
          role="dialog" aria-modal="true" aria-labelledby="tituloModalCalificar">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloModalCalificar">
                    <span class="sget-modal__icono"
                          style="background:color-mix(in srgb,var(--sget-ambars) 16%,transparent);color:var(--sget-ambars)">
                        <i class="fas fa-star"></i>
                    </span>
                    <span>Califica tu viaje</span>
                </h2>
                <p class="sget-modal__sub">Tu opinión ayuda a mejorar el servicio y a los conductores.</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="id_via_cal" data-sget-campo="id_via_cal" value="0">

            <div class="sget-field">
                <label class="sget-label">Viaje</label>
                <p style="font-weight:800;font-size:.875rem" data-sget-texto="viaje">—</p>
                <p class="sget-help" data-sget-texto="conductor">—</p>
            </div>

            <div class="sget-field" style="margin-top:1.25rem" data-campo="pun_cal">
                <label class="sget-label" for="cal_estrellas">
                    Tu puntuación <span class="sget-label__req">*</span>
                </label>
                <div class="sget-estrellas" id="cal_estrellas" role="radiogroup" aria-label="Puntuación de 1 a 5">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="sget-estrella" data-sget-estrella="<?= $i ?>"
                                role="radio" aria-checked="<?= $i === 5 ? 'true' : 'false' ?>"
                                aria-label="<?= $i ?> de 5 estrellas" title="<?= $i ?> de 5">
                            <i class="fas fa-star"></i>
                        </button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="pun_cal" id="cal_puntos" value="5">
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                <p class="sget-help" data-sget-estrella-texto>5 de 5 · Excelente</p>
            </div>

            <div class="sget-field" style="margin-top:1.25rem" data-campo="com_cal">
                <label class="sget-label" for="cal_comentario">
                    <i class="fas fa-comment"></i> Comentario (opcional)
                </label>
                <textarea id="cal_comentario" name="com_cal" class="sget-textarea" rows="3" maxlength="255"
                          placeholder="¿Cómo fue el trato, la limpieza del vehículo o la puntualidad?"></textarea>
                <span class="sget-help">Máximo 255 caracteres. Se le envía al conductor.</span>
            </div>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Ahora no</button>
            <button type="submit" class="sget-btn sget-btn--primario">
                <i class="fas fa-paper-plane"></i> Enviar calificación
            </button>
        </footer>
    </form>
</div>

<script>
(function () {
    'use strict';
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();

    function iniciar() {
        var modal = document.getElementById('modalCalificar');
        if (!modal) return;

        var form    = modal.querySelector('[data-sget-calificar]');
        var puntos  = document.getElementById('cal_puntos');
        var texto   = modal.querySelector('[data-sget-estrella-texto]');
        var ETIQUETAS = ['', 'Muy malo', 'Malo', 'Regular', 'Bueno', 'Excelente'];

        /* --- Estrellas --- */
        function pintar(n) {
            modal.querySelectorAll('[data-sget-estrella]').forEach(function (b) {
                var i = parseInt(b.dataset.sgetEstrella, 10);
                b.classList.toggle('es-activa', i <= n);
                b.classList.toggle('es-elegida', i === n);
                b.setAttribute('aria-checked', i === n ? 'true' : 'false');
            });
            if (puntos) puntos.value = String(n);
            if (texto) texto.textContent = n + ' de 5 · ' + (ETIQUETAS[n] || '');
        }

        modal.addEventListener('click', function (e) {
            var estrella = e.target.closest('[data-sget-estrella]');
            if (estrella) pintar(parseInt(estrella.dataset.sgetEstrella, 10));
        });

        // Al abrir el modal se reinicia siempre a 5 estrellas
        modal.addEventListener('sget:modal-abierto', function (ev) {
            if (!ev.detail || ev.detail.id !== 'modalCalificar') return;
            pintar(5);
            if (window.SGETModal) window.SGETModal.limpiarErrores(form);
        });

        /* --- Envío --- */
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var idViaje = parseInt(form.id_via_cal.value, 10);
            if (idViaje <= 0) {
                window.SGETModal.toast('No se sabe qué viaje vas a calificar.', 'error');
                return;
            }

            var btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Enviando…';

            var cuerpo = new FormData(form);
            cuerpo.append('_token', window.SGETModal.__token);
            cuerpo.append('modulo', 'calificacion');
            cuerpo.append('accion', 'registrar');

            fetch('../api/index.php', {
                method: 'POST',
                body: cuerpo,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.status === 'ok') {
                        window.SGETModal.toast(j.mensaje, 'exito');
                        window.SGETModal.cerrar('modalCalificar');
                        document.dispatchEvent(new CustomEvent('sget:calificacion-guardada', { detail: { id_viaje: idViaje } }));
                    } else {
                        window.SGETModal.toast(j.mensaje || 'No se pudo guardar la calificación.', 'error');
                    }
                })
                .catch(function () { window.SGETModal.toast('Error de comunicación con el servidor.', 'error'); })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar calificación';
                });
        });
    }
})();
</script>

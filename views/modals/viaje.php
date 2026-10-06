<?php
/**
 * views/modals/viaje.php
 * -----------------------------------------------------------------------------
 * MODAL DE VIAJES · Crear / Editar (drawer)
 * -----------------------------------------------------------------------------
 * Variables opcionales:  $viajeModal  array|null
 * -----------------------------------------------------------------------------
 */
$__viaje = $viajeModal ?? [];
$__edicion = !empty($__viaje['id_via']);
$v = fn(string $k, $def = '') => htmlspecialchars((string)($__viaje[$k] ?? $def), ENT_QUOTES, 'UTF-8');

$__rutas     = RutaService::todas(true);
$__conductores = ViajeService::conductoresDisponibles();
$__vehiculos = VehiculoService::disponiblesParaDespacho();
?>
<?php // Antes: panel lateral (drawer). Ahora: modal centrado, igual que el resto de CRUD. ?>
<div class="sget-modal-wrap" id="modalViaje" data-sget-capa data-titulo="<?= $__edicion ? 'Editar viaje' : 'Programar viaje' ?>">
    <div class="sget-overlay"></div>

    <form class="sget-modal sget-modal--lg" data-sget-panel novalidate
          data-sget-form data-sget-cerrar-al-guardar="modalViaje"
          role="dialog" aria-modal="true" aria-labelledby="tituloModalViaje">

            <header class="sget-modal__head">
                <div>
                    <h2 class="sget-modal__titulo" id="tituloModalViaje">
                        <span class="sget-modal__icono"><i class="fas fa-bus"></i></span>
                        <span data-sget-texto="titulo"><?= $__edicion ? 'Editar Viaje #' . (int)$__viaje['id_via'] : 'Programar Nuevo Viaje' ?></span>
                    </h2>
                    <p class="sget-modal__sub">Elige ruta, recurso humano y la fecha/hora exacta de salida.</p>
                </div>
                <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </header>

            <div class="sget-modal__body sget-scroll">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="modulo" value="viaje">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id_via" data-sget-campo="id_via" value="<?= $v('id_via', '0') ?>">

                <!-- Ruta + tarifa automática -->
                <div class="sget-field" data-campo="id_rut_via">
                    <label class="sget-label" for="viaje_ruta">
                        <i class="fas fa-route"></i> Ruta programada <span class="sget-label__req">*</span>
                    </label>
                    <select id="viaje_ruta" name="id_rut_via" class="sget-select" required data-sget-autofocus data-sget-campo="id_rut_via">
                        <option value="">Selecciona una ruta…</option>
                        <?php foreach ($__rutas as $r):
                            $tarifa = (float)$r['val_rut'];
                            $hora   = Fecha::soloHora($r['hora_salida'] ?? '');
                            $etiqueta = $r['nom_rut'] . ' · ' . $r['ori_rut'] . ' → ' . $r['des_rut'];
                            if ($hora !== '') $etiqueta .= ' · salida ' . $hora;
                        ?>
                            <option value="<?= (int)$r['id_rut'] ?>"
                                    data-tarifa="<?= $tarifa ?>"
                                    data-hora="<?= htmlspecialchars($hora, ENT_QUOTES, 'UTF-8') ?>"
                                <?= (string)(int)($__viaje['id_rut_via'] ?? '') === (string)(int)$r['id_rut'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?> — $<?= number_format($tarifa, 0, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <!-- Recurso humano y unidad -->
                <div class="sget-form-2col" style="margin-top:1rem">
                    <div class="sget-field" data-campo="id_usu_via">
                        <label class="sget-label" for="viaje_conductor">
                            <i class="fas fa-user-tie"></i> Conductor <span class="sget-label__req">*</span>
                        </label>
                        <select id="viaje_conductor" name="id_usu_via" class="sget-select" required data-sget-campo="id_usu_via">
                            <option value="">Selecciona un conductor…</option>
                            <?php foreach ($__conductores as $c): ?>
                                <option value="<?= (int)$c['id_usu'] ?>"
                                        <?= (string)(int)($__viaje['id_usu_via'] ?? '') === (string)(int)$c['id_usu'] ? 'selected' : '' ?>
                                        data-disponible="<?= $c['disponible'] ? '1' : '0' ?>"
                                        data-motivo="<?= htmlspecialchars((string)$c['motivo'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($c['nom_usu'], ENT_QUOTES, 'UTF-8') ?>
                                    <?= $c['tel_usu'] ? ' · ' . htmlspecialchars($c['tel_usu'], ENT_QUOTES, 'UTF-8') : '' ?>
                                    <?= $c['disponible'] ? '' : ' · ocupado en ese horario' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>

                    <div class="sget-field" data-campo="id_veh">
                        <label class="sget-label" for="viaje_veh">
                            <i class="fas fa-bus-simple"></i> Vehículo <span class="sget-label__req">*</span>
                        </label>
                        <select id="viaje_veh" name="id_veh" class="sget-select" required data-sget-campo="id_veh">
                            <option value="">Selecciona una placa…</option>
                            <?php foreach ($__vehiculos as $ve): ?>
                                <option value="<?= (int)$ve['id_veh'] ?>"
                                        <?= (string)(int)($__viaje['id_veh'] ?? '') === (string)(int)$ve['id_veh'] ? 'selected' : '' ?>
                                        data-disponible="<?= $ve['disponible'] ? '1' : '0' ?>"
                                        data-motivo="<?= htmlspecialchars((string)$ve['motivo'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($ve['pla_veh'], ENT_QUOTES, 'UTF-8') ?>
                                    · <?= htmlspecialchars($ve['mode_veh'], ENT_QUOTES, 'UTF-8') ?>
                                    · <?= (int)$ve['cap_veh'] ?> puestos
                                    <?= $ve['disponible'] ? '' : ' · no disponible' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                        <span class="sget-help" data-sget-help-disponibilidad>
                            Cambia la fecha o la hora y la lista se recalcula con los horarios reales.
                        </span>
                    </div>
                </div>

                <!-- FECHA Y HORA DE SALIDA  (corregido: date + time, nunca DATETIME con ceros) -->
                <div class="sget-form-2col" style="margin-top:1rem">
                    <div class="sget-field" data-campo="fec_via">
                        <label class="sget-label" for="viaje_fecha">
                            <i class="fas fa-calendar-day"></i> Fecha de salida <span class="sget-label__req">*</span>
                        </label>
                        <input type="date" id="viaje_fecha" name="fec_via" class="sget-input" required data-sget-campo="fec_via"
                               min="<?= date('Y-m-d') ?>"
                               value="<?= $v('fec_via') ? htmlspecialchars(Fecha::soloFecha($__viaje['fec_via'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>

                    <div class="sget-field" data-campo="hor_sal_via">
                        <label class="sget-label" for="viaje_hora">
                            <i class="fas fa-clock"></i> Hora de salida <span class="sget-label__req">*</span>
                        </label>
                        <input type="time" id="viaje_hora" name="hor_sal_via" class="sget-input sget-input--mono" required data-sget-campo="hor_sal_via"
                               value="<?= $v('hor_sal_via') ? htmlspecialchars(Fecha::soloHora($__viaje['hor_sal_via'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>
                </div>

                <!-- Llegada y tarifa -->
                <div class="sget-form-2col" style="margin-top:1rem">
                    <div class="sget-field" data-campo="hor_lleg_via">
                        <label class="sget-label" for="viaje_llegada">
                            <i class="fas fa-flag-checkered"></i> Hora estimada de llegada <span class="sget-label__opt">(opc.)</span>
                        </label>
                        <input type="time" id="viaje_llegada" name="hor_lleg_via" class="sget-input sget-input--mono" data-sget-campo="hor_lleg_via"
                               value="<?= $v('hor_lleg_via') ? htmlspecialchars(Fecha::soloHora($__viaje['hor_lleg_via'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>

                    <div class="sget-field" data-campo="val_via">
                        <label class="sget-label" for="viaje_tarifa">Tarifa del pasaje <span class="sget-label__req">*</span></label>
                        <input type="number" id="viaje_tarifa" name="val_via" class="sget-input sget-input--mono" data-sget-campo="val_via"
                               step="0.01" min="1" max="99999999" required placeholder="0.00" value="<?= $v('val_via') ?>">
                        <span class="sget-help">Si lo dejas en 0 se hereda la tarifa de la ruta.</span>
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>
                </div>

                <!-- Resumen en vivo -->
                <div class="sget-card" style="margin-top:1.25rem;padding:1rem;background:var(--sget-superficie-2)">
                    <p class="sget-label" style="margin-bottom:.5rem"><i class="fas fa-eye"></i> Resumen del despacho</p>
                    <p class="sget-help" data-resumen>
                        Selecciona la ruta y completa la fecha para ver el detalle.
                    </p>
                </div>
            </div>

            <footer class="sget-modal__foot">
                <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
                <button type="submit" class="sget-btn sget-btn--primario">
                    <i class="fas fa-floppy-disk"></i> <span>Guardar Viaje</span>
                </button>
            </footer>
    </form>
</div>

<script>
(function () {
    'use strict';
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();

    /* =====================================================================
       DISPONIBILIDAD EN VIVO
       ---------------------------------------------------------------------
       Los selectores se recalculan al cambiar la fecha o la hora consultando
       `DisponibilidadService` por el API. Es la MISMA función que valida al
       guardar, así que el administrador nunca elige una opción que el backend
       vaya a rechazar dos segundos después.

       Las opciones que chocan NO se eliminan del <select>: se muestran con el
       motivo. Ocultarlas haría imposible entender por qué un conductor que
       «está disponible» hoy no aparece para un viaje de mañana.
       ===================================================================== */
    function iniciar() {
        var modal = document.getElementById('modalViaje');
        if (!modal) return;

        var form     = modal.querySelector('[data-sget-panel]');
        var fecha    = modal.querySelector('#viaje_fecha');
        var hora     = modal.querySelector('#viaje_hora');
        var selCond  = modal.querySelector('#viaje_conductor');
        var selVeh   = modal.querySelector('#viaje_veh');
        var ayuda    = modal.querySelector('[data-sget-help-disponibilidad]');
        if (!form || !fecha || !hora || !selCond || !selVeh) return;

        var idViajeEnEdicion = modal.querySelector('[name="id_via"]');
        var pendientes = null;

        function pedir() {
            if (pendientes) return;
            pendientes = setTimeout(function () {
                pendientes = null;
                refrescar();
            }, 220);
        }

        function refrescar() {
            var cuerpo = new FormData();
            cuerpo.append('_token', window.SGETModal.__token);
            cuerpo.append('modulo', 'viaje');
            cuerpo.append('accion', 'disponibles');
            cuerpo.append('fec_via', fecha.value);
            cuerpo.append('hor_sal_via', hora.value);
            if (idViajeEnEdicion && idViajeEnEdicion.value) {
                cuerpo.append('id_via', idViajeEnEdicion.value);
            }

            fetch('../api/index.php', {
                method: 'POST',
                body: cuerpo,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.status !== 'ok') return;
                    pintar(selCond, j.datos.conductores || [], 'id_usu');
                    pintar(selVeh,  j.datos.vehiculos   || [], 'id_veh');
                    if (ayuda && j.datos.margen_min) {
                        ayuda.textContent = 'Margen operativo entre viajes: ' + j.datos.margen_min +
                            ' min. Un recurso solo queda libre pasado ese margen.';
                    }
                })
                .catch(function () { /* si falla, el backend volverá a validar al guardar */ });
        }

        /* Reconstruye un <select> conservando la selección actual.
           `textContent` para todo el texto: los nombres vienen de la BD. */
        function pintar(select, filas, campo) {
            var previo = select.value;
            var vacio = select.querySelector('option[value=""]');

            Array.prototype.slice.call(select.options).forEach(function (o) {
                if (o !== vacio) select.removeChild(o);
            });

            filas.forEach(function (fila) {
                var op = document.createElement('option');
                op.value = String(fila[campo]);
                op.dataset.disponible = fila.disponible ? '1' : '0';
                op.dataset.motivo = fila.motivo || '';
                op.textContent = fila.nom_usu
                    ? fila.nom_usu + (fila.tel_usu ? ' · ' + fila.tel_usu : '')
                    : fila.pla_veh + ' · ' + fila.mode_veh + ' · ' + fila.cap_veh + ' puestos';

                if (!fila.disponible) {
                    op.textContent += ' — ' + (fila.motivo || 'no disponible en ese horario');
                    op.disabled = true;
                }
                select.appendChild(op);
            });

            if (previo) {
                var existe = Array.prototype.some.call(select.options, function (o) { return o.value === previo; });
                if (existe) select.value = previo;
            }
        }

        fecha.addEventListener('change', pedir);
        hora.addEventListener('change', pedir);

        document.addEventListener('sget:modal-abierto', function (e) {
            if (!e.detail || e.detail.id !== 'modalViaje') return;
            if (fecha.value && hora.value) refrescar();
        });
    }
})();
</script>

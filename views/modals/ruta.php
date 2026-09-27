<?php
/**
 * views/modals/ruta.php
 * -----------------------------------------------------------------------------
 * MODAL DE RUTAS · Crear / Editar
 * -----------------------------------------------------------------------------
 * ANTES era un panel lateral (drawer) que empujaba el contenido y, en móvil,
 * ocupaba la pantalla completa. Ahora es un MODAL centrado: más rápido de
 * abrir/cerrar, no desplaza la tabla de fondo y en móvil se convierte en una
 * hoja inferior donde el botón de confirmar queda al alcance del pulgar.
 *
 * Variables opcionales antes del include:
 *   $rutaModal  array|null   datos para modo edición (vacío = alta)
 *
 * Campos del dominio (los que antes NO se guardaban y quedaban como ''):
 *   ori_rut       Ciudad de SALIDA   (obligatorio)
 *   des_rut       Ciudad de DESTINO  (obligatorio)
 *   dis_rut       Distancia en km
 *   hora_salida   Hora de salida por defecto de los viajes de la ruta
 *   duracion_min  Duración estimada del trayecto (define el cierre automático)
 *   val_rut       Tarifa base
 * -----------------------------------------------------------------------------
 */
$__rutaModal = $rutaModal ?? [];
$__esEdicion = !empty($__rutaModal['id_rut']);
$v = fn(string $k, $def = '') => htmlspecialchars((string)($__rutaModal[$k] ?? $def), ENT_QUOTES, 'UTF-8');
?>
<div class="sget-modal-wrap" id="modalRuta" data-sget-capa data-titulo="<?= $__esEdicion ? 'Editar ruta' : 'Nueva ruta' ?>">
    <div class="sget-overlay"></div>

    <form class="sget-modal sget-modal--lg" data-sget-panel novalidate
          data-sget-form data-sget-cerrar-al-guardar="modalRuta"
          role="dialog" aria-modal="true" aria-labelledby="tituloModalRuta">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloModalRuta">
                    <span class="sget-modal__icono"><i class="fas fa-route"></i></span>
                    <span data-sget-texto="titulo"><?= $__esEdicion ? 'Editar Ruta #' . (int)$__rutaModal['id_rut'] : 'Registrar Nueva Ruta' ?></span>
                </h2>
                <p class="sget-modal__sub">Define el trayecto, la tarifa, la hora y la duración del recorrido.</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="modulo" value="ruta">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_rut" data-sget-campo="id_rut" value="<?= $v('id_rut', '0') ?>">
            <input type="hidden" name="img_actual" data-sget-campo="img_actual" value="<?= $v('img_rut') ?>">

            <!-- Identificación -->
            <div class="sget-field" data-campo="nom_rut">
                <label class="sget-label" for="ruta_nom">
                    <i class="fas fa-signature"></i> Nombre del trayecto <span class="sget-label__req">*</span>
                </label>
                <input type="text" id="ruta_nom" name="nom_rut" class="sget-input" maxlength="100" required
                       data-sget-autofocus placeholder="Ej.: Fusagasugá - Bogotá" value="<?= $v('nom_rut') ?>">
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <!-- SALIDA / DESTINO -->
            <div class="sget-form-2col" style="margin-top:1rem">
                <div class="sget-field" data-campo="ori_rut">
                    <label class="sget-label" for="ruta_ori">
                        <i class="fas fa-location-arrow text-emerald-400"></i> Ciudad de SALIDA <span class="sget-label__req">*</span>
                    </label>
                    <input type="text" id="ruta_ori" name="ori_rut" class="sget-input" maxlength="100" required
                           placeholder="Ej.: Fusagasugá" value="<?= $v('ori_rut') ?>">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <div class="sget-field" data-campo="des_rut">
                    <label class="sget-label" for="ruta_des">
                        <i class="fas fa-map-marker-alt text-rose-400"></i> Ciudad de DESTINO <span class="sget-label__req">*</span>
                    </label>
                    <input type="text" id="ruta_des" name="des_rut" class="sget-input" maxlength="100" required
                           placeholder="Ej.: Bogotá" value="<?= $v('des_rut') ?>">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>
            </div>

            <!-- Distancia / hora de salida / tarifa -->
            <div class="sget-form-3col" style="margin-top:1rem">
                <div class="sget-field" data-campo="dis_rut">
                    <label class="sget-label" for="ruta_dis">
                        <i class="fas fa-ruler-horizontal"></i> Distancia (km) <span class="sget-label__opt">(opc.)</span>
                    </label>
                    <input type="number" id="ruta_dis" name="dis_rut" class="sget-input sget-input--mono"
                           step="0.1" min="0" max="99999" placeholder="0.0"
                           data-estimar-duracion value="<?= $v('dis_rut') ?>">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <div class="sget-field" data-campo="hora_salida">
                    <label class="sget-label" for="ruta_hora">
                        <i class="fas fa-clock"></i> Hora de salida <span class="sget-label__opt">(opc.)</span>
                    </label>
                    <input type="time" id="ruta_hora" name="hora_salida" class="sget-input sget-input--mono"
                           value="<?= htmlspecialchars(Fecha::soloHora($__rutaModal['hora_salida'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <div class="sget-field" data-campo="val_rut">
                    <label class="sget-label" for="ruta_val">
                        <i class="fas fa-tag"></i> Tarifa base <span class="sget-label__req">*</span>
                    </label>
                    <input type="number" id="ruta_val" name="val_rut" class="sget-input sget-input--mono"
                           step="0.01" min="1" max="99999999" required placeholder="0.00" value="<?= $v('val_rut') ?>">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>
            </div>

            <!-- DURACIÓN: define cuándo termina cada viaje de esta ruta -->
            <div class="sget-field" data-campo="duracion_min" style="margin-top:1rem">
                <label class="sget-label" for="ruta_dur">
                    <i class="fas fa-hourglass-half"></i>
                    Duración estimada del trayecto <span class="sget-label__req">*</span>
                </label>
                <div style="display:flex;gap:.5rem;align-items:stretch">
                    <input type="number" id="ruta_dur" name="duracion_min" class="sget-input sget-input--mono"
                           min="5" max="2880" step="5" required
                           data-sget-default="<?= (int)($__rutaModal['duracion_min'] ?? Config::DURACION_VIAJE_MIN_POR_DEFECTO) ?>"
                           value="<?= (int)($__rutaModal['duracion_min'] ?? Config::DURACION_VIAJE_MIN_POR_DEFECTO) ?>">
                    <span class="sget-input" data-duracion-legible
                          style="display:grid;place-items:center;flex-shrink:0;min-width:5.5rem;font-size:.8125rem;font-weight:700;color:var(--sget-texto-suave)">
                        <?= RutaService::duracionLegible($__rutaModal['duracion_min'] ?? Config::DURACION_VIAJE_MIN_POR_DEFECTO) ?>
                    </span>
                </div>
                <p class="sget-help" style="margin-top:.5rem">
                    Los viajes de esta ruta se cierran solos al cumplirse
                    <strong>salida + duración</strong>, no 24 h después.
                    Escribe la distancia y la duración se estima sola (45 km/h).
                </p>
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <!-- Estado -->
            <div class="sget-field" data-campo="estado" style="margin-top:1rem">
                <label class="sget-label" for="ruta_estado">
                    <i class="fas fa-toggle-on"></i> Estado de la ruta
                </label>
                <select id="ruta_estado" name="estado" class="sget-select">
                    <option value="1" <?= ((int)($__rutaModal['estado'] ?? 1) === 1) ? 'selected' : '' ?>>Activa</option>
                    <option value="0" <?= ((int)($__rutaModal['estado'] ?? 1) === 0) ? 'selected' : '' ?>>Suspendida</option>
                </select>
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <!-- Imagen -->
            <div class="sget-field" data-campo="img_rut" style="margin-top:1rem">
                <label class="sget-label" for="ruta_img">
                    <i class="fas fa-image"></i> Fotografía del trayecto <span class="sget-label__opt">(opc.)</span>
                </label>
                <input type="file" id="ruta_img" name="img_rut" class="sget-input" accept="image/png,image/jpeg,image/webp">
                <p class="sget-help">JPG, PNG o WEBP · máximo 3 MB.</p>
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
            <button type="submit" class="sget-btn sget-btn--primario">
                <i class="fas fa-floppy-disk"></i> <span>Guardar Ruta</span>
            </button>
        </footer>
    </form>
</div>

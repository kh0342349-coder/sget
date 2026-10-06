<?php
/**
 * Modal de alta de un pasajero que aborda durante el viaje en curso.
 * Requiere $viajeTemporal: id_via, origen, destino y tarifa.
 */
$__temporal = $viajeTemporal ?? [];
$__temporalValor = (float)($__temporal['tarifa'] ?? 0);
?>
<div class="sget-modal-wrap" id="modalPasajeroTemporal" data-sget-capa data-titulo="Agregar pasajero en ruta">
    <div class="sget-overlay"></div>

    <form id="formPasajeroTemporal" class="sget-modal sget-modal--sm" data-sget-panel novalidate
          role="dialog" aria-modal="true" aria-labelledby="tituloPasajeroTemporal">
        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloPasajeroTemporal">
                    <span class="sget-modal__icono"><i class="fas fa-user-plus"></i></span>
                    <span>Agregar pasajero en ruta</span>
                </h2>
                <p class="sget-modal__sub">El registro queda asociado al viaje y a la liquidación de caja.</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="id_via" value="<?= (int)($__temporal['id_via'] ?? 0) ?>">

            <div class="sget-field">
                <label class="sget-label" for="temporalNombre">Nombres y apellidos <span class="sget-label__req">*</span></label>
                <input class="sget-input" id="temporalNombre" name="nombre" type="text" maxlength="100" required autocomplete="name">
            </div>

            <div class="sget-form-2col" style="margin-top:1rem">
                <div class="sget-field">
                    <label class="sget-label" for="temporalDocumento">Documento (opcional)</label>
                    <input class="sget-input" id="temporalDocumento" name="documento" type="text" maxlength="30" autocomplete="off">
                </div>
                <div class="sget-field">
                    <label class="sget-label" for="temporalTelefono">Teléfono (opcional)</label>
                    <input class="sget-input" id="temporalTelefono" name="telefono" type="tel" maxlength="30" autocomplete="tel">
                </div>
            </div>

            <div class="sget-form-2col" style="margin-top:1rem">
                <div class="sget-field">
                    <label class="sget-label" for="temporalOrigen">Punto de abordaje <span class="sget-label__req">*</span></label>
                    <input class="sget-input" id="temporalOrigen" name="punto_abordaje" type="text" maxlength="120" required
                           value="<?= htmlspecialchars((string)($__temporal['origen'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="sget-field">
                    <label class="sget-label" for="temporalDestino">Destino <span class="sget-label__req">*</span></label>
                    <input class="sget-input" id="temporalDestino" name="destino_abordaje" type="text" maxlength="120" required
                           value="<?= htmlspecialchars((string)($__temporal['destino'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="sget-form-2col" style="margin-top:1rem">
                <div class="sget-field">
                    <label class="sget-label" for="temporalValor">Valor del pasaje <span class="sget-label__req">*</span></label>
                    <input class="sget-input sget-input--mono" id="temporalValor" name="valor_pagado" type="number"
                           min="0.01" max="99999999" step="0.01" required value="<?= $__temporalValor > 0 ? htmlspecialchars((string)$__temporalValor, ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>
                <div class="sget-field">
                    <label class="sget-label" for="temporalMetodo">Método de pago <span class="sget-label__req">*</span></label>
                    <select class="sget-select" id="temporalMetodo" name="metodo_pago" required>
                        <option value="Efectivo">Efectivo recibido ahora</option>
                        <option value="Pago al abordar">Pago al abordar</option>
                    </select>
                </div>
            </div>
            <p class="sget-help" style="margin-top:.75rem">
                El efectivo se registra como pagado y abordado. “Pago al abordar” queda pendiente y no se suma a caja hasta confirmarlo.
            </p>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
            <button type="submit" class="sget-btn sget-btn--primario">
                <i class="fas fa-ticket"></i> Registrar pasajero
            </button>
        </footer>
    </form>
</div>

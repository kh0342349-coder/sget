<?php
/**
 * views/modals/cancelar-viaje.php
 * -----------------------------------------------------------------------------
 * MODAL DE CANCELACIÓN DE VIAJE  ·  una sola instancia para todos los viajes
 * -----------------------------------------------------------------------------
 * El diálogo se rellena al abrirlo con los datos que trae el botón
 * (atributo `data-sget-datos`, vía SGETModal.abrir(id, {datos})). Se renderiza
 * UNA vez, no uno por viaje.
 *
 * REGLAS DE NEGOCIO que refleja en pantalla:
 *   1. Si el viaje AÚN NO SALE → anotación OBLIGATORIA (mínimo N caracteres) y
 *      se envía literalmente a cada pasajero reservado.
 *   2. Si el viaje YA SALIÓ → basta el motivo; la anotación se archiva.
 *   3. Si el viaje ya VENCIÓ su duración → no se puede cancelar.
 *   4. Antes de confirmar se muestra cuántos pasajeros se van a notificar.
 *
 * IMPORTANTE: aquí solo se refleja la EXPERIENCIA DE USUARIO. La autoridad es
 * ViajeService::cancelar(), que vuelve a comprobar todo en el servidor.
 * -----------------------------------------------------------------------------
 */
$__minimo  = Config::MIN_ANOTACION_CANCELACION;
$__motivos = '';
foreach (ViajeService::motivosCancelacion() as $valor => $etiqueta) {
    $__motivos .= '<option value="' . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '">'
               . htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') . '</option>';
}
?>
<div class="sget-modal-wrap sget-confirm" id="modalCancelarViaje"
     data-sget-capa data-titulo="Cancelar viaje">

    <div class="sget-overlay"></div>

    <form class="sget-modal" data-sget-panel novalidate
          data-sget-form
          data-sget-cerrar-al-guardar="modalCancelarViaje"
          data-sget-min-anotacion="<?= $__minimo ?>"
          data-sget-anotacion-obligatoria="1">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo">
                    <span class="sget-modal__icono sget-modal__icono--peligro" data-sget-mostrar="iconoBan">
                        <i class="fas fa-ban"></i>
                    </span>
                    <span class="sget-modal__icono" data-sget-mostrar="iconoVencido">
                        <i class="fas fa-hourglass-end"></i>
                    </span>
                    <span data-sget-texto="titulo">Cancelar viaje</span>
                </h2>
                <p class="sget-modal__sub" data-sget-texto="trayecto"></p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">

            <!-- Aviso: el viaje ya venció su duración y no se puede cancelar -->
            <div class="sget-flash sget-flash--aviso" data-sget-mostrar="avisoVencido">
                <i class="fas fa-circle-exclamation"></i>
                <span>
                    Este viaje ya cumplió su duración de trayecto
                    (<strong data-sget-texto="duracion"></strong>, terminaba el
                    <span data-sget-texto="vence"></span>) y se cerrará automáticamente.
                    No se puede cancelar.
                </span>
            </div>

            <!-- Ficha del viaje (común a los dos estados) -->
            <div class="sget-card" style="padding:1rem;background:var(--sget-superficie-2)">
                <div class="sget-form-2col" style="gap:.75rem">
                    <div>
                        <p class="sget-label">Salida programada</p>
                        <p class="sget-mono" style="margin-top:.25rem" data-sget-texto="salida"></p>
                    </div>
                    <div>
                        <p class="sget-label">Termina previsto</p>
                        <p class="sget-mono" style="margin-top:.25rem">
                            <span data-sget-texto="vence"></span>
                            <span class="sget-badge sget-badge--neutro" style="margin-left:.25rem" data-sget-texto="duracion"></span>
                        </p>
                    </div>
                </div>
                <div class="sget-form-2col" style="gap:.75rem;margin-top:.75rem">
                    <div>
                        <p class="sget-label">Conductor</p>
                        <p class="sget-truncar" style="margin-top:.25rem;font-size:.8125rem">
                            <i class="fas fa-user-tie"></i> <span data-sget-texto="conductor"></span>
                        </p>
                    </div>
                    <div>
                        <p class="sget-label">Unidad</p>
                        <p class="sget-truncar" style="margin-top:.25rem;font-size:.8125rem">
                            <i class="fas fa-bus"></i> <span data-sget-texto="placa"></span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Formulario de cancelación -->
            <div data-sget-mostrar="zonaFormulario">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="modulo" value="viaje">
                <input type="hidden" name="accion" value="cancelar">
                <input type="hidden" name="id" data-sget-campo="id" value="">

                <!-- Impacto sobre los pasajeros -->
                <div class="sget-impacto">
                    <span class="sget-impacto__chip">
                        <i class="fas fa-users"></i>
                        <span data-sget-texto="pasajeros">0</span>
                    </span>
                    <span data-sget-texto="impacto"></span>
                </div>

                <!-- MOTIVO (siempre obligatorio) -->
                <div class="sget-field" data-campo="motivo" style="margin-top:1.25rem">
                    <label class="sget-label" for="cancelar_motivo">
                        <i class="fas fa-list-check"></i> Motivo de la cancelación <span class="sget-label__req">*</span>
                    </label>
                    <select id="cancelar_motivo" name="motivo" class="sget-select" required>
                        <option value="">Selecciona un motivo…</option>
                        <?= $__motivos ?>
                    </select>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <!-- ANOTACIÓN: obligatoria si el viaje aún no sale -->
                <div class="sget-confirm__caja" data-sget-mostrar="cajaAnotacion">
                    <div class="sget-confirm__titulo-caja">
                        <i class="fas fa-envelope-open-text"></i>
                        <span data-sget-texto="etiquetaAnotacion">Anotación para los pasajeros (obligatoria)</span>
                        <span class="sget-confirm__contador" data-contador>0/<?= $__minimo ?></span>
                    </div>
                    <textarea class="sget-textarea" rows="4" maxlength="500" name="anotacion"
                              data-campo="anotacion_cancelacion"
                              data-anotacion></textarea>
                    <p class="sget-help" style="margin-top:.5rem" data-sget-texto="textoAnotacion"></p>
                    <span class="sget-error" data-error-anotacion style="margin-top:.5rem">
                        <i class="fas fa-circle-exclamation"></i><span></span>
                    </span>
                </div>

                <label class="sget-check" style="margin-top:1rem">
                    <input type="checkbox" id="cancelar_confirmo" name="confirmo" value="1">
                    <span>
                        Entiendo que esta acción <strong>cancela las reservas activas</strong> de los pasajeros
                        y que la operación queda registrada en la auditoría del sistema.
                    </span>
                </label>
                <span class="sget-error" data-campo="confirmo" style="margin-top:.375rem">
                    <i class="fas fa-circle-exclamation"></i><span>Debes confirmar que entiendes la cancelación.</span>
                </span>
            </div>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Volver</button>
            <button type="submit" class="sget-btn sget-btn--peligro" data-sget-mostrar="botonCancelar">
                <i class="fas fa-ban"></i> <span>Cancelar y notificar</span>
            </button>
            <button type="button" class="sget-btn sget-btn--primario" data-sget-mostrar="botonEntendido" data-sget-cerrar>
                Entendido
            </button>
        </footer>
    </form>
</div>

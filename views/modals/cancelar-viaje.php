<?php
/**
 * views/modals/cancelar-viaje.php
 * -----------------------------------------------------------------------------
 * MODAL DE CANCELACIÓN DE VIAJE
 * -----------------------------------------------------------------------------
 * Requisitos de negocio que implementa:
 *   1. Si el viaje AÚN NO SALE  -> la anotación es OBLIGATORIA (>= 15 caracteres)
 *      y se envía a todos los pasajeros con reserva activa.
 *   2. Si el viaje YA SALIÓ      -> basta el motivo; la anotación queda archivada.
 *   3. Siempre se muestra cuántos pasajeros serán notificados ANTES de confirmar,
 *      para que la decisión sea consciente.
 *
 * Variables antes del include:
 *   $viajeCancelar  array  fila del viaje (id_via, nom_rut, fec_via, hor_sal_via…)
 *   $numPasajeros   int
 * -----------------------------------------------------------------------------
 */
$__viaje  = $viajeCancelar ?? [];
$__id     = (int)($__viaje['id_via'] ?? 0);
$__pasj   = (int)($numPasajeros ?? 0);

$__instante = Fecha::instanteSalida($__viaje['fec_via'] ?? null, $__viaje['hor_sal_via'] ?? null);
$__yaSalio  = $__instante !== null && strtotime($__instante) <= time();

$__trayecto = trim(($__viaje['nom_rut'] ?? 'Ruta sin nombre')
    . (!empty($__viaje['ori_rut']) ? ' (' . $__viaje['ori_rut'] . ' → ' . ($__viaje['des_rut'] ?? '?') . ')' : ''));
?>
<div class="sget-modal-wrap sget-confirm" id="modalCancelarViaje"
     data-sget-capa
     data-titulo="Cancelar viaje"
     data-viaje="<?= $__id ?>"
     data-ya-salio="<?= $__yaSalio ? '1' : '0' ?>"
     data-pasajeros="<?= $__pasj ?>">

    <div class="sget-overlay"></div>

    <form class="sget-modal" data-sget-panel novalidate>
        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo">
                    <span class="sget-modal__icono sget-modal__icono--peligro"><i class="fas fa-ban"></i></span>
                    <span>Cancelar viaje #<?= $__id ?></span>
                </h2>
                <p class="sget-modal__sub" data-sget-texto="trayecto"><?= htmlspecialchars($__trayecto, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="modulo" value="viaje">
            <input type="hidden" name="accion" value="cancelar">
            <input type="hidden" name="id" value="<?= $__id ?>">

            <!-- Estado actual del viaje -->
            <div class="sget-card" style="padding:1rem;background:var(--sget-superficie-2)">
                <div class="sget-form-2col" style="gap:.75rem">
                    <div>
                        <p class="sget-label">Salida programada</p>
                        <p class="sget-mono" style="margin-top:.25rem"><?= htmlspecialchars(Fecha::legible($__instante), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div>
                        <p class="sget-label">Conductor / Unidad</p>
                        <p style="margin-top:.25rem;font-size:.8125rem">
                            <?= htmlspecialchars((string)($__viaje['conductor'] ?? 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?>
                            ·
                            <?= htmlspecialchars((string)($__viaje['pla_veh'] ?? 'Sin placa'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Aviso de impacto -->
            <div class="sget-impacto">
                <span class="sget-impacto__chip">
                    <i class="fas fa-users"></i>
                    <span data-sget-texto="numPasajeros"><?= $__pasj ?></span> pasajero(s)
                </span>
                <span data-mensaje-impacto>
                    <?= $__yaSalio
                        ? 'serán notificados del cierre de esta salida.'
                        : 'serán notificados automáticamente de la cancelación.' ?>
                </span>
            </div>

            <!-- MOTIVO (obligatorio) -->
            <div class="sget-field" data-campo="motivo" style="margin-top:1.25rem">
                <label class="sget-label" for="cancelar_motivo">
                    <i class="fas fa-list-check"></i> Motivo de la cancelación <span class="sget-label__req">*</span>
                </label>
                <select id="cancelar_motivo" name="motivo" class="sget-select" required data-sget-autofocus>
                    <option value="">Selecciona un motivo…</option>
                    <?php foreach (ViajeService::motivosCancelacion() as $valor => $etiqueta): ?>
                        <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <!-- ANOTACIÓN OBLIGATORIA si aún no sale -->
            <div class="sget-confirm__caja" data-sget-caja-anotacion <?= $__yaSalio ? 'hidden' : '' ?>>
                <div class="sget-confirm__titulo-caja">
                    <i class="fas fa-envelope-open-text"></i>
                    <?= $__yaSalio ? 'Anotación para el archivo (opcional)' : 'Anotación para los pasajeros (obligatoria)' ?>
                    <span class="sget-confirm__contador" data-contador>0/<?= Config::MIN_ANOTACION_CANCELACION ?></span>
                </div>
                <textarea class="sget-textarea" rows="4" maxlength="500"
                          name="anotacion"
                          data-sget-campo="anotacion_cancelacion"
                          data-anotacion
                          placeholder="Ej.: El vehículo presentó una falla en el motor y no puede cumplir la salida programada. Se reprograma la salida para las 14:00 desde el mismo punto de encuentro."></textarea>
                <p class="sget-help" style="margin-top:.5rem" data-texto-anotacion>
                    <?= $__yaSalio
                        ? 'Este viaje ya salió; la anotación se archiva con el cierre para trazabilidad.'
                        : 'El viaje aún no sale, por lo que SGET <strong>exige</strong> esta anotación y la envía literalmente a cada pasajero reservado.' ?>
                </p>
                <div class="sget-error" data-error-anotacion style="margin-top:.5rem">
                    <i class="fas fa-circle-exclamation"></i><span></span>
                </div>
            </div>

            <label class="sget-check" style="margin-top:1rem">
                <input type="checkbox" name="confirmo" id="cancelar_confirmo" required>
                <span>
                    Entiendo que esta acción <strong>cancela las reservas activas</strong> de los pasajeros
                    y que la operación queda registrada en la auditoría del sistema.
                </span>
            </label>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Volver</button>
            <button type="submit" class="sget-btn sget-btn--peligro">
                <i class="fas fa-ban"></i> <span>Cancelar viaje y notificar</span>
            </button>
        </footer>
    </form>
</div>

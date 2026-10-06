<?php
/**
 * views/modals/vehiculo.php
 * -----------------------------------------------------------------------------
 * MODAL DE FLOTA · Crear / Editar
 * -----------------------------------------------------------------------------
 * Antes era un panel lateral (drawer). Ahora es un modal centrado, coherente
 * con el resto del sistema y mucho más rápido de abrir y cerrar.
 *
 * Variables opcionales:  $vehiculoModal  array|null
 * -----------------------------------------------------------------------------
 */
$__veh      = $vehiculoModal ?? [];
$__edicion  = !empty($__veh['id_veh']);
$v = fn(string $k, $def = '') => htmlspecialchars((string)($__veh[$k] ?? $def), ENT_QUOTES, 'UTF-8');
?>
<div class="sget-modal-wrap" id="modalVehiculo" data-sget-capa data-titulo="<?= $__edicion ? 'Editar vehículo' : 'Registrar vehículo' ?>">
    <div class="sget-overlay"></div>

    <form class="sget-modal sget-modal--sm" data-sget-panel novalidate
          data-sget-form data-sget-cerrar-al-guardar="modalVehiculo"
          role="dialog" aria-modal="true" aria-labelledby="tituloModalVehiculo">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloModalVehiculo">
                    <span class="sget-modal__icono"><i class="fas fa-bus"></i></span>
                    <span data-sget-texto="titulo"><?= $__edicion ? 'Editar Vehículo #' . (int)$__veh['id_veh'] : 'Registrar Vehículo' ?></span>
                </h2>
                <p class="sget-modal__sub">Unidad de transporte, capacidad y disponibilidad.</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="modulo" value="vehiculo">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_veh" data-sget-campo="id_veh" value="<?= $v('id_veh', '0') ?>">

            <div class="sget-field" data-campo="pla_veh">
                <label class="sget-label" for="veh_placa">
                    <i class="fas fa-id-card"></i> Placa <span class="sget-label__req">*</span>
                </label>
                <input type="text" id="veh_placa" name="pla_veh" class="sget-input sget-input--mono"
                       maxlength="10" required data-sget-autofocus
                       placeholder="ABC123" style="text-transform:uppercase"
                       value="<?= $v('pla_veh') ?>">
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <div class="sget-field" data-campo="mode_veh" style="margin-top:1rem">
                <label class="sget-label" for="veh_modelo">
                    <i class="fas fa-tag"></i> Línea / Modelo <span class="sget-label__req">*</span>
                </label>
                <input type="text" id="veh_modelo" name="mode_veh" class="sget-input" maxlength="50" required
                       placeholder="Ej.: Chevrolet N300" value="<?= $v('mode_veh') ?>">
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <div class="sget-form-2col" style="margin-top:1rem">
                <div class="sget-field" data-campo="cap_veh">
                    <label class="sget-label" for="veh_cap">
                        <i class="fas fa-layer-group"></i> Capacidad <span class="sget-label__req">*</span>
                    </label>
                    <input type="number" id="veh_cap" name="cap_veh" class="sget-input sget-input--mono"
                           min="1" max="120" required placeholder="0" value="<?= $v('cap_veh') ?>">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <div class="sget-field" data-campo="est_veh">
                    <label class="sget-label" for="veh_estado">
                        <i class="fas fa-toggle-on"></i> Estado operativo
                    </label>
                    <?php $__estadoVeh = VehiculoService::normalizar((string)($__veh['est_veh'] ?? Config::VEH_DISPONIBLE)); ?>
                    <select id="veh_estado" name="est_veh" class="sget-select" data-sget-estado-veh>
                        <?php foreach ([Config::VEH_DISPONIBLE, Config::VEH_MANTENIMIENTO, Config::VEH_FUERA_SERVICIO] as $__opcion): ?>
                            <option value="<?= htmlspecialchars($__opcion, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $__estadoVeh === $__opcion ? 'selected' : '' ?>>
                                <?= htmlspecialchars($__opcion, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="sget-help"><?= VehiculoService::ayudaEstado($__estadoVeh) ?></span>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>
            </div>

            <p class="sget-help" style="margin-top:1rem">
                <i class="fas fa-circle-info"></i>
                Al asignar la unidad a un viaje su estado pasa a
                <strong>Asignado</strong> y vuelve a <strong>Disponible</strong>
                al finalizar o cancelar el viaje. El estado
                <strong>Asignado</strong> no se elige a mano: lo aplica el sistema.
            </p>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
            <button type="submit" class="sget-btn sget-btn--primario">
                <i class="fas fa-floppy-disk"></i> <span>Guardar Vehículo</span>
            </button>
        </footer>
    </form>
</div>

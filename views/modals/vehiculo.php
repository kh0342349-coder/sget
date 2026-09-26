<?php
/**
 * views/modals/vehiculo.php
 * ------------------------------------------------------------------------------
 * MODAL DE FLOTA · Crear / Editar
 * ------------------------------------------------------------------------------
 * Variables opcionales:  $vehiculoModal  array|null
 * ------------------------------------------------------------------------------
 */
$__veh   = $vehiculoModal ?? [];
$__edicion = !empty($__veh['id_veh']);
$v = fn(string $k, $def = '') => htmlspecialchars((string)($__veh[$k] ?? $def), ENT_QUOTES, 'UTF-8');
?>
<div class="sget-drawer-wrap" id="modalVehiculo" data-sget-capa data-titulo="<?= $__edicion ? 'Editar vehículo' : 'Registrar vehículo' ?>">
    <div class="sget-overlay"></div>
    <aside class="sget-drawer" role="dialog" aria-modal="true" aria-labelledby="tituloModalVehiculo">
        <form data-sget-panel novalidate>
            <header class="sget-drawer__head">
                <div>
                    <h2 class="sget-modal__titulo" id="tituloModalVehiculo">
                        <span class="sget-modal__icono"><i class="fas fa-bus"></i></span>
                        <span data-sget-texto="titulo"><?= $__edicion ? 'Editar Vehículo #' . (int)$__veh['id_veh'] : 'Registrar Vehículo' ?></span>
                    </h2>
                    <p class="sget-modal__sub">Unidad de transporte, capacidad y disponibilidad operativa.</p>
                </div>
                <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </header>

            <div class="sget-drawer__body sget-scroll">
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
                        <select id="veh_estado" name="est_veh" class="sget-select">
                            <option value="<?= Config::VEH_DISPONIBLE ?>"     <?= (int)($__veh['est_veh'] ?? 1) === Config::VEH_DISPONIBLE ? 'selected' : '' ?>>Disponible</option>
                            <option value="<?= Config::VEH_FUERA_SERVICIO ?>" <?= (int)($__veh['est_veh'] ?? 1) === Config::VEH_FUERA_SERVICIO ? 'selected' : '' ?>>Fuera de servicio</option>
                        </select>
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>
                </div>

                <p class="sget-help" style="margin-top:1rem">
                    <i class="fas fa-circle-info"></i>
                    Al asignar la unidad a un viaje su estado cambia automáticamente a
                    <strong>Fuera de servicio</strong>, y vuelve a <strong>Disponible</strong> al finalizar o cancelar el viaje.
                </p>
            </div>

            <footer class="sget-drawer__foot">
                <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
                <button type="submit" class="sget-btn sget-btn--primario">
                    <i class="fas fa-floppy-disk"></i> <span>Guardar Vehículo</span>
                </button>
            </footer>
        </form>
    </aside>
</div>

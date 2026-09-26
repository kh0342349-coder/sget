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
<div class="sget-drawer-wrap" id="modalViaje" data-sget-capa data-titulo="<?= $__edicion ? 'Editar viaje' : 'Programar viaje' ?>">
    <div class="sget-overlay"></div>
    <aside class="sget-drawer sget-drawer--ancho" role="dialog" aria-modal="true" aria-labelledby="tituloModalViaje">
        <form data-sget-panel novalidate>

            <header class="sget-drawer__head">
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

            <div class="sget-drawer__body sget-scroll">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="modulo" value="viaje">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id_via" data-sget-campo="id_via" value="<?= $v('id_via', '0') ?>">

                <!-- Ruta + tarifa automática -->
                <div class="sget-field" data-campo="id_rut_via">
                    <label class="sget-label" for="viaje_ruta">
                        <i class="fas fa-route"></i> Ruta programada <span class="sget-label__req">*</span>
                    </label>
                    <select id="viaje_ruta" name="id_rut_via" class="sget-select" required data-sget-autofocus>
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
                        <select id="viaje_conductor" name="id_usu_via" class="sget-select" required>
                            <option value="">Selecciona un conductor…</option>
                            <?php foreach ($__conductores as $c): ?>
                                <option value="<?= (int)$c['id_usu'] ?>" <?= (string)(int)($__viaje['id_usu_via'] ?? '') === (string)(int)$c['id_usu'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nom_usu'], ENT_QUOTES, 'UTF-8') ?>
                                    <?= $c['tel_usu'] ? ' · ' . htmlspecialchars($c['tel_usu'], ENT_QUOTES, 'UTF-8') : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>

                    <div class="sget-field" data-campo="id_veh">
                        <label class="sget-label" for="viaje_veh">
                            <i class="fas fa-bus-simple"></i> Vehículo <span class="sget-label__req">*</span>
                        </label>
                        <select id="viaje_veh" name="id_veh" class="sget-select" required>
                            <option value="">Selecciona una placa…</option>
                            <?php foreach ($__vehiculos as $ve): ?>
                                <option value="<?= (int)$ve['id_veh'] ?>" <?= (string)(int)($__viaje['id_veh'] ?? '') === (string)(int)$ve['id_veh'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ve['pla_veh'], ENT_QUOTES, 'UTF-8') ?>
                                    · <?= htmlspecialchars($ve['mode_veh'], ENT_QUOTES, 'UTF-8') ?>
                                    · <?= (int)$ve['cap_veh'] ?> puestos
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>
                </div>

                <!-- FECHA Y HORA DE SALIDA  (corregido: date + time, nunca DATETIME con ceros) -->
                <div class="sget-form-2col" style="margin-top:1rem">
                    <div class="sget-field" data-campo="fec_via">
                        <label class="sget-label" for="viaje_fecha">
                            <i class="fas fa-calendar-day"></i> Fecha de salida <span class="sget-label__req">*</span>
                        </label>
                        <input type="date" id="viaje_fecha" name="fec_via" class="sget-input" required
                               min="<?= date('Y-m-d') ?>"
                               value="<?= $v('fec_via') ? htmlspecialchars(Fecha::soloFecha($__viaje['fec_via'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>

                    <div class="sget-field" data-campo="hor_sal_via">
                        <label class="sget-label" for="viaje_hora">
                            <i class="fas fa-clock"></i> Hora de salida <span class="sget-label__req">*</span>
                        </label>
                        <input type="time" id="viaje_hora" name="hor_sal_via" class="sget-input sget-input--mono" required
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
                        <input type="time" id="viaje_llegada" name="hor_lleg_via" class="sget-input sget-input--mono"
                               value="<?= $v('hor_lleg_via') ? htmlspecialchars(Fecha::soloHora($__viaje['hor_lleg_via'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?>">
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>

                    <div class="sget-field" data-campo="val_via">
                        <label class="sget-label" for="viaje_tarifa">Tarifa del pasaje <span class="sget-label__req">*</span></label>
                        <input type="number" id="viaje_tarifa" name="val_via" class="sget-input sget-input--mono"
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

            <footer class="sget-drawer__foot">
                <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
                <button type="submit" class="sget-btn sget-btn--primario">
                    <i class="fas fa-floppy-disk"></i> <span>Guardar Viaje</span>
                </button>
            </footer>
        </form>
    </aside>
</div>

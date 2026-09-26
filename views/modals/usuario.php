<?php
/**
 * views/modals/usuario.php
 * ------------------------------------------------------------------------------
 * MODAL DE USUARIOS · Crear / Editar
 * ------------------------------------------------------------------------------
 * Variables opcionales:  $usuarioModal  array|null
 * ------------------------------------------------------------------------------
 */
$__usr   = $usuarioModal ?? [];
$__edicion = !empty($__usr['id_usu']);
$v = fn(string $k, $def = '') => htmlspecialchars((string)($__usr[$k] ?? $def), ENT_QUOTES, 'UTF-8');
?>
<div class="sget-drawer-wrap" id="modalUsuario" data-sget-capa data-titulo="<?= $__edicion ? 'Editar usuario' : 'Nuevo usuario' ?>">
    <div class="sget-overlay"></div>
    <aside class="sget-drawer" role="dialog" aria-modal="true" aria-labelledby="tituloModalUsuario">
        <form data-sget-panel novalidate>
            <header class="sget-drawer__head">
                <div>
                    <h2 class="sget-modal__titulo" id="tituloModalUsuario">
                        <span class="sget-modal__icono"><i class="fas fa-user-gear"></i></span>
                        <span data-sget-texto="titulo"><?= $__edicion ? 'Editar: ' . $__usr['nom_usu'] : 'Registrar Nuevo Usuario' ?></span>
                    </h2>
                    <p class="sget-modal__sub">Datos de identificación, contacto, rol y estado de la cuenta.</p>
                </div>
                <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </header>

            <div class="sget-drawer__body sget-scroll">
                <?= Auth::campoToken() ?>
                <input type="hidden" name="modulo" value="usuario">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id_usu" data-sget-campo="id_usu" value="<?= $v('id_usu', '0') ?>">

                <div class="sget-form-2col">
                    <div class="sget-field" data-campo="tip_doc_usu">
                        <label class="sget-label" for="usr_tipdoc">
                            <i class="fas fa-address-card"></i> Tipo documento <span class="sget-label__req">*</span>
                        </label>
                        <select id="usr_tipdoc" name="tip_doc_usu" class="sget-select" required data-sget-autofocus>
                            <?php foreach (['CC' => 'Cédula (CC)', 'TI' => 'Tarjeta (TI)', 'CE' => 'Extranjería (CE)', 'G' => 'Registro civil (G)', 'NIT' => 'NIT'] as $val => $txt): ?>
                                <option value="<?= $val ?>" <?= (string)($__usr['tip_doc_usu'] ?? 'CC') === $val ? 'selected' : '' ?>><?= $txt ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>

                    <div class="sget-field" data-campo="num_doc_usu">
                        <label class="sget-label" for="usr_numdoc">
                            <i class="fas fa-hashtag"></i> N.º documento <span class="sget-label__req">*</span>
                        </label>
                        <input type="text" id="usr_numdoc" name="num_doc_usu" class="sget-input sget-input--mono"
                               maxlength="20" required placeholder="Ej.: 101234567"
                               value="<?= $v('num_doc_usu') ?>">
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>
                </div>

                <div class="sget-field" data-campo="nom_usu" style="margin-top:1rem">
                    <label class="sget-label" for="usr_nombre">
                        <i class="fas fa-user"></i> Nombre completo <span class="sget-label__req">*</span>
                    </label>
                    <input type="text" id="usr_nombre" name="nom_usu" class="sget-input" maxlength="100" required
                           placeholder="Ej.: Juan Pérez" value="<?= $v('nom_usu') ?>">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <div class="sget-form-2col" style="margin-top:1rem">
                    <div class="sget-field" data-campo="corre_usu">
                        <label class="sget-label" for="usr_correo">
                            <i class="fas fa-envelope"></i> Correo <span class="sget-label__req">*</span>
                        </label>
                        <input type="email" id="usr_correo" name="corre_usu" class="sget-input" maxlength="100" required
                               placeholder="correo@ejemplo.com" value="<?= $v('corre_usu') ?>">
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>

                    <div class="sget-field" data-campo="tel_usu">
                        <label class="sget-label" for="usr_tel">
                            <i class="fas fa-phone"></i> Teléfono <span class="sget-label__opt">(opc.)</span>
                        </label>
                        <input type="text" id="usr_tel" name="tel_usu" class="sget-input sget-input--mono"
                               maxlength="20" placeholder="300 000 0000" value="<?= $v('tel_usu') ?>">
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>
                </div>

                <div class="sget-form-2col" style="margin-top:1rem">
                    <div class="sget-field" data-campo="id_rol_usu">
                        <label class="sget-label" for="usr_rol">
                            <i class="fas fa-user-shield"></i> Rol <span class="sget-label__req">*</span>
                        </label>
                        <select id="usr_rol" name="id_rol_usu" class="sget-select" required>
                            <?php foreach (UsuarioService::ETIQUETA_ROL as $val => $txt): ?>
                                <option value="<?= $val ?>" <?= (int)($__usr['id_rol_usu'] ?? 3) === $val ? 'selected' : '' ?>><?= $txt ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>

                    <div class="sget-field" data-campo="estado">
                        <label class="sget-label" for="usr_estado">
                            <i class="fas fa-toggle-on"></i> Estado de la cuenta
                        </label>
                        <select id="usr_estado" name="estado" class="sget-select">
                            <option value="<?= Config::USU_ACTIVO ?>"   <?= (int)($__usr['estado'] ?? 1) === Config::USU_ACTIVO ? 'selected' : '' ?>>Activo</option>
                            <option value="<?= Config::USU_INACTIVO ?>" <?= (int)($__usr['estado'] ?? 1) === Config::USU_INACTIVO ? 'selected' : '' ?>>Inactivo (suspendido)</option>
                        </select>
                        <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    </div>
                </div>

                <div class="sget-field" data-campo="contra_usu" style="margin-top:1rem">
                    <label class="sget-label" for="usr_clave">
                        <i class="fas fa-lock"></i> Contraseña
                        <?php if ($__edicion): ?>
                            <span class="sget-label__opt">(déjala vacía para mantenerla)</span>
                        <?php else: ?>
                            <span class="sget-label__req">*</span>
                        <?php endif; ?>
                    </label>
                    <input type="password" id="usr_clave" name="contra_usu" class="sget-input" maxlength="100"
                           autocomplete="new-password" placeholder="Mínimo 6 caracteres">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <p class="sget-help" style="margin-top:1rem">
                    <i class="fas fa-shield-halved"></i>
                    Las contraseñas se guardan cifradas con <code>password_hash()</code>. Suspender una cuenta la bloquea
                    sin borrar su historial de viajes ni reservas.
                </p>
            </div>

            <footer class="sget-drawer__foot">
                <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
                <button type="submit" class="sget-btn sget-btn--primario">
                    <i class="fas fa-floppy-disk"></i> <span>Guardar Usuario</span>
                </button>
            </footer>
        </form>
    </aside>
</div>

<?php
/**
 * modal_auth.php
 * -----------------------------------------------------------------------------
 * MODALES PÚBLICOS: Iniciar sesión y Crear cuenta
 * -----------------------------------------------------------------------------
 * ANTES: el modal de login traía colores oscuros FIJOS en línea
 *        (style="background:#111827; color:#f8fafc"). Con el tema claro activo
 *        se veía un cuadro negro dentro de una página blanca: ilegible y con
 *        la impresión de estar roto.
 * AHORA: todo sale de los tokens del tema (--sget-superficie, --sget-texto…),
 *        así que el modal es claro en tema claro y oscuro en tema oscuro.
 *
 * Ambos usan el motor común (assets/js/sget-modal.js):
 *     data-sget-capa / data-sget-panel / data-sget-cerrar
 * Las funciones abrirPanel()/cerrarPanel()/cambiarAPanel() siguen existiendo
 * (delegan en el motor) para no tocar los onclick ya escritos.
 * -----------------------------------------------------------------------------
 */
?>

<!-- SDK Oficial de Google reCAPTCHA v2 -->
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<!-- ======================================================================= -->
<!-- MODAL · INICIAR SESIÓN                                                   -->
<!-- ======================================================================= -->
<div id="panelLogin"
     class="sget-modal-wrap modal-isla-container"
     data-sget-capa
     data-titulo="Iniciar sesión"
     role="dialog" aria-modal="true" aria-labelledby="tituloPanelLogin">

    <div class="sget-modal sget-modal--sm modal-isla-card" data-sget-panel>

        <header class="sget-modal__head">
            <div style="display:flex;align-items:center;gap:.875rem">
                <span class="sget-modal__icono" style="width:2.75rem;height:2.75rem;font-size:1.125rem">
                    <i class="fas fa-bus"></i>
                </span>
                <div>
                    <h2 id="tituloPanelLogin" class="sget-modal__titulo">
                        <?= $lang['mdl_bienvenido_nuevo'] ?? 'Bienvenido de nuevo' ?>
                    </h2>
                    <p class="sget-modal__sub"><?= $lang['mdl_sub_login'] ?? 'Inicia sesión en SGET' ?></p>
                </div>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?php if (!empty($_SESSION['msg'])): ?>
                <div class="sget-flash sget-flash--error" style="margin:0 0 1.25rem">
                    <i class="fas fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($_SESSION['msg'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['msg_success_login'])): ?>
                <div class="sget-flash sget-flash--exito" style="margin:0 0 1.25rem">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['msg_success_login'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php unset($_SESSION['msg_success_login']); ?>
            <?php endif; ?>

            <form action="validar.php" method="POST" class="space-y-4">
                <div class="sget-field" data-campo="documento">
                    <label class="sget-label" for="loginDocumento">
                        <?= $lang['mdl_doc'] ?? 'N° DOCUMENTO' ?><span class="sget-label__req">*</span>
                    </label>
                    <input type="text" id="loginDocumento" name="documento" placeholder="Ej: 113050" required
                           autocomplete="username" inputmode="numeric" data-sget-autofocus
                           class="sget-input sget-input--mono">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <div class="sget-field" data-campo="clave">
                    <label class="sget-label" for="loginClave">
                        <?= $lang['mdl_pass'] ?? 'CONTRASEÑA' ?><span class="sget-label__req">*</span>
                    </label>
                    <div style="position:relative">
                        <input type="password" id="loginClave" name="clave" placeholder="••••••" required
                               autocomplete="current-password"
                               class="sget-input sget-input--mono" style="padding-right:2.75rem">
                        <button type="button" data-ver-clave="#loginClave" aria-label="Mostrar contraseña"
                                style="position:absolute;top:0;right:0;height:100%;width:2.75rem;display:grid;place-items:center;color:var(--sget-texto-suave);border-radius:0 var(--sget-radio) var(--sget-radio) 0">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                    </div>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <!-- reCAPTCHA v2 (clave oficial de prueba para localhost) -->
                <div class="flex justify-center py-1">
                    <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI" data-theme="light"></div>
                </div>

                <button type="submit" class="sget-btn sget-btn--primario sget-btn--bloque sget-btn--lg">
                    <i class="fas fa-right-to-bracket"></i>
                    <?= $lang['mdl_btn_ingresar'] ?? 'INGRESAR AL SISTEMA' ?>
                </button>
            </form>

            <div class="g_id_signin flex justify-center"></div>

            <div style="padding-top:1.25rem;text-align:center">
                <p class="sget-help">
                    <?= $lang['mdl_no_cuenta'] ?? '¿No tienes cuenta?' ?>
                    <button type="button" data-sget-ir-a="panelRegistro"
                            style="color:var(--sget-azul);font-weight:800">
                        <?= $lang['mdl_registrate_aqui'] ?? 'Regístrate aquí' ?>
                    </button>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- MODAL · CREAR CUENTA                                                      -->
<!-- ======================================================================= -->
<div id="panelRegistro"
     class="sget-modal-wrap modal-isla-container"
     data-sget-capa
     data-titulo="Crear cuenta"
     role="dialog" aria-modal="true" aria-labelledby="tituloPanelRegistro">

    <div class="sget-modal sget-modal--sm modal-isla-card" data-sget-panel>

        <header class="sget-modal__head">
            <div style="display:flex;align-items:center;gap:.875rem">
                <span class="sget-modal__icono"
                      style="background:color-mix(in srgb,#10b981 14%,transparent);color:#10b981">
                    <i class="fas fa-user-plus"></i>
                </span>
                <div>
                    <h2 id="tituloPanelRegistro" class="sget-modal__titulo">
                        <?= $lang['mdl_crear_cuenta'] ?? 'Crear Cuenta' ?>
                    </h2>
                    <p class="sget-modal__sub">Regístrate con tu documento para gestionar tus viajes</p>
                </div>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">

            <?php if (!empty($_SESSION['msg_registro'])): ?>
                <div class="sget-flash sget-flash--error" style="margin:0 0 1.25rem">
                    <i class="fas fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($_SESSION['msg_registro'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php unset($_SESSION['msg_registro']); ?>
            <?php endif; ?>

            <form action="nuevo_usuario.php" method="POST" id="formRegistro" novalidate class="space-y-4">

                <!-- TIPO DE DOCUMENTO -->
                <div class="sget-field" data-campo="tipo_doc">
                    <label class="sget-label" for="reg_tipo_doc">
                        <?= $lang['mdl_tipo_doc'] ?? 'Tipo de Documento' ?><span class="sget-label__req">*</span>
                    </label>
                    <select id="reg_tipo_doc" name="tipo_doc" required data-sget-autofocus class="sget-select">
                        <option value="" disabled selected>Selecciona un tipo</option>
                        <option value="CC">Cédula de Ciudadanía (CC)</option>
                        <option value="TI">Tarjeta de Identidad (TI)</option>
                        <option value="CE">Cédula de Extranjería (CE)</option>
                        <option value="PP">Pasaporte (PP)</option>
                    </select>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <!-- NÚMERO DE DOCUMENTO -->
                <div class="sget-field" data-campo="num_doc">
                    <label class="sget-label" for="reg_documento">
                        <?= $lang['mdl_doc'] ?? 'Número de Documento' ?>
                        <span class="sget-label__opt" style="color:#059669;font-weight:800">(Tu ID de ingreso)</span>
                    </label>
                    <input type="text" id="reg_documento" name="documento" required
                           placeholder="Ej: 1007123456" autocomplete="username" inputmode="numeric"
                           class="sget-input sget-input--mono">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <!-- NOMBRE -->
                <div class="sget-field" data-campo="nom_usu">
                    <label class="sget-label" for="reg_nombre">
                        <?= $lang['mdl_nombre_completo'] ?? 'Nombre Completo' ?><span class="sget-label__req">*</span>
                    </label>
                    <input type="text" id="reg_nombre" name="nom_usu" required
                           placeholder="Tu nombre y apellido" autocomplete="name" maxlength="100"
                           class="sget-input">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <!-- CORREO -->
                <div class="sget-field" data-campo="corre_usu">
                    <label class="sget-label" for="reg_correo">
                        <?= $lang['mdl_correo'] ?? 'Correo Electrónico' ?><span class="sget-label__req">*</span>
                    </label>
                    <input type="email" id="reg_correo" name="corre_usu" required
                           placeholder="correo@ejemplo.com" autocomplete="email" maxlength="100"
                           class="sget-input">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <!-- CONTRASEÑA -->
                <div class="sget-field" data-campo="clave_usu">
                    <label class="sget-label" for="reg_pass">
                        <?= $lang['mdl_pass'] ?? 'Contraseña' ?><span class="sget-label__req">*</span>
                    </label>
                    <div style="position:relative">
                        <input type="password" id="reg_pass" name="clave_usu" required
                               placeholder="Mínimo 6 caracteres" autocomplete="new-password" minlength="6"
                               class="sget-input" style="padding-right:2.75rem">
                        <button type="button" data-ver-clave="#reg_pass" aria-label="Mostrar contraseña"
                                style="position:absolute;top:0;right:0;height:100%;width:2.75rem;display:grid;place-items:center;color:var(--sget-texto-suave);border-radius:0 var(--sget-radio) var(--sget-radio) 0">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                    </div>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <!-- CONFIRMAR -->
                <div class="sget-field" data-campo="confirmar_clave">
                    <label class="sget-label" for="reg_pass_confirm">
                        <?= $lang['mdl_confirm_pass'] ?? 'Confirmar Contraseña' ?><span class="sget-label__req">*</span>
                    </label>
                    <input type="password" id="reg_pass_confirm" name="confirmar_clave" required
                           placeholder="Repite tu contraseña" autocomplete="new-password" minlength="6"
                           class="sget-input">
                    <span class="sget-error" id="error_pass_match">
                        <i class="fas fa-circle-exclamation"></i><span>Las contraseñas no coinciden.</span>
                    </span>
                </div>

                <!-- POLÍTICA DE DATOS -->
                <label class="sget-check" for="acepta_politica" style="padding-top:.5rem">
                    <input type="checkbox" id="acepta_politica" name="acepta_politica" value="1" required>
                    <span>
                        <?= $lang['mdl_acepto_politica'] ?? 'Acepto la' ?>
                        <button type="button" data-sget-ir-a="panelPolitica"
                                style="color:#059669;font-weight:800">
                            <?= $lang['mdl_tratamiento_datos'] ?? 'Política de Tratamiento de Datos Personales' ?>
                        </button> (Ley 1581 de 2012).
                    </span>
                </label>
                <span class="sget-error" data-campo="acepta_politica">
                    <i class="fas fa-circle-exclamation"></i><span>Debes aceptar la política para registrarte.</span>
                </span>

                <button type="submit" class="sget-btn sget-btn--bloque sget-btn--lg"
                        style="background:linear-gradient(135deg,#10b981,#059669);color:#04110b">
                    <i class="fas fa-user-plus"></i>
                    <?= $lang['mdl_btn_registrarse'] ?? 'Registrarse' ?>
                </button>
            </form>

            <div style="margin-top:1.5rem;padding-top:1.25rem;text-align:center" class="sget-help">
                <?= $lang['mdl_ya_cuenta'] ?? '¿Ya tienes una cuenta?' ?>
                <button type="button" data-sget-ir-a="panelLogin"
                        style="color:#059669;font-weight:800">
                    <?= $lang['mdl_inicia_aqui'] ?? 'Inicia sesión' ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/* ==========================================================================
   Validación del formulario de registro.
   Usa el sistema de errores del motor común (data-campo → .sget-error).
   ========================================================================== */
function validarRegistro(event) {
    var pass    = document.getElementById('reg_pass');
    var confirm = document.getElementById('reg_pass_confirm');
    var form    = document.getElementById('formRegistro');
    var errores = {};

    if (pass.value && pass.value.length < 6) {
        errores.clave_usu = 'La contraseña debe tener al menos 6 caracteres.';
    }
    if (pass.value !== confirm.value) {
        errores.confirmar_clave = 'Las contraseñas no coinciden.';
    }
    if (!document.getElementById('acepta_politica').checked) {
        errores.acepta_politica = 'Debes aceptar la política de tratamiento de datos para registrarte.';
    }

    if (Object.keys(errores).length) {
        event.preventDefault();
        SGETModal.errores(errores, form);
        SGETModal.toast('Revisa los campos marcados en rojo.', 'error');
        return false;
    }

    var boton = form.querySelector('button[type="submit"]');
    if (boton) {
        boton.disabled = true;
        boton.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Creando cuenta...';
    }
    return true;
}

/* Mostrar / ocultar contraseña */
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-ver-clave]');
    if (!btn) return;
    e.preventDefault();
    var input = document.querySelector(btn.dataset.verClave);
    if (!input) return;
    var visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    btn.innerHTML = '<i class="fas fa-' + (visible ? 'eye' : 'eye-slash') + ' text-xs"></i>';
    btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
});

/* El reCAPTCHA debe seguir al tema, o se ve blanco sobre un modal oscuro. */
function pintarRecaptcha() {
    document.querySelectorAll('.g-recaptcha').forEach(function (el) {
        el.setAttribute('data-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', pintarRecaptcha);
} else {
    pintarRecaptcha();
}
document.addEventListener('sget:tema', pintarRecaptcha);
</script>

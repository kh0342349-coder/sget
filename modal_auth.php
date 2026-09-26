<?php
/**
 * modal_auth.php
 * -----------------------------------------------------------------------------
 * MODALES PÚBLICOS: Iniciar sesión y Crear cuenta.
 * -----------------------------------------------------------------------------
 * REFACTOR: antes estos modales tenían su propio sistema (clases `hidden`,
 * `opacity-0`, `scale-95` manipuladas a mano desde `abrirPanel`/`cerrarPanel`
 * en index.php). Eso provocaba dos fallos:
 *   - el `display` del contenedor dependía de utilidades de Tailwind que
 *     cualquier hoja de estilos nueva podía pisear;
 *   - el foco, el bloqueo de scroll, el cierre con Escape y el `role="dialog"`
 *     no existían, así que no eran accesibles ni usable con teclado.
 *
 * Ahora usan el MISMO motor que el panel interno (assets/js/sget-modal.js):
 *     data-sget-capa    → identifica la capa
 *     data-sget-panel   → el cuadro interior (recibe la animación de entrada)
 *     data-sget-cerrar  → botón de cierre
 * Las funciones `abrirPanel()` / `cerrarPanel()` / `cambiarAPanel()` siguen
 * existiendo (delegan en el motor) para no tocar los `onclick` ya escritos.
 * -----------------------------------------------------------------------------
 */
?>

<!-- SDK Oficial de Google reCAPTCHA v2 -->
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<!-- ======================================================================= -->
<!-- MODAL · INICIAR SESIÓN                                                   -->
<!-- ======================================================================= -->
<div id="panelLogin"
     class="sget-modal-wrap fixed inset-0 z-50 p-4 modal-isla-container"
     data-sget-capa
     data-titulo="Iniciar sesión"
     role="dialog" aria-modal="true" aria-labelledby="tituloPanelLogin">

    <div class="sget-modal sget-modal--sm modal-isla-card"
         data-sget-panel
         style="background:#111827;border-color:rgba(255,255,255,.10);color:#f8fafc">

        <button type="button" data-sget-cerrar aria-label="Cerrar"
                class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-white/5 hover:bg-white/10
                       text-slate-400 hover:text-white flex items-center justify-center transition-colors cursor-pointer">
            <i class="fas fa-times text-sm"></i>
        </button>

        <div class="flex flex-col space-y-6">
            <div>
                <h3 id="tituloPanelLogin" class="text-2xl font-black tracking-tight" style="color:#fff">
                    <?= $lang['mdl_bienvenido_nuevo'] ?? 'Bienvenido de nuevo' ?>
                </h3>
                <p class="text-xs mt-1 font-medium text-slate-400"><?= $lang['mdl_sub_login'] ?? 'Inicia sesión en SGET' ?></p>
            </div>

            <!-- Mensaje de error (si viene de la sesión) -->
            <?php if (!empty($_SESSION['msg'])): ?>
                <div class="sget-flash sget-flash--error" style="margin:0">
                    <i class="fas fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($_SESSION['msg'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <form action="validar.php" method="POST" class="space-y-4">
                <div class="sget-field">
                    <label class="sget-label" style="color:#cbd5e1" for="loginDocumento">
                        <?= $lang['mdl_doc'] ?? 'N° DOCUMENTO' ?>
                    </label>
                    <input type="text" id="loginDocumento" name="documento" placeholder="Ej: 113050" required
                           autocomplete="username" data-sget-autofocus
                           class="w-full px-4 py-2.5 rounded-xl border text-xs font-semibold text-white
                                  focus:outline-none focus:ring-2 focus:ring-sky-500 transition-all"
                           style="background:#1f293d;border-color:rgba(255,255,255,.10)">
                </div>

                <div class="sget-field">
                    <label class="sget-label" style="color:#cbd5e1" for="loginClave">
                        <?= $lang['mdl_pass'] ?? 'CONTRASEÑA' ?>
                    </label>
                    <div style="position:relative">
                        <input type="password" id="loginClave" name="clave" placeholder="••••••" required
                               autocomplete="current-password"
                               class="w-full px-4 py-2.5 rounded-xl border text-xs font-semibold text-white
                                      focus:outline-none focus:ring-2 focus:ring-sky-500 transition-all pr-11"
                               style="background:#1f293d;border-color:rgba(255,255,255,.10)">
                        <button type="button" data-ver-clave="#loginClave" aria-label="Mostrar contraseña"
                                class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-400
                                       hover:text-sky-400 transition-colors cursor-pointer">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- reCAPTCHA v2 (clave oficial de prueba para localhost) -->
                <div class="flex justify-center overflow-hidden py-1">
                    <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI" data-theme="dark"></div>
                </div>

                <button type="submit" class="sget-btn sget-btn--primario sget-btn--bloque" style="margin-top:.5rem">
                    <?= $lang['mdl_btn_ingresar'] ?? 'INGRESAR AL SISTEMA' ?>
                </button>
            </form>

            <div class="g_id_signin flex justify-center"></div>

            <div class="pt-4 border-t text-center" style="border-color:rgba(255,255,255,.10)">
                <p class="text-xs text-slate-400 font-medium">
                    <?= $lang['mdl_no_cuenta'] ?? '¿No tienes cuenta?' ?>
                    <button type="button" data-sget-ir-a="panelRegistro"
                            class="text-sky-400 font-bold hover:underline ml-1 cursor-pointer">
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
     class="sget-modal-wrap fixed inset-0 z-50 p-4 modal-isla-container"
     data-sget-capa
     data-titulo="Crear cuenta"
     role="dialog" aria-modal="true" aria-labelledby="tituloPanelRegistro">

    <div class="sget-modal sget-modal--sm modal-isla-card sget-scroll"
         data-sget-panel
         style="max-height:92dvh;overflow-y:auto">

        <button type="button" data-sget-cerrar aria-label="Cerrar"
                class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 dark:bg-white/5
                       text-slate-400 hover:text-slate-600 dark:hover:text-white
                       flex items-center justify-center transition-colors cursor-pointer">
            <i class="fas fa-times text-sm"></i>
        </button>

        <div class="text-center space-y-2 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-500
                        flex items-center justify-center mx-auto text-xl font-bold">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3 id="tituloPanelRegistro" class="text-xl font-black text-slate-900 dark:text-white">
                <?= $lang['mdl_crear_cuenta'] ?? 'Crear Cuenta' ?>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                Regístrate para ingresar con tu documento y gestionar tus viajes en SGET
            </p>
        </div>

        <?php if (!empty($_SESSION['msg_registro'])): ?>
            <div class="sget-flash sget-flash--error" style="margin:0 0 1rem">
                <i class="fas fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($_SESSION['msg_registro'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php unset($_SESSION['msg_registro']); ?>
        <?php endif; ?>

        <form action="nuevo_usuario.php" method="POST" id="formRegistro" novalidate class="space-y-4">

            <!-- TIPO DE DOCUMENTO -->
            <div class="sget-field">
                <label class="sget-label" for="reg_tipo_doc"><?= $lang['mdl_tipo_doc'] ?? 'Tipo de Documento' ?></label>
                <div style="position:relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                        <i class="fas fa-id-card text-xs"></i>
                    </span>
                    <select id="reg_tipo_doc" name="tipo_doc" required data-sget-autofocus
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border text-xs
                                   focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all cursor-pointer"
                            style="background:var(--sget-superficie-2);border-color:var(--sget-borde);color:var(--sget-texto)">
                        <option value="" disabled selected>Selecciona un tipo</option>
                        <option value="CC">Cédula de Ciudadanía (CC)</option>
                        <option value="TI">Tarjeta de Identidad (TI)</option>
                        <option value="CE">Cédula de Extranjería (CE)</option>
                        <option value="PP">Pasaporte (PP)</option>
                    </select>
                </div>
            </div>

            <!-- NÚMERO DE DOCUMENTO -->
            <div class="sget-field" data-campo="num_doc">
                <label class="sget-label" for="reg_documento">
                    <?= $lang['mdl_doc'] ?? 'Número de Documento' ?>
                    <span class="sget-label__opt" style="color:#059669;font-weight:800">(Tu ID de Ingreso)</span>
                </label>
                <div style="position:relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                        <i class="fas fa-hashtag text-xs"></i>
                    </span>
                    <input type="text" id="reg_documento" name="documento" required placeholder="Ej: 1007123456"
                           autocomplete="username" inputmode="numeric"
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border text-xs
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                </div>
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <!-- NOMBRE COMPLETO -->
            <div class="sget-field" data-campo="nom_usu">
                <label class="sget-label" for="reg_nombre"><?= $lang['mdl_nombre_completo'] ?? 'Nombre Completo' ?></label>
                <div style="position:relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                        <i class="fas fa-user text-xs"></i>
                    </span>
                    <input type="text" id="reg_nombre" name="nom_usu" required placeholder="Tu nombre y apellido"
                           autocomplete="name" maxlength="100"
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border text-xs
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                </div>
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <!-- CORREO -->
            <div class="sget-field" data-campo="corre_usu">
                <label class="sget-label" for="reg_correo"><?= $lang['mdl_correo'] ?? 'Correo Electrónico' ?></label>
                <div style="position:relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                        <i class="fas fa-envelope text-xs"></i>
                    </span>
                    <input type="email" id="reg_correo" name="corre_usu" required placeholder="correo@ejemplo.com"
                           autocomplete="email" maxlength="100"
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border text-xs
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                </div>
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <!-- CONTRASEÑA -->
            <div class="sget-field" data-campo="clave_usu">
                <label class="sget-label" for="reg_pass"><?= $lang['mdl_pass'] ?? 'Contraseña' ?></label>
                <div style="position:relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                        <i class="fas fa-lock text-xs"></i>
                    </span>
                    <input type="password" id="reg_pass" name="clave_usu" required placeholder="Mínimo 6 caracteres"
                           autocomplete="new-password" minlength="6"
                           class="w-full pl-10 pr-11 py-2.5 rounded-xl border text-xs
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                    <button type="button" data-ver-clave="#reg_pass" aria-label="Mostrar contraseña"
                            class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-400
                                   hover:text-emerald-500 transition-colors cursor-pointer">
                        <i class="fas fa-eye text-xs"></i>
                    </button>
                </div>
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <!-- CONFIRMAR CONTRASEÑA -->
            <div class="sget-field" data-campo="confirmar_clave">
                <label class="sget-label" for="reg_pass_confirm"><?= $lang['mdl_confirm_pass'] ?? 'Confirmar Contraseña' ?></label>
                <div style="position:relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                        <i class="fas fa-shield-alt text-xs"></i>
                    </span>
                    <input type="password" id="reg_pass_confirm" name="confirmar_clave" required
                           placeholder="Repite tu contraseña" autocomplete="new-password" minlength="6"
                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border text-xs
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                </div>
                <span class="sget-error" id="error_pass_match">
                    <i class="fas fa-circle-exclamation"></i><span>Las contraseñas no coinciden.</span>
                </span>
            </div>

            <!-- POLÍTICA DE DATOS -->
            <label class="sget-check" for="acepta_politica">
                <input type="checkbox" id="acepta_politica" name="acepta_politica" value="1" required>
                <span>
                    <?= $lang['mdl_acepto_politica'] ?? 'Acepto la' ?>
                    <button type="button" data-sget-ir-a="panelPolitica"
                            class="text-emerald-500 font-bold hover:underline cursor-pointer">
                        <?= $lang['mdl_tratamiento_datos'] ?? 'Política de Tratamiento de Datos Personales' ?>
                    </button> (Ley 1581 de 2012).
                </span>
            </label>

            <button type="submit" class="sget-btn sget-btn--bloque" style="background:linear-gradient(135deg,#10b981,#059669);color:#04110b">
                <?= $lang['mdl_btn_registrarse'] ?? 'Registrarse' ?>
            </button>
        </form>

        <div class="mt-6 pt-4 border-t text-center text-xs text-slate-500" style="border-color:var(--sget-borde)">
            <?= $lang['mdl_ya_cuenta'] ?? '¿Ya tienes una cuenta?' ?>
            <button type="button" data-sget-ir-a="panelLogin"
                    class="text-emerald-500 font-bold hover:underline cursor-pointer">
                <?= $lang['mdl_inicia_aqui'] ?? 'Inicia sesión' ?>
            </button>
        </div>
    </div>
</div>

<script>
/* ==========================================================================
   Validación del formulario de registro (antes: `hidden` sobre el mensaje de
   error; ahora usa el sistema de errores del motor común).
   ========================================================================== */
function validarRegistro(event) {
    var pass    = document.getElementById('reg_pass');
    var confirm = document.getElementById('reg_pass_confirm');
    var errores = {};

    if (pass.value.length && pass.value.length < 6) {
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
        SGETModal.errores(errores, document.getElementById('formRegistro'));
        SGETModal.toast('Revisa los campos marcados en rojo.', 'error');
        return false;
    }

    var boton = document.querySelector('#formRegistro button[type="submit"]');
    if (boton) { boton.disabled = true; boton.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Creando cuenta...'; }
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
</script>

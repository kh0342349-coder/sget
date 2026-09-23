<!-- SDK Oficial de Google reCAPTCHA v2 -->
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<!-- MODAL INICIAR SESIÓN -->
<div id="panelLogin" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 modal-isla-container opacity-0 pointer-events-none hidden transition-opacity duration-300">
    <div class="modal-isla-card bg-[#111827] border border-white/10 rounded-3xl p-6 sm:p-8 max-w-md w-full relative transform scale-95 transition-transform duration-300 shadow-2xl">
        
        <!-- Botón para cerrar -->
        <button onclick="cerrarPanel('panelLogin')" class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white flex items-center justify-center transition-colors cursor-pointer">
            <i class="fas fa-times text-sm"></i>
        </button>

        <!-- FORMULARIO LOGIN -->
        <div class="flex flex-col space-y-6">
            <div>
                <h3 class="text-2xl font-black text-white tracking-tight"><?= $lang['mdl_bienvenido_nuevo'] ?? 'Bienvenido de nuevo'; ?></h3>
                <p class="text-xs text-slate-400 mt-1 font-medium"><?= $lang['mdl_sub_login'] ?? 'Inicia sesión en SGET'; ?></p>
            </div>

            <!-- Mensaje de Error (si existe en la sesión) -->
            <?php if (isset($_SESSION['msg'])): ?>
                <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs font-bold text-center">
                    <?= $_SESSION['msg']; ?>
                </div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <!-- Formulario de Iniciar Sesión -->
            <form action="validar.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-300 mb-1.5"><?= $lang['mdl_doc'] ?? 'N° DOCUMENTO'; ?></label>
                    <input type="text" name="documento" placeholder="Ej: 113050" required
                           class="w-full px-4 py-2.5 bg-[#1f293d] border border-white/10 rounded-xl text-xs font-semibold text-white focus:outline-none focus:ring-2 focus:ring-sky-500 transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-300 mb-1.5"><?= $lang['mdl_pass'] ?? 'CONTRASEÑA'; ?></label>
                    <input type="password" name="clave" placeholder="••••••" required
                           class="w-full px-4 py-2.5 bg-[#1f293d] border border-white/10 rounded-xl text-xs font-semibold text-white focus:outline-none focus:ring-2 focus:ring-sky-500 transition-all">
                </div>

                <!-- WIDGET reCAPTCHA v2 (CLAVE OFICIAL DE PRUEBA GLOBAL PARA LOCALHOST) -->
                <div class="flex justify-center overflow-hidden py-1">
                    <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI" data-theme="dark"></div>
                </div>

                <button type="submit" class="w-full py-3 rounded-xl bg-sky-500 text-slate-950 font-black text-xs hover:bg-sky-400 transition-all shadow-lg shadow-sky-500/20 cursor-pointer uppercase tracking-wider mt-2">
                    <?= $lang['mdl_btn_ingresar'] ?? 'INGRESAR AL SISTEMA'; ?>
                </button>
            </form>

            <!-- Contenedor dinámico del botón de Google -->
            <div class="g_id_signin flex justify-center"></div>

            <!-- ENLACE PARA IR AL REGISTRO -->
            <div class="pt-4 border-t border-white/10 text-center">
                <p class="text-xs text-slate-400 font-medium">
                    <?= $lang['mdl_no_cuenta'] ?? '¿No tienes cuenta?'; ?> 
                    <button onclick="cambiarAPanel('panelRegistro')" class="text-sky-400 font-bold hover:underline ml-1 cursor-pointer">
                        <?= $lang['mdl_registrate_aqui'] ?? 'Regístrate aquí'; ?>
                    </button>
                </p>
            </div>
        </div>

    </div>
</div>

<!-- MODAL REGISTRO -->
<div id="panelRegistro" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 modal-isla-container opacity-0 pointer-events-none hidden transition-opacity duration-300">
    <div class="modal-isla-card bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl p-6 sm:p-8 max-w-md w-full relative transform scale-95 transition-transform duration-300 shadow-2xl max-h-[90vh] overflow-y-auto">
        
        <button onclick="cerrarPanel('panelRegistro')" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-slate-600 dark:hover:text-white flex items-center justify-center transition-colors cursor-pointer">
            <i class="fas fa-times text-sm"></i>
        </button>

        <div class="text-center space-y-2 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center mx-auto text-xl font-bold">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3 class="text-xl font-black text-slate-900 dark:text-white"><?= $lang['mdl_crear_cuenta'] ?? 'Crear Cuenta'; ?></h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Regístrate para ingresar con tu documento y gestionar tus viajes en SGET</p>
        </div>

        <!-- MENSAJE DE ERROR DINÁMICO DESDE SESIÓN PHP -->
        <?php if (isset($_SESSION['msg_registro'])): ?>
            <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-bold text-center">
                <i class="fas fa-exclamation-triangle mr-1"></i> <?= $_SESSION['msg_registro']; ?>
            </div>
            <?php unset($_SESSION['msg_registro']); ?>
        <?php endif; ?>

        <form action="nuevo_usuario.php" method="POST" id="formRegistro" onsubmit="return validarRegistro(event)" class="space-y-4">
            
            <!-- TIPO DE DOCUMENTO -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1 text-left"><?= $lang['mdl_tipo_doc'] ?? 'Tipo de Documento'; ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400"><i class="fas fa-id-card text-xs"></i></span>
                    <select name="tipo_doc" required class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500 outline-none transition-all cursor-pointer">
                        <option value="" disabled selected>Selecciona un tipo</option>
                        <option value="CC">Cédula de Ciudadanía (CC)</option>
                        <option value="TI">Tarjeta de Identidad (TI)</option>
                        <option value="CE">Cédula de Extranjería (CE)</option>
                        <option value="PP">Pasaporte (PP)</option>
                    </select>
                </div>
            </div>

            <!-- NÚMERO DE DOCUMENTO -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1 text-left"><?= $lang['mdl_doc'] ?? 'Número de Documento'; ?> <span class="text-emerald-500 font-black">(Tu ID de Ingreso)</span></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400"><i class="fas fa-hashtag text-xs"></i></span>
                    <input type="text" name="documento" required placeholder="Ej: 1007123456" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                </div>
            </div>

            <!-- NOMBRE COMPLETO -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1 text-left"><?= $lang['mdl_nombre_completo'] ?? 'Nombre Completo'; ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400"><i class="fas fa-user text-xs"></i></span>
                    <input type="text" name="nom_usu" required placeholder="Tu nombre y apellido" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                </div>
            </div>

            <!-- CORREO ELECTRÓNICO -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1 text-left"><?= $lang['mdl_correo'] ?? 'Correo Electrónico'; ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400"><i class="fas fa-envelope text-xs"></i></span>
                    <input type="email" name="corre_usu" required placeholder="correo@ejemplo.com" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                </div>
            </div>

            <!-- CONTRASEÑA -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1 text-left"><?= $lang['mdl_pass'] ?? 'Contraseña'; ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400"><i class="fas fa-lock text-xs"></i></span>
                    <input type="password" id="reg_pass" name="clave_usu" required placeholder="••••••••" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                </div>
            </div>

            <!-- CONFIRMAR CONTRASEÑA -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1 text-left"><?= $lang['mdl_confirm_pass'] ?? 'Confirmar Contraseña'; ?></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400"><i class="fas fa-shield-alt text-xs"></i></span>
                    <input type="password" id="reg_pass_confirm" name="confirmar_clave" required placeholder="••••••••" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                </div>
                <p id="error_pass_match" class="text-[11px] font-bold text-red-500 hidden mt-1 text-left">
                    <i class="fas fa-exclamation-circle"></i> Las contraseñas no coinciden.
                </p>
            </div>

            <!-- CHECKBOX POLÍTICA DE DATOS -->
            <div class="flex items-start gap-2.5 my-3 text-left">
                <input type="checkbox" id="acepta_politica" name="acepta_politica" value="1" required
                    class="mt-0.5 w-4 h-4 rounded border-slate-300 dark:border-white/20 text-emerald-500 focus:ring-emerald-400 dark:bg-slate-800 cursor-pointer">
                <label for="acepta_politica" class="text-xs text-slate-600 dark:text-slate-400 font-medium leading-snug">
                    <?= $lang['mdl_acepto_politica'] ?? 'Acepto la'; ?> 
                    <button type="button" onclick="abrirPanel('panelPolitica')" class="text-emerald-500 font-bold hover:underline cursor-pointer">
                        <?= $lang['mdl_tratamiento_datos'] ?? 'Política de Tratamiento de Datos Personales'; ?>
                    </button> (Ley 1581 de 2012).
                </label>
            </div>

            <button type="submit" class="w-full py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs uppercase tracking-wider transition-all shadow-lg shadow-emerald-500/20 cursor-pointer">
                <?= $lang['mdl_btn_registrarse'] ?? 'Registrarse'; ?>
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-white/10 text-center text-xs text-slate-500">
            <?= $lang['mdl_ya_cuenta'] ?? '¿Ya tienes una cuenta?'; ?> 
            <button onclick="cambiarAPanel('panelLogin')" class="text-emerald-500 font-bold hover:underline cursor-pointer">
                <?= $lang['mdl_inicia_aqui'] ?? 'Inicia sesión'; ?>
            </button>
        </div>
    </div>
</div>

<!-- SCRIPT VALIDACIÓN DE COINCIDENCIA DE CONTRASEÑAS -->
<script>
function validarRegistro(event) {
    const pass = document.getElementById('reg_pass').value;
    const confirm = document.getElementById('reg_pass_confirm').value;
    const errorMsg = document.getElementById('error_pass_match');

    if (pass !== confirm) {
        event.preventDefault();
        if (errorMsg) errorMsg.classList.remove('hidden');
        return false;
    }
    
    if (errorMsg) errorMsg.classList.add('hidden');
    return true;
}
</script>
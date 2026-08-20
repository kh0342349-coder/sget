<!-- SDK Oficial de Google reCAPTCHA v2 -->
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<!-- ========================================== -->
<!--     ESTRUCTURA HTML DE LA ISLA FLOTANTE    -->
<!-- ========================================== -->

<!-- MODAL LOGIN -->
<div id="panelLogin" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 modal-isla-container opacity-0 hidden transition-all duration-300">
    <div class="modal-isla-card bg-[#111827] border border-white/10 rounded-3xl max-w-3xl w-full relative transform scale-95 transition-all duration-300 shadow-2xl overflow-hidden grid md:grid-cols-2">
        
        <!-- Botón para cerrar -->
        <button onclick="cerrarPanel('panelLogin')" class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white flex items-center justify-center transition-colors">
            <i class="fas fa-times text-sm"></i>
        </button>

        <!-- COLUMNA IZQUIERDA: FORMULARIO -->
        <div class="p-6 sm:p-8 flex flex-col justify-between space-y-6">
            <div>
                <h3 class="text-2xl font-black text-white tracking-tight">Bienvenido de nuevo</h3>
                <p class="text-xs text-slate-400 mt-1 font-medium">Inicia sesión en SGET</p>
            </div>

            <!-- Mensaje de Error (si existe) -->
            <?php if (isset($_SESSION['msg'])): ?>
                <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs font-bold text-center">
                    <?= $_SESSION['msg']; ?>
                </div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <!-- Formulario de Iniciar Sesión -->
            <form action="validar.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-300 mb-1.5">N° DOCUMENTO</label>
                    <input type="text" name="documento" placeholder="Ej: 113050" required
                           class="w-full px-4 py-2.5 bg-[#1f293d] border border-white/10 rounded-xl text-xs font-semibold text-white focus:outline-none focus:ring-2 focus:ring-sky-500 transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-300 mb-1.5">CONTRASEÑA</label>
                    <input type="password" name="clave" placeholder="••••••" required
                           class="w-full px-4 py-2.5 bg-[#1f293d] border border-white/10 rounded-xl text-xs font-semibold text-white focus:outline-none focus:ring-2 focus:ring-sky-500 transition-all">
                </div>

                <!-- WIDGET reCAPTCHA v2 (CLAVE OFICIAL DE PRUEBA GOOGLE LOCALHOST) -->
                <div class="flex justify-center overflow-hidden py-1">
                    <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI" data-theme="dark"></div>
                </div>

                <button type="submit" class="w-full py-3 rounded-xl bg-sky-500 text-slate-950 font-black text-xs hover:bg-sky-400 transition-all shadow-lg shadow-sky-500/20 cursor-pointer uppercase tracking-wider mt-2">
                    INGRESAR AL SISTEMA
                </button>
            </form>

            <!-- Contenedor dinámico del botón de Google -->
            <div class="g_id_signin flex justify-center"></div>
        </div>

        <!-- COLUMNA DERECHA: TARJETA INFORMATIVA -->
        <div class="hidden md:flex flex-col justify-between p-8 bg-gradient-to-br from-slate-900 to-[#0b1329] border-l border-white/5 relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-sky-500/10 rounded-full blur-2xl pointer-events-none"></div>
            
            <div class="space-y-4 relative z-10">
                <span class="inline-block px-3 py-1 rounded-full bg-sky-500/10 border border-sky-500/20 text-sky-400 text-[10px] font-extrabold uppercase tracking-widest">
                    SGET PLATFORM
                </span>
                <h4 class="text-xl font-black text-white leading-snug">Control total de tu flota</h4>
                <p class="text-xs text-slate-400 leading-relaxed font-medium">
                    Gestiona rutas, asignaciones y monitorea cada unidad de transporte de manera eficiente.
                </p>
            </div>

            <div class="pt-6 border-t border-white/10 relative z-10">
                <p class="text-xs text-slate-400 font-medium">
                    ¿No tienes cuenta? 
                    <button onclick="cambiarAPanel('panelRegistro')" class="text-sky-400 font-bold hover:underline ml-1">Regístrate aquí</button>
                </p>
            </div>
        </div>

    </div>
</div>

<!-- MODAL REGISTRO -->
<div id="panelRegistro" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 modal-isla-container opacity-0 hidden transition-all duration-300">
    <div class="modal-isla-card bg-[#111827] border border-white/10 rounded-3xl p-6 sm:p-8 max-w-md w-full relative transform scale-95 transition-all duration-300 shadow-2xl">
        
        <button onclick="cerrarPanel('panelRegistro')" class="absolute top-5 right-5 text-slate-400 hover:text-white transition-colors">
            <i class="fas fa-times text-lg"></i>
        </button>

        <div class="text-center space-y-2 mb-6">
            <div class="w-12 h-12 rounded-2xl bg-purple-500/10 text-purple-400 flex items-center justify-center mx-auto text-xl font-extrabold">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3 class="text-2xl font-black text-white">Crear Cuenta</h3>
            <p class="text-xs text-slate-400 font-medium">Regístrate como pasajero en SGET</p>
        </div>

        <form action="controllers/registro.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-300 mb-1">Nombre Completo</label>
                <input type="text" name="nombre" placeholder="Tu nombre completo" required
                       class="w-full px-4 py-2.5 bg-[#1f293d] border border-white/10 rounded-xl text-xs font-semibold text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
            </div>

            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-300 mb-1">Número de Documento</label>
                <input type="text" name="documento" placeholder="Número de documento" required
                       class="w-full px-4 py-2.5 bg-[#1f293d] border border-white/10 rounded-xl text-xs font-semibold text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
            </div>

            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-300 mb-1">Correo Electrónico</label>
                <input type="email" name="correo" placeholder="correo@ejemplo.com" required
                       class="w-full px-4 py-2.5 bg-[#1f293d] border border-white/10 rounded-xl text-xs font-semibold text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
            </div>

            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-300 mb-1">Contraseña</label>
                <input type="password" name="clave" placeholder="••••••••" required
                       class="w-full px-4 py-2.5 bg-[#1f293d] border border-white/10 rounded-xl text-xs font-semibold text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
            </div>

            <button type="submit" class="w-full py-3 rounded-xl bg-purple-600 text-white font-black text-xs hover:bg-purple-500 transition-all shadow-lg shadow-purple-500/20 cursor-pointer mt-2 uppercase tracking-wider">
                REGISTRARSE
            </button>
        </form>

        <div class="mt-6 text-center text-xs font-medium text-slate-400">
            ¿Ya tienes cuenta? 
            <button onclick="cambiarAPanel('panelLogin')" class="text-purple-400 font-extrabold hover:underline ml-1">Inicia sesión</button>
        </div>
    </div>
</div>
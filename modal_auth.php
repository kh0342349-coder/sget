<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- ISLA FLOTANTE: LOGIN -->
<div id="panelLogin" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 modal-isla-container hidden opacity-0 transition-all duration-300">
    <div class="relative w-full max-w-4xl bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl modal-isla-card overflow-hidden flex flex-col md:flex-row transform scale-95 transition-all duration-300" id="contenidoLogin">
        
        <button type="button" onclick="cerrarPanel('panelLogin')" class="absolute top-4 right-4 z-10 p-2 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 transition-colors cursor-pointer">
            <i class="fas fa-times text-sm"></i>
        </button>

        <div class="w-full md:w-1/2 p-8 sm:p-12 flex flex-col justify-center space-y-6 overflow-y-auto max-h-[85vh]">
            <div class="border-b border-slate-200 dark:border-white/10 pb-4">
                <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">Bienvenido de nuevo</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Inicia sesión en SGET</p>
            </div>

            <!-- MENSAJE DE ÉXITO TRAS REGISTRARSE (VERDE) -->
            <?php if (isset($_SESSION['msg_success_login'])): ?>
                <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-bold flex items-center gap-2">
                    <i class="fas fa-check-circle text-sm shrink-0"></i>
                    <span>
                        <?php 
                            echo htmlspecialchars($_SESSION['msg_success_login']); 
                            unset($_SESSION['msg_success_login']); 
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- MENSAJE DE ERROR LOGIN (ROJO) -->
            <?php if (isset($_SESSION['msg'])): ?>
                <div class="p-3.5 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 text-xs font-bold flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-sm shrink-0"></i>
                    <span>
                        <?php 
                            echo htmlspecialchars($_SESSION['msg']); 
                            unset($_SESSION['msg']); 
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <form action="validar.php" method="POST" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">N° Documento</label>
                    <input type="text" name="documento" required placeholder="Ingrese su número de documento" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl py-3 px-4 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Contraseña</label>
                    <input type="password" name="clave" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl py-3 px-4 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                </div>
                <button type="submit" class="w-full py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-slate-950 font-extrabold rounded-xl shadow-lg hover:opacity-90 transition-all text-xs tracking-wider uppercase cursor-pointer">
                    Ingresar al Sistema
                </button>
            </form>
        </div>

        <div class="w-full md:w-1/2 bg-gradient-to-br from-slate-900 to-blue-950 p-8 sm:p-12 flex flex-col justify-between text-white relative overflow-hidden">
            <div>
                <span class="px-3 py-1 bg-sky-500/10 text-sky-400 border border-sky-500/20 rounded-full text-[10px] font-bold uppercase tracking-wider">SGET Platform</span>
                <h4 class="text-xl font-bold mt-4">Control total de tu flota</h4>
                <p class="text-xs text-slate-300 leading-relaxed my-4">Gestiona rutas, asignaciones y monitorea cada unidad de transporte de manera eficiente.</p>
            </div>
            <div class="text-[11px] text-slate-400 border-t border-white/10 pt-4">
                ¿No tienes cuenta? <button type="button" onclick="cambiarAPanel('panelRegistro')" class="text-sky-400 font-semibold hover:underline cursor-pointer">Regístrate aquí</button>
            </div>
        </div>

    </div>
</div>

<!-- ISLA FLOTANTE: REGISTRO -->
<div id="panelRegistro" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 modal-isla-container hidden opacity-0 transition-all duration-300">
    <div class="relative w-full max-w-4xl bg-white dark:bg-[#121826] border border-slate-200 dark:border-white/10 rounded-3xl modal-isla-card overflow-hidden flex flex-col md:flex-row transform scale-95 transition-all duration-300" id="contenidoRegistro">
        
        <button type="button" onclick="cerrarPanel('panelRegistro')" class="absolute top-4 right-4 z-10 p-2 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 transition-colors cursor-pointer">
            <i class="fas fa-times text-sm"></i>
        </button>

        <div class="w-full md:w-5/12 bg-gradient-to-br from-blue-950 to-slate-900 p-8 sm:p-10 flex flex-col justify-between text-white relative overflow-hidden">
            <div>
                <span class="px-3 py-1 bg-sky-500/10 text-sky-400 border border-sky-500/20 rounded-full text-[10px] font-bold uppercase tracking-wider">Únete a SGET</span>
                <h4 class="text-xl font-bold mt-4">Empieza a viajar seguro</h4>
                <p class="text-xs text-slate-300 leading-relaxed mt-2">Crea tu cuenta para acceder a la reserva de tickets, consulta de rutas en vivo y seguimiento de flota.</p>
            </div>
            
            <div class="text-[11px] text-slate-400 border-t border-white/10 pt-4 mt-6">
                ¿Ya tienes una cuenta registrada? <br>
                <button type="button" onclick="cambiarAPanel('panelLogin')" class="text-sky-400 font-bold hover:underline cursor-pointer mt-1 inline-block">Inicia sesión aquí</button>
            </div>
        </div>

        <div class="w-full md:w-7/12 p-6 sm:p-8 flex flex-col justify-center space-y-4 overflow-y-auto max-h-[85vh]">
            <div class="border-b border-slate-200 dark:border-white/10 pb-3">
                <h3 class="text-xl font-extrabold text-slate-900 dark:text-white">Crear Cuenta de Usuario</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Diligencia tus datos personales para habilitar tu perfil.</p>
            </div>

            <!-- MENSAJE DE ERROR REGISTRO (ROJO) -->
            <?php if (isset($_SESSION['msg_registro'])): ?>
                <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 text-xs font-bold flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-sm shrink-0"></i>
                    <span>
                        <?php 
                            echo htmlspecialchars($_SESSION['msg_registro']); 
                            unset($_SESSION['msg_registro']); 
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <form action="nuevo_usuario.php" method="POST" class="space-y-3">
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="space-y-1">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Tipo Doc.</label>
                        <select name="tipo_doc" required class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                            <option value="CC">C.C.</option>
                            <option value="TI">T.I.</option>
                            <option value="CE">C.E.</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2 space-y-1">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">N° Documento</label>
                        <input type="text" name="documento" required placeholder="Ej: 1012345678" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Nombre Completo</label>
                    <input type="text" name="nom_usu" required placeholder="Nombres y Apellidos" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Correo Electrónico</label>
                        <input type="email" name="corre_usu" required placeholder="correo@ejemplo.com" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Teléfono / Celular</label>
                        <input type="tel" name="tel_usu" placeholder="300 000 0000" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Contraseña</label>
                        <input type="password" name="clave_usu" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Confirmar Contraseña</label>
                        <input type="password" name="confirmar_clave" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-white/[0.03] border border-slate-300 dark:border-white/10 rounded-xl p-2.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:border-sky-500">
                    </div>
                </div>

                <input type="hidden" name="id_rol_usu" value="3">

                <button type="submit" class="w-full py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-slate-950 font-extrabold rounded-xl shadow-lg hover:opacity-90 transition-all text-xs tracking-wider uppercase cursor-pointer mt-2">
                    Registrarme en SGET
                </button>
            </form>
        </div>
    </div>
</div>
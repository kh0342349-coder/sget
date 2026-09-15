<?php
// Asegurar que el diccionario esté disponible
$idiomaActualModal = $_SESSION['sget_idioma'] ?? 'es';
?>
<div id="modalConfiguraciónSGET" class="fixed inset-0 bg-black/70 backdrop-blur-md z-[110] hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-[32px] max-w-2xl w-full p-8 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto custom-scrollbar">
        
        <!-- Cabecera del Modal -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-500/10 text-sky-500 dark:text-sky-400 flex items-center justify-center font-bold text-base">
                    <i class="fas fa-cog"></i>
                </div>
                <div>
                    <h3 class="font-black text-slate-900 dark:text-white text-base"><?= $lang['cfg_titulo'] ?? 'Configuración del Sistema' ?></h3>
                    <p class="text-xs text-slate-400"><?= $lang['cfg_sub'] ?? 'Actualiza tus datos personales.' ?></p>
                </div>
            </div>
            <button type="button" onclick="cerrarModalConfiguración()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-2 cursor-pointer transition-colors">
                <i class="fas fa-times text-base"></i>
            </button>
        </div>

        <!-- Formulario dentro del Modal -->
        <form action="../procesos/guardar_configuracion.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            
            <!-- Foto de Perfil -->
            <div class="flex items-center gap-6 p-4 bg-slate-50 dark:bg-white/5 rounded-2xl border border-slate-200 dark:border-white/5">
                <div class="w-16 h-16 bg-gradient-to-tr from-sky-400 via-blue-500 to-purple-600 rounded-2xl flex items-center justify-center text-slate-950 font-black text-2xl shadow-lg shrink-0">
                    <?= $inicialUsuario ?? 'U' ?>
                </div>
                <div class="space-y-1.5 flex-1">
                    <label class="block text-xs font-bold text-slate-900 dark:text-white"><?= $lang['cfg_foto'] ?? 'Cambiar Foto de Perfil' ?></label>
                    <input type="file" name="foto_perfil" accept="image/*" class="w-full text-xs text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-sky-500/10 file:text-sky-500 hover:file:bg-sky-500/20 cursor-pointer">
                </div>
            </div>

            <!-- Campos de Texto -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-900 dark:text-white"><?= $lang['cfg_nombre'] ?? 'Nombre Completo' ?></label>
                    <input type="text" name="nombre" value="<?= htmlspecialchars($_SESSION['nombre_usuario'] ?? '') ?>" required class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 rounded-2xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 transition-all">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-900 dark:text-white"><?= $lang['cfg_correo'] ?? 'Correo Electrónico' ?></label>
                    <input type="email" name="correo" value="<?= htmlspecialchars($_SESSION['correo'] ?? '') ?>" required class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 rounded-2xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 transition-all">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-900 dark:text-white"><?= $lang['cfg_telefono'] ?? 'Número de Teléfono' ?></label>
                    <input type="text" name="telefono" value="<?= htmlspecialchars($_SESSION['telefono'] ?? '') ?>" placeholder="Ej.: 3101234567" data-i18n-placeholder-es="Ej.: 3101234567" data-i18n-placeholder-en="e.g. 3101234567" class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 rounded-2xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 transition-all">
                </div>

                <!-- Selector de Idioma -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-900 dark:text-white"><?= $lang['cfg_idioma'] ?? 'Idioma del Sistema' ?></label>
                    <select name="idioma" id="selectIdiomaModal" data-sget-language style="min-width: 108px;" class="sget-language-selector w-full px-4 py-3 bg-slate-100 dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 rounded-2xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 transition-all cursor-pointer">
                        <option value="es" <?= $idiomaActualModal === 'es' ? 'selected' : '' ?>>🇪🇸 ESP — Español (Colombia)</option>
                        <option value="en" <?= $idiomaActualModal === 'en' ? 'selected' : '' ?>>🇺🇸 ENG — English (US)</option>
                    </select>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-white/10">
                <button type="button" onclick="cerrarModalConfiguración()" class="px-5 py-3 bg-slate-200 dark:bg-white/5 hover:bg-slate-300 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 font-black text-xs uppercase tracking-wider rounded-2xl transition-all cursor-pointer">
                    <?= $lang['cfg_cancelar'] ?? 'Cancelar' ?>
                </button>
                <button type="submit" class="px-6 py-3 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl transition-all shadow-lg cursor-pointer">
                    <?= $lang['cfg_guardar'] ?? 'Guardar Cambios' ?>
                </button>
            </div>

        </form>
    </div>
</div>

<script>
    window.abrirModalConfiguración = function() {
        const modal = document.getElementById('modalConfiguraciónSGET');
        if (modal) modal.classList.remove('hidden');
    }

    window.cerrarModalConfiguración = function() {
        const modal = document.getElementById('modalConfiguraciónSGET');
        if (modal) modal.classList.add('hidden');
    }
</script>
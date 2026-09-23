<!-- includes/modal_permisos.php -->
<div id="modalGestionPermisos" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/70 backdrop-blur-sm p-4 overflow-y-auto">
    
    <!-- Contenedor Ventana Modal -->
    <div class="bg-bg-tarjeta dark:bg-[#1e293b] border border-slate-200 dark:border-white/10 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden transform transition-all">
        
        <!-- Header del Modal -->
        <div class="p-5 border-b border-slate-200 dark:border-white/5 flex justify-between items-center bg-slate-50 dark:bg-white/5">
            <h3 class="text-lg font-extrabold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-user-gear text-neon-azul"></i> Permisos de: <span id="modalNombreUsuario" class="text-neon-azul"></span>
            </h3>
            <button onclick="cerrarModalPermisos()" class="text-color-mutado hover:text-white transition-colors p-1 rounded-lg">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Formulario de Permisos -->
        <form id="formGuardarPermisos">
            <input type="hidden" id="modalIdUsuario" name="id_usu">

            <div class="p-6 max-h-[70vh] overflow-y-auto space-y-4">
                
                <!-- Loader mientras carga vía AJAX -->
                <div id="loaderPermisos" class="py-12 text-center">
                    <i class="fas fa-spinner fa-spin text-3xl text-neon-azul"></i>
                    <p class="mt-3 text-sm text-color-mutado font-medium">Cargando catálogo de permisos...</p>
                </div>

                <!-- Contenedor dinámico cargado desde permisos.js -->
                <div id="contenedorPermisos" class="hidden space-y-4"></div>
            </div>

            <!-- Footer del Modal -->
            <div class="p-4 border-t border-slate-200 dark:border-white/5 bg-slate-50 dark:bg-white/5 flex justify-end gap-3">
                <button type="button" onclick="cerrarModalPermisos()" class="px-4 py-2 bg-slate-200 dark:bg-white/10 hover:bg-slate-300 dark:hover:bg-white/20 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition-all">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 bg-gradient-to-r from-neon-azul to-neon-morado text-white rounded-xl text-xs font-bold shadow-md hover:opacity-90 transition-all flex items-center gap-2">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>

    </div>
</div>
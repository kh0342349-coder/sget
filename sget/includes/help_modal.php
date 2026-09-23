<!-- Archivo: includes/help_modal.php -->

<!-- BOTÓN FLOTANTE DE AYUDA (Esquina inferior derecha) -->
<button type="button" onclick="abrirModalAyuda()" class="fixed bottom-6 right-6 z-50 w-12 h-12 bg-sky-500 hover:bg-sky-400 text-slate-950 rounded-full shadow-2xl flex items-center justify-center font-bold transition-all duration-300 hover:scale-110 cursor-pointer border border-sky-400/30" title="Soporte y Ayuda">
    <i class="fas fa-question text-base"></i>
</button>

<!-- MODAL DE AYUDA GLOBAL -->
<div id="modalAyudaSGET" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-[32px] max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-white/10">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-500 dark:text-sky-400 flex items-center justify-center font-bold">
                    <i class="fas fa-info-circle text-xs"></i>
                </div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">Ayuda SGET: <span class="text-sky-500 dark:text-sky-400"><?php echo $submoduloTexto ?? 'Sistema'; ?></span></h3>
            </div>
            <button onclick="cerrarModalAyuda()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 cursor-pointer"><i class="fas fa-times"></i></button>
        </div>
        <div id="contenidoAyudaModulo" class="text-xs text-slate-500 dark:text-slate-400 space-y-2 leading-relaxed">
            <!-- Contenido dinámico inyectado por JS -->
        </div>
        <button onclick="cerrarModalAyuda()" class="w-full py-3 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl transition-all shadow-lg cursor-pointer">
            Entendido
        </button>
    </div>
</div>

<script>
    function abrirModalAyuda() {
        const modalAyuda = document.getElementById('modalAyudaSGET');
        const contenedorTexto = document.getElementById('contenidoAyudaModulo');
        if (modalAyuda && contenedorTexto) {
            contenedorTexto.innerHTML = `
                <p class="font-semibold text-slate-700 dark:text-slate-200">Ayuda general de la vista actual:</p>
                <ul class="list-disc pl-4 space-y-1 text-slate-500 dark:text-slate-400 mt-1">
                    <li>Puedes utilizar <strong>Ctrl + K</strong> en cualquier momento para buscar funciones del sistema.</li>
                    <li>Navega por el panel izquierdo para cambiar de módulo.</li>
                    <li>Recuerda que por tu seguridad, la sesión se cerrará tras 3 minutos de inactividad.</li>
                </ul>`;
            modalAyuda.classList.remove('hidden');
        }
    }
    
    function cerrarModalAyuda() {
        const modalAyuda = document.getElementById('modalAyudaSGET');
        if (modalAyuda) modalAyuda.classList.add('hidden');
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F1') {
            e.preventDefault();
            abrirModalAyuda();
        }
    });
</script>
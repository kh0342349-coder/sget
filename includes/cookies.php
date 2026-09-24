<!-- 1. MODAL CENTRAL PRINCIPAL DE COOKIES -->
<div id="ktmCookieOverlay" class="fixed inset-0 z-[120] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-[#111111] border border-white/20 max-w-xl w-full p-8 text-center shadow-2xl relative">
        
        <!-- Logo de SGET Destacado (logo-sget-2.png) -->
        <div class="flex justify-center mb-6">
            <img src="img/logo-sget-2.png" 
                 alt="SGET Logo" 
                 class="h-20 sm:h-24 w-auto object-contain filter drop-shadow-[0_0_12px_rgba(14,165,233,0.3)] hover:scale-105 transition-transform duration-300">
        </div>

        <!-- Texto del Aviso -->
        <p class="text-xs sm:text-sm text-slate-200 leading-relaxed font-medium mb-8">
            Al hacer clic en "Aceptar todas las cookies", usted da su consentimiento para el almacenamiento de cookies en su dispositivo para mejorar la navegación del sitio, analizar el uso del sitio y apoyar nuestros esfuerzos de desarrollo. Las cookies también pueden rechazarse.
        </p>

        <!-- Botones Principales -->
        <div class="space-y-3 max-w-md mx-auto">
            <button onclick="rechazarCookiesOpcionales()" class="w-full py-3.5 bg-black hover:bg-zinc-900 text-white font-black text-xs uppercase tracking-widest border border-white transition-all cursor-pointer">
                Rechazar Todas
            </button>
            <button onclick="aceptarTodasCookies()" class="w-full py-3.5 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs uppercase tracking-widest transition-all cursor-pointer">
                Aceptar Todas las Cookies
            </button>
        </div>

        <!-- Enlace a Configuración -->
        <div class="mt-6">
            <button onclick="abrirDrawerCookies()" class="text-xs font-black text-slate-400 hover:text-white uppercase tracking-wider underline transition-colors cursor-pointer">
                Configuración de Cookies
            </button>
        </div>
    </div>
</div>

<!-- 2. PANEL LATERAL DESPLEGABLE (DRAWER ESTILO KTM) -->
<div id="ktmCookieDrawer" class="fixed inset-0 z-[130] bg-black/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 hidden">
    <div id="ktmDrawerContent" class="fixed top-0 left-0 bottom-0 w-full max-w-md bg-[#0a0a0a] border-r border-white/10 flex flex-col justify-between transform -translate-x-full transition-transform duration-300 text-left shadow-2xl">
        
        <!-- Header Fijo con Logo -->
        <div class="p-6 border-b border-white/10 flex items-center justify-between shrink-0 bg-[#0a0a0a]">
            <img src="img/logo-sget-2.png" 
                 alt="SGET Logo" 
                 class="h-12 w-auto object-contain filter drop-shadow-[0_0_8px_rgba(14,165,233,0.25)]">
            <!-- Al dar clic a la X vuelve al modal principal de cookies -->
            <button onclick="cerrarDrawerYVolverAModal()" class="text-slate-400 hover:text-white text-xl font-bold cursor-pointer transition-colors" title="Volver al menú principal">✕</button>
        </div>

        <!-- Cuerpo Desplazable con Todas las Categorías -->
        <div class="p-6 overflow-y-auto flex-grow space-y-6 custom-scrollbar">
            <div>
                <h3 class="text-lg font-black text-white mb-2">Centro de preferencia de la privacidad</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Cuando visita cualquier sitio web, el mismo podría obtener o guardar información en su navegador, generalmente mediante el uso de cookies. Esta información se usa principalmente para que el sitio funcione correctamente.
                </p>
            </div>

            <button onclick="aceptarTodasCookies()" class="w-full py-3 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs uppercase tracking-widest transition-all cursor-pointer">
                Permitirlas Todas
            </button>

            <div class="space-y-6 pt-2">
                <h4 class="text-xs font-black uppercase text-white tracking-wider border-b border-white/10 pb-2">Gestionar las preferencias</h4>
                
                <!-- 1. Estrictamente Necesarias -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-white">Cookies estrictamente necesarias</span>
                        <span class="text-[10px] font-black uppercase text-sky-400">Activas siempre</span>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Estas cookies son necesarias para que el sitio web funcione y no se pueden desactivar en nuestros sistemas. Permiten mantener la sesión del usuario segura y autenticada.
                    </p>
                </div>

                <!-- 2. Rendimiento -->
                <div class="space-y-2 pt-4 border-t border-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-white">Cookies de rendimiento</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="chkRendimiento" class="sr-only peer" checked>
                            <div class="w-9 h-5 bg-zinc-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                        </label>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Nos permiten medir el desempeño del sitio e identificar las páginas más populares para optimizar los tiempos de carga en las consultas de viajes.
                    </p>
                </div>

                <!-- 3. Funcionalidad / Servicios Externos (Google Login) -->
                <div class="space-y-2 pt-4 border-t border-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-white">Cookies de funcionalidad y servicios de terceros</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="chkFuncionalidad" class="sr-only peer" checked>
                            <div class="w-9 h-5 bg-zinc-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                        </label>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Permiten la integración de herramientas como el inicio de sesión rápido con Google Sign-In y la personalización de preferencias.
                    </p>
                </div>

                <!-- 4. Dirigidas -->
                <div class="space-y-2 pt-4 border-t border-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-white">Cookies dirigidas</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="chkDirigidas" class="sr-only peer">
                            <div class="w-9 h-5 bg-zinc-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                        </label>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Pueden ser establecidas por nuestros socios para crear un perfil de sus intereses y mostrarle anuncios relevantes en otros sitios.
                    </p>
                </div>

                <!-- 5. Redes Sociales -->
                <div class="space-y-2 pt-4 border-t border-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-white">Cookies de redes sociales</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="chkRedesSociales" class="sr-only peer">
                            <div class="w-9 h-5 bg-zinc-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                        </label>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Configuradas por servicios de redes sociales integrados al sitio para permitirle compartir nuestro contenido con sus contactos.
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer Fijo con Botones de Acción -->
        <div class="p-6 border-t border-white/10 bg-[#0a0a0a] shrink-0 space-y-2">
            <button onclick="rechazarCookiesOpcionales()" class="w-full py-3 bg-transparent border border-sky-500 text-sky-400 hover:bg-sky-500/10 font-black text-xs uppercase tracking-widest transition-all cursor-pointer">
                Rechazarlas todas
            </button>
            <button onclick="guardarDrawerCookies()" class="w-full py-3 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs uppercase tracking-widest transition-all cursor-pointer">
                Confirmar mis preferencias
            </button>
        </div>

    </div>
</div>

<!-- LÓGICA JAVASCRIPT FUNCIONAL DE COOKIES -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const consent = localStorage.getItem('sget_cookies_consent');
        if (!consent) {
            const overlay = document.getElementById('ktmCookieOverlay');
            if (overlay) overlay.classList.remove('hidden');
        } else {
            const prefs = JSON.parse(consent);
            cargarPreferenciasGuardadas(prefs);
            aplicarVisibilidadGoogle(prefs);
        }
    });

    function aceptarTodasCookies() {
        const prefs = {
            necesarias: true,
            rendimiento: true,
            funcionalidad: true,
            dirigidas: true,
            redesSociales: true
        };
        localStorage.setItem('sget_cookies_consent', JSON.stringify(prefs));
        actualizarSwitches(prefs);
        aplicarVisibilidadGoogle(prefs);
        cerrarTodoCookies();
    }

    function rechazarCookiesOpcionales() {
        const prefs = {
            necesarias: true,
            rendimiento: false,
            funcionalidad: false,
            dirigidas: false,
            redesSociales: false
        };
        localStorage.setItem('sget_cookies_consent', JSON.stringify(prefs));
        actualizarSwitches(prefs);
        aplicarVisibilidadGoogle(prefs);
        cerrarTodoCookies();
    }

    function guardarDrawerCookies() {
        const prefs = {
            necesarias: true,
            rendimiento: document.getElementById('chkRendimiento')?.checked ?? false,
            funcionalidad: document.getElementById('chkFuncionalidad')?.checked ?? false,
            dirigidas: document.getElementById('chkDirigidas')?.checked ?? false,
            redesSociales: document.getElementById('chkRedesSociales')?.checked ?? false
        };
        localStorage.setItem('sget_cookies_consent', JSON.stringify(prefs));
        aplicarVisibilidadGoogle(prefs);
        cerrarTodoCookies();
    }

    function aplicarVisibilidadGoogle(prefs) {
        const permitidas = prefs && (prefs.funcionalidad || prefs.redesSociales);
        const elementosGoogle = document.querySelectorAll('.g_id_signin, .google-auth-wrapper');
        
        elementosGoogle.forEach(el => {
            if (permitidas) {
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });
    }

    function cargarPreferenciasGuardadas(prefs) {
        actualizarSwitches(prefs);
    }

    function actualizarSwitches(prefs) {
        if (document.getElementById('chkRendimiento')) document.getElementById('chkRendimiento').checked = !!prefs.rendimiento;
        if (document.getElementById('chkFuncionalidad')) document.getElementById('chkFuncionalidad').checked = !!prefs.funcionalidad;
        if (document.getElementById('chkDirigidas')) document.getElementById('chkDirigidas').checked = !!prefs.dirigidas;
        if (document.getElementById('chkRedesSociales')) document.getElementById('chkRedesSociales').checked = !!prefs.redesSociales;
    }

    function abrirDrawerCookies() {
        const overlay = document.getElementById('ktmCookieOverlay');
        const drawer = document.getElementById('ktmCookieDrawer');
        const content = document.getElementById('ktmDrawerContent');

        if (overlay) overlay.classList.add('hidden');
        if (drawer && content) {
            drawer.classList.remove('hidden');
            setTimeout(() => {
                drawer.classList.remove('opacity-0', 'pointer-events-none');
                content.classList.remove('-translate-x-full');
            }, 10);
        }
    }

    function cerrarDrawerYVolverAModal() {
        const overlay = document.getElementById('ktmCookieOverlay');
        const drawer = document.getElementById('ktmCookieDrawer');
        const content = document.getElementById('ktmDrawerContent');

        if (drawer && content) {
            content.classList.add('-translate-x-full');
            drawer.classList.add('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                drawer.classList.add('hidden');
                if (overlay) overlay.classList.remove('hidden');
            }, 300);
        }
    }

    function cerrarDrawerCookies() {
        const drawer = document.getElementById('ktmCookieDrawer');
        const content = document.getElementById('ktmDrawerContent');
        
        if (drawer && content) {
            content.classList.add('-translate-x-full');
            drawer.classList.add('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                drawer.classList.add('hidden');
            }, 300);
        }
    }

    function cerrarTodoCookies() {
        const overlay = document.getElementById('ktmCookieOverlay');
        if (overlay) overlay.classList.add('hidden');
        cerrarDrawerCookies();
    }
</script>
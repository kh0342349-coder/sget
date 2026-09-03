<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../helpers/AuthHelper.php';

$rolUsuario = $_SESSION['rol'] ?? $_SESSION['id_rol_usu'] ?? 0;
$idUsuarioSesion = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0;
$nombreRealHeader = htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario SGET', ENT_QUOTES, 'UTF-8');
$inicialUsuario = !empty($nombreRealHeader) ? strtoupper(substr($nombreRealHeader, 0, 1)) : 'U';

$pagina_titulo = basename($_SERVER['PHP_SELF'], '.php');
$submoduloTexto = "Inicio";

// --- CATÁLOGOS DINÁMICOS POR ROL PARA EL BUSCADOR ---
$catalogoOpciones = [];
$etiquetaRolHeader = 'Usuario';
$colorRolHeader = 'text-slate-500';

if ($rolUsuario == 1) { // ADMIN
    $etiquetaRolHeader = 'Administrador';
    $colorRolHeader = 'text-sky-500';
    $submoduloTexto = str_replace('_', ' ', ucfirst($pagina_titulo));
    $catalogoOpciones = [
        ["titulo" => "Inicio / Dashboard", "categoria" => "Principal", "descripcion" => "Vista general del sistema", "url" => "admin.php", "icono" => "fa-chart-pie"],
        ["titulo" => "Gestión de Usuarios", "categoria" => "Admin", "descripcion" => "Usuarios y roles", "url" => "usuarios.php", "icono" => "fa-users"],
        ["titulo" => "Gestión de Permisos", "categoria" => "Admin", "descripcion" => "Asignar funciones al personal", "url" => "gestion_permisos.php", "icono" => "fa-key"],
        ["titulo" => "Rutas de Transporte", "categoria" => "Operaciones", "descripcion" => "Gestión de trayectos", "url" => "rutas.php", "icono" => "fa-route"],
        ["titulo" => "Control de Viajes", "categoria" => "Operaciones", "descripcion" => "Monitoreo de viajes", "url" => "viajes_3.php", "icono" => "fa-calendar-alt"]
    ];
} elseif ($rolUsuario == 2) { // CONDUCTOR
    $etiquetaRolHeader = 'Conductor';
    $colorRolHeader = 'text-emerald-500';
    if ($pagina_titulo === 'conductor' || $pagina_titulo === 'dashboard_conductor') $submoduloTexto = "Inicio";
    else if ($pagina_titulo === 'viajes_conductor') $submoduloTexto = "Mis Viajes";
    else if ($pagina_titulo === 'viaje_asignado') $submoduloTexto = "Viaje Asignado";
    $catalogoOpciones = [
        ["titulo" => "Dashboard", "categoria" => "Principal", "descripcion" => "Métricas de tu jornada", "url" => "conductor.php", "icono" => "fa-chart-pie"],
        ["titulo" => "Mis Viajes", "categoria" => "Rutas", "descripcion" => "Consulta de viajes", "url" => "viajes_conductor.php", "icono" => "fa-route"],
        ["titulo" => "Viaje Asignado", "categoria" => "Operaciones", "descripcion" => "Detalles del viaje actual", "url" => "viaje_asignado.php", "icono" => "fa-bus"]
    ];
} elseif ($rolUsuario == 3) { // PASAJERO
    $etiquetaRolHeader = 'Pasajero';
    $colorRolHeader = 'text-purple-500';
    if ($pagina_titulo === 'pasajero') $submoduloTexto = "Inicio";
    else if ($pagina_titulo === 'viajes_pasajero') $submoduloTexto = "Ver Viajes";
    else if ($pagina_titulo === 'historial_pasajero') $submoduloTexto = "Historial";
    $catalogoOpciones = [
        ["titulo" => "Panel Pasajero", "categoria" => "Principal", "descripcion" => "Resumen de tus viajes", "url" => "pasajero.php", "icono" => "fa-th-large", "permiso" => null],
        ["titulo" => "Ver Viajes Disponibles", "categoria" => "Rutas", "descripcion" => "Rutas, precios y horarios", "url" => "viajes_pasajero.php", "icono" => "fa-bus", "permiso" => null],
        ["titulo" => "Historial de Reservas", "categoria" => "Viajes", "descripcion" => "Histórico de pasajes", "url" => "historial_pasajero.php", "icono" => "fa-history", "permiso" => null],
        ["titulo" => "Calificar Servicio", "categoria" => "Calificaciones", "descripcion" => "Evaluar al conductor", "url" => "calificar.php", "icono" => "fa-star", "permiso" => null]
    ];
}

$opcionesSGET = [];
foreach ($catalogoOpciones as $opcion) {
    if (!isset($opcion['permiso']) || $opcion['permiso'] === null || AuthHelper::tienePermiso($conexion, $idUsuarioSesion, $opcion['permiso'])) {
        $opcionesSGET[] = $opcion;
    }
}
?>

<script>
    const OPCIONES_SGET = <?php echo json_encode($opcionesSGET); ?>;
</script>

<!-- HEADER FLOTANTE TIPO CÁPSULA -->
<header class="header-floating h-16 bg-white/80 dark:bg-[#0f172a]/80 backdrop-blur-xl border border-slate-200/90 dark:border-white/10 rounded-[24px] flex items-center justify-between px-6 sticky top-4 z-40 my-4 shadow-xl relative transition-all duration-300">
    
    <div class="flex items-center gap-4 flex-1 max-w-xl">
        <button id="btnToggleSidebar" type="button" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-slate-200/50 dark:bg-white/5 text-slate-700 dark:text-slate-300 hover:bg-sky-500/20 hover:text-sky-500 transition-all border border-slate-300/50 dark:border-white/10 cursor-pointer shrink-0">
            <i class="fas fa-bars text-sm"></i>
        </button>

        <div class="text-slate-500 dark:text-slate-400 font-medium text-xs tracking-wide hidden lg:block shrink-0">
            <?= $etiquetaRolHeader ?> &nbsp;/&nbsp; <span class="text-slate-900 dark:text-white font-extrabold"><?php echo $submoduloTexto; ?></span>
        </div>

        <div class="relative w-full max-w-xs md:max-w-sm ml-1">
            <div class="relative flex items-center">
                <i class="fas fa-search absolute left-4 text-slate-400 text-xs pointer-events-none"></i>
                <input type="text" id="inputBuscadorHeader" placeholder="Buscar función... (Ctrl + K)" autocomplete="off" class="w-full pl-10 pr-8 py-2.5 bg-slate-100 dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 rounded-full text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-sky-500 transition-all shadow-inner">
                <span id="btnLimpiarBuscador" class="absolute right-3.5 text-slate-400 hover:text-sky-500 text-xs cursor-pointer hidden"><i class="fas fa-times"></i></span>
            </div>
            <div id="resultadosBusquedaHeader" class="absolute top-full left-0 right-0 mt-3 bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-3xl shadow-2xl overflow-hidden hidden z-50 max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-white/5 custom-scrollbar"></div>
        </div>
    </div>

    <div class="flex items-center space-x-3 sm:space-x-4 shrink-0">
        <div class="relative group shrink-0">
            <button type="button" onclick="abrirModalAyuda()" class="w-10 h-10 rounded-2xl bg-sky-500/10 text-sky-500 dark:text-sky-400 hover:bg-sky-500/20 border border-sky-500/20 transition-all flex items-center justify-center text-sm shadow-sm cursor-pointer" title="Guía del módulo (F1)">
                <i class="fas fa-question text-xs"></i>
            </button>
        </div>

        <button id="themeToggle" type="button" class="w-10 h-10 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-amber-400 bg-slate-200/50 dark:bg-white/5 hover:bg-slate-300 dark:hover:bg-white/10 rounded-2xl transition-all border border-slate-300/50 dark:border-white/10 text-sm cursor-pointer" title="Cambiar Tema">
            <i id="themeIcon" class="fas fa-moon text-base"></i>
        </button>

        <div class="hidden md:block text-right">
            <p class="text-xs font-extrabold text-slate-900 dark:text-white leading-tight"><?php echo $nombreRealHeader; ?></p>
            <p class="text-[9px] <?= $colorRolHeader ?> font-black uppercase tracking-widest flex items-center justify-end gap-1 mt-0.5">
                <span class="w-1.5 h-1.5 rounded-full <?= str_replace('text', 'bg', $colorRolHeader) ?> inline-block animate-pulse"></span> ONLINE
            </p>
        </div>
        
        <div class="w-10 h-10 bg-gradient-to-tr from-sky-400 via-blue-500 to-purple-600 rounded-2xl flex items-center justify-center text-slate-950 font-black text-sm shadow-md shadow-sky-500/20 shrink-0">
            <?php echo $inicialUsuario; ?>
        </div>

        <a href="../assets/cerrar.php" class="w-10 h-10 flex items-center justify-center text-slate-500 hover:text-red-500 bg-slate-200/50 dark:bg-white/5 hover:bg-red-500/10 rounded-2xl transition-all border border-slate-300/50 dark:border-white/10 text-sm" title="Cerrar Sesión">
            <i class="fas fa-sign-out-alt text-base"></i> 
        </a>
    </div>
</header>

<!-- MODAL GUÍA DE AYUDA (F1) -->
<div id="modalAyudaSGET" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-[32px] max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-white/10">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-500 dark:text-sky-400 flex items-center justify-center font-bold">
                    <i class="fas fa-info-circle text-xs"></i>
                </div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">Ayuda SGET: <span class="text-sky-500 dark:text-sky-400"><?php echo $submoduloTexto; ?></span></h3>
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

<!-- MODAL INACTIVIDAD (3 MIN) -->
<div id="inactivityModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-[32px] max-w-xs w-full p-6 shadow-2xl text-center space-y-4">
        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center mx-auto text-xl">
            <i class="fas fa-user-clock"></i>
        </div>
        <h4 class="text-base font-black text-slate-900 dark:text-white">¿Sigues ahí?</h4>
        <p class="text-xs text-slate-500 dark:text-slate-400">Tu sesión se cerrará automáticamente por inactividad.</p>
        <div class="text-xs font-bold text-red-500 dark:text-red-400 bg-red-500/10 py-2 px-3 rounded-2xl border border-red-500/20 font-mono">
            Cierre en: <span id="countdownTimer" class="font-extrabold text-sm">30</span> seg
        </div>
        <button id="btnContinuar" class="w-full py-3 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl transition-all shadow-lg cursor-pointer">
            Continuar Sesión
        </button>
    </div>
</div>

<script>
    // --- LÓGICA CONSOLIDADA (Tema, Buscador, Inactividad y Ayuda) ---
    document.addEventListener('DOMContentLoaded', () => {

        // 1. TEMA OSCURO
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const themeGuardado = localStorage.getItem('theme');
        const esOscuro = themeGuardado === 'dark' || (!themeGuardado && window.matchMedia('(prefers-color-scheme: dark)').matches);
        
        const actualizarIconoTema = (oscuro) => {
            if (themeIcon) themeIcon.className = oscuro ? "fas fa-sun text-amber-400 text-base" : "fas fa-moon text-slate-700 text-base";
        };

        if (esOscuro) document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
        actualizarIconoTema(esOscuro);

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const nuevoEstado = document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', nuevoEstado ? 'dark' : 'light');
                actualizarIconoTema(nuevoEstado);
            });
        }

        // 2. SIDEBAR TOGGLE
        const btnToggle = document.getElementById('btnToggleSidebar');
        if (localStorage.getItem('sidebar_collapsed') === 'true') document.body.classList.add('sidebar-collapsed');
        
        if (btnToggle) {
            btnToggle.addEventListener('click', (e) => {
                e.preventDefault();
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar_collapsed', document.body.classList.contains('sidebar-collapsed'));
                window.dispatchEvent(new CustomEvent('toggle-sidebar')); // Para sincronizar animaciones si es necesario
            });
        }

        // 3. BUSCADOR CTRL+K
        const inputBuscador = document.getElementById('inputBuscadorHeader');
        const contenedorResultados = document.getElementById('resultadosBusquedaHeader');
        const btnLimpiar = document.getElementById('btnLimpiarBuscador');

        if (inputBuscador && contenedorResultados) {
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    inputBuscador.focus();
                }
            });

            inputBuscador.addEventListener('input', function () {
                const query = this.value.trim().toLowerCase();
                if (btnLimpiar) btnLimpiar.classList.toggle('hidden', query.length === 0);

                if (query.length === 0) {
                    contenedorResultados.innerHTML = '';
                    contenedorResultados.classList.add('hidden');
                    return;
                }

                const coincidencias = OPCIONES_SGET.filter(item =>
                    item.titulo.toLowerCase().includes(query) ||
                    item.categoria.toLowerCase().includes(query) ||
                    item.descripcion.toLowerCase().includes(query)
                );

                contenedorResultados.innerHTML = '';
                if (coincidencias.length === 0) {
                    contenedorResultados.innerHTML = `<div class="p-4 text-center text-xs text-slate-400">No se encontraron opciones.</div>`;
                } else {
                    coincidencias.forEach(item => {
                        contenedorResultados.innerHTML += `
                            <a href="${item.url}" class="flex items-center gap-3 p-3.5 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors group">
                                <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-500 dark:text-sky-400 flex items-center justify-center shrink-0">
                                    <i class="fas ${item.icono} text-xs"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-extrabold text-slate-900 dark:text-white truncate">${item.titulo}</p>
                                        <span class="text-[9px] font-black px-2 py-0.5 rounded-full bg-slate-200 dark:bg-white/10 text-slate-600 dark:text-slate-400 uppercase">${item.categoria}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 truncate mt-0.5">${item.descripcion}</p>
                                </div>
                            </a>`;
                    });
                }
                contenedorResultados.classList.remove('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!inputBuscador.contains(e.target) && !contenedorResultados.contains(e.target)) contenedorResultados.classList.add('hidden');
            });

            if (btnLimpiar) {
                btnLimpiar.addEventListener('click', () => {
                    inputBuscador.value = '';
                    contenedorResultados.innerHTML = '';
                    contenedorResultados.classList.add('hidden');
                    btnLimpiar.classList.add('hidden');
                    inputBuscador.focus();
                });
            }
        }

        // 4. INACTIVIDAD (3 MINUTOS)
        const TOTAL_INACTIVITY_TIME = 3 * 60 * 1000; 
        const WARNING_TIME = 30 * 1000;              
        let inactivityTimer, countdownInterval, timeLeft = 30;
        const modalInactividad = document.getElementById('inactivityModal');
        const countdownSpan = document.getElementById('countdownTimer');
        const btnContinuar = document.getElementById('btnContinuar');

        function iniciarTemporizadorInactividad() {
            clearTimeout(inactivityTimer);
            clearInterval(countdownInterval);
            if (modalInactividad) modalInactividad.classList.add('hidden');
            
            inactivityTimer = setTimeout(mostrarAdvertenciaCierre, TOTAL_INACTIVITY_TIME - WARNING_TIME);
        }

        function mostrarAdvertenciaCierre() {
            timeLeft = 30;
            if (countdownSpan) countdownSpan.textContent = timeLeft;
            if (modalInactividad) modalInactividad.classList.remove('hidden');

            countdownInterval = setInterval(() => {
                timeLeft--;
                if (countdownSpan) countdownSpan.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = "../assets/cerrar.php"; // Cierre unificado
                }
            }, 1000);
        }

        ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart', 'click'].forEach(evt => {
            document.addEventListener(evt, () => {
                if (modalInactividad && modalInactividad.classList.contains('hidden')) iniciarTemporizadorInactividad();
            }, true);
        });

        if (btnContinuar) btnContinuar.addEventListener('click', iniciarTemporizadorInactividad);
        iniciarTemporizadorInactividad();
    });

    // 5. MODAL DE AYUDA GLOBAL (F1)
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
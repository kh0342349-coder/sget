<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conexion)) {
    include_once __DIR__ . '/../assets/conexion.php';
}

if (isset($_POST['idioma']) && in_array($_POST['idioma'], ['es', 'en'], true)) {
    $_SESSION['sget_idioma'] =$_POST['idioma'];
}
$idiomaActual =$_SESSION['sget_idioma'] ?? 'es';

if ($idiomaActual === 'en') {
    @include_once __DIR__ . '/../lang/en.php';
} else {
    @include_once __DIR__ . '/../lang/es.php';
}
require_once __DIR__ . '/../helpers/AuthHelper.php';

$rolUsuario = $_SESSION['rol'] ?? $_SESSION['id_rol_usu'] ?? 0;
$idUsuarioSesión = $_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0;
$nombreRealHeader = htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario SGET', ENT_QUOTES, 'UTF-8');$inicialUsuario = !empty($nombreRealHeader) ? strtoupper(substr($nombreRealHeader, 0, 1)) : 'U';

$pagina_titulo = basename($_SERVER['PHP_SELF'], '.php');$submoduloTexto = "Inicio";

$catalogoOpciones = [];
$etiquetaRolHeader = 'Usuario';$colorRolHeader = 'text-slate-500';

if ($rolUsuario == 1) { // ADMIN$etiquetaRolHeader = 'Administrador';
    $colorRolHeader = 'text-sky-500';$submoduloTexto = str_replace('_', ' ', ucfirst($pagina_titulo));$catalogoOpciones = [
        ["titulo" => "Inicio / Dashboard", "categoria" => "Principal", "descripcion" => "Vista general del sistema", "url" => "admin.php", "icono" => "fa-chart-pie"],
        ["titulo" => "Gestión de Usuarios", "categoria" => "Admin", "descripcion" => "Usuarios y roles", "url" => "usuarios.php", "icono" => "fa-users"],
        ["titulo" => "Gestión de Permisos", "categoria" => "Admin", "descripcion" => "Asignar funciones al personal", "url" => "gestion_permisos.php", "icono" => "fa-key"],
        ["titulo" => "Rutas de Transporte", "categoria" => "Operaciones", "descripcion" => "Gestión de trayectos", "url" => "rutas.php", "icono" => "fa-route"],
        ["titulo" => "Control de Viajes", "categoria" => "Operaciones", "descripcion" => "Monitoreo de viajes", "url" => "viajes_3.php", "icono" => "fa-calendar-alt"]
    ];
} elseif ($rolUsuario == 2) { // CONDUCTOR
    $etiquetaRolHeader = 'Conductor';$colorRolHeader = 'text-emerald-500';
    if ($pagina_titulo === 'conductor' || $pagina_titulo === 'dashboard_conductor')$submoduloTexto = "Inicio";
    else if ($pagina_titulo === 'viajes_conductor')$submoduloTexto = "Mis Viajes";
    else if ($pagina_titulo === 'viaje_asignado')$submoduloTexto = "Viaje Asignado";
    $catalogoOpciones = [
        ["titulo" => "Dashboard", "categoria" => "Principal", "descripcion" => "Métricas de tu jornada", "url" => "conductor.php", "icono" => "fa-chart-pie"],
        ["titulo" => "Mis Viajes", "categoria" => "Rutas", "descripcion" => "Consulta de viajes", "url" => "viajes_conductor.php", "icono" => "fa-route"],
        ["titulo" => "Viaje Asignado", "categoria" => "Operaciones", "descripcion" => "Detalles del viaje actual", "url" => "viaje_asignado.php", "icono" => "fa-bus"]
    ];
} elseif ($rolUsuario == 3) { // PASAJERO
    $etiquetaRolHeader = 'Pasajero';$colorRolHeader = 'text-purple-500';
    if ($pagina_titulo === 'pasajero')$submoduloTexto = "Inicio";
    else if ($pagina_titulo === 'viajes_pasajero')$submoduloTexto = "Ver Viajes";
    else if ($pagina_titulo === 'historial_pasajero')$submoduloTexto = "Historial";
    $catalogoOpciones = [
        ["titulo" => "Panel Pasajero", "categoria" => "Principal", "descripcion" => "Resumen de tus viajes", "url" => "pasajero.php", "icono" => "fa-th-large", "permiso" => null],
        ["titulo" => "Ver Viajes Disponibles", "categoria" => "Rutas", "descripcion" => "Rutas, precios y horarios", "url" => "viajes_pasajero.php", "icono" => "fa-bus", "permiso" => null],
        ["titulo" => "Historial de Reservas", "categoria" => "Viajes", "descripcion" => "Histórico de pasajes", "url" => "historial_pasajero.php", "icono" => "fa-history", "permiso" => null],
        ["titulo" => "Calificar Servicio", "categoria" => "Calificaciones", "descripcion" => "Evaluar al conductor", "url" => "calificar.php", "icono" => "fa-star", "permiso" => null]
    ];
}

$opcionesSGET = [];
foreach ($catalogoOpciones as$opcion) {
    if (!isset($opcion['permiso']) OR $opcion['permiso'] === null OR AuthHelper::tienePermiso($conexion, $idUsuarioSesión, $opcion['permiso'])) {
        $opcionesSGET[] =$opcion;
    }
}
?>

<!-- CARGA INICIAL DEL TEMA -->
<script>
    (function() {
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    })();
</script>

<script>window.SGET_LANGUAGE_URL = '../set_language.php'; document.documentElement.setAttribute('data-language', '<?= htmlspecialchars($idiomaActual, ENT_QUOTES, 'UTF-8') ?>');</script>
<script src="../js/i18n.js?v=20260908-1"></script>
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
            <?php echo $etiquetaRolHeader; ?> &nbsp;/&nbsp; <span class="text-slate-900 dark:text-white font-extrabold"><?php echo $submoduloTexto; ?></span>
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
        <!-- BUZÓN DE NOTIFICACIONES (avisos de cancelación de viajes, etc.) -->
        <?php $__noLeidas = 0;
        if (class_exists('NotificacionService') && $idUsuarioSesión > 0) {
            try { $__noLeidas = NotificacionService::noLeidas($idUsuarioSesión); } catch (Throwable $e) { $__noLeidas = 0; }
        } ?>
        <div class="relative shrink-0">
            <button type="button" data-sget-modal="modalNotificaciones"
                    class="w-10 h-10 rounded-2xl bg-slate-200/50 dark:bg-white/5 text-slate-700 dark:text-slate-300
                           hover:bg-sky-500/20 hover:text-sky-500 transition-all flex items-center justify-center
                           border border-slate-300/50 dark:border-white/10 text-sm shadow-sm cursor-pointer relative"
                    title="Notificaciones" aria-label="Notificaciones<?= $__noLeidas ? ', ' . $__noLeidas . ' sin leer' : '' ?>">
                <i class="fas fa-bell text-xs"></i>
                <?php if ($__noLeidas > 0): ?>
                    <span class="sget-badge sget-badge--error"
                          style="position:absolute;top:-.375rem;right:-.375rem;padding:.125rem .375rem;font-size:.5rem;min-width:1.125rem;justify-content:center">
                        <?= $__noLeidas > 9 ? '9+' : $__noLeidas ?>
                    </span>
                <?php endif; ?>
            </button>
        </div>

        <div class="relative group shrink-0">
            <button type="button" data-sget-modal="modalAyuda" class="w-10 h-10 rounded-2xl bg-sky-500/10 text-sky-500 dark:text-sky-400 hover:bg-sky-500/20 border border-sky-500/20 transition-all flex items-center justify-center text-sm shadow-sm cursor-pointer" title="Guía del módulo (F1)">
                <i class="fas fa-question text-xs"></i>
            </button>
        </div>

        <div class="relative shrink-0">
            <select id="headerLanguageSelector" data-sget-language aria-label="Language" style="min-width: 108px;" class="sget-language-selector h-10 px-2 rounded-2xl bg-slate-100 dark:bg-slate-900/80 text-xs font-black text-slate-700 dark:text-slate-200 border border-slate-300/50 dark:border-white/10 cursor-pointer focus:outline-none transition-all">
                <option value="es" <?php echo $idiomaActual === 'es' ? 'selected' : ''; ?>>🇪🇸 ESP</option>
                <option value="en" <?php echo $idiomaActual === 'en' ? 'selected' : ''; ?>>🇺🇸 ENG</option>
            </select>
        </div>

        <!-- BOTÓN DE MODO OSCURO / CLARO -->
        <button id="themeToggle" type="button" class="w-10 h-10 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-amber-400 bg-slate-200/50 dark:bg-white/5 hover:bg-slate-300 dark:hover:bg-white/10 rounded-2xl transition-all border border-slate-300/50 dark:border-white/10 text-sm cursor-pointer" title="Cambiar Tema">
            <i id="themeIcon" class="fas fa-moon text-base"></i>
        </button>

        <div class="hidden md:block text-right">
            <p class="text-xs font-extrabold text-slate-900 dark:text-white leading-tight"><?php echo $nombreRealHeader; ?></p>
            <p class="text-[9px] <?php echo $colorRolHeader; ?> font-black uppercase tracking-widest flex items-center justify-end gap-1 mt-0.5">
                <span class="w-1.5 h-1.5 rounded-full <?php echo str_replace('text', 'bg', $colorRolHeader); ?> inline-block animate-pulse"></span> ONLINE
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

<!-- MODAL BLOQUEO POR INACTIVIDAD -->
<div id="inactivityModal" class="fixed inset-0 bg-black/80 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-[32px] max-w-sm w-full p-6 shadow-2xl text-center space-y-4">
        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center mx-auto text-xl">
            <i class="fas fa-lock"></i>
        </div>
        <h4 class="text-base font-black text-slate-900 dark:text-white">Sesión Bloqueada por Inactividad</h4>
        <p class="text-xs text-slate-500 dark:text-slate-400">Presiona el botón para desbloquear la sesión temporalmente.</p>
        
        <div id="timerContainer" class="text-xs font-bold text-red-500 dark:text-red-400 bg-red-500/10 py-2 px-3 rounded-2xl border border-red-500/20 font-mono">
            Suspensión automática en: <span id="countdownTimer" class="font-extrabold text-sm">60</span> seg
        </div>

        <div id="mensajeErrorModal" class="text-[11px] font-bold text-red-500 hidden bg-red-500/10 p-2 rounded-xl border border-red-500/20"></div>

        <div class="space-y-1 text-left">
            <input type="password" id="passwordConfirm" placeholder="Contraseña (Opcional)" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-sky-500 transition-all">
        </div>

        <button id="btnContinuar" class="w-full py-3 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl transition-all shadow-lg cursor-pointer">
            Desbloquear Sesión
        </button>
    </div>
</div>

<<<<<<< Updated upstream
<!-- SCRIPT GENERAL: CAMBIO DE TEMA, INACTIVIDAD Y BUSCADOR -->
=======
<!-- INCLUSIÓN DEL COMPONENTE DE INACTIVIDAD CENTRALIZADO Y SEGURO -->
<?php @include_once __DIR__ . '/modal_inactividad.php'; ?>

<!-- MODALES DEL SISTEMA (ayuda contextual y buzón de notificaciones).
     Se incluyen desde el header para que existan en TODAS las páginas. -->
<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
if (file_exists(dirname(__DIR__) . '/views/modals/ayuda.php')) {
    include dirname(__DIR__) . '/views/modals/ayuda.php';
}
if (file_exists(dirname(__DIR__) . '/views/modals/notificaciones.php')) {
    include dirname(__DIR__) . '/views/modals/notificaciones.php';
}
?>

<!-- SCRIPT GENERAL DE CONFIGURACIÓN, CAMBIO DE TEMA Y BUSCADOR -->
>>>>>>> Stashed changes
<script>
    document.addEventListener('DOMContentLoaded', () => {
        
        // --------------------------------------------------
        // 1. MANEJADOR DEL BOTÓN DE TEMA (MODO OSCURO / CLARO)
        // --------------------------------------------------
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');

        function updateThemeIcon() {
            if (!themeIcon) return;
            if (document.documentElement.classList.contains('dark')) {
                themeIcon.className = 'fas fa-sun text-base text-amber-400';
            } else {
                themeIcon.className = 'fas fa-moon text-base text-slate-600';
            }
        }

        updateThemeIcon();

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                }
                updateThemeIcon();
            });
        }

        // --------------------------------------------------
        // 2. BLOQUEO POR INACTIVIDAD (DESBLOQUEO LOCAL LIBRE)
        // --------------------------------------------------
        let timeOutInactivity; 
        let countdownInterval;
        let isLocked = false;
        let timeLeft = 60;
        
        const modal = document.getElementById('inactivityModal');
        const timerContainer = document.getElementById('timerContainer');
        const btnContinuar = document.getElementById('btnContinuar');
        const passwordInput = document.getElementById('passwordConfirm');
        const mensajeError = document.getElementById('mensajeErrorModal');

        function resetTimer() {
            if (isLocked) return;
            clearTimeout(timeOutInactivity);
            timeOutInactivity = setTimeout(showInactivityModal, 60000);
        }

        function showInactivityModal() {
            if (!modal) return;
            isLocked = true;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            if (passwordInput) {
                passwordInput.value = '';
                passwordInput.focus();
            }
            if (mensajeError) {
                mensajeError.classList.add('hidden');
            }

            iniciarCuentaRegresiva(60);
        }

        function iniciarCuentaRegresiva(segundos) {
            clearInterval(countdownInterval);
            timeLeft = segundos;

            if (timerContainer) {
                timerContainer.className = "text-xs font-bold text-red-500 dark:text-red-400 bg-red-500/10 py-2 px-3 rounded-2xl border border-red-500/20 font-mono transition-all";
                timerContainer.innerHTML = `Suspensión automática en: <span id="countdownTimer" class="font-extrabold text-sm">${timeLeft}</span> seg`;
            }

            countdownInterval = setInterval(() => {
                timeLeft--;
                const timerSpan = document.getElementById('countdownTimer');
                if (timerSpan) timerSpan.textContent = timeLeft;

                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = '../assets/cerrar.php';
                }
            }, 1000);
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', () => {
                if (isLocked) {
                    iniciarCuentaRegresiva(60);
                }
            });
        }

        // DESBLOQUEO LOCAL INMEDIATO SIN VALIDAR CONTRASEÑA EN SERVIDOR
        function desbloquearSesion() {
            clearInterval(countdownInterval);
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            isLocked = false;
            resetTimer();
        }

        if (btnContinuar) {
            btnContinuar.addEventListener('click', desbloquearSesion);
        }

        if (passwordInput) {
            passwordInput.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') {
                    desbloquearSesion();
                }
            });
        }

        window.onmousemove = resetTimer;
        window.onmousedown = resetTimer; 
        window.onclick = resetTimer;
        window.onscroll = resetTimer;
        window.onkeypress = resetTimer;
        resetTimer();

        // --------------------------------------------------
        // 3. BUSCADOR INTEGRADO EN HEADER
        // --------------------------------------------------
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

                const coincidencias = typeof OPCIONES_SGET !== 'undefined' ? OPCIONES_SGET.filter(item =>
                    item.titulo.toLowerCase().includes(query) ||
                    item.categoria.toLowerCase().includes(query) ||
                    item.descripcion.toLowerCase().includes(query)
                ) : [];

                    contenedorResultados.innerHTML = '';
                if (coincidencias.length === 0) {
                    contenedorResultados.innerHTML = `<div class="p-4 text-center text-xs text-slate-400">Sin resultados</div>`;
                } else {
                    coincidencias.forEach(item => {
                        const a = document.createElement('a');
                        a.href = item.url;
                        a.className = "flex items-center gap-3 p-3 hover:bg-slate-100 dark:hover:bg-white/5 transition-all";
                        a.innerHTML = `<i class="fas ${item.icono} text-sky-500"></i><div><p class="text-xs font-bold text-slate-800 dark:text-white">${item.titulo}</p><p class="text-[10px] text-slate-400">${item.descripcion}</p></div>`;
                        contenedorResultados.appendChild(a);
                    });
                }
                contenedorResultados.classList.remove('hidden');
            });
        }
    });
</script>
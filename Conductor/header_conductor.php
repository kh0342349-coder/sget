<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nombreRealHeader = htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Conductor', ENT_QUOTES, 'UTF-8');
$inicialUsuario = !empty($nombreRealHeader) ? strtoupper(substr($nombreRealHeader, 0, 1)) : 'C';

// Opciones del buscador específicas para el rol Conductor
$opcionesConductor = [
    [
        "titulo"      => "Panel Principal",
        "categoria"   => "Operaciones",
        "descripcion" => "Estado de la ruta actual y turno asignado",
        "url"         => "conductor_panel.php",
        "icono"       => "fa-tachometer-alt"
    ],
    [
        "titulo"      => "Iniciar / Finalizar Ruta",
        "categoria"   => "Viajes",
        "descripcion" => "Iniciar recorrido o marcar llegada a destino",
        "url"         => "viaje_en_curso.php",
        "icono"       => "fa-play-circle"
    ],
    [
        "titulo"      => "Mis Turnos Asignados",
        "categoria"   => "Horarios",
        "descripcion" => "Consultar programación de viajes y buses",
        "url"         => "mis_turnos.php",
        "icono"       => "fa-calendar-alt"
    ],
    [
        "titulo"      => "Reportar Novedad o Incidencia",
        "categoria"   => "Soporte",
        "descripcion" => "Notificar fallas mecánicas, retrasos o tráfico",
        "url"         => "reportar_incidencia.php",
        "icono"       => "fa-exclamation-triangle"
    ],
    [
        "titulo"      => "Mis Calificaciones",
        "categoria"   => "Perfil",
        "descripcion" => "Ver puntajes y comentarios de pasajeros",
        "url"         => "mis_calificaciones.php",
        "icono"       => "fa-star"
    ]
];
?>

<script>
    const OPCIONES_SGET = <?php echo json_encode($opcionesConductor); ?>;
</script>

<!-- HEADER CONDUCTOR -->
<header class="h-20 bg-white/80 dark:bg-[#0b0f19]/80 backdrop-blur-md border-b border-slate-200 dark:border-white/5 flex items-center justify-between px-6 md:px-8 sticky top-0 z-40 transition-colors duration-300">
    
    <!-- LADO IZQUIERDO: Indicador y Buscador -->
    <div class="flex items-center gap-4 flex-1 max-w-md">
        <div class="hidden sm:flex items-center gap-2 shrink-0">
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wider uppercase font-mono">Panel Conductor</span>
        </div>

        <!-- BUSCADOR EN HEADER -->
        <div class="relative w-full">
            <div class="relative flex items-center">
                <i class="fas fa-search absolute left-3.5 text-slate-400 dark:text-slate-500 text-xs pointer-events-none"></i>
                <input 
                    type="text" 
                    id="inputBuscadorHeader" 
                    placeholder="Buscar función... (Ctrl + K)"
                    autocomplete="off"
                    class="w-full pl-9 pr-8 py-2 bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl text-xs text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 transition-all shadow-inner"
                >
                <span id="btnLimpiarBuscador" class="absolute right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs cursor-pointer hidden">
                    <i class="fas fa-times"></i>
                </span>
            </div>

            <div id="resultadosBusquedaHeader" class="absolute top-full left-0 right-0 mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl overflow-hidden hidden z-50 max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
            </div>
        </div>
    </div>

    <!-- LADO DERECHO: Usuario y Controles -->
    <div class="flex items-center space-x-3 md:space-x-5 shrink-0 ml-4">
        <button type="button" id="themeToggle" class="btn-theme-toggle w-10 h-10 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-white/10 rounded-xl transition-all duration-200 flex items-center justify-center cursor-pointer shadow-sm" title="Cambiar Tema">
            <i id="themeIcon" class="fas fa-moon text-base pointer-events-none"></i>
        </button>

        <div class="h-8 w-[1px] bg-slate-200 dark:bg-white/10 hidden sm:block"></div>

        <div class="hidden md:block text-right">
            <p class="text-xs font-bold text-slate-900 dark:text-white leading-tight"><?php echo $nombreRealHeader; ?></p>
            <p class="text-[10px] text-emerald-500 dark:text-emerald-400 font-extrabold uppercase tracking-widest flex items-center justify-end gap-1 mt-0.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 inline-block animate-pulse"></span> Online
            </p>
        </div>
        
        <div class="w-9 h-9 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-md shadow-blue-500/20 shrink-0">
            <?php echo $inicialUsuario; ?>
        </div>

        <a href="../assets/cerrar.php" class="w-10 h-10 bg-slate-100 dark:bg-white/5 hover:bg-red-500/10 text-slate-500 hover:text-red-500 dark:text-slate-400 dark:hover:text-red-400 border border-slate-200 dark:border-white/10 rounded-xl transition-all duration-200 flex items-center justify-center" title="Cerrar Sesión">
            <i class="fas fa-sign-out-alt text-base pointer-events-none"></i> 
        </a>
    </div>
</header>

<!-- MODAL DE ADVERTENCIA POR INACTIVIDAD -->
<div id="inactivityModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl max-w-xs w-full p-4 shadow-xl text-center space-y-3">
        <h4 class="text-base font-bold text-slate-800 dark:text-white">¿Estás ahí?</h4>
        <div class="text-xs font-semibold text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-950/40 py-1.5 px-3 rounded-lg border border-red-200 dark:border-red-900/50">
            Cierre en: <span id="countdownTimer" class="font-bold">30</span> seg
        </div>
        <button id="btnContinuar" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold text-sm rounded-lg transition-colors shadow-md shadow-blue-600/20 cursor-pointer">
            Continuar sesión
        </button>
    </div>
</div>

<script>
(function() {
    // --- GESTIÓN DE TEMA ---
    function actualizarIcono(esOscuro) {
        const iconos = document.querySelectorAll('#themeIcon, .theme-icon');
        iconos.forEach(icon => {
            icon.className = esOscuro ? 'fas fa-sun text-amber-400 text-base pointer-events-none' : 'fas fa-moon text-slate-600 text-base pointer-events-none';
        });
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#themeToggle, #theme-toggle, .btn-theme-toggle');
        if (!btn) return;
        
        e.preventDefault();
        const nuevoEstado = !document.documentElement.classList.contains('dark');
        document.documentElement.classList.toggle('dark', nuevoEstado);
        localStorage.setItem('theme', nuevoEstado ? 'dark' : 'light');
        actualizarIcono(nuevoEstado);
    });

    // --- BUSCADOR INTERACTIVO ---
    document.addEventListener('DOMContentLoaded', function() {
        actualizarIcono(document.documentElement.classList.contains('dark'));

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

                renderizarResultadosBuscador(coincidencias);
            });

            function renderizarResultadosBuscador(lista) {
                contenedorResultados.innerHTML = '';

                if (lista.length === 0) {
                    contenedorResultados.innerHTML = `
                        <div class="p-4 text-center text-xs text-slate-400 dark:text-slate-500">
                            No se encontraron opciones.
                        </div>`;
                    contenedorResultados.classList.remove('hidden');
                    return;
                }

                lista.forEach(item => {
                    const enlace = document.createElement('a');
                    enlace.href = item.url;
                    enlace.className = 'flex items-center gap-3 p-3 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors group';

                    enlace.innerHTML = `
                        <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                            <i class="fas ${item.icono} text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">${item.titulo}</p>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 uppercase">${item.categoria}</span>
                            </div>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5">${item.descripcion}</p>
                        </div>
                    `;
                    contenedorResultados.appendChild(enlace);
                });

                contenedorResultados.classList.remove('hidden');
            }

            document.addEventListener('click', (e) => {
                if (!inputBuscador.contains(e.target) && !contenedorResultados.contains(e.target)) {
                    contenedorResultados.classList.add('hidden');
                }
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
    });

    // --- GESTIÓN DE INACTIVIDAD ---
    const TOTAL_INACTIVITY_TIME = 3 * 60 * 1000;
    const WARNING_TIME = 30 * 1000;
    let inactivityTimer, countdownInterval, timeLeft = 30;

    const modal = document.getElementById('inactivityModal');
    const countdownSpan = document.getElementById('countdownTimer');
    const btnContinuar = document.getElementById('btnContinuar');

    function iniciarTemporizadorInactividad() {
        clearTimeout(inactivityTimer);
        clearInterval(countdownInterval);
        if (modal) modal.classList.add('hidden');
        inactivityTimer = setTimeout(mostrarAdvertenciaCierre, TOTAL_INACTIVITY_TIME - WARNING_TIME);
    }

    function mostrarAdvertenciaCierre() {
        timeLeft = 30;
        if (countdownSpan) countdownSpan.textContent = timeLeft;
        if (modal) modal.classList.remove('hidden');

        countdownInterval = setInterval(() => {
            timeLeft--;
            if (countdownSpan) countdownSpan.textContent = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(countdownInterval);
                window.location.href = "../assets/cerrar.php";
            }
        }, 1000);
    }

    ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart', 'click'].forEach(evento => {
        document.addEventListener(evento, () => {
            if (modal && modal.classList.contains('hidden')) iniciarTemporizadorInactividad();
        }, true);
    });

    if (btnContinuar) btnContinuar.addEventListener('click', iniciarTemporizadorInactividad);

    iniciarTemporizadorInactividad();
})();
</script>
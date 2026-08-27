<?php
// Archivo: header.php
$nombreRealHeader = isset($_SESSION['nombre_usuario']) ? htmlspecialchars($_SESSION['nombre_usuario']) : "Administrador";

$pagina_titulo = basename($_SERVER['PHP_SELF'], '.php');
$submodulo = "Inicio";

if ($pagina_titulo === 'admin') $submodulo = "Inicio";
else if ($pagina_titulo === 'usuarios') $submodulo = "Gestión de Usuarios";
else if ($pagina_titulo === 'asignaciones') $submodulo = "Asignaciones";
else if ($pagina_titulo === 'rutas') $submodulo = "Rutas de Transporte";
else if ($pagina_titulo === 'viajes') $submodulo = "Control de Viajes";
else if ($pagina_titulo === 'vehiculos') $submodulo = "Inventario de Vehículos";
else if ($pagina_titulo === 'ranking_conductores') $submodulo = "Calificaciones";
else if ($pagina_titulo === 'reportes') $submodulo = "Módulo de Reportes";

// Opciones del sistema para el buscador
$opcionesSGET = [
    [
        "titulo"      => "Inicio / Dashboard",
        "categoria"   => "Principal",
        "descripcion" => "Vista general del sistema y métricas",
        "url"         => "admin.php",
        "icono"       => "fa-chart-pie"
    ],
    [
        "titulo"      => "Gestión de Usuarios",
        "categoria"   => "Administración",
        "descripcion" => "Administrar usuarios, roles y accesos",
        "url"         => "usuarios.php",
        "icono"       => "fa-users"
    ],
    [
        "titulo"      => "Asignaciones",
        "categoria"   => "Operaciones",
        "descripcion" => "Asignar vehículos, rutas y conductores",
        "url"         => "asignaciones.php",
        "icono"       => "fa-tasks"
    ],
    [
        "titulo"      => "Rutas de Transporte",
        "categoria"   => "Rutas",
        "descripcion" => "Crear, editar y gestionar trayectos",
        "url"         => "rutas.php",
        "icono"       => "fa-route"
    ],
    [
        "titulo"      => "Control de Viajes",
        "categoria"   => "Operaciones",
        "descripcion" => "Monitoreo y registro de viajes en curso",
        "url"         => "viajes.php",
        "icono"       => "fa-bus-alt"
    ],
    [
        "titulo"      => "Inventario de Vehículos",
        "categoria"   => "Flota",
        "descripcion" => "Estado de la flota, mantenimiento y fichas",
        "url"         => "vehiculos.php",
        "icono"       => "fa-bus"
    ],
    [
        "titulo"      => "Ranking y Calificaciones",
        "categoria"   => "Calidad",
        "descripcion" => "Evaluación y puntajes de conductores",
        "url"         => "ranking_conductores.php",
        "icono"       => "fa-star"
    ],
    [
        "titulo"      => "Módulo de Reportes",
        "categoria"   => "Informes",
        "descripcion" => "Exportar estadísticas e informes generales",
        "url"         => "reportes.php",
        "icono"       => "fa-file-invoice"
    ]
];
?>

<!-- Inyección de opciones a JavaScript -->
<script>
    const OPCIONES_SGET = <?php echo json_encode($opcionesSGET); ?>;
</script>

<header class="h-16 bg-white/80 dark:bg-[#1e293b]/80 backdrop-blur-md border-b border-slate-200 dark:border-white/5 flex items-center justify-between px-6 sticky top-0 z-20 transition-colors duration-300">
    
    <!-- LADO IZQUIERDO: Botón Toggle, Breadcrumb y BUSCADOR -->
    <div class="flex items-center gap-4 flex-1 max-w-xl">
        <!-- BOTÓN TOGGLE -->
        <label for="sidebar-toggle-checkbox" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors shadow-sm cursor-pointer shrink-0" title="Ocultar/Mostrar Menú">
            <i class="fas fa-bars text-lg"></i>
        </label>

        <!-- Breadcrumb -->
        <div class="text-slate-400 dark:text-slate-500 font-medium text-sm tracking-wide hidden lg:block shrink-0">
            Dashboard &nbsp;/&nbsp; <span class="text-slate-800 dark:text-white font-semibold"><?php echo $submodulo; ?></span>
        </div>

        <!-- BUSCADOR GLOBAL (HEADER) -->
        <div class="relative w-full max-w-xs md:max-w-sm ml-2">
            <div class="relative flex items-center">
                <i class="fas fa-search absolute left-3.5 text-slate-400 dark:text-slate-500 text-sm pointer-events-none"></i>
                <input 
                    type="text" 
                    id="inputBuscadorHeader" 
                    placeholder="Buscar función... (Ctrl + K)"
                    autocomplete="off"
                    class="w-full pl-9 pr-8 py-2 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 rounded-xl text-xs sm:text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 dark:focus:border-blue-500 transition-all shadow-inner"
                >
                <span id="btnLimpiarBuscador" class="absolute right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs cursor-pointer hidden">
                    <i class="fas fa-times"></i>
                </span>
            </div>

            <!-- RESULTADOS DE BÚSQUEDA -->
            <div id="resultadosBusquedaHeader" class="absolute top-full left-0 right-0 mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl overflow-hidden hidden z-50 max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/50">
            </div>
        </div>
    </div>

    <!-- LADO DERECHO: Acciones y Perfil -->
    <div class="flex items-center space-x-3 sm:space-x-6 shrink-0">
        <button id="themeToggle" class="text-slate-400 hover:text-amber-400 p-2 hover:bg-slate-100 dark:hover:bg-white/5 rounded-xl transition-all duration-200 text-sm" title="Cambiar Tema">
            <i id="themeIcon" class="fas fa-moon text-base"></i>
        </button>

        <div class="hidden md:block text-right">
            <p class="text-sm font-bold text-slate-800 dark:text-white"><?php echo $nombreRealHeader; ?></p>
            <p class="text-[10px] text-emerald-500 dark:text-emerald-400 font-extrabold uppercase tracking-widest flex items-center justify-end gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 inline-block animate-pulse"></span> Online
            </p>
        </div>
        
        <div class="w-10 h-10 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-full flex items-center justify-center text-white font-bold text-base shadow-md shadow-blue-500/20 shrink-0">
            <?php echo strtoupper(substr($nombreRealHeader, 0, 1)); ?>
        </div>

        <a href="../assets/cerrar.php" class="flex items-center space-x-1 text-slate-400 hover:text-red-400 p-2 hover:bg-slate-100 dark:hover:bg-white/5 rounded-xl transition-all duration-200 text-sm" title="Cerrar Sesión">
            <i class="fas fa-sign-out-alt text-base"></i> 
        </a>
    </div>
</header>

<!-- MODAL DE ADVERTENCIA POR INACTIVIDAD -->
<div id="inactivityModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl max-w-xs w-full p-4 shadow-xl text-center space-y-3">
        <h4 class="text-base font-bold text-slate-800 dark:text-white">
            ¿Estás ahí?
        </h4>

        <div class="text-xs font-semibold text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-950/40 py-1.5 px-3 rounded-lg border border-red-200 dark:border-red-900/50">
            Cierre en: <span id="countdownTimer" class="font-bold">30</span> seg
        </div>

        <button id="btnContinuar" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold text-sm rounded-lg transition-colors shadow-md shadow-blue-600/20 cursor-pointer">
            Continuar sesión
        </button>
    </div>
</div>

<script>
    // --- LÓGICA DE TEMA (MODO OSCURO/CLARO) ---
    const themeToggleBtn = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    function actualizarIcono(isDark) {
        if (themeIcon) {
            themeIcon.className = isDark ? "fas fa-sun text-base text-amber-400" : "fas fa-moon text-base text-slate-600";
        }
    }

    actualizarIcono(document.documentElement.classList.contains('dark'));

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const esOscuro = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', esOscuro ? 'dark' : 'light');
            actualizarIcono(esOscuro);
            
            if (typeof chartInstance !== 'undefined') {
                chartInstance.options.datasets[0].borderColor = esOscuro ? '#1e293b' : '#ffffff';
                chartInstance.update();
            }
        });
    }

    // --- LÓGICA DEL BUSCADOR DE FUNCIONES ---
    document.addEventListener('DOMContentLoaded', () => {
        const inputBuscador = document.getElementById('inputBuscadorHeader');
        const contenedorResultados = document.getElementById('resultadosBusquedaHeader');
        const btnLimpiar = document.getElementById('btnLimpiarBuscador');

        if (inputBuscador && contenedorResultados) {
            // Atajo de teclado: Ctrl + K
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    inputBuscador.focus();
                }
            });

            // Filtrado interactivo
            inputBuscador.addEventListener('input', function () {
                const query = this.value.trim().toLowerCase();

                if (btnLimpiar) {
                    if (query.length > 0) btnLimpiar.classList.remove('hidden');
                    else btnLimpiar.classList.add('hidden');
                }

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
                            No se encontraron funciones asociadas.
                        </div>`;
                    contenedorResultados.classList.remove('hidden');
                    return;
                }

                lista.forEach(item => {
                    const enlace = document.createElement('a');
                    enlace.href = item.url;
                    enlace.className = 'flex items-center gap-3 p-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group';

                    enlace.innerHTML = `
                        <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                            <i class="fas ${item.icono} text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">${item.titulo}</p>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 uppercase tracking-wider">${item.categoria}</span>
                            </div>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5">${item.descripcion}</p>
                        </div>
                    `;
                    contenedorResultados.appendChild(enlace);
                });

                contenedorResultados.classList.remove('hidden');
            }

            // Ocultar desplegable al hacer clic fuera
            document.addEventListener('click', (e) => {
                if (!inputBuscador.contains(e.target) && !contenedorResultados.contains(e.target)) {
                    contenedorResultados.classList.add('hidden');
                }
            });

            // Botón de limpiar input
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

    // --- LÓGICA DE INACTIVIDAD Y CUENTA REGRESIVA ---
    const TOTAL_INACTIVITY_TIME = 3 * 60 * 1000; // 3 minutos
    const WARNING_TIME = 30 * 1000;              // 30 segundos

    let inactivityTimer;
    let countdownInterval;
    let timeLeft = 30;

    const modal = document.getElementById('inactivityModal');
    const countdownSpan = document.getElementById('countdownTimer');
    const btnContinuar = document.getElementById('btnContinuar');

    function iniciarTemporizadorInactividad() {
        clearTimeout(inactivityTimer);
        clearInterval(countdownInterval);
        
        if (modal) modal.classList.add('hidden');
        
        inactivityTimer = setTimeout(() => {
            mostrarAdvertenciaCierre();
        }, TOTAL_INACTIVITY_TIME - WARNING_TIME);
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

    const eventosUsuario = ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart', 'click'];

    eventosUsuario.forEach(evento => {
        document.addEventListener(evento, () => {
            if (modal && modal.classList.contains('hidden')) {
                iniciarTemporizadorInactividad();
            }
        }, true);
    });

    if (btnContinuar) {
        btnContinuar.addEventListener('click', () => {
            iniciarTemporizadorInactividad();
        });
    }

    iniciarTemporizadorInactividad();
</script>
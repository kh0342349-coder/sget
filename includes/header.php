<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conexion)) {
    include_once __DIR__ . '/../assets/conexion.php';
}

// PROCESAR ACTUALIZACIÓN DEL PERFIL DE USUARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_perfil']) && $_POST['accion_perfil'] === 'actualizar_configuracion') {$id_usuario_config = intval($_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0);
    $nuevo_nombre = trim($_POST['nom_usu'] ?? '');
    $nuevo_correo = trim($_POST['corre_usu'] ?? '');
    $nuevo_pass   = trim($_POST['pass_usu'] ?? '');

    if ($id_usuario_config > 0 && !empty($nuevo_nombre) && !empty($nuevo_correo)) {
        if (!empty($nuevo_pass)) {
            // Se encripta la nueva contraseña
            $pass_hash = password_hash($nuevo_pass, PASSWORD_DEFAULT);
            $stmtUpdUser =$conexion->prepare("UPDATE usuario SET nom_usu = ?, corre_usu = ?, pass_usu = ? WHERE id_usu = ?");
            if ($stmtUpdUser) {$stmtUpdUser->bind_param("sssi", $nuevo_nombre,$nuevo_correo, $pass_hash,$id_usuario_config);
                $stmtUpdUser->execute();$stmtUpdUser->close();
            }
        } else {
            // Se actualiza únicamente el nombre y el correo
            $stmtUpdUser =$conexion->prepare("UPDATE usuario SET nom_usu = ?, corre_usu = ? WHERE id_usu = ?");
            if ($stmtUpdUser) {
                $stmtUpdUser->bind_param("ssi", $nuevo_nombre, $nuevo_correo,$id_usuario_config);
                $stmtUpdUser->execute();$stmtUpdUser->close();
            }
        }

        // Actualizar datos de la sesión actual
        $_SESSION['nombre_usuario'] =$nuevo_nombre;
        $_SESSION['corre_usu'] =$nuevo_correo;

        // Recargar la página actual conservando los parámetros GET
        $redirectUrl =$_SERVER['PHP_SELF'];
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'],$queryParams);
            unset($queryParams['config_status']);$queryParams['config_status'] = 'success';
            $redirectUrl .= '?' . http_build_query($queryParams);
        } else {
            $redirectUrl .= '?config_status=success';
        }

        echo "<script>window.location.href = '" . $redirectUrl . "';</script>";
        exit();
    }
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
$idUsuarioSesión = intval($_SESSION['id_usu'] ?? $_SESSION['user_id'] ?? 0);

// Obtener datos actualizados del usuario desde MySQL
$user_nombre_header =$_SESSION['nombre_usuario'] ?? 'Usuario SGET';
$user_correo_header =$_SESSION['corre_usu'] ?? '';

if ($idUsuarioSesión > 0 && isset($conexion) &&$conexion) {
    $resUserHeader =$conexion->query("SELECT nom_usu, corre_usu FROM usuario WHERE id_usu = $idUsuarioSesión");
    if ($resUserHeader &&$resUserHeader->num_rows > 0) {
        $rowHeader =$resUserHeader->fetch_assoc();
        $user_nombre_header =$rowHeader['nom_usu'];
        $user_correo_header =$rowHeader['corre_usu'];
    }
}

$nombreRealHeader = htmlspecialchars($user_nombre_header, ENT_QUOTES, 'UTF-8');$inicialUsuario = !empty($nombreRealHeader) ? strtoupper(substr($nombreRealHeader, 0, 1)) : 'U';

// OBTENER LA FOTO DE PERFIL DESDE LA SESIÓN
$fotoPerfilUsuario =$_SESSION['foto_usuario'] ?? '';

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
        <div class="relative group shrink-0">
            <button type="button" onclick="abrirModalAyuda()" class="w-10 h-10 rounded-2xl bg-sky-500/10 text-sky-500 dark:text-sky-400 hover:bg-sky-500/20 border border-sky-500/20 transition-all flex items-center justify-center text-sm shadow-sm cursor-pointer" title="Guía del módulo (F1)">
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

        <!-- TUERCA DE CONFIGURACIÓN DE CUENTA -->
        <button type="button" onclick="abrirModalConfigCuenta()" class="w-10 h-10 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-sky-500 hover:rotate-45 bg-slate-200/50 dark:bg-white/5 hover:bg-slate-300 dark:hover:bg-white/10 rounded-2xl transition-all border border-slate-300/50 dark:border-white/10 text-sm cursor-pointer" title="Configurar Cuenta">
            <i class="fas fa-cog text-base"></i>
        </button>

        <div class="hidden md:block text-right">
            <p class="text-xs font-extrabold text-slate-900 dark:text-white leading-tight"><?php echo $nombreRealHeader; ?></p>
            <p class="text-[9px] <?php echo $colorRolHeader; ?> font-black uppercase tracking-widest flex items-center justify-end gap-1 mt-0.5">
                <span class="w-1.5 h-1.5 rounded-full <?php echo str_replace('text', 'bg', $colorRolHeader); ?> inline-block animate-pulse"></span> ONLINE
            </p>
        </div>
        
        <!-- FOTO DE PERFIL / INICIAL DEL USUARIO -->
        <?php if (!empty($fotoPerfilUsuario)): ?>
            <img src="<?php echo htmlspecialchars($fotoPerfilUsuario, ENT_QUOTES, 'UTF-8'); ?>" 
                 alt="Foto de perfil" 
                 referrerpolicy="no-referrer"
                 class="w-10 h-10 rounded-2xl object-cover shadow-md border border-sky-500/30 shrink-0">
        <?php else: ?>
            <div class="w-10 h-10 bg-gradient-to-tr from-sky-400 via-blue-500 to-purple-600 rounded-2xl flex items-center justify-center text-slate-950 font-black text-sm shadow-md shadow-sky-500/20 shrink-0">
                <?php echo $inicialUsuario; ?>
            </div>
        <?php endif; ?>

        <a href="../assets/cerrar.php" class="w-10 h-10 flex items-center justify-center text-slate-500 hover:text-red-500 bg-slate-200/50 dark:bg-white/5 hover:bg-red-500/10 rounded-2xl transition-all border border-slate-300/50 dark:border-white/10 text-sm" title="Cerrar Sesión">
            <i class="fas fa-sign-out-alt text-base"></i> 
        </a>
    </div>
</header>

<!-- NOTIFICACIÓN DE ÉXITO -->
<?php if (isset($_GET['config_status']) &&$_GET['config_status'] === 'success'): ?>
<div id="toastConfigSuccess" class="fixed bottom-6 right-6 z-[120] bg-emerald-500 text-slate-950 font-black text-xs px-5 py-3.5 rounded-2xl shadow-2xl flex items-center gap-3 transition-all">
    <i class="fas fa-check-circle text-base"></i>
    <span>¡Información de la cuenta actualizada correctamente!</span>
</div>
<script>
    setTimeout(() => {
        const toast = document.getElementById('toastConfigSuccess');
        if (toast) toast.remove();
    }, 4000);
</script>
<?php endif; ?>

<!-- MODAL DE CONFIGURACIÓN DE CUENTA FUNCIONAL -->
<div id="overlayConfigCuenta" onclick="cerrarModalConfigCuenta()" class="fixed inset-0 bg-black/80 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">
    <div onclick="event.stopPropagation()" class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-[32px] max-w-md w-full p-6 shadow-2xl space-y-5 relative">
        
        <div class="flex justify-between items-center border-b border-slate-200 dark:border-white/10 pb-4">
            <h3 class="font-extrabold text-slate-900 dark:text-white text-base flex items-center gap-2">
                <i class="fas fa-user-cog text-sky-500"></i> Configuración de Cuenta
            </h3>
            <button type="button" onclick="cerrarModalConfigCuenta()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" class="space-y-4">
            <input type="hidden" name="accion_perfil" value="actualizar_configuracion">

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nombre Completo</label>
                <div class="relative">
                    <i class="fas fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="nom_usu" value="<?php echo htmlspecialchars($user_nombre_header, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full pl-9 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs focus:outline-none focus:border-sky-500 text-slate-900 dark:text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Correo Electrónico</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="email" name="corre_usu" value="<?php echo htmlspecialchars($user_correo_header, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full pl-9 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs focus:outline-none focus:border-sky-500 text-slate-900 dark:text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nueva Contraseña <span class="text-slate-400 font-normal">(Opcional)</span></label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="password" name="pass_usu" placeholder="Déjala en blanco para mantener la actual" class="w-full pl-9 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs focus:outline-none focus:border-sky-500 text-slate-900 dark:text-white">
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 dark:border-white/10 flex gap-3">
                <button type="button" onclick="cerrarModalConfigCuenta()" class="flex-1 py-3 bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-slate-200 rounded-2xl text-xs font-bold uppercase tracking-wider transition-all cursor-pointer">Cancelar</button>
                <button type="submit" class="flex-1 py-3 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black rounded-2xl text-xs uppercase tracking-wider shadow-lg shadow-sky-500/20 transition-all cursor-pointer">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- INCLUSIÓN DEL COMPONENTE DE INACTIVIDAD CENTRALIZADO Y SEGURO -->
<?php @include_once __DIR__ . '/modal_inactividad.php'; ?>

<!-- SCRIPT GENERAL DE CONFIGURACIÓN, CAMBIO DE TEMA Y BUSCADOR -->
<script>
    function abrirModalConfigCuenta() {
        const modal = document.getElementById('overlayConfigCuenta');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function cerrarModalConfigCuenta() {
        const modal = document.getElementById('overlayConfigCuenta');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        
        // 1. MANEJADOR DEL BOTÓN DE TEMA (MODO OSCURO / CLARO)
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

        // 2. BUSCADOR INTEGRADO EN HEADER
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
<?php
/**
 * includes/header.php
 * -----------------------------------------------------------------------------
 * CABECERA FLOTANTE DEL PANEL INTERNO
 * -----------------------------------------------------------------------------
 * · Arranca por `core/bootstrap.php`: una sola sesión, una sola conexión y una
 *   sola política de autorización para todo el sistema (antes cada partial
 *   abría la suya con `session_start()` + mysqli).
 * · El guardado del perfil se hace AQUÍ, en el servidor, con token anti-CSRF y
 *   validación: antes era SQL suelto dentro del propio header, un archivo que se
 *   incluye en todas las pantallas.
 * · Los datos del usuario salen de la sesión y de una consulta preparada, nunca
 *   de `$_POST` ni de una interpolación dentro del SQL.
 * -----------------------------------------------------------------------------
 */
if (!class_exists('Auth')) {
    require_once dirname(__DIR__) . '/core/bootstrap.php';
}

/* --------------------------------------------------------------------------
 * ACTUALIZACIÓN DEL PERFIL DE CUENTA (POST → PRG)
 * ------------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['accion_perfil'] ?? '') === 'actualizar_configuracion') {

    // Token anti-CSRF: sin esto, cualquier página externa podía cambiar el
    // nombre, el correo o la contraseña de la sesión con un POST automático.
    if (!Auth::validarToken((string)($_POST['_token'] ?? ''))) {
        Flash::error('La sesión del formulario caducó. Vuelve a intentarlo.');
        sget_redirigir($_SERVER['PHP_SELF'] ?? Config::basePath() . '/index.php');
    }

    $idUsuario   = Auth::id();
    $nuevoNombre = trim((string)($_POST['nom_usu'] ?? ''));
    $nuevoCorreo = trim((string)($_POST['corre_usu'] ?? ''));
    $nuevaClave  = (string)($_POST['pass_usu'] ?? '');

    $v = Validator::de($_POST)
        ->requerido('nom_usu', 'el nombre')
        ->requerido('corre_usu', 'el correo');

    $v->texto('nom_usu', 'El nombre completo', 3, 100);
    $v->email('corre_usu', 'El correo electrónico');

    // El correo es ÚNICO en la base de datos: se avisa antes de que reviente
    // la restricción, con un mensaje que el usuario entiende.
    $correoDeOtro = Database::scalar(
        'SELECT id_usu FROM usuario WHERE LOWER(corre_usu) = ? AND id_usu <> ? LIMIT 1',
        [mb_strtolower($nuevoCorreo), $idUsuario]
    );
    $v->agregaSi($correoDeOtro !== null, 'corre_usu', 'Ese correo ya está registrado por otra cuenta.');

    if ($nuevaClave !== '') {
        $errorClave = Password::validar($nuevaClave, 'La contraseña');
        if ($errorClave) {
            $v->agrega('pass_usu', $errorClave);
        }
    }

    if ($v->falla()) {
        Flash::error($v->primerError() ?? 'Revisa los datos del formulario.');
        sget_redirigir($_SERVER['PHP_SELF'] ?? Config::basePath() . '/index.php');
    }

    if ($nuevaClave !== '') {
        Database::query(
            'UPDATE usuario SET nom_usu = ?, corre_usu = ?, pass_usu = ? WHERE id_usu = ?',
            [$nuevoNombre, $nuevoCorreo, Password::hash($nuevaClave), $idUsuario]
        );
        Logger::registrar(Database::pdo(), 'CAMBIAR_CONTRASENA',
            'El usuario #' . $idUsuario . ' cambió su contraseña desde la configuración de cuenta.');
    } else {
        Database::query(
            'UPDATE usuario SET nom_usu = ?, corre_usu = ? WHERE id_usu = ?',
            [$nuevoNombre, $nuevoCorreo, $idUsuario]
        );
    }

    Logger::registrar(Database::pdo(), 'EDITAR_PERFIL',
        'El usuario #' . $idUsuario . ' actualizó sus datos de cuenta.');

    // La sesión se sincroniza con los datos nuevos (si no, la cabecera seguía
    // mostrando el nombre anterior hasta el siguiente ingreso).
    $_SESSION['nombre_usuario'] = $nuevoNombre;
    $_SESSION['corre_usu']       = $nuevoCorreo;

    Flash::exito('Información de la cuenta actualizada correctamente.');
    sget_redirigir($_SERVER['PHP_SELF'] ?? Config::basePath() . '/index.php');
}

if (isset($_POST['idioma']) && in_array($_POST['idioma'], ['es', 'en'], true)) {
    $_SESSION['sget_idioma'] = $_POST['idioma'];
}

// Idioma + diccionario: partial único e idempotente (antes se repetía a mano en
// header, sidebar y header_index, y aquí el <script> quedó sin abrir, así que se
// imprimía código JavaScript como texto encima de la cabecera).
require_once __DIR__ . '/i18n.php';

$rolUsuario      = Auth::rol();
$idUsuarioSesión = Auth::id();

// Datos del usuario para la cabecera. Se leen de la sesión (que ya se sincronizó
// al guardar el perfil) y se refrescan con una consulta preparada.
$user_nombre_header = Auth::nombre();
$user_correo_header = (string)($_SESSION['corre_usu'] ?? '');

if ($idUsuarioSesión > 0) {
    $filaUsuario = Database::one(
        'SELECT nom_usu, corre_usu FROM usuario WHERE id_usu = ?',
        [$idUsuarioSesión]
    );
    if ($filaUsuario) {
        $user_nombre_header = (string)$filaUsuario['nom_usu'];
        $user_correo_header = (string)$filaUsuario['corre_usu'];
    }
}

$nombreRealHeader = htmlspecialchars($user_nombre_header, ENT_QUOTES, 'UTF-8');
$inicialUsuario   = mb_strtoupper(mb_substr($user_nombre_header, 0, 1)) ?: 'U';

// FOTO DE PERFIL DESDE LA SESIÓN
$fotoPerfilUsuario = (string)($_SESSION['foto_usuario'] ?? '');

$pagina_titulo  = basename($_SERVER['PHP_SELF'], '.php');
$submoduloTexto = 'Inicio';

$catalogoOpciones  = [];
$etiquetaRolHeader = 'Usuario';
$colorRolHeader    = 'text-slate-500';

if ($rolUsuario == Config::ROL_ADMIN) {
    $etiquetaRolHeader = 'Administrador';
    $colorRolHeader    = 'text-sky-500';
    $submoduloTexto    = str_replace('_', ' ', ucfirst($pagina_titulo));

    /* Cada entrada declara el PERMISO que exige: el buscador global solo ofrece
       lo que el usuario tiene concedido de verdad, y todas las `url` apuntan a
       módulos que existen (antes una de ellas iba a `viajes_3.php`, inexistente). */
    $catalogoOpciones = [
        ["titulo" => "Inicio / Dashboard",          "categoria" => "Principal",    "descripcion" => "Vista general del sistema",          "url" => "admin.php",              "icono" => "fa-chart-pie",     "permiso" => "admin"],
        ["titulo" => "Gestión de Usuarios",         "categoria" => "Admin",        "descripcion" => "Usuarios y roles",                    "url" => "usuarios.php",           "icono" => "fa-users",         "permiso" => "usuarios"],
        ["titulo" => "Gestión de Permisos",         "categoria" => "Admin",        "descripcion" => "Asignar funciones al personal",       "url" => "gestion_permisos.php",   "icono" => "fa-key",            "permiso" => "gestion_permisos"],
        ["titulo" => "Rutas de Transporte",         "categoria" => "Operaciones",  "descripcion" => "Gestión de trayectos",                "url" => "rutas.php",              "icono" => "fa-route",         "permiso" => "rutas"],
        ["titulo" => "Control de Viajes",           "categoria" => "Operaciones",  "descripcion" => "Programación y monitoreo",            "url" => "viajes.php",             "icono" => "fa-calendar-alt",   "permiso" => "viajes"],
        ["titulo" => "Flota de Vehículos",          "categoria" => "Operaciones",  "descripcion" => "Unidades y disponibilidad",           "url" => "vehiculos.php",          "icono" => "fa-bus",            "permiso" => "vehiculos"],
        ["titulo" => "Recaudo y Abordaje",          "categoria" => "Operaciones",  "descripcion" => "Cobro y manifiesto de pasajeros",      "url" => "asignaciones.php",       "icono" => "fa-cash-register",  "permiso" => "asignaciones"],
        ["titulo" => "Anuncios de la Landing",      "categoria" => "Comunicación", "descripcion" => "Promociones y avisos de la portada",  "url" => "anuncios.php",           "icono" => "fa-images",        "permiso" => "anuncios"],
        ["titulo" => "Comunicados",                 "categoria" => "Comunicación", "descripcion" => "Avisos para pasajeros y conductores", "url" => "comunicados.php",       "icono" => "fa-bullhorn",      "permiso" => "comunicados"],
        ["titulo" => "Panel de Información",        "categoria" => "Informes",     "descripcion" => "Métricas e informes del negocio",     "url" => "reportes.php",           "icono" => "fa-chart-column",   "permiso" => "reportes"],
        ["titulo" => "Calificaciones",              "categoria" => "Informes",     "descripcion" => "Rendimiento de los conductores",       "url" => "ranking_conductores.php","icono" => "fa-star",           "permiso" => "ranking_conductores"],
        ["titulo" => "Logs de Auditoría",           "categoria" => "Informes",     "descripcion" => "Trazabilidad de cada acción",         "url" => "logs.php",               "icono" => "fa-file-alt",       "permiso" => "logs"],
    ];
} elseif ($rolUsuario == Config::ROL_CONDUCTOR) {
    $etiquetaRolHeader = 'Conductor';
    $colorRolHeader    = 'text-emerald-500';
    if ($pagina_titulo === 'conductor' || $pagina_titulo === 'dashboard_conductor') $submoduloTexto = "Inicio";
    else if ($pagina_titulo === 'viajes_conductor')  $submoduloTexto = "Mis Viajes";
    else if ($pagina_titulo === 'viaje_asignado')    $submoduloTexto = "Viaje Asignado";
    else if ($pagina_titulo === 'resenas_conductor') $submoduloTexto = "Mis Reseñas";
    $catalogoOpciones = [
        ["titulo" => "Dashboard",      "categoria" => "Principal",   "descripcion" => "Métricas de tu jornada",      "url" => "conductor.php",         "icono" => "fa-chart-pie", "permiso" => null],
        ["titulo" => "Mis Viajes",     "categoria" => "Rutas",       "descripcion" => "Consulta de viajes",           "url" => "viajes_conductor.php",  "icono" => "fa-route",     "permiso" => null],
        ["titulo" => "Viaje Asignado", "categoria" => "Operaciones", "descripcion" => "Detalles del viaje actual",   "url" => "viaje_asignado.php",    "icono" => "fa-bus",       "permiso" => null],
        ["titulo" => "Mis Reseñas",    "categoria" => "Operaciones", "descripcion" => "Lo que dicen los pasajeros",  "url" => "resenas_conductor.php", "icono" => "fa-star",      "permiso" => null],
    ];
} elseif ($rolUsuario == Config::ROL_PASAJERO) {
    $etiquetaRolHeader = 'Pasajero';
    $colorRolHeader    = 'text-purple-500';
    if ($pagina_titulo === 'pasajero')          $submoduloTexto = "Inicio";
    else if ($pagina_titulo === 'viajes_pasajero')    $submoduloTexto = "Ver Viajes";
    else if ($pagina_titulo === 'historial_pasajero') $submoduloTexto = "Historial";
    else if ($pagina_titulo === 'calificar')          $submoduloTexto = "Calificaciones";
    $catalogoOpciones = [
        ["titulo" => "Panel Pasajero",          "categoria" => "Principal",      "descripcion" => "Resumen de tus viajes",       "url" => "pasajero.php",          "icono" => "fa-th-large", "permiso" => null],
        ["titulo" => "Ver Viajes Disponibles",  "categoria" => "Rutas",          "descripcion" => "Rutas, precios y horarios",   "url" => "viajes_pasajero.php",   "icono" => "fa-bus",       "permiso" => null],
        ["titulo" => "Historial de Reservas",  "categoria" => "Viajes",         "descripcion" => "Histórico de pasajes",         "url" => "historial_pasajero.php","icono" => "fa-history",  "permiso" => null],
        ["titulo" => "Calificar Servicio",      "categoria" => "Calificaciones","descripcion" => "Evaluar al conductor",        "url" => "calificar.php",         "icono" => "fa-star",      "permiso" => null],
    ];
}

/* Catálogo efectivo: se descartan las opciones cuyo permiso no esté concedido.
   Con `permiso => null` la opción es siempre visible: son las pantallas propias
   del rol, ya protegidas por `Auth::requerirRol` en cada archivo. */
$opcionesSGET = [];
foreach ($catalogoOpciones as $opcion) {
    if (empty($opcion['permiso']) || Auth::tieneAcceso((string) $opcion['permiso'])) {
        $opcionesSGET[] = $opcion;
    }
}
?>

<script>
    window.SGET_CSRF = <?= json_encode(Auth::token()) ?>;
</script>
<script>
    const OPCIONES_SGET = <?php echo json_encode($opcionesSGET); ?>;
</script>

<!-- HEADER FLOTANTE TIPO CÁPSULA -->
<header class="header-floating h-16 bg-white/80 dark:bg-[#0f172a]/80 backdrop-blur-xl border border-slate-200/90 dark:border-white/10 rounded-[24px] flex items-center justify-between px-6 sticky top-4 z-40 my-4 shadow-xl relative transition-all duration-300">
    
    <div class="header-floating__inicio flex items-center gap-4 flex-1 max-w-xl">
        <button id="btnToggleSidebar" type="button" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-slate-200/50 dark:bg-white/5 text-slate-700 dark:text-slate-300 hover:bg-sky-500/20 hover:text-sky-500 transition-all border border-slate-300/50 dark:border-white/10 cursor-pointer shrink-0">
            <i class="fas fa-bars text-sm"></i>
        </button>

        <div class="text-slate-500 dark:text-slate-400 font-medium text-xs tracking-wide hidden lg:block shrink-0">
            <?php echo $etiquetaRolHeader; ?> &nbsp;/&nbsp; <span class="text-slate-900 dark:text-white font-extrabold"><?php echo $submoduloTexto; ?></span>
        </div>

        <?php /* El buscador global solo se muestra si realmente hay algo que buscar.
                 Conductor y Pasajero tienen 3-4 pantallas: un buscador vacío
                 empujaba el contenido y obligaba a desplazarse. */ ?>
        <?php if (count($opcionesSGET) >= 5): ?>
        <div class="relative w-full max-w-xs md:max-w-sm ml-1">
            <div class="relative flex items-center">
                <i class="fas fa-search absolute left-4 text-slate-400 text-xs pointer-events-none"></i>
                <input type="text" id="inputBuscadorHeader" value="" placeholder="Buscar función... (Ctrl + K)" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" class="w-full pl-10 pr-8 py-2.5 bg-slate-100 dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 rounded-full text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-sky-500 transition-all shadow-inner">
                <span id="btnLimpiarBuscador" class="absolute right-3.5 text-slate-400 hover:text-sky-500 text-xs cursor-pointer hidden"><i class="fas fa-times"></i></span>
            </div>
            <div id="resultadosBusquedaHeader" class="absolute top-full left-0 right-0 mt-3 bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-3xl shadow-2xl overflow-hidden hidden z-50 max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-white/5 custom-scrollbar"></div>
        </div>
        <?php else: ?>
        <span class="hidden md:inline-flex items-center gap-1.5 text-slate-500 dark:text-slate-400 text-xs font-semibold truncate max-w-[14rem]">
            <i class="fas fa-map-marker-alt text-sky-400"></i>
            <?= htmlspecialchars($submoduloTexto, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php endif; ?>
    </div>

    <div class="header-floating__acciones flex items-center gap-3 sm:gap-4 shrink-0">
        <!-- BUZÓN DE NOTIFICACIONES (avisos de cancelación de viajes, etc.) -->
        <?php $__noLeidas = 0;
        if (class_exists('NotificacionService') && $idUsuarioSesión > 0) {
            try { $__noLeidas = NotificacionService::noLeidas($idUsuarioSesión); } catch (Throwable $e) { $__noLeidas = 0; }
        } ?>
        <div class="relative shrink-0">
            <button type="button" data-sget-modal="modalNotificaciones" data-sget-noti-bell
                    class="w-10 h-10 rounded-2xl bg-slate-200/50 dark:bg-white/5 text-slate-700 dark:text-slate-300
                           hover:bg-sky-500/20 hover:text-sky-500 transition-all flex items-center justify-center
                           border border-slate-300/50 dark:border-white/10 text-sm shadow-sm cursor-pointer relative"
                    title="Notificaciones" aria-label="Notificaciones<?= $__noLeidas ? ', ' . $__noLeidas . ' sin leer' : '' ?>">
                <i class="fas fa-bell text-xs"></i>
                <span class="sget-badge sget-badge--error" data-sget-noti-contador
                      style="position:absolute;top:-.375rem;right:-.375rem;padding:.125rem .375rem;font-size:.5rem;min-width:1.125rem;justify-content:center"
                      <?= $__noLeidas > 0 ? '' : 'hidden' ?>><?= $__noLeidas > 9 ? '9+' : $__noLeidas ?></span>
            </button>
        </div>

        <div class="relative shrink-0">
            <?php // El selector envía a set_language.php con un POST real: si i18n.js
                   // falla o el navegador tiene el JS desactivado, cambiar el idioma
                   // sigue funcionando. Con JS, i18n.js lo intercepta y no recarga. ?>
            <form method="POST" action="<?= Config::basePath() ?>/set_language.php" class="contents" data-sget-idioma-form>
                <?= Auth::campoToken() ?>
                <input type="hidden" name="idioma" value="<?= htmlspecialchars($idiomaActual, ENT_QUOTES, 'UTF-8') ?>">
                <select id="headerLanguageSelector" data-sget-language name="idioma" aria-label="Idioma / Language"
                        title="Cambiar idioma"
                        class="sget-language-selector h-10 px-2 rounded-2xl bg-slate-100 dark:bg-slate-900/80 text-xs font-black
                               text-slate-700 dark:text-slate-200 border border-slate-300/50 dark:border-white/10
                               cursor-pointer focus:outline-none transition-all">
                    <option value="es" <?= $idiomaActual === 'es' ? 'selected' : ''; ?>>🇪🇸 ESP</option>
                    <option value="en" <?= $idiomaActual === 'en' ? 'selected' : ''; ?>>🇺🇸 ENG</option>
                </select>
                <noscript><button type="submit" class="sget-btn sget-btn--sm" style="margin-left:.5rem">Aplicar</button></noscript>
            </form>
        </div>

        <!-- BOTÓN DE MODO OSCURO / CLARO -->
        <button id="themeToggle" type="button" class="w-10 h-10 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-amber-400 bg-slate-200/50 dark:bg-white/5 hover:bg-slate-300 dark:hover:bg-white/10 rounded-2xl transition-all border border-slate-300/50 dark:border-white/10 text-sm cursor-pointer" title="Cambiar Tema">
            <i id="themeIcon" class="fas fa-moon text-base"></i>
        </button>

        <!-- TUERCA DE CONFIGURACIÓN DE CUENTA -->
        <button type="button" data-sget-modal="modalConfigCuenta" class="w-10 h-10 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-sky-500 hover:rotate-45 bg-slate-200/50 dark:bg-white/5 hover:bg-slate-300 dark:hover:bg-white/10 rounded-2xl transition-all border border-slate-300/50 dark:border-white/10 text-sm cursor-pointer" title="Configurar Cuenta">
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

        <!--
            Cierre de sesión por POST y con token.

            Antes era un enlace GET: bastaba con que alguien cargara una imagen
            con esa URL desde otro sitio para cerrarle la sesión al usuario.
            `assets/cerrar.php` ahora solo admite GET para el cierre por
            inactividad, que dispara la propia aplicación.
        -->
        <form method="POST" action="<?= Config::basePath() ?>/assets/cerrar.php" class="contents">
            <?= Auth::campoToken() ?>
            <button type="submit"
                    class="w-10 h-10 flex items-center justify-center text-slate-500 hover:text-red-500 bg-slate-200/50 dark:bg-white/5 hover:bg-red-500/10 rounded-2xl transition-all border border-slate-300/50 dark:border-white/10 text-sm cursor-pointer"
                    title="Cerrar Sesión" aria-label="Cerrar Sesión">
                <i class="fas fa-sign-out-alt text-base"></i>
            </button>
        </form>
    </div>
</header>

<!--
    MODAL · CONFIGURACIÓN DE CUENTA
    -----------------------------------------------------------------------------
    Antes este diálogo era el ÚNICO del sistema que no usaba el motor común:
    un `div` con `onclick="cerrarModalConfigCuenta()"`, sin overlay gestionado,
    sin Escape, sin foco atrapado y con su propio JavaScript. Eso rompía la
    regla de la casa (todos los diálogos pasan por SGETModal) y hacía que el
    sidebar quedara utilizable por detrás.

    Ahora usa `data-sget-capa` / `data-sget-panel` / `data-sget-cerrar`, lleva
    token anti-CSRF y se abre con `data-sget-modal="modalConfigCuenta"`.
    El guardado lo procesa este mismo archivo, en el servidor (ver arriba), y
    responde con `Flash`: el mismo aviso que ve el resto del sistema.
-->
<div id="modalConfigCuenta" class="sget-modal-wrap" data-sget-capa data-titulo="Configuración de cuenta">
    <div class="sget-overlay"></div>

    <form class="sget-modal sget-modal--sm" data-sget-panel method="POST"
          action="<?= htmlspecialchars($_SERVER['PHP_SELF'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          novalidate role="dialog" aria-modal="true" aria-labelledby="tituloConfigCuenta">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloConfigCuenta">
                    <span class="sget-modal__icono"><i class="fas fa-user-cog"></i></span>
                    <span>Configuración de cuenta</span>
                </h2>
                <p class="sget-modal__sub">Actualiza tus datos de acceso a SGET.</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="accion_perfil" value="actualizar_configuracion">

            <div class="sget-field" data-campo="nom_usu">
                <label class="sget-label" for="cfgNombre">Nombre completo <span class="sget-label__req">*</span></label>
                <input type="text" id="cfgNombre" name="nom_usu" required maxlength="100"
                       autocomplete="name"
                       class="sget-input" value="<?= htmlspecialchars($user_nombre_header, ENT_QUOTES, 'UTF-8') ?>">
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <div class="sget-field" data-campo="corre_usu">
                <label class="sget-label" for="cfgCorreo">Correo electrónico <span class="sget-label__req">*</span></label>
                <input type="email" id="cfgCorreo" name="corre_usu" required maxlength="100"
                       autocomplete="email"
                       class="sget-input" value="<?= htmlspecialchars($user_correo_header, ENT_QUOTES, 'UTF-8') ?>">
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <div class="sget-field" data-campo="pass_usu">
                <label class="sget-label" for="cfgClave">
                    Nueva contraseña
                    <span class="sget-label__opt">(opcional)</span>
                </label>
                <input type="password" id="cfgClave" name="pass_usu" autocomplete="new-password"
                       placeholder="Déjala vacía para mantener la actual"
                       class="sget-input">
                <p class="sget-help">Mínimo <?= Password::MIN ?> caracteres. Al cambiarla tendrás que usarla en el próximo ingreso.</p>
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
            <button type="submit" class="sget-btn sget-btn--primario">
                <i class="fas fa-floppy-disk"></i> Guardar cambios
            </button>
        </footer>
    </form>
</div>

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
<script>
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
                const nuevoTema = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
                window.SGETTheme.set(nuevoTema);
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
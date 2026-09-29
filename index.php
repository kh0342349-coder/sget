<?php
// Detección dinámica de protocolo (HTTP en red local / HTTPS en producción)
$esHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,         // Persiste mientras el navegador permanezca abierto
        'path'     => '/',
        'domain'   => '',        // Dominio actual (localhost, IP o producción)
        'secure'   => $esHttps,  // false en red local HTTP para permitir login; true en HTTPS
        'httponly' => true,      // Tridente defensivo: Inaccesible desde JavaScript (XSS)
        'samesite' => 'Lax'      // Tridente defensivo: Protección CSRF
    ]);
    session_start();
}

// Ruta de conexión a la base de datos
require_once 'assets/conexion.php';

/* -----------------------------------------------------------------------------
 * ANUNCIOS DE LA LANDING
 * -----------------------------------------------------------------------------
 * Los banners los administra el administrador desde Admin/anuncios.php: sube la
 * imagen, los textos, el enlace y la vigencia. Aquí solo se pintan los que están
 * vigentes, en el orden que él definió.
 *
 * Antes estos textos estaban escritos a mano en el HTML, así que anunciar una
 * promoción obligaba a editar código y subir la página.
 * -------------------------------------------------------------------------- */
$anunciosLanding = [];
try {
    $anunciosLanding = AnuncioService::vigentes();   // solo activos y dentro de fecha
} catch (Throwable $e) {
    $anunciosLanding = [];                          // la landing nunca debe caerse por esto
}

// Solo se calculan si no hay nada que pintar, y solo hacen falta para el aviso
// que ve el administrador: son dos consultas y no se ejecutan siempre.
$anunciosTotales   = 0;
$anunciosInactivos = 0;
$puedeVerAnuncios  = false;

if (empty($anunciosLanding)) {
    try {
        // tieneAcceso() ya devuelve false si no hay sesión, y el Admin tiene
        // acceso total: no hace falta comprobar el rol a mano.
        $puedeVerAnuncios = Auth::tieneAcceso('anuncios');
        if ($puedeVerAnuncios) {
            $r = AnuncioService::resumen();
            $anunciosTotales   = (int)($r['total'] ?? 0);
            $anunciosInactivos = (int)($r['total'] ?? 0) - (int)($r['activos'] ?? 0);
        }
    } catch (Throwable $e) {
        $puedeVerAnuncios = false;
    }
}

// Consulta SQL ajustada para traer imagen de ruta (img_rut) y valor del viaje (val_via)
$query_viajes = "SELECT 
                    v.id_via,
                    v.val_via,
                    r.nom_rut,
                    r.img_rut,
                    r.ori_rut AS origen,
                    r.des_rut AS destino,
                    v.hor_sal_via AS hora_salida,
                    v.est_via AS estado_viaje,
                    veh.pla_veh AS placa_veh
                 FROM viaje v
                 INNER JOIN rutas r ON v.id_rut_via = r.id_rut
                 LEFT JOIN vehiculo veh ON v.id_veh = veh.id_veh
                 WHERE v.est_via IN ('Programado', 'En curso')
                 ORDER BY v.id_via DESC
                 LIMIT 6";

$resultado_viajes = mysqli_query($conexion, $query_viajes);

// Control de errores en la consulta
if (!$resultado_viajes) {
    die("Error en la consulta SQL: " . mysqli_error($conexion));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGET - Sistema Inteligente de Transporte</title>

    <!-- Tema: se ejecuta ANTES del primer pintado para evitar el parpadeo -->
    <script src="assets/js/theme-init.js?v=<?= @filemtime('assets/js/theme-init.js') ?: '1' ?>"></script>

    <!-- Tailwind CSS & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- CSS MODULAR DE LA LANDING PAGE (variables + reset + estilos propios) -->
    <link rel="stylesheet" href="assets/css/index.css?v=<?= @filemtime('assets/css/index.css') ?: '1' ?>">

    <!-- SDK de Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client?hl=en" async defer></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'neon-azul': 'var(--neon-azul)',
                        'neon-morado': 'var(--neon-morado)'
                    }
                }
            }
        };


        // Manejador del Token devuelto por Google
        function handleGoogleResponse(response) {
            fetch('controllers/auth_google.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ token: response.credential })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert((window.SGET_I18N?.t('Error en inicio de sesión con Google:') || 'Google sign-in error:') + ' ' + data.message);
                }
            })
            .catch(error => console.error('Error al comunicarse con el servidor:', error));
        }
    </script>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen flex flex-col antialiased">

    <!-- HEADER MODULAR -->
    <?php include 'includes/header_index.php'; ?>

    <main class="flex-grow pt-28">
        
        <!-- HERO SECTION -->
        <section id="inicio" class="hero-section py-16 px-6 relative overflow-hidden">
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-gradient-to-tr from-sky-500/20 to-blue-600/10 blur-[120px] rounded-full pointer-events-none"></div>

            <div class="max-w-5xl mx-auto text-center space-y-6 relative z-10">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-sky-500/10 border border-sky-500/30 text-sky-600 dark:text-sky-400 text-xs font-extrabold tracking-wide uppercase shadow-sm">
                    <i class="fas fa-bus-alt text-sm"></i> Plataforma Líder en Transporte
                </div>

                <h1 class="text-4xl sm:text-5xl md:text-6xl font-black tracking-tight leading-tight text-slate-900 dark:text-white">
                    Viaja Seguro y Monitorea tu Flota en <span class="text-transparent bg-clip-text bg-gradient-to-r from-sky-400 via-blue-500 to-indigo-600">Tiempo Real</span>
                </h1>

                <p class="text-base sm:text-lg max-w-2xl mx-auto font-medium leading-relaxed text-slate-600 dark:text-slate-300">
                    Consulta horarios, rutas disponibles y asegura tu desplazamiento con la tecnología integral de SGET.
                </p>

            </div>
        </section>

        <!-- ================================================================== -->
        <!-- CARRUSEL DE ANUNCIOS (contenido administrable desde Admin/anuncios) -->
        <!-- ================================================================== -->
        <?php if (!empty($anunciosLanding)): ?>
            <section id="anuncios" class="px-6 pt-10" aria-label="Anuncios y promociones">
                <div class="max-w-7xl mx-auto sget-anuncio-carrusel" data-sget-carrusel
                     data-sget-vista-url="<?= htmlspecialchars(Config::basePath() . '/procesos/anuncio_vista.php', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="sget-anuncio-carrusel__pista" data-sget-carrusel-pista>
                        <?php foreach ($anunciosLanding as $i => $an):
                            $url  = !empty($an['url_imagen']) ? $an['url_imagen'] : '';
                            $href = !empty($an['enlace']) ? $an['enlace'] : '';
                            $tono = (string)($an['color_tema'] ?? 'azul');
                            // El atributo de carga diferida se calcula en PHP y se
                            // imprime como unidad: escribir un ternario con comillas
                            // dobles dentro del HTML dejaba el archivo entero con un
                            // error de sintaxis.
                            $cargaDiferida = $i === 0 ? 'loading="eager"' : 'loading="lazy"';
                        ?>
                            <article class="sget-anuncio-carrusel__item<?= $i === 0 ? ' es-activo' : '' ?>"
                                     data-sget-slide="<?= (int)$an['id_ann'] ?>"
                                     aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">

                                <?php if ($url !== ''): ?>
                                    <img src="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars((string)$an['titulo'], ENT_QUOTES, 'UTF-8') ?>"
                                         <?= $cargaDiferida ?>>
                                <?php endif; ?>

                                <div class="sget-anuncio-carrusel__velo"></div>

                                <div class="sget-anuncio-carrusel__texto sget-anuncio-carrusel__texto--<?= htmlspecialchars($tono, ENT_QUOTES, 'UTF-8') ?>">
                                    <p class="sget-anuncio-carrusel__titulo">
                                        <?= htmlspecialchars((string)$an['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>

                                    <?php if (!empty($an['subtitulo'])): ?>
                                        <p class="sget-anuncio-carrusel__sub">
                                            <?= htmlspecialchars((string)$an['subtitulo'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($an['descripcion'])): ?>
                                        <p class="sget-anuncio-carrusel__desc">
                                            <?= htmlspecialchars((string)$an['descripcion'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($href !== ''): ?>
                                        <?php
                                        // Un enlace interno se abre en la misma pestaña (el
                                        // usuario va a reservar); uno externo, en otra.
                                        $esInterno = !preg_match('~^https?://~i', $href);
                                        ?>
                                        <a class="sget-anuncio-carrusel__boton"
                                           href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                                           <?= $esInterno ? '' : 'target="_blank" rel="noopener"' ?>>
                                            <?= htmlspecialchars((string)($an['boton_texto'] ?: 'Más información'), ENT_QUOTES, 'UTF-8') ?>
                                            <i class="fas fa-arrow-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($anunciosLanding) > 1): ?>
                        <button type="button" class="sget-anuncio-carrusel__flecha sget-anuncio-carrusel__flecha--izq"
                                data-sget-carrusel-move="-1" aria-label="Anuncio anterior">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button type="button" class="sget-anuncio-carrusel__flecha sget-anuncio-carrusel__flecha--der"
                                data-sget-carrusel-move="1" aria-label="Anuncio siguiente">
                            <i class="fas fa-chevron-right"></i>
                        </button>

                        <div class="sget-anuncio-carrusel__puntos" role="tablist" aria-label="Ir a un anuncio">
                            <?php foreach ($anunciosLanding as $i => $an): ?>
                                <button type="button" role="tab"
                                        class="sget-anuncio-carrusel__punto<?= $i === 0 ? ' es-activo' : '' ?>"
                                        data-sget-carrusel-ir="<?= (int)$i ?>"
                                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                                        aria-label="<?= htmlspecialchars((string)$an['titulo'], ENT_QUOTES, 'UTF-8') ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php elseif ($puedeVerAnuncios): ?>
            <!--
                NO HAY ANUNCIOS VISIBLES, PERO QUIEN ESTÁ MIRANDO ES EL ADMIN.

                POR QUÉ ESTE BLOQUE
                  El carrusel desaparece en silencio cuando no hay anuncios
                  publicados. Para el visitante público está bien (una portada sin
                  promociones), pero el administrador se quedaba sin forma de
                  saber si el módulo estaba roto, si su anuncio estaba oculto o si
                  la imagen no se había subido. Este aviso solo se pinta si quien
                  mira la portada tiene permiso para administrar anuncios, así que
                  el público nunca ve un mensaje de administration.
            -->
            <section id="anuncios" class="px-6 pt-10" aria-label="Aviso de anuncios">
                <div class="max-w-7xl mx-auto">
                    <div class="sget-anuncio-vacio">
                        <span class="sget-anuncio-vacio__icono"><i class="fas fa-image"></i></span>
                        <div>
                            <p class="sget-anuncio-vacio__titulo">
                                <?= $anunciosTotales === 0
                                    ? 'Todavía no hay ningún anuncio en la landing'
                                    : 'Ninguno de tus anuncios se está viendo ahora mismo' ?>
                            </p>
                            <p class="sget-anuncio-vacio__texto">
                                <?php if ($anunciosTotales === 0): ?>
                                    Sube el primero y aparecerá de inmediato en esta página.
                                <?php elseif ($anunciosInactivos > 0): ?>
                                    Tienes <?= (int)$anunciosInactivos ?> anuncio(s) sin publicar. Los que estén fuera de su
                                    fecha de vigencia también dejan de mostrarse.
                                <?php else: ?>
                                    Todos están publicados: revisa su fecha de vigencia y comprueba que la
                                    imagen siga en el servidor.
                                <?php endif; ?>
                            </p>
                        </div>
                        <a class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-900 text-sm font-extrabold transition-colors whitespace-nowrap"
                           href="Admin/anuncios.php">
                            <i class="fas fa-sliders"></i> Revisar anuncios
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="max-w-7xl mx-auto px-6"><div class="divider-glow"></div></div>

        <!-- SECCIÓN 2: VIAJES EN VIVO (VISUAL Y MINIMALISTA) -->
        <section id="viajes-disponibles" class="py-20 px-6 max-w-7xl mx-auto space-y-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-200 dark:border-white/10 pb-6">
                <div>
                    <span class="text-xs font-extrabold text-emerald-500 uppercase tracking-widest flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span> SALIDAS PROGRAMADAS
                    </span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mt-1">Viajes Disponibles Ahora</h2>
                </div>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 max-w-md font-medium">
                    Consulta las rutas activas listas para abordar con asignación de vehículos en tiempo real.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($resultado_viajes && mysqli_num_rows($resultado_viajes) > 0): ?>
                    <?php while ($viaje = mysqli_fetch_assoc($resultado_viajes)): ?>
                        <?php 
                            $nombreImagen = trim($viaje['img_rut'] ?? '');
                            $rutaImagen = !empty($nombreImagen) ? "img/rutas/" . $nombreImagen : "";
                        ?>
                        <!-- Tarjeta Limpia Enfocada en la Imagen de Destino -->
                        <div class="relative overflow-hidden rounded-3xl h-64 border border-slate-200 dark:border-white/10 shadow-xl group transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl flex flex-col justify-between p-5 bg-slate-950">
                            
                            <!-- Imagen de la ruta a pantalla completa con zoom suave al pasar el mouse -->
                            <?php if (!empty($nombreImagen) && file_exists("img/rutas/" . $nombreImagen)): ?>
                                <img src="<?php echo htmlspecialchars($rutaImagen); ?>" 
                                     alt="<?php echo htmlspecialchars($viaje['nom_rut'] ?? 'Ruta'); ?>" 
                                     class="absolute inset-0 w-full h-full object-cover object-center z-0 transition-transform duration-700 group-hover:scale-110">
                            <?php endif; ?>
                            
                            <!-- Degradado suave en los extremos para legibilidad -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-black/60 z-0"></div>

                            <!-- Header: Hora y Estado Activo -->
                            <div class="relative z-10 flex items-center justify-between">
                                <span class="text-[11px] font-mono font-bold text-white bg-black/50 backdrop-blur-md px-3 py-1 rounded-full border border-white/15 shadow-sm">
                                    <i class="far fa-clock text-sky-400 mr-1"></i>
                                    <?= !empty($viaje['hora_salida']) ? date('h:i A', strtotime($viaje['hora_salida'])) : 'En Breve'; ?>
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-300 bg-emerald-900/60 backdrop-blur-md px-3 py-1 rounded-full border border-emerald-500/40 flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    ACTIVO
                                </span>
                            </div>

                            <!-- Bloque Inferior: Precio, Ruta y Botón Reservar -->
                            <div class="relative z-10 space-y-3 pt-4 border-t border-white/15">
                                <div>
                                    <?php if (!empty($viaje['val_via'])): ?>
                                        <p class="text-xs font-black uppercase tracking-wider text-amber-300 drop-shadow-md">
                                            $<?= number_format($viaje['val_via'], 0, ',', '.'); ?> COP
                                        </p>
                                    <?php endif; ?>

                                    <h3 class="font-black text-white text-xl sm:text-2xl tracking-tight leading-tight truncate drop-shadow-lg" title="<?= htmlspecialchars($viaje['nom_rut'] ?? ($viaje['origen'] . ' - ' . $viaje['destino'])); ?>">
                                        <?= htmlspecialchars($viaje['nom_rut'] ?? ($viaje['origen'] . ' - ' . $viaje['destino'])); ?>
                                    </h3>
                                    <p class="text-[11px] font-semibold text-slate-300 flex items-center gap-1 mt-0.5">
                                        <i class="fas fa-bus-alt text-sky-400"></i> Placa: <span class="font-mono text-white"><?= htmlspecialchars($viaje['placa_veh'] ?? 'Sin Asignar'); ?></span>
                                    </p>
                                </div>

                                <!-- Botón Reservar -->
                                <button data-sget-modal="panelLogin" class="w-full py-3 bg-sky-500 hover:bg-sky-400 active:bg-sky-600 text-slate-950 font-black text-xs uppercase tracking-widest rounded-2xl shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer group-hover:shadow-sky-500/30">
                                    <i class="fas fa-ticket-alt"></i> RESERVAR PASAJE
                                </button>
                            </div>

                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full py-16 px-6 text-center card-glass rounded-3xl">
                        <div class="w-16 h-16 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center mx-auto mb-4 text-2xl">
                            <i class="fas fa-route"></i>
                        </div>
                        <p class="text-base font-extrabold text-slate-800 dark:text-slate-200">No hay viajes activos programados en este momento</p>
                        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Las nuevas salidas aparecerán aquí automáticamente tan pronto sean asignadas por la administración.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECCIÓN 3: SERVICIOS Y VENTAJAS -->
        <section id="servicios" class="py-16 px-6 max-w-6xl mx-auto space-y-10">
            <div class="text-center space-y-2">
                <span class="text-xs font-extrabold text-indigo-500 uppercase tracking-widest">¿POR QUÉ SGET?</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Servicios Diseñados para la Eficiencia</h2>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6">
                <div class="card-glass glow-hover rounded-3xl p-8 space-y-4 text-left group">
                    <div class="w-12 h-12 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-sky-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">Gestión de Viajes</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Control automatizado de itinerarios, asignaciones e imprevistos de ruta al instante.</p>
                </div>
                
                <div class="card-glass glow-hover rounded-3xl p-8 space-y-4 text-left group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">Control de Conductores</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Monitoreo permanente de turnos, disponibilidades y estados del personal operativo.</p>
                </div>
                
                <div class="card-glass glow-hover rounded-3xl p-8 space-y-4 text-left group">
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-500 flex items-center justify-center text-xl font-bold group-hover:scale-110 transition-transform">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">Panel de Información</h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Información general del sistema, historiales de viajes, usuarios y rutas, y las ganancias reales del negocio.</p>
                </div>
            </div>
        </section>

    </main>

    <!-- FOOTER CON ENLACE LEGAL Y COOKIES -->
    <footer class="p-6 text-center text-slate-500 dark:text-slate-400 text-xs font-semibold border-t border-slate-200 dark:border-white/10 bg-white/80 dark:bg-[#0b0f19]/80 backdrop-blur-md flex flex-col sm:flex-row items-center justify-between max-w-7xl mx-auto w-full gap-4">
        <p>&copy; 2026 SGET - Sistema de Gestión de Transporte. Todos los derechos reservados.</p>
        <div class="flex items-center gap-4">
            <button data-sget-modal="panelConfigCookies" class="hover:text-amber-600 dark:hover:text-amber-400 underline transition-colors cursor-pointer flex items-center gap-1.5">
                <i class="fas fa-cookie-bite text-amber-700 dark:text-amber-500"></i> Configuración de Cookies
            </button>
            <span>•</span>
            <button data-sget-modal="panelPolitica" class="hover:text-sky-500 underline transition-colors cursor-pointer">
                Tratamiento de Datos Personales (Ley 1581)
            </button>
        </div>
    </footer>

    <!-- INCLUSIÓN DEL MODAL AUTENTICACIÓN -->
    <?php include 'modal_auth.php'; ?>

    <!-- Botón de Google Identity Services (se monta al abrirse el modal) -->
    <script src="assets/js/sget-google.js?v=<?= @filemtime('assets/js/sget-google.js') ?: '1' ?>"></script>

    <!-- Carrusel de anuncios de la landing (rotación + conteo de vistas) -->
    <script src="assets/js/sget-anuncios.js?v=<?= @filemtime('assets/js/sget-anuncios.js') ?: '1' ?>"></script>

    <!-- BANNER FLOTANTE DE AVISO DE COOKIES CON GALLETA CAFÉ Y SOPORTE CLARO/OSCURO -->
    <div id="cookieBanner" class="fixed bottom-4 left-4 right-4 md:left-auto md:right-4 md:max-w-md bg-white/95 dark:bg-[#0f172a]/95 text-slate-800 dark:text-white p-5 rounded-3xl border border-slate-200 dark:border-white/10 shadow-2xl z-[100] backdrop-blur-md hidden transition-all duration-300">
        <div class="flex items-start gap-3.5">
            <!-- Contenedor con la Galleta Café -->
            <div class="w-10 h-10 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-500 flex items-center justify-center shrink-0 mt-0.5 shadow-sm">
                <i class="fas fa-cookie-bite text-xl"></i>
            </div>
            <div class="space-y-2">
                <h4 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-1.5">
                    Aviso de Privacidad y Cookies
                </h4>
                <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed font-medium">
                    Utilizamos cookies técnicas necesarias para el funcionamiento seguro de SGET y cookies opcionales para recordar tus preferencias de navegación.
                </p>
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button onclick="aceptarTodasCookies()" class="px-4 py-2 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-[10px] uppercase tracking-wider rounded-xl transition-all cursor-pointer shadow-lg shadow-sky-500/20">
                        Aceptar Todas
                    </button>
                    <button data-sget-modal="panelConfigCookies" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20 text-slate-800 dark:text-white border border-slate-200 dark:border-white/10 font-extrabold text-[10px] uppercase tracking-wider rounded-xl transition-all cursor-pointer">
                        Configurar
                    </button>
                    <button onclick="rechazarCookiesOpcionales()" class="px-2.5 py-2 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold text-[10px] underline cursor-pointer">
                        Solo Necesarias
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE CONFIGURACIÓN DE COOKIES CON MODOS CLARO/OSCURO -->
    <div id="panelConfigCookies"
         class="sget-modal-wrap fixed inset-0 z-[110] p-4 modal-isla-container"
         data-sget-capa data-titulo="Preferencias de cookies"
         role="dialog" aria-modal="true" aria-labelledby="tituloCookies">
        <div class="sget-modal sget-modal--lg modal-isla-card" data-sget-panel>
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-500 flex items-center justify-center font-bold text-lg">
                        <i class="fas fa-cookie-bite"></i>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">Centro de Preferencias de Cookies</h3>
                        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">Personaliza tus opciones de privacidad en SGET</p>
                    </div>
                </div>
                <button type="button" data-sget-cerrar aria-label="Cerrar" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-slate-600 dark:hover:text-white flex items-center justify-center transition-colors cursor-pointer">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- OPCIONES DE CONFIGURACIÓN -->
            <div class="my-4 overflow-y-auto pr-2 space-y-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed text-left">
                <!-- 1. Estrictamente Necesarias -->
                <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-white/5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-extrabold text-slate-900 dark:text-white text-sm">Cookies Estrictamente Necesarias</span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Siempre Activas</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        Indispensables para el inicio de sesión seguro, autenticación del usuario (`PHPSESSID`) y mantenimiento de la sesión activa en SGET. No se pueden desactivar.
                    </p>
                </div>

                <!-- 2. Preferencias / Funcionales -->
                <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-white/5 space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="chkCookiePreferencias" class="font-extrabold text-slate-900 dark:text-white text-sm cursor-pointer">Cookies de Preferencias</label>
                        <input type="checkbox" id="chkCookiePreferencias" checked class="w-4 h-4 rounded text-sky-500 focus:ring-sky-400 dark:bg-slate-900 cursor-pointer">
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        Permiten recordar tus selecciones personalizadas, como el idioma elegido (Español/Inglés) y el tema de la interfaz (Claro/Oscuro).
                    </p>
                </div>

                <!-- 3. Rendimiento / Analítica -->
                <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-white/5 space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="chkCookieAnalitica" class="font-extrabold text-slate-900 dark:text-white text-sm cursor-pointer">Cookies de Rendimiento y Analítica</label>
                        <input type="checkbox" id="chkCookieAnalitica" class="w-4 h-4 rounded text-sky-500 focus:ring-sky-400 dark:bg-slate-900 cursor-pointer">
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        Nos ayudan a recopilar información anónima sobre el uso del sistema para optimizar los tiempos de carga y mejorar el control de rutas.
                    </p>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-white/10 flex flex-wrap gap-2 justify-end">
                <button onclick="guardarConfiguracionCookies()" class="px-5 py-2.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs uppercase tracking-wider transition-all shadow-lg cursor-pointer">
                    Guardar Preferencias
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL POLÍTICA DE TRATAMIENTO DE DATOS -->
    <div id="panelPolitica"
         class="sget-modal-wrap fixed inset-0 z-50 p-4 modal-isla-container"
         data-sget-capa data-titulo="Política de tratamiento de datos"
         role="dialog" aria-modal="true" aria-labelledby="tituloPolitica">
        <div class="sget-modal sget-modal--lg modal-isla-card" data-sget-panel>
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center font-bold text-lg">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">Tratamiento de Datos Personales</h3>
                        <p class="text-[11px] font-bold text-slate-400">Cumplimiento Ley 1581 de 2012</p>
                    </div>
                </div>
                <button type="button" data-sget-cerrar aria-label="Cerrar" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-white/5 text-slate-400 hover:text-slate-600 dark:hover:text-white flex items-center justify-center transition-colors cursor-pointer">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- CUERPO DE LA POLÍTICA -->
            <div class="my-4 overflow-y-auto pr-2 space-y-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed text-left">
                <div>
                    <h4 class="font-extrabold text-slate-900 dark:text-white text-sm mb-1">1. Responsable del Tratamiento</h4>
                    <p>El sistema <strong>SGET (Sistema de Gestión de Transporte)</strong> actúa como responsable del tratamiento de sus datos personales recolectados a través de esta plataforma digital.</p>
                </div>

                <div>
                    <h4 class="font-extrabold text-slate-900 dark:text-white text-sm mb-1">2. Finalidad de la Recolección</h4>
                    <p>Los datos solicitados (nombre completo, documento de identidad, correo electrónico y número celular) serán tratados exclusivamente para:</p>
                    <ul class="list-disc list-inside mt-1 space-y-0.5 ml-2">
                        <li>Creación y validación de la cuenta de usuario.</li>
                        <li>Gestión, reserva y control de cupos en viajes y rutas.</li>
                        <li>Notificaciones operativas sobre itinerarios y novedades del servicio.</li>
                        <li>Seguridad del sistema e identificación de perfiles de acceso.</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-extrabold text-slate-900 dark:text-white text-sm mb-1">3. Derechos del Titular (Habeas Data)</h4>
                    <p>De conformidad con la normatividad vigente, como titular de los datos usted tiene derecho a:</p>
                    <ul class="list-disc list-inside mt-1 space-y-0.5 ml-2">
                        <li>Conocer, actualizar y rectificar sus datos personales.</li>
                        <li>Solicitar prueba de la autorización otorgada.</li>
                        <li>Revocar la autorización y/o solicitar la supresión de sus datos cuando sea procedente.</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-extrabold text-slate-900 dark:text-white text-sm mb-1">4. Seguridad y Confidencialidad</h4>
                    <p>SGET implementa protocolos técnicos de cifrado y medidas de seguridad digital para prevenir el acceso no autorizado, la alteración o la filtración de la información de los usuarios.</p>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-white/10 flex justify-end">
                <button data-sget-cerrar class="px-5 py-2.5 rounded-xl bg-slate-900 text-white dark:bg-sky-500 dark:text-slate-950 font-extrabold text-xs hover:opacity-90 transition-all cursor-pointer">
                    Entendido
                </button>
            </div>
        </div>
    </div>

    <!-- SCRIPTS DE CONTROL DEL MODAL, COOKIES Y GOOGLE SIGN-IN -->
    <script>
        // --- GESTIÓN DE COOKIES Y PREFERENCIAS ---
        document.addEventListener('DOMContentLoaded', () => {
            const consent = localStorage.getItem('sget_cookies_consent');
            if (!consent) {
                const banner = document.getElementById('cookieBanner');
                if (banner) banner.classList.remove('hidden');
            } else {
                aplicarPreferenciasCookies(JSON.parse(consent));
            }
        });

        function aceptarTodasCookies() {
            const prefs = { necesarias: true, preferencias: true, analitica: true };
            localStorage.setItem('sget_cookies_consent', JSON.stringify(prefs));
            ocultarBannerYModalesCookies();
            aplicarPreferenciasCookies(prefs);
        }

        function rechazarCookiesOpcionales() {
            const prefs = { necesarias: true, preferencias: false, analitica: false };
            localStorage.setItem('sget_cookies_consent', JSON.stringify(prefs));
            ocultarBannerYModalesCookies();
            aplicarPreferenciasCookies(prefs);
        }

        function guardarConfiguracionCookies() {
            const prefs = {
                necesarias: true,
                preferencias: document.getElementById('chkCookiePreferencias')?.checked ?? true,
                analitica: document.getElementById('chkCookieAnalitica')?.checked ?? false
            };
            localStorage.setItem('sget_cookies_consent', JSON.stringify(prefs));
            cerrarPanel('panelConfigCookies');
            ocultarBannerYModalesCookies();
            aplicarPreferenciasCookies(prefs);
        }

        function ocultarBannerYModalesCookies() {
            const banner = document.getElementById('cookieBanner');
            if (banner) banner.classList.add('hidden');
        }

        function aplicarPreferenciasCookies(prefs) {
            if (!prefs.preferencias) {
                // Si el usuario desactiva cookies de preferencias opcionales
            }
        }

        // --- LÓGICA DE MODALES ---
        /* ------------------------------------------------------------------
           BOTÓN DE INICIO DE SESIÓN CON GOOGLE
           ------------------------------------------------------------------
           El montaje vive ahora en assets/js/sget-google.js. Antes estaba aquí,
           dentro de abrirPanel(), y por eso el botón casi nunca aparecía: la
           cabecera y las tarjetas de la landing abren el modal con
           data-sget-modal="panelLogin", que pasa por SGETModal.abrir() sin
           llamar nunca a esta función.

           sget-google.js se monta al escuchar el evento `sget:modal-abierto`
           que emite el motor de modales, así que da igual por dónde se abra.
        ------------------------------------------------------------------ */

        /* ------------------------------------------------------------------
           ADAPTADORES DEL MOTOR COMÚN DE MODALES (assets/js/sget-modal.js)
           ------------------------------------------------------------------
           Estas tres funciones se conservan porque el HTML las sigue invocando
           (`onclick="abrirPanel(...)"`), pero ya NO manipulan clases a mano:
           delegan en SGETModal, que centraliza la animación, el foco, el
           bloqueo de scroll, el cierre con Escape y el aria-modal.
           Antes cada panel jugaba con `hidden` + `opacity-0` + `scale-95`, y
           cualquier hoja de estilos que declarara `display` en el contenedor
           (como `.modal-isla-container`) los dejaba invisibles pero presentes,
           bloqueando toda la página.
           ------------------------------------------------------------------ */
        function abrirPanel(idPanel) {
            if (typeof SGETModal === 'undefined') {
                // Red de seguridad: si el motor no cargó, al menos se quita
                // el bloqueo de clics en lugar de dejar la página muerta.
                const p = document.getElementById(idPanel);
                if (p) p.classList.remove('hidden', 'pointer-events-none', 'opacity-0');
                return;
            }

            // SGETModal.abrir() emite `sget:modal-abierto`, que es lo que
            // dispara el montaje del botón de Google y del reCAPTCHA con tema.
            SGETModal.abrir(idPanel);
        }

        function cerrarPanel(idPanel) {
            if (typeof SGETModal === 'undefined') {
                const p = document.getElementById(idPanel);
                if (p) p.classList.add('hidden', 'pointer-events-none', 'opacity-0');
                return;
            }
            SGETModal.cerrar(idPanel, false);
        }

        function cambiarAPanel(idDestino) {
            ['panelLogin', 'panelRegistro', 'panelPolitica', 'panelConfigCookies'].forEach(cerrarPanel);
            setTimeout(() => abrirPanel(idDestino), 220);
        }

        /* ------------------------------------------------------------------------
           COMPORTAMIENTO ADICIONAL DE LA LANDING
           ------------------------------------------------------------------------ */
        /* ------------------------------------------------------------------
           CARRUSEL DE ANUNCIOS
           ------------------------------------------------------------------
           El carrusel (rotación, puntos, flechas, deslizamiento y conteo de
           vistas) vive AHORA en assets/js/sget-anuncios.js, no aquí dentro.

           POR QUÉ SE SACÓ
             La landing era la última página con lógica escrita en el HTML, y la
             regla de la casa dice lo contrario: el comportamiento se resuelve con
             atributos data-sget-*, y lo que no cabe así, en assets/js/. Este
             bloque no era un parche de la landing sino un módulo entero, con su
             rotación, su gesto de deslizamiento y su pausa con reduced-motion.
        ------------------------------------------------------------------ */
        // Escape ya lo resuelve el motor común (cierra la capa superior).

        // Navegación interna entre modales (login <-> registro, política)
        document.addEventListener('click', function (event) {
            const enlace = event.target.closest('[data-sget-ir-a]');
            if (!enlace) return;
            event.preventDefault();
            cambiarAPanel(enlace.dataset.sgetIrA);
        });

        // Menú hamburguesa de la cabecera en móvil
        document.addEventListener('click', function (event) {
            const burger = event.target.closest('[data-sget-burger]');
            if (!burger) return;
            const nav = document.querySelector('.landing-nav');
            if (nav) nav.dataset.abierto = nav.dataset.abierto === '1' ? '0' : '1';
        });

        document.addEventListener("DOMContentLoaded", function () {
            <?php if (!empty($_SESSION['abrir_login']) || !empty($_SESSION['msg_success_login'])): ?>
                abrirPanel('panelLogin');
                <?php unset($_SESSION['abrir_login'], $_SESSION['msg_success_login']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
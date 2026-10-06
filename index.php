<?php
/**
 * index.php — PORTADA PÚBLICA DE SGET
 * -----------------------------------------------------------------------------
 * Loader único: `core/bootstrap.php` deja lista la sesión, la base de datos,
 * los servicios y los mensajes Flash. Esta página ya no abre la sesión ni crea
 * la conexión por su cuenta (antes lo hacía, y eso obligaba a repetir el bloque
 * en cada archivo, con cuatro sitios distintos configurando la cookie).
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

$BASE = Config::basePath();

/* Aviso de «necesitas iniciar sesión» cuando se llega desde el botón DIRECCIÓN
   del módulo de anuncios. Ese botón lleva a la portada; si el enlace del anuncio
   apunta a un módulo del panel (`Admin/viajes.php`), hace falta una sesión, y
   sin este aviso el visitante landing ve un 403 sin explicación. */
if (isset($_GET['aviso_portal']) && !Auth::estaLogueado()) {
    Flash::aviso('Para abrir los módulos del panel de SGET necesitas iniciar sesión.');
}

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
        // tieneAcceso() ya devuelve false si no hay sesión, y el rol concede
        // lo que tiene en `rol_permiso`: no hace falta comprobar el rol a mano.
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

/* -----------------------------------------------------------------------------
 * VIAJES PUBLICADOS
 * -----------------------------------------------------------------------------
 * La consulta pasa por el servicio (PDO + sentencias preparadas) en lugar de
 * SQL suelto con mysqli. Antes, un fallo de conexión aquí mataba la portada
 * entera con un `die()` que enseñaba el error crudo al visitante.
 * -------------------------------------------------------------------------- */
try {
    $viajesPublicos = ViajeService::listar([
        'estados' => [Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO],
    ]);
} catch (Throwable $e) {
    error_log('[SGET][landing] ' . $e->getMessage());
    $viajesPublicos = [];
}

/* Cifras de confianza del hero. Se calculan UNA vez y con tolerancia a fallo:
   la portada es pública y no puede caerse porque falte un dato. */
$totalRutas = 0;
try {
    $totalRutas = count(RutaService::todas(true));
} catch (Throwable $e) {
    error_log('[SGET][landing] rutas: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SGET · Sistema Inteligente de Transporte. Consulta rutas, horarios y reserva tu cupo en línea.">
    <title>SGET - Sistema Inteligente de Transporte</title>

    <!-- Tema: se ejecuta ANTES del primer pintado para evitar el parpadeo -->
    <script src="assets/js/theme-init.js?v=<?= @filemtime('assets/js/theme-init.js') ?: '1' ?>"></script>

    <!-- Tailwind CSS & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- CSS MODULAR DE LA LANDING PAGE (variables + reset + estilos propios) -->
    <link rel="stylesheet" href="assets/css/index.css?v=<?= @filemtime('assets/css/index.css') ?: '1' ?>">

    <!-- SDK de Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client?hl=es" async defer></script>

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

        /* Token anti-CSRF para el login y el registro con Google.
           Lo consume assets/js/sget-google.js al enviar el id_token. */
        window.SGET_CSRF = <?= json_encode(Auth::token()) ?>;
    </script>
</head>
<body class="bg-slate-50 dark:bg-[#0b0f19] text-slate-800 dark:text-slate-100 min-h-screen flex flex-col antialiased">

    <!-- HEADER MODULAR -->
    <?php include 'includes/header_index.php'; ?>

    <main class="flex-grow pt-28">
        <?= Flash::render() ?>
        
        <!-- HERO SECTION -->
        <section id="inicio" class="hero-section py-16 px-6 relative overflow-hidden">
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-gradient-to-tr from-sky-500/20 to-blue-600/10 blur-[120px] rounded-full pointer-events-none"></div>

            <div class="max-w-5xl mx-auto text-center space-y-6 relative z-10">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-sky-500/10 border border-sky-500/30 text-sky-600 dark:text-sky-400 text-xs font-extrabold tracking-wide uppercase shadow-sm">
                    <i class="fas fa-bus-alt text-sm"></i> Plataforma Líder en Transporte
                </div>

                <h1 class="text-4xl sm:text-5xl md:text-6xl font-black tracking-tight leading-tight text-slate-900 dark:text-white">
                    Viaja Seguro y Monitorea tu Flota
                </h1>

                <p class="text-base sm:text-lg max-w-2xl mx-auto font-medium leading-relaxed text-slate-600 dark:text-slate-300">
                    Consulta horarios, rutas disponibles y asegura tu desplazamiento con la tecnología integral de SGET.
                </p>

                <!--
                    LLAMADAS A LA ACCIÓN
                    El hero tenía un título y un párrafo, y nada más: no había forma
                    de entrar al sistema desde el punto de entrada más visible de la
                    página. Se añaden dos: la acción principal y la secundaria.

                    Ambas abren el modal de acceso, que es la puerta real: el
                    contenido de la landing es público y la reserva requiere
                    sesión.
                -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3 pt-2">
                    <button type="button" data-sget-ir-a="panelLogin"
                            class="group inline-flex items-center justify-center gap-2.5 px-7 py-3.5 rounded-2xl bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-sm shadow-xl shadow-sky-500/25 hover:shadow-2xl hover:shadow-sky-500/35 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                        <i class="fas fa-right-to-bracket transition-transform group-hover:translate-x-0.5"></i>
                        Iniciar sesión
                    </button>
                    <a href="#viajes-disponibles"
                       class="inline-flex items-center justify-center gap-2.5 px-7 py-3.5 rounded-2xl bg-white/70 dark:bg-white/[0.06] border border-slate-200 dark:border-white/15 backdrop-blur-md text-slate-800 dark:text-slate-100 font-extrabold text-sm hover:bg-white dark:hover:bg-white/10 hover:-translate-y-0.5 transition-all duration-200">
                        <i class="fas fa-bus text-sky-500 dark:text-sky-400"></i>
                        Ver viajes disponibles
                    </a>
                </div>

                <!-- PRUEBAS DE CONFIANZA: sin esto el hero promete sin respaldo -->
                <dl class="grid grid-cols-2 gap-4 sm:gap-8 max-w-lg mx-auto pt-4">
                    <?php foreach ([
                        ['fa-route',        $totalRutas,               'Rutas activas'],
                        ['fa-bus',          count($viajesPublicos),     'Viajes publicados'],
                    ] as [$icono, $valor, $rotulo]): ?>
                        <div class="text-center">
                            <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400 flex items-center justify-center gap-1.5">
                                <i class="fas <?= $icono ?> text-sky-500/70"></i>
                                <?= $rotulo ?>
                            </dt>
                            <dd class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-0.5">
                                <?= htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8') ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

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

                                    <?php
                                        /* EL BOTÓN SE PINT SIEMPRE
                                         * ---------------------------------------------------------------------
                                         * Antes todo este bloque estaba dentro de
                                         * `if ($href !== '')`: si el anuncio no tenía enlace, la
                                         * tarjeta se quedaba sin ningún botón. Eso convertía el
                                         * módulo de anuncios en algo inconsistente —una promoción
                                         * Depending de cómo se rellenara el formulario aparecía
                                         * CTA y otra no— y además dejaba al visitante sin salida.
                                         *
                                         * Ahora el botón se pinta siempre, con el texto que haya
                                         * puesto el administrador o «Más información» por defecto.
                                         * Lo que cambia es el DESTINO, no su existencia.
                                         */
                                    ?>
                                    <?php
                                        /* DESTINO DEL BOTÓN DEL ANUNCIO
                                         * ---------------------------------------------------------------------
                                         * El botón SIEMPRE pasa por el acceso al sistema:
                                         *
                                         *   · Usuario NO autenticado  -> se abre el modal de INICIO DE
                                         *     SESIÓN. Da igual a qué apunte el anuncio: el contenido
                                         *     de la landing es público, así que el botón es siempre
                                         *     la puerta de entrada, nunca un destino arbitrario.
                                         *   · Usuario YA autenticado -> va al destino del anuncio.
                                         *     Mandarle a un formulario de login a quien ya tiene
                                         *     sesión sería un bucle sin salida.
                                         *
                                         * Antes cada anuncio enviaba a su `enlace` tal cual. Eso
                                         * producía dos fallos:
                                         *   1. Un visitante sin sesión llegaba a una URL interna,
                                         *      la rebotaba a la portada y además AVISABA por
                                         *      `aviso_portal`: tres navegaciones para nada.
                                         *   2. El `enlace` sale de la base de datos, así que
                                         *      aceptarlo sin filtrar convertía al módulo de
                                         *      anuncios en un vector de redirección a sitios
                                         *      externos.
                                         *
                                         * Aquí el destino solo se usa si es una ruta interna
                                         * (empieza por «/» o es una ruta relativa simple). Cualquier
                                         * URL absoluta externa se descarta.
                                         */
                                        $destino = '';
                                        if (!preg_match('~^(?:https?:)?//~i', $href) && !str_contains($href, '\\')) {
                                            $destino = $href;
                                        }
                                        $haySesion = Auth::estaLogueado();
                                        ?>
                                        <?php if (!$haySesion): ?>
                                            <button type="button"
                                                    class="sget-anuncio-carrusel__boton"
                                                    data-sget-ir-a="panelLogin"
                                                    aria-label="Iniciar sesión para ver más información">
                                                <?= htmlspecialchars((string)($an['boton_texto'] ?: 'Más información'), ENT_QUOTES, 'UTF-8') ?>
                                                <i class="fas fa-arrow-right"></i>
                                            </button>
                                        <?php elseif ($destino !== ''): ?>
                                            <a class="sget-anuncio-carrusel__boton"
                                               href="<?= htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars((string)($an['boton_texto'] ?: 'Más información'), ENT_QUOTES, 'UTF-8') ?>
                                                <i class="fas fa-arrow-right"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="sget-anuncio-carrusel__boton" aria-disabled="true">
                                                <?= htmlspecialchars((string)($an['boton_texto'] ?: 'Más información'), ENT_QUOTES, 'UTF-8') ?>
                                                <i class="fas fa-arrow-right"></i>
                                            </span>
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
            <?php /* ID DISTINTO al del carrusel: había dos `id="anuncios"` en el
                   mismo documento. Un id duplicado es HTML inválido, rompe las
                   anclas y hace que el CSS apunte al elemento equivocado. */ ?>
            <section id="anuncios-admin" class="px-6 pt-10" aria-label="Aviso de anuncios">
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
                           href="<?= htmlspecialchars(Config::basePath(), ENT_QUOTES, 'UTF-8') ?>/Admin/anuncios.php">
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
                <?php if (!empty($viajesPublicos)): ?>
                    <?php foreach (array_slice($viajesPublicos, 0, 6) as $viaje): ?>
                        <?php
                            $nombreImagen = trim((string)($viaje['img_rut'] ?? ''));
                            $rutaImagen   = $nombreImagen !== '' ? 'img/rutas/' . basename($nombreImagen) : '';
                            $hayImagen    = $rutaImagen !== '' && is_file(__DIR__ . '/' . $rutaImagen);
                            $tituloRuta   = trim((string)($viaje['nom_rut'] ?? ''))
                                           ?: trim(($viaje['ori_rut'] ?? '') . ' → ' . ($viaje['des_rut'] ?? ''));
                            $hora         = !empty($viaje['hor_sal_via']) ? substr((string)$viaje['hor_sal_via'], 0, 5) : '';
                        ?>
                        <!-- Tarjeta limpia, enfocada en la imagen de la ruta -->
                        <article class="relative overflow-hidden rounded-3xl h-64 border border-slate-200 dark:border-white/10 shadow-xl group transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl flex flex-col justify-between p-5 bg-slate-950">

                            <?php if ($hayImagen): ?>
                                <img src="<?= htmlspecialchars($rutaImagen, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($tituloRuta, ENT_QUOTES, 'UTF-8') ?>"
                                     loading="lazy"
                                     class="absolute inset-0 w-full h-full object-cover object-center z-0 transition-transform duration-700 group-hover:scale-110">
                            <?php endif; ?>

                            <!-- Degradado para que los textos siempre tengan contraste -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-black/60 z-0"></div>

                            <div class="relative z-10 flex items-center justify-between">
                                <span class="text-[11px] font-mono font-bold text-white bg-black/50 backdrop-blur-md px-3 py-1 rounded-full border border-white/15 shadow-sm">
                                    <i class="far fa-clock text-sky-400 mr-1"></i>
                                    <?= $hora !== '' ? htmlspecialchars($hora, ENT_QUOTES, 'UTF-8') : 'En breve' ?>
                                </span>
                                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-300 bg-emerald-900/60 backdrop-blur-md px-3 py-1 rounded-full border border-emerald-500/40 flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <?= (string)$viaje['est_via'] === Config::VIA_EN_CURSO ? 'En curso' : 'Activo' ?>
                                </span>
                            </div>

                            <div class="relative z-10 space-y-3 pt-4 border-t border-white/15">
                                <div>
                                    <?php if (!empty($viaje['val_via'])): ?>
                                        <p class="text-xs font-black uppercase tracking-wider text-amber-300 drop-shadow-md">
                                            $<?= number_format((float)$viaje['val_via'], 0, ',', '.') ?> COP
                                        </p>
                                    <?php endif; ?>

                                    <h3 class="font-black text-white text-xl sm:text-2xl tracking-tight leading-tight truncate drop-shadow-lg"
                                        title="<?= htmlspecialchars($tituloRuta, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($tituloRuta, ENT_QUOTES, 'UTF-8') ?>
                                    </h3>
                                    <p class="text-[11px] font-semibold text-slate-300 flex items-center gap-1 mt-0.5">
                                        <i class="fas fa-bus-alt text-sky-400"></i> Placa:
                                        <span class="font-mono text-white"><?= htmlspecialchars((string)($viaje['pla_veh'] ?? 'Sin asignar'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </p>
                                </div>

                                <button type="button" data-sget-modal="panelLogin"
                                        class="w-full py-3 bg-sky-500 hover:bg-sky-400 active:bg-sky-600 text-slate-950 font-black text-xs uppercase tracking-widest rounded-2xl shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer group-hover:shadow-sky-500/30">
                                    <i class="fas fa-ticket-alt"></i> Reservar pasaje
                                </button>
                            </div>

                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full py-16 px-6 text-center card-glass rounded-3xl">
                        <div class="w-16 h-16 rounded-2xl bg-sky-500/10 text-sky-500 flex items-center justify-center mx-auto mb-4 text-2xl">
                            <i class="fas fa-route"></i>
                        </div>
                        <p class="text-base font-extrabold text-slate-800 dark:text-slate-200">No hay viajes activos programados en este momento</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">Las nuevas salidas aparecerán aquí automáticamente tan pronto sean asignadas por la administración.</p>
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

        <div class="max-w-7xl mx-auto px-6"><div class="divider-glow"></div></div>

        <!-- ================================================================== -->
        <!-- SECCIÓN 4: NOSOTROS                                                -->
        <!-- ================================================================== -->
        <!--
            ESTA SECCIÓN NO EXISTÍA Y EL MENÚ YA LA ENLAZABA.
            El header declara cinco entradas — Inicio, Viaja con nosotros, Servicios,
            Nosotros y Contacto — pero el documento solo tenía tres secciones.
            Dos enlaces del menú principal eran enlaces MUERTOS: clic y no pasaba
            nada. Aquí se crean con contenido real, no como relleno.
        -->
        <section id="nosotros" class="py-16 px-6 max-w-6xl mx-auto space-y-10 scroll-mt-28">
            <div class="text-center space-y-2">
                <span class="text-xs font-extrabold text-indigo-500 uppercase tracking-widest">Sobre el sistema</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Una plataforma pensada para operar</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl mx-auto leading-relaxed">
                    SGET coordina la operación completa: disponibilidad real de flota y conductores,
                    reservas sin sobreventa, cobros en terminal y avisos al pasajero en cada cambio.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <?php foreach ([
                    ['fa-clock-rotate-left', 'Reserva sin sobreventa',
                     'El cupo se descuenta dentro de una transacción: dos pasajeros simultáneos nunca se pasan de la capacidad.'],
                    ['fa-user-shield', 'Permisos por rol',
                     'Cada módulo comprueba el permiso en el servidor, no solo que el botón esté oculto.'],
                    ['fa-bell', 'Avisos en tiempo real',
                     'Cada cambio de un viaje —cancelación, recordatorio, cierre— llega al buzón del pasajero.'],
                    ['fa-chart-simple', 'Información para decidir',
                     'Reportes de viajes, reservas, rutas y Conductores con datos reales de la operación.'],
                ] as [$icono, $titulo, $texto]): ?>
                    <article class="card-glass glow-hover rounded-3xl p-6 space-y-3 text-left group">
                        <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-sky-500 text-lg group-hover:scale-110 transition-transform">
                            <i class="fas <?= $icono ?>"></i>
                        </span>
                        <h3 class="font-extrabold text-base text-slate-900 dark:text-white"><?= $titulo ?></h3>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed"><?= $texto ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="max-w-7xl mx-auto px-6"><div class="divider-glow"></div></div>

        <!-- ================================================================== -->
        <!-- SECCIÓN 5: CONTACTO                                                  -->
        <!-- ================================================================== -->
        <!-- Misma razón que la anterior: el menú la enlazaba y no existía. -->
        <section id="contacto" class="py-16 px-6 max-w-5xl mx-auto space-y-8 scroll-mt-28">
            <div class="text-center space-y-2">
                <span class="text-xs font-extrabold text-sky-500 uppercase tracking-widest">Contacto</span>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">¿Necesitas ayuda?</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl mx-auto leading-relaxed">
                    Para consultas de reservas, incidencias de un viaje o información de rutas.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <?php foreach ([
                    ['fa-building',  'Terminal de transporte',
                     'Av. Principal s/n · Lunes a sábado, 7:00 a 18:00', 'fa-clock'],
                    ['fa-envelope',  'Correo electrónico',
                     'soporte@sget.local · Respuesta en el día hábil', 'fa-paper-plane'],
                    ['fa-phone',     'Teléfono',
                     '+57 000 000 0000 · Línea de atención', 'fa-phone-volume'],
                    ['fa-user-tie',  'Atención personalizada',
                     'Preséntate en la terminal con tu documento', 'fa-id-card'],
                ] as [$icono, $titulo, $texto, $iconoTexto]): ?>
                    <article class="card-glass rounded-3xl p-6 flex items-start gap-4">
                        <span class="inline-flex items-center justify-center w-11 h-11 shrink-0 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-lg">
                            <i class="fas <?= $icono ?>"></i>
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-extrabold text-base text-slate-900 dark:text-white"><?= $titulo ?></h3>
                            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed flex items-center gap-1.5 mt-0.5">
                                <i class="fas <?= $iconoTexto ?> text-[10px] opacity-60"></i>
                                <?= $texto ?>
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- CIERRE: llamada final a la acción -->
            <div class="text-center pt-2">
                <button type="button" data-sget-ir-a="panelLogin"
                        class="inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-2xl bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-sm shadow-xl shadow-sky-500/25 hover:shadow-2xl hover:shadow-sky-500/35 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                    <i class="fas fa-right-to-bracket"></i>
                    Entrar al sistema
                </button>
            </div>
        </section>

    </main>

    <!-- FOOTER CON ENLACE LEGAL Y COOKIES -->
    <footer class="p-6 text-center text-slate-500 dark:text-slate-400 text-xs font-semibold border-t border-slate-200 dark:border-white/10 bg-white/80 dark:bg-[#0b0f19]/80 backdrop-blur-md flex flex-col sm:flex-row items-center justify-between max-w-7xl mx-auto w-full gap-4">
        <p>&copy; 2026 SGET - Sistema de Gestión de Transporte. Todos los derechos reservados.</p>
        <div class="flex items-center gap-4">
            <button type="button" onclick="abrirConfiguracionCookies()"
                    title="Ver y cambiar tus preferencias de cookies"
                    class="hover:text-amber-600 dark:hover:text-amber-400 underline transition-colors cursor-pointer flex items-center gap-1.5">
                <i class="fas fa-cookie-bite text-amber-700 dark:text-amber-500"></i> Configuración de Cookies
            </button>
            <span>•</span>
            <button data-sget-modal="panelPolitica" class="hover:text-sky-500 underline transition-colors cursor-pointer">
                Tratamiento de Datos Personales (Ley 1581)
            </button>
        </div>
    </footer>

    <!-- ================================================================== -->
    <!-- ACCESO FLOTANTE A SGET                                             -->
    <!-- ================================================================== -->
    <!-- Sigue al scroll y refleja el estado real de la sesión. Se oculta      -->
    <!-- mientras el aviso de cookies está abierto (misma esquina).            -->
    <?php include 'views/partials/acceso_landing.php'; ?>

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
                    <button type="button" onclick="abrirConfiguracionCookies()" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20 text-slate-800 dark:text-white border border-slate-200 dark:border-white/10 font-extrabold text-[10px] uppercase tracking-wider rounded-xl transition-all cursor-pointer">
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

            <!--
                ESTADO REAL, MOSTRADO AL ABRIR EL PANEL
                Se rellena con `sincronizarPanelCookies()` para que el usuario vea
                lo que está guardado de verdad, no un estado supuesto.
            -->
            <div class="my-4 px-4 py-3 rounded-2xl bg-sky-500/10 border border-sky-500/20 flex items-start gap-2.5">
                <i class="fas fa-circle-info text-sky-500 mt-0.5"></i>
                <p class="text-[11px] text-slate-600 dark:text-slate-300">
                    <span id="estadoConsentimientoCookies">Cargando estado…</span>
                </p>
            </div>

            <!-- OPCIONES DE CONFIGURACIÓN -->
            <div class="my-4 overflow-y-auto pr-2 space-y-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed text-left">

                <!-- 1. NECESARIAS · siempre activas, no se offering -->
                <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-white/5 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-extrabold text-slate-900 dark:text-white text-sm">Necesarias</span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 whitespace-nowrap">Siempre activas</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        La cookie de sesión <code class="sget-mono">PHPSESSID</code> mantiene tu acceso
                        abierto y protege el sistema frente a{seciones y ataques. Se crea al iniciar
                        sesión, es <code class="sget-mono">HttpOnly</code> (el navegador no permite que
                        JavaScript la lea) y usa <code class="sget-mono">SameSite=Lax</code>.
                        No se puede desactivar porque sin ella no hay sesión ni seguridad posible.
                    </p>
                </div>

                <!-- 2. PREFERENCIAS · tema -->
                <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-white/5 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <label for="chkCookiePreferencias" class="font-extrabold text-slate-900 dark:text-white text-sm cursor-pointer">Preferencias</label>
                        <input type="checkbox" id="chkCookiePreferencias" class="w-4 h-4 rounded text-sky-500 focus:ring-sky-400 dark:bg-slate-900 cursor-pointer shrink-0">
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        Recuerdan el <strong>tema claro u oscuro</strong> de la interfaz para no tener que
                        elegirlo en cada visita. Se guarda en la cookie
                        <code class="sget-mono">sget_tema</code>.
                    </p>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 leading-snug">
                        Si la desmarcas, la cookie <code class="sget-mono">sget_tema</code> se elimina
                        y el tema deja de recordarse: volverá al de tu sistema en cada visita.
                    </p>
                </div>

                <!-- 3. ANALÍTICA · se declara que NO se usa -->
                <div class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-white/5 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-extrabold text-slate-900 dark:text-white text-sm">Analítica</span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-slate-500/10 text-slate-600 dark:text-slate-300 border border-slate-400/20 whitespace-nowrap">No utilizada</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        <strong>No utilizamos cookies analíticas actualmente.</strong> No hay Google
                        Analytics ni ninguna herramienta externa de medición: SGET no envía datos de
                        navegación a terceros.
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
        /* ------------------------------------------------------------------
           GESTIÓN DE COOKIES Y PREFERENCIAS
           ------------------------------------------------------------------
           DELega TODO en `window.SGETCookies` (assets/js/theme-init.js), que es
           el ÚNICO lugar del proyecto que escribe cookies.

           Antes esta lógica vivía aquí y el escritor de la cookie estaba en el
           theme-init, sin relación entre ambos. El resultado era la incoherencia
           que había que eliminar: el panel decía «Preferencias: desactivadas» y
           `sget_tema` se creaba igual en la siguiente carga.

           La decisión de consentimiento se guarda en `localStorage` porque no es
           una cookie: no la crea JavaScript sola ni viaja al servidor.
        ------------------------------------------------------------------ */

        function leerConsentimientoCookies() {
            return (window.SGETCookies && window.SGETCookies.consentimiento)
                ? window.SGETCookies.consentimiento()
                : null;
        }

        /** Refleja en el panel lo que hay guardado AHORA MISMO. */
        function sincronizarPanelCookies() {
            const consent = leerConsentimientoCookies();
            const chkPref = document.getElementById('chkCookiePreferencias');
            const chkAnl  = document.getElementById('chkCookieAnalitica');
            if (chkPref) chkPref.checked = !!(consent && consent.preferencias);
            if (chkAnl)  chkAnl.checked  = !!(consent && consent.analitica);

            const estado = document.getElementById('estadoConsentimientoCookies');
            if (estado) {
                if (!consent) {
                    estado.textContent = 'Todavía no has guardado tus preferencias.';
                } else if (consent.preferencias) {
                    estado.textContent = 'Preferencias activas: el tema se recuerda entre visitas.';
                } else {
                    estado.textContent = 'Solo cookies necesarias: el tema no se recuerda.';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            sincronizarPanelCookies();

            // El aviso solo aparece si el usuario aún no ha decidido nada.
            if (!leerConsentimientoCookies()) {
                const banner = document.getElementById('cookieBanner');
                if (banner) banner.classList.remove('hidden');
            }
        });

        /** Aplica el consentimiento: crea o borra `sget_tema` según corresponda. */
        function aplicarPreferenciasCookies(prefs) {
            if (window.SGETCookies && window.SGETCookies.guardar) {
                window.SGETCookies.guardar(prefs);
            }
            sincronizarPanelCookies();
        }

        function aceptarTodasCookies() {
            aplicarPreferenciasCookies({ necesarias: true, preferencias: true, analitica: false });
            ocultarBannerYModalesCookies();
        }

        function rechazarCookiesOpcionales() {
            aplicarPreferenciasCookies({ necesarias: true, preferencias: false, analitica: false });
            ocultarBannerYModalesCookies();
        }

        function guardarConfiguracionCookies() {
            aplicarPreferenciasCookies({
                necesarias:   true,
                preferencias: !!(document.getElementById('chkCookiePreferencias') && document.getElementById('chkCookiePreferencias').checked),
                analitica:    !!(document.getElementById('chkCookieAnalitica') && document.getElementById('chkCookieAnalitica').checked)
            });
            cerrarPanel('panelConfigCookies');
            ocultarBannerYModalesCookies();
        }

        function ocultarBannerYModalesCookies() {
            const banner = document.getElementById('cookieBanner');
            if (banner) banner.classList.add('hidden');
            document.dispatchEvent(new CustomEvent('sget:cookies-cerradas'));
        }

        /* Botón «Configuración de Cookies» del pie: abre el panel con el estado
           real guardado, para poder cambiarlo en cualquier momento. */
        function abrirConfiguracionCookies() {
            sincronizarPanelCookies();
            abrirPanel('panelConfigCookies');
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

        /* Sombra de la cabecera al bajar: separa el fijo del contenido sin
           necesitar una imagen ni un degradado. */
        const cabecera = document.getElementById('landingHeader');
        if (cabecera) {
            const marcarScroll = () => {
                cabecera.dataset.scroll = window.scrollY > 8 ? '1' : '0';
            };
            marcarScroll();
            window.addEventListener('scroll', marcarScroll, { passive: true });
        }

        /* ------------------------------------------------------------------
           NAVEGACIÓN DE LA PORTADA · desplazamiento suave + sección activa
           ------------------------------------------------------------------
           El menú usa anclas (`#seccion`). Sin este bloque el salto del
           navegador es seco e instantáneo, y además el resaltado de la entrada
           activa se quedaba siempre en «Inicio» por mucho que se bajara.

           Se respeta `prefers-reduced-motion`: quien tiene el movimiento
           reducidoACTIVADO recibe un salto directo, sin animación.
           ------------------------------------------------------------------ */
        (function () {
            const enlaces = document.querySelectorAll('[data-sget-seccion]');
            if (!enlaces.length) return;

            const suave = !window.matchMedia
                || !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            /* --- Desplazamiento suave --- */
            document.addEventListener('click', function (event) {
                const enlace = event.target.closest('[data-sget-seccion]');
                if (!enlace) return;

                const seccion = document.getElementById(enlace.dataset.sgetSeccion);
                if (!seccion) return;           // deja que el navegador haga su cosa

                event.preventDefault();

                /* UN SOLO SCROLL, DELEGANDO EL DESPLAZAMIENTO AL NAVEGADOR.
                 *
                 * El fallo que se corrige: se encadenaban DOS desplazamientos,
                 *
                 *     seccion.scrollIntoView({ behavior: 'smooth' });
                 *     window.scrollBy({ top: -alto, behavior: 'smooth' });
                 *
                 * El primero arranca un scroll suave asíncrono y el segundo
                 * lanza otro por encima mientras el primero sigue en curso: el
                 * navegador cancela el primero y el destino queda a merced del
                 * segundo. En la práctica la página no se movía y el clic en
                 * «Nosotros» no hacía nada.
                 *
                 * Ahora es UNA sola llamada. El hueco que deja la cabecera fija
                 * NO se calcula aquí a mano: lo declara el CSS con
                 * `scroll-margin-top` en `section[id]` (ver assets/css/index.css).
                 * Así el margen vive en un único sitio y no hay dos medidas de la
                 * misma cabecera que puedan desincronizarse.
                 */
                seccion.scrollIntoView({
                    behavior: suave ? 'smooth' : 'auto',
                    block: 'start'
                });

                // Al navegar con teclado el foco debe viajar a la sección; si no,
                // el siguiente Tab sigue en el menú y parece que no ha pasado nada.
                if (!seccion.hasAttribute('tabindex')) {
                    seccion.setAttribute('tabindex', '-1');
                }
                seccion.focus({ preventScroll: true });

                // La URL debe reflejar la sección (compartir enlace, botón atrás).
                if (history.replaceState) {
                    history.replaceState(null, '', enlace.getAttribute('href') || ('#' + enlace.dataset.sgetSeccion));
                }

                marcarActivo(enlace.dataset.sgetSeccion);
                cerrarMenuMovil();
            });

            /* --- Sección activa según la posición del scroll --- */
            function marcarActivo(clave) {
                enlaces.forEach(function (el) {
                    const activo = el.dataset.sgetSeccion === clave;
                    el.classList.toggle('es-activo', activo);
                    if (activo) el.setAttribute('aria-current', 'true');
                    else el.removeAttribute('aria-current');
                });
            }

            function cerrarMenuMovil() {
                const menu = document.querySelector('[data-abierto="1"]');
                if (!menu) return;
                menu.dataset.abierto = '0';
                const boton = document.querySelector('[aria-controls="' + menu.id + '"]');
                if (boton) boton.setAttribute('aria-expanded', 'false');
            }

            let pendiente = null;
            function alDesplazar() {
                if (pendiente) return;
                pendiente = requestAnimationFrame(function () {
                    pendiente = null;

                    const referencia = window.scrollY + window.innerHeight * 0.35;
                    let actual = null;
                    document.querySelectorAll('section[id]').forEach(function (s) {
                        if (s.offsetTop <= referencia) actual = s.id;
                    });
                    if (actual) marcarActivo(actual);
                });
            }

            window.addEventListener('scroll', alDesplazar, { passive: true });
            window.addEventListener('resize', alDesplazar, { passive: true });
            alDesplazar();
        })();

        /* ------------------------------------------------------------------
           ACCESO FLOTANTE · se aparta cuando el aviso de cookies está abierto
           ------------------------------------------------------------------
           El botón vive abajo a la izquierda y el aviso de cookies abajo a la
           derecha; en móvil el aviso ocupa todo el ancho. Dos cajas apiladas en
           la misma esquina tapan el contenido y hacen clic donde no toca, así
           que el acceso flotante se oculta mientras el aviso siga abierto.

           POR QUÉ UN OBSERVADOR Y NO UN EVENTO
             La primera versión solo sincronizaba al cargar y al hacer clic, y
             fallaba: en ese momento el aviso todavía tenía la clase `hidden`
             (se quita en otro `DOMContentLoaded` posterior), así que el script
             veía «cerrado», lo dejaba visible y no volvía a comprobar nada.
             Con `MutationObserver` sobre el propio aviso se detecta cualquier
             cambio de estado, venga de donde venga.
           ------------------------------------------------------------------ */
        (function () {
            const acceso = document.getElementById('accesoFlotante');
            const banner = document.getElementById('cookieBanner');
            if (!acceso || !banner) return;

            function sincronizar() {
                const visible = !banner.classList.contains('hidden');
                acceso.dataset.sgetOculto = visible ? '1' : '0';
                acceso.setAttribute('aria-hidden', visible ? 'true' : 'false');
            }

            new MutationObserver(sincronizar)
                .observe(banner, { attributes: true, attributeFilter: ['class'] });

            sincronizar();
            document.addEventListener('DOMContentLoaded', sincronizar);
        })();

        // Navegación interna entre modales (login <-> registro, política)
        document.addEventListener('click', function (event) {
            const enlace = event.target.closest('[data-sget-ir-a]');
            if (!enlace) return;
            event.preventDefault();
            cambiarAPanel(enlace.dataset.sgetIrA);
        });

        document.addEventListener("DOMContentLoaded", function () {
            <?php
            /* Reapertura automática del modal correspondiente tras un
               POST/redirect (PRG). Antes solo se miraba `abrir_login`, así que
               un error de REGISTRO (quepone `abrir_registro`) se mostraba en el
               modal equivocado o directamente no se veía. */
            $abrirLogin    = !empty($_SESSION['abrir_login']);
            $abrirRegistro = !empty($_SESSION['abrir_registro']);
            unset($_SESSION['abrir_login'], $_SESSION['abrir_registro']);
            ?>
            <?php if ($abrirRegistro): ?>
                abrirPanel('panelRegistro');
            <?php elseif ($abrirLogin): ?>
                abrirPanel('panelLogin');
            <?php endif; ?>
        });
    </script>
</body>
</html>
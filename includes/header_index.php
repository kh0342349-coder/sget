<?php
/**
 * includes/header_index.php
 * -----------------------------------------------------------------------------
 * CABECERA DE LA PORTADA PÚBLICA
 * -----------------------------------------------------------------------------
 * COMPONENTE REUTILIZABLE, no un bloque copiado en cada página.
 *   · El marcado y el comportamiento de la navegación viven AQUÍ.
 *   · `includes/landing_nav.php` aporta los enlaces, de modo que añadir una
 *     sección nueva de la landing es cambiar una lista, no reescribir el
 *     HTML ni el JavaScript en dos sitios.
 *   · Los botones «Iniciar sesión» / «Registrarse» se montan con el MISMO
 *     motor de modales del panel (`data-sget-modal`), así que el velo, el foco
 *     atrapado y la tecla Escape se comportan igual en toda la aplicación.
 * -----------------------------------------------------------------------------
 */
if (!class_exists('Auth')) {
    require_once dirname(__DIR__) . '/core/bootstrap.php';
}
require_once __DIR__ . '/i18n.php';

$estaAutenticado  = Auth::estaLogueado();
$nombreRealHeader = $estaAutenticado ? htmlspecialchars(Auth::nombre(), ENT_QUOTES, 'UTF-8') : '';
$rolActualHeader  = $estaAutenticado ? (int) Auth::rol() : 0;

/* Etiqueta del rol, para el panel de la portada. */
$etiquetaRol = match ($rolActualHeader) {
    Config::ROL_ADMIN     => 'Administrador',
    Config::ROL_CONDUCTOR => 'Conductor',
    Config::ROL_PASAJERO  => 'Pasajero',
    default               => 'Sesión activa',
};

/* Enlaces de la portada: sección => [rótulo, icono, ANCLA].
 *
 * EL ANCLA ES UNA ANCLA, NO UNA RUTA
 *   Antes cada enlace era `index.php#seccion`. Estando ya en la portada eso
 *   provocaba una RECARGA COMPLETA del documento para mover el scroll unos
 *   píxeles: se perdía el estado, se pedían otra vez los mismos recursos y el
 *   salto era seco y sin animación.
 *
 *   Este header solo se incluye en `index.php`, así que un `#seccion` a secas
 *   siempre funciona, degrada bien sin JavaScript y permite el desplazamiento
 *   suave y el resaltado de la sección activa (`data-sget-seccion`).
 */
$seccionesLanding = [
    ['inicio',              'Inicio',             'fa-house',       '#inicio'],
    ['viajes-disponibles', 'Viaja con nosotros', 'fa-bus',         '#viajes-disponibles'],
    ['servicios',          'Servicios',          'fa-layer-group', '#servicios'],
    ['nosotros',            'Nosotros',           'fa-circle-info', '#nosotros'],
    ['contacto',            'Contacto',           'fa-envelope',    '#contacto'],
];

$paginaActualLanding = $pagina_actual ?? 'index';
$BASE = Config::basePath();
?>

<!-- CABECERA FLOTANTE DE LA PORTADA -->
<header class="landing-header" id="landingHeader">
    <div class="landing-header__inner">

        <!-- LOGO -->
        <a href="<?= $BASE ?>/index.php" class="landing-header__marca" aria-label="SGET, ir al inicio">
            <img src="<?= $BASE ?>/img/largo-blanco.png" alt="SGET"
                 class="landing-header__logo landing-header__logo--claro">
            <img src="<?= $BASE ?>/img/largo-negro.png" alt="SGET"
                 class="landing-header__logo landing-header__logo--oscuro">
        </a>

        <!-- NAVEGACIÓN CENTRAL (escritorio) -->
        <nav class="landing-nav" aria-label="Secciones de SGET">
            <?php foreach ($seccionesLanding as $i => [$clave, $rotulo, $icono, $ancla]): ?>
                <a href="<?= $ancla ?>"
                   class="landing-nav__link<?= $i === 0 ? ' es-activo' : '' ?>"
                   data-sget-seccion="<?= $clave ?>"
                   <?= $i === 0 ? 'aria-current="true"' : '' ?>>
                    <i class="fas <?= $icono ?>" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- ACCIONES -->
        <div class="landing-header__acciones">

            <!-- Selector de idioma -->
            <select data-sget-language aria-label="Idioma / Language" title="Cambiar idioma"
                    class="sget-language-selector landing-header__idioma">
                <option value="es" <?= $idiomaActual === 'es' ? 'selected' : '' ?>>🇪🇸 ESP</option>
                <option value="en" <?= $idiomaActual === 'en' ? 'selected' : '' ?>>🇺🇸 ENG</option>
            </select>

            <!-- Tema claro / oscuro -->
            <button id="theme-toggle" type="button" class="landing-header__icono"
                    aria-label="Cambiar entre tema claro y oscuro" title="Cambiar tema">
                <i id="themeIcon" class="fas fa-moon" aria-hidden="true"></i>
            </button>

            <?php if (!$estaAutenticado): ?>
                <div class="landing-header__acceso">
                    <button type="button" data-sget-modal="panelLogin" class="landing-header__boton landing-header__boton--neutro">
                        <i class="fas fa-right-to-bracket" aria-hidden="true"></i> Iniciar sesión
                    </button>
                    <button type="button" data-sget-modal="panelRegistro" class="landing-header__boton landing-header__boton--primario">
                        <i class="fas fa-user-plus" aria-hidden="true"></i> Registrarse
                    </button>
                </div>
            <?php else: ?>
                <!-- Tarjeta de sesión: nombre, rol y salida -->
                <div class="landing-header__sesion">
                    <a class="landing-header__perfil" href="<?= $BASE ?>/<?= Auth::inicioPorRol() ?>"
                       title="Ir a mi panel">
                        <span class="landing-header__avatar" aria-hidden="true">
                            <?= mb_strtoupper(mb_substr(Auth::nombre(), 0, 1)) ?: 'U' ?>
                        </span>
                        <span class="landing-header__perfil-datos">
                            <strong><?= $nombreRealHeader ?></strong>
                            <small><?= htmlspecialchars($etiquetaRol, ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                    </a>
                    <!-- Ver includes/header.php: el cierre es POST + token. -->
                    <form method="POST" action="<?= $BASE ?>/assets/cerrar.php" class="contents">
                        <?= Auth::campoToken() ?>
                        <button type="submit" class="landing-header__salir cursor-pointer"
                                title="Cerrar sesión" aria-label="Cerrar sesión">
                            <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Menú móvil -->
            <button type="button" data-sget-burger aria-label="Abrir menú" aria-expanded="false"
                    class="landing-header__burger">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <!-- Menú desplegable (móvil / tablet) -->
    <nav class="landing-menu" data-abierto="0" aria-label="Secciones de SGET (menú móvil)">
        <?php foreach ($seccionesLanding as $i => [$clave, $rotulo, $icono, $ancla]): ?>
            <a href="<?= $ancla ?>" class="landing-menu__link<?= $i === 0 ? ' es-activo' : '' ?>"
               data-sget-seccion="<?= $clave ?>">
                <i class="fas <?= $icono ?>" aria-hidden="true"></i>
                <span><?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>

        <?php if (!$estaAutenticado): ?>
            <div class="landing-menu__acceso">
                <button type="button" data-sget-modal="panelLogin" class="sget-btn sget-btn--bloque sget-btn--neutro">
                    <i class="fas fa-right-to-bracket"></i> Iniciar sesión
                </button>
                <button type="button" data-sget-modal="panelRegistro" class="sget-btn sget-btn--bloque sget-btn--primario">
                    <i class="fas fa-user-plus"></i> Registrarse
                </button>
            </div>
        <?php else: ?>
            <form method="POST" action="<?= $BASE ?>/assets/cerrar.php" class="contents">
                <?= Auth::campoToken() ?>
                <button type="submit" class="landing-menu__salir cursor-pointer w-full">
                    <i class="fas fa-arrow-right-from-bracket"></i> Cerrar sesión
                </button>
            </form>
        <?php endif; ?>
    </nav>
</header>

<?php /* El idioma, `data-language` e i18n.js los emite includes/i18n.php (una sola vez). */ ?>

<!-- Motor común de modales: el MISMO que usa el panel interno -->
<script src="<?= $BASE ?>/assets/js/sget-modal.js?v=<?= @filemtime(Config::raiz('assets/js/sget-modal.js')) ?: '1' ?>"></script>

<script>
/* -------------------------------------------------------------------------
   COMPORTAMIENTO DE LA CABECERA
   -------------------------------------------------------------------------
   Se delega en `SGETTheme`, la API compartida con el panel, para que el botón
   de la luna haga EXACTAMENTE lo mismo en la portada que dentro de la
   aplicación (incluido el evento `sget:tema`, que obliga a repintar el botón de
   Google y el reCAPTCHA al cambiar el tema).
   ------------------------------------------------------------------------- */
(function () {
    'use strict';

    var btn = document.getElementById('theme-toggle');
    if (btn && window.SGETTheme) {
        btn.addEventListener('click', function () { window.SGETTheme.toggle(); });
    }

    /* Menú móvil.
       `data-abierto` es el único estado: el CSS decide la visibilidad, así que
       el botón no tiene que pelearse con las clases de Tailwind. Además se
       cierra con Escape y al pulsar fuera, y devuelve el foco al botón. */
    var burger = document.querySelector('[data-sget-burger]');
    var menu   = document.querySelector('.landing-menu');

    if (burger && menu) {
        var cerrarMenu = function () {
            menu.dataset.abierto = '0';
            burger.setAttribute('aria-expanded', 'false');
        };

        burger.addEventListener('click', function (e) {
            e.stopPropagation();
            var abierto = menu.dataset.abierto === '1';
            menu.dataset.abierto = abierto ? '0' : '1';
            burger.setAttribute('aria-expanded', abierto ? 'false' : 'true');
        });

        document.addEventListener('click', function (e) {
            if (menu.dataset.abierto !== '1') return;
            if (e.target.closest('.landing-menu') || e.target.closest('[data-sget-burger]')) return;
            cerrarMenu();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && menu.dataset.abierto === '1') {
                cerrarMenu();
                burger.focus();
            }
        });
    }

    /* Al marcar un enlace del menú en móvil se cierra: si no, el menú sigue
       tapando la sección a la que se acaba de ir. */
    if (menu) {
        menu.addEventListener('click', function (e) {
            if (e.target.closest('a[href]')) cerrarMenu();
        });
    }
})();
</script>
<?php
/**
 * includes/modal_inactividad.php
 * -----------------------------------------------------------------------------
 * BLOQUEO DE SESIÓN POR INACTIVIDAD
 * -----------------------------------------------------------------------------
 * REFACTOR: la versión anterior llevaba todos los colores FIJOS en estilo
 * inline (background:#0f172a, color:#f8fafc, rgba(15,23,42,.85) + blur). Eso
 * producía dos fallos reportados:
 *   · en tema claro se veía un cuadro negro dentro de una página blanca;
 *   · el fondo era semitransparente, así que el sidebar se veía "a través" del
 *     bloqueo y parecía que se podía seguir usando.
 * Ahora usa el sistema de diseño (tokens del tema) y un fondo opaco, y el
 * bloqueo se aplica sobre TODA la ventana con `inert` (lo hace js/inactividad.js
 * sobre las ramas del DOM hermanas).
 *
 * IDs que js/inactividad.js necesita (no renombrar sin actualizar el JS):
 *   modalBloqueoInactividad · inputPasswordModal · btnDesbloquearModal
 *   mensajeErrorModal · contenedorTemporizadorInactividad · temporizadorRegresivoModal
 * -----------------------------------------------------------------------------
 */

$inactividadInicialmenteBloqueada = !empty($_SESSION['sesion_bloqueada']);
$inactividadContexto = hash('sha256', (string)($_SESSION['inactividad_contexto'] ?? bin2hex(random_bytes(16))));

$inactividadConfig = [
    'inicialmenteBloqueada' => $inactividadInicialmenteBloqueada,
    'contexto'              => $inactividadContexto,
    // El plazo llega desde el servidor: el cliente no decide cuánto se espera.
    'minutosInactividad'    => Config::MINUTOS_INACTIVIDAD,
    'segundosGracia'        => Config::SEGUNDOS_GRACIA_INACTIVIDAD,
    'bloqueadaEn'           => isset($_SESSION['inactividad_bloqueada_en']) ? (int) $_SESSION['inactividad_bloqueada_en'] : null,
    'temporizadorIniciaEn'  => isset($_SESSION['inactividad_temporizador_inicia_en']) ? (int) $_SESSION['inactividad_temporizador_inicia_en'] : null,
    'cierraEn'              => isset($_SESSION['inactividad_cierra_en']) ? (int) $_SESSION['inactividad_cierra_en'] : null,
];

/** Resuelve una ruta interna desde Admin/, Conductor/, Pasajero/ o la raíz. */
function obtenerRutaCs(string $ruta): string
{
    $uri = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '');
    return preg_match('~/(admin|conductor|pasajero)/~i', $uri) ? '../' . $ruta : $ruta;
}
?>
<div id="modalBloqueoInactividad"
     role="dialog" aria-modal="true"
     aria-labelledby="tituloBloqueoInactividad"
     aria-describedby="descripcionBloqueoInactividad"
     style="display: <?= $inactividadInicialmenteBloqueada ? 'flex' : 'none' ?>">

    <div class="sget-inactividad-velo"></div>

    <div class="sget-inactividad-caja" role="document">
        <span class="sget-inactividad-icono"><i class="fas fa-lock"></i></span>

        <h3 id="tituloBloqueoInactividad">Sesión bloqueada por inactividad</h3>
        <p id="descripcionBloqueoInactividad">
            Ingresa la contraseña de tu cuenta para reanudar la sesión.
        </p>

        <div id="mensajeErrorModal" class="sget-flash sget-flash--error" role="alert" aria-live="assertive" style="display:none"></div>

        <div style="text-align:left">
            <input type="password" id="inputPasswordModal" class="sget-input"
                   autocomplete="current-password" placeholder="Contraseña actual"
                   aria-label="Contraseña actual">
        </div>

        <button id="btnDesbloquearModal" type="button" class="sget-btn sget-btn--primario sget-btn--bloque">
            <i class="fas fa-unlock"></i> Desbloquear sesión
        </button>

        <div id="contenedorTemporizadorInactividad" style="display:none;margin-top:1rem"></div>
        <span id="temporizadorRegresivoModal" style="display:none"></span>

        <p style="margin:1.25rem 0 0">
            <a id="enlaceCerrarSesionInactividad" class="sget-help"
               href="<?= htmlspecialchars(obtenerRutaCs('assets/cerrar.php'), ENT_QUOTES, 'UTF-8') ?>">
                Cerrar sesión
            </a>
        </p>
    </div>
</div>

<script>
    /* El bloqueo tiene que tapar la VENTANA COMPLETA, sidebar incluido.
       Este partial se emite dentro de `.sget-shell` (lo incluye header.php), y
       `.sget-shell` tiene una animación de entrada que lo convierte en bloque
       contenedor: un `position: fixed` con `inset: 0` anclado ahí solo cubría
       la columna del contenido y dejaba el menú lateral totalmente utilizable.
       Por eso el modal se sube a <body> de inmediato, sin esperar al JS del
       bloqueo (mismo criterio que `montarEnBody()` en assets/js/sget-modal.js). */
    (function () {
        var bloqueo = document.getElementById('modalBloqueoInactividad');
        if (bloqueo && bloqueo.parentElement !== document.body) {
            document.body.appendChild(bloqueo);
        }
    })();
</script>

<style>
    /* El bloqueo de inactividad es un caso especial: necesita tapar la página
       entera, incluido el sidebar, así que NO usa .sget-modal-wrap. */
    #modalBloqueoInactividad {
        position: fixed;
        inset: 0;
        z-index: 99999;                 /* por encima del sidebar (z-50) */
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    /* Velo opaco: si fuera semitransparente se vería el sidebar "detrás" */
    .sget-inactividad-velo {
        position: absolute;
        inset: 0;
        z-index: 0;
        background: var(--sget-overlay-osc);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }

    .sget-inactividad-caja {
        position: relative;
        z-index: 1;                     /* por encima del velo */
        width: 100%;
        max-width: 24rem;
        text-align: center;
        padding: 2rem 1.5rem;
        border-radius: var(--sget-radio-lg);
        background: var(--sget-superficie);
        color: var(--sget-texto);
        border: 1px solid var(--sget-borde);
        box-shadow: 0 25px 60px -12px rgba(0, 0, 0, .7);
    }

    .sget-inactividad-icono {
        display: grid;
        place-items: center;
        width: 3.5rem; height: 3.5rem;
        margin: 0 auto 1.25rem;
        border-radius: var(--sget-radio);
        font-size: 1.375rem;
        background: color-mix(in srgb, var(--sget-ambars) 15%, transparent);
        color: var(--sget-ambars);
        border: 1px solid color-mix(in srgb, var(--sget-ambars) 35%, transparent);
    }

    .sget-inactividad-caja h3 { margin: 0 0 .5rem; font-size: 1.125rem; font-weight: 800; }
    .sget-inactividad-caja p  { margin: 0 0 1.25rem; font-size: .8125rem; color: var(--sget-texto-suave); }
    .sget-inactividad-caja .sget-input { margin-bottom: 1rem; }
</style>

<script>
    window.SGET_INACTIVITY_CONFIG = <?= json_encode($inactividadConfig,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= obtenerRutaCs('js/inactividad.js') ?>"></script>

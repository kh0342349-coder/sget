<?php
/**
 * includes/i18n.php
 * -----------------------------------------------------------------------------
 * IDIOMA + DICCIONARIO + i18n.js  ·  punto ÚNICO de entrada
 * -----------------------------------------------------------------------------
 * Este partial se incluye desde `includes/header.php`, `includes/sidebar.php` e
 * `includes/header_index.php`. Es idempotente: aunque se incluya varias veces,
 * el diccionario se carga una sola vez y el `<script>` de i18n.js se emite una
 * sola vez.
 *
 * ANTES cada archivo repetía el bloque a mano y en `includes/header.php` el
 * `<script>` quedó sin abrir, de modo que PHP imprimía esto como texto visible
 * encima de la cabecera:
 *     document.documentElement.setAttribute('data-language', 'es');
 *
 * Variables que define para quien lo incluye:
 *     $lang         array  diccionario de textos del idioma activo
 *     $idiomaActual string 'es' | 'en'
 *     $prefijoJs    string '' o '../' según la profundidad de la página
 * -----------------------------------------------------------------------------
 */

/**
 * Ruta relativa desde la página actual hasta la raíz del proyecto.
 *
 * Ejemplos (proyecto instalado en http://localhost/sget/):
 *     /sget/index.php        -> ''        (0 carpetas por encima)
 *     /sget/Admin/rutas.php  -> '../'     (1 carpeta)
 *     /sget/Conductor/x.php  -> '../'
 *
 * Se basa en el NOMBRE de la carpeta del proyecto dentro de SCRIPT_NAME, de modo
 * que funciona igual si el proyecto está en la raíz del servidor
 * (/index.php, /Admin/rutas.php) o en un subdirectorio (/sget/..., /app/sget/...).
 */
function prefijo_relativo_de_pagina(): string
{
    $normalizar = static function (string $ruta): string {
        return str_replace('\\', '/', $ruta);
    };

    $partes = array_values(array_filter(
        explode('/', $normalizar($_SERVER['SCRIPT_NAME'] ?? '/')),
        static fn(string $s): bool => $s !== '' && $s !== '.'
    ));

    if (!$partes) return '';

    // El último segmento es el archivo (index.php, rutas.php…), no una carpeta.
    array_pop($partes);

    $proyecto = basename($normalizar(dirname(__DIR__)));
    $indice   = array_search($proyecto, $partes, true);

    // Si el nombre de la carpeta del proyecto aparece en la ruta, se cuenta lo
    // que hay por detrás de él. Si no aparece, se usa la profundidad completa.
    $carpetas = $indice === false ? $partes : array_slice($partes, $indice + 1);

    return str_repeat('../', count($carpetas));
}

if (!defined('SGET_I18N_CARGADO')) {
    define('SGET_I18N_CARGADO', true);

    $idiomaActual = $_SESSION['sget_idioma'] ?? 'es';
    if (!in_array($idiomaActual, ['es', 'en'], true)) {
        $idiomaActual = 'es';
    }

    // Prefijo relativo según la profundidad de la página QUE INCLUYE este archivo:
    //   /index.php            -> ''      (la landing)
    //   /Admin/rutas.php      -> '../'
    //
    // ANTES: `$prefijoJs = str_contains($_SERVER['SCRIPT_NAME'], '/') ? '../' : '';`
    // eso mira si la RUTA tiene alguna barra, y en una instalación real dentro de
    // una carpeta (http://localhost/sget/) SCRIPT_NAME es '/sget/index.php': tiene
    // barra, así que la landing recibía '../' y pedía set_language.php en
    // http://localhost/set_language.php, que NO existe → 404 silencioso dentro de
    // un try/catch → el idioma se traducía en pantalla pero nunca se guardaba en
    // la sesión, y al recargar (o al volver a español) todo volvía al original.
    // Ahora se cuenta cuántas carpetas hay ENTRE la raíz del proyecto y la página.
    $prefijoJs  = prefijo_relativo_de_pagina();

    @include_once dirname(__DIR__) . '/lang/' . $idiomaActual . '.php';
    if (!isset($lang) || !is_array($lang)) {
        $lang = [];
    }
    // El diccionario debe existir siempre: si el idioma no trae la clave,
    // quien llama usa  $lang['clave'] ?? 'texto por defecto'.
    $lang['_idioma'] = $idiomaActual;
    $lang['_prefijo'] = $prefijoJs;
}
?>

<script>
    (function () {
        var raiz = document.documentElement;
        raiz.setAttribute('data-language', <?= json_encode($idiomaActual, JSON_UNESCAPED_UNICODE) ?>);
        raiz.setAttribute('lang', <?= json_encode($idiomaActual === 'en' ? 'en' : 'es') ?>);

        window.SGET_LANGUAGE_URL = <?= json_encode($prefijoJs . 'set_language.php') ?>;
        window.SGET_I18N = window.SGET_I18N || {};
        window.SGET_I18N.diccionario = <?= json_encode($lang, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    })();
</script>
<?php /* i18n.js se emite UNA sola vez aunque el partial se incluya varias veces. */ ?>
<?php if (!defined('SGET_I18N_JS_CARGADO')): ?>
    <?php define('SGET_I18N_JS_CARGADO', true); ?>
    <script src="<?= $prefijoJs ?>js/i18n.js?v=20260908-1" defer></script>
<?php endif; ?>

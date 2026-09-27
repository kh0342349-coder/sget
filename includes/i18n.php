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

if (!defined('SGET_I18N_CARGADO')) {
    define('SGET_I18N_CARGADO', true);

    $idiomaActual = $_SESSION['sget_idioma'] ?? 'es';
    if (!in_array($idiomaActual, ['es', 'en'], true)) {
        $idiomaActual = 'es';
    }

    // Prefijo relativo según la profundidad de la página que incluye este archivo:
    //   /index.php            -> ''
    //   /Admin/rutas.php      -> '../'
    $rutaScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $prefijoJs  = str_contains($rutaScript, '/') ? '../' : '';

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

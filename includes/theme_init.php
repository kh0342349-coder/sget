<?php
/**
 * includes/theme_init.php
 * -----------------------------------------------------------------------------
 * WRAPPER LEGACY del script de tema.
 * -----------------------------------------------------------------------------
 * El tema vive ahora en `assets/js/theme-init.js` y lo cargan TODAS las páginas
 * con una etiqueta <script> en su <head> (debe ir antes del primer pintado,
 * sin `defer`).
 *
 * Antes solo lo cargaban 5 de 30 páginas: en las otras, el botón de la cabecera
 * lanzaba "SGETTheme is not defined" y el tema no cambiaba nunca.
 *
 * Este archivo se conserva para no romper a quien aún lo incluya con PHP.
 * -----------------------------------------------------------------------------
 */
$v = @filemtime(Config::raiz('assets/js/theme-init.js')) ?: '1';
?>
<script src="<?= Config::basePath() ?>/assets/js/theme-init.js?v=<?= $v ?>"></script>

<?php
/**
 * views/partials/foot.php
 * -----------------------------------------------------------------------------
 * Cierre común: barra de progreso + scripts base del sistema.
 * -----------------------------------------------------------------------------
 * Uso:
 *     $jsExtra = ['mi-modulo.js'];
 *     include __DIR__ . '/../views/partials/foot.php';
 * -----------------------------------------------------------------------------
 */
$jsExtra = $jsExtra ?? [];

/**
 * Versión de caché POR ARCHIVO.
 *
 * POR QUÉ NO SE USA UNA SOLA VARIABLE
 *   Antes todos los scripts se etiquetaban con el filemtime de
 *   `sget-modal.js`. Eso solo invalida la caché si cambió ESE archivo: si se
 *   tocaba sget-cru.js o sget-page.js, el navegador seguía sirviendo la
 *   versión anterior y el arreglo "no se veía reflejado" hasta que se vaciaba
 *   la caché a mano. Cada script lleva ahora su propia huella.
 */
$version = static function (string $archivo): int|string {
    return (int) (@filemtime(Config::raiz('assets/js/' . $archivo)) ?: 1);
};
?>
    <div class="sget-toast-zona" role="status" aria-live="polite"></div>

    <!-- Barra de progreso de la transición entre módulos -->
    <div class="sget-barra-progreso" aria-hidden="true"></div>

    <!--
        JS BASE. El orden importa:
          1. transicion  intercepta la navegación entre módulos
          2. modal       motor único de overlays (modal / drawer / confirmar)
          3. cru         búsqueda, filtros y envío de formularios
    -->
    <script src="../assets/js/sget-transicion.js?v=<?= $version('sget-transicion.js') ?>"></script>
    <script src="../assets/js/sget-modal.js?v=<?= $version('sget-modal.js') ?>"></script>
    <script src="../assets/js/sget-cru.js?v=<?= $version('sget-cru.js') ?>"></script>
    <?php foreach ($jsExtra as $js): ?>
        <script src="../assets/js/<?= htmlspecialchars($js, ENT_QUOTES, 'UTF-8') ?>?v=<?= $version($js) ?>"></script>
    <?php endforeach; ?>
</body>
</html>

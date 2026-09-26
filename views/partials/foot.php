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
$mv = filemtime(Config::raiz('assets/js/sget-modal.js')) ?: '1';
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
    <script src="../assets/js/sget-transicion.js?v=<?= $mv ?>"></script>
    <script src="../assets/js/sget-modal.js?v=<?= $mv ?>"></script>
    <script src="../assets/js/sget-cru.js?v=<?= $mv ?>"></script>
    <?php foreach ($jsExtra as $js): ?>
        <script src="../assets/js/<?= htmlspecialchars($js, ENT_QUOTES, 'UTF-8') ?>?v=<?= $mv ?>"></script>
    <?php endforeach; ?>
</body>
</html>

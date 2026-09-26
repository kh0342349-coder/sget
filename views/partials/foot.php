<?php
/**
 * views/partials/foot.php
 * -----------------------------------------------------------------------------
 * Cierre común:Scripts base del sistema + zona de toasts.
 * -----------------------------------------------------------------------------
 * Uso:
 *     include __DIR__ . '/../views/partials/foot.php';
 * -----------------------------------------------------------------------------
 */
$jsExtra = $jsExtra ?? [];
$mv = filemtime(Config::raiz('assets/js/sget-modal.js')) ?: '1';
?>
    <div class="sget-toast-zona" role="status" aria-live="polite"></div>

    <!-- JS BASE: motor de modales + lógica CRUD compartida -->
    <script src="../assets/js/sget-modal.js?v=<?= $mv ?>"></script>
    <script src="../assets/js/sget-cru.js?v=<?= $mv ?>"></script>
    <?php foreach ($jsExtra as $js): ?>
        <script src="../assets/js/<?= htmlspecialchars($js, ENT_QUOTES, 'UTF-8') ?>?v=<?= $mv ?>"></script>
    <?php endforeach; ?>
</body>
</html>

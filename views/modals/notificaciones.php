<?php
/**
 * views/modals/notificaciones.php
 * -----------------------------------------------------------------------------
 * BUZÓN DEL USUARIO
 * -----------------------------------------------------------------------------
 * Muestra los avisos del sistema, en especial las CANCELACIONES DE VIAJE:
 * motivo, anotación obligatoria y fecha de salida, tal como las recibió el
 * pasajero. Es el cierre del flujo "el administrador cancela → el pasajero se
 * entera", que antes no existía.
 * -----------------------------------------------------------------------------
 */
$__notis = [];
$__noLeidas = 0;
if (class_exists('NotificacionService') && isset($idUsuarioSesión) && $idUsuarioSesión > 0) {
    try {
        $__notis    = NotificacionService::bandeja($idUsuarioSesión, 25);
        $__noLeidas = NotificacionService::noLeidas($idUsuarioSesión);
    } catch (Throwable $e) {
        $__notis = [];
    }
}
?>
<div class="sget-modal-wrap" id="modalNotificaciones" data-sget-capa data-titulo="Notificaciones">
    <div class="sget-overlay"></div>
    <div class="sget-modal" role="dialog" aria-modal="true" aria-labelledby="tituloNotificaciones">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloNotificaciones">
                    <span class="sget-modal__icono"><i class="fas fa-bell"></i></span>
                    <span>Notificaciones</span>
                    <?php if ($__noLeidas > 0): ?>
                        <span class="sget-badge sget-badge--error" data-sget-texto="noLeidas"><?= $__noLeidas ?></span>
                    <?php endif; ?>
                </h2>
                <p class="sget-modal__sub">Avisos del sistema, cambios de viaje y confirmaciones de reserva.</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?php if (empty($__notis)): ?>
                <div class="sget-vacio" style="border:none;background:transparent;padding:2rem 1rem">
                    <span class="sget-vacio__icono"><i class="fas fa-bell-slash"></i></span>
                    <p class="sget-label" style="font-size:.8125rem">No tienes notificaciones</p>
                    <p class="sget-help">Aquí te avisaremos si se cancela un viaje que tenías reservado.</p>
                </div>
            <?php else: ?>
                <ul style="display:flex;flex-direction:column;gap:.75rem">
                    <?php foreach ($__notis as $n):
                        $leida    = (int)$n['leida'] === 1;
                        $esCancel = $n['tipo'] === NotificacionService::TIPO_CANCELACION;
                    ?>
                        <li style="padding:.875rem 1rem;border-radius:var(--sget-radio);
                                   border:1px solid <?= $leida ? 'var(--sget-borde)' : 'color-mix(in srgb,var(--sget-azul) 40%,transparent)' ?>;
                                   background:<?= $leida ? 'var(--sget-superficie-2)' : 'color-mix(in srgb,var(--sget-azul) 5%,var(--sget-superficie))' ?>">
                            <div style="display:flex;align-items:flex-start;gap:.625rem">
                                <i class="fas <?= $esCancel ? 'fa-ban' : 'fa-circle-info' ?>"
                                   style="color:<?= $esCancel ? 'var(--sget-rojo)' : 'var(--sget-azul)' ?>;margin-top:.125rem"></i>
                                <div style="flex:1;min-width:0">
                                    <p style="font-size:.8125rem;font-weight:800"><?= htmlspecialchars((string)$n['titulo'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="sget-help" style="white-space:pre-line;margin-top:.375rem;font-size:.75rem"><?= htmlspecialchars((string)$n['cuerpo'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="sget-help sget-mono" style="margin-top:.5rem;font-size:.625rem">
                                        <?= htmlspecialchars(Fecha::legible($n['fec_envio']), ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </div>
                                <?php if (!$leida): ?>
                                    <button type="button" class="sget-icon-btn" style="width:1.75rem;height:1.75rem"
                                            title="Marcar como leída" aria-label="Marcar como leída"
                                            data-sget-accion="leerNotificacion"
                                            data-sget-dato='<?= htmlspecialchars(json_encode(['id' => (int)$n['id_not']], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                        <i class="fas fa-check" style="font-size:.625rem"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cerrar</button>
            <?php if ($__noLeidas > 0): ?>
                <button type="button" class="sget-btn sget-btn--primario"
                        data-sget-accion="leerTodasNotificaciones">
                    <i class="fas fa-check-double"></i> Marcar todas como leídas
                </button>
            <?php endif; ?>
        </footer>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // "Marcar todas" es una acción puntual con su propio manejador, porque
        // necesita recargar para actualizar el contador de la cabecera.
        document.querySelectorAll('[data-sget-accion="leerTodasNotificaciones"]').forEach(function (b) {
            b.addEventListener('click', function () {
                var cuerpo = new FormData();
                cuerpo.append('_token', SGETModal.__token);
                cuerpo.append('modulo', 'notificacion');
                cuerpo.append('accion', 'leerTodas');

                fetch('../api/index.php', {
                    method: 'POST', body: cuerpo,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); })
                  .then(function (j) { SGETModal.toast(j.mensaje, 'exito'); setTimeout(function () { location.reload(); }, 700); });
            });
        });
    });
</script>

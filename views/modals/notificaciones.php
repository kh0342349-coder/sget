<?php
/**
 * views/modals/notificaciones.php
 * -----------------------------------------------------------------------------
 * BUZÓN DEL USUARIO (todos los roles)
 * -----------------------------------------------------------------------------
 * Se incluye desde includes/header.php, así que existe en TODAS las páginas.
 * Muestra los avisos del sistema con el motivo, la anotación y el estado REAL
 * del viaje al que se refieren.
 *
 * El HTML se pinta en el servidor (la bandeja se ve aunque el JS no haya
 * cargado todavía) y assets/js/sget-notificaciones.js lo refresca en vivo:
 * marcar leída, eliminar, vaciar y el contador de la cabecera, sin recargar.
 * -----------------------------------------------------------------------------
 */
$__notis    = [];
$__noLeidas = 0;
$__usuario  = (int)($_SESSION['id_usu'] ?? 0);
if (class_exists('NotificacionService') && $__usuario > 0) {
    try {
        $__notis    = NotificacionService::bandeja($__usuario, ['limite' => 40]);
        $__noLeidas = NotificacionService::noLeidas($__usuario);
    } catch (Throwable $e) {
        $__notis = [];
    }
}

/** Enlace directo al viaje de cada aviso, según el rol de quien mira. */
$__rutaViaje = static function (int $rol): string {
    switch ($rol) {
        case 1:  return 'viajes.php';
        case 2:  return 'viajes_conductor.php';
        default: return 'viajes_pasajero.php';
    }
};
$__baseRuta = $rolUsuario == 1 ? '' : ($rolUsuario == 2 ? '../' : '../');
$__esc     = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="sget-modal-wrap" id="modalNotificaciones" data-sget-capa data-titulo="Notificaciones">
    <div class="sget-overlay"></div>
    <div class="sget-modal sget-modal--lg" role="dialog" aria-modal="true" aria-labelledby="tituloNotificaciones">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloNotificaciones">
                    <span class="sget-modal__icono"><i class="fas fa-bell"></i></span>
                    <span data-sget-noti-titulo>Notificaciones</span>
                    <span class="sget-badge sget-badge--error" data-sget-noti-contador
                          <?= $__noLeidas > 0 ? '' : 'hidden' ?>><?= $__noLeidas > 9 ? '9+' : $__noLeidas ?></span>
                </h2>
                <p class="sget-modal__sub">
                    Avisos de tus viajes: cancellations, reservas confirmadas, asignaciones y comunicados.
                </p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?php if (empty($__notis)): ?>
                <div class="sget-vacio" data-sget-noti-vacio style="border:none;background:transparent;padding:2rem 1rem">
                    <span class="sget-vacio__icono"><i class="fas fa-bell-slash"></i></span>
                    <p class="sget-label" style="font-size:.8125rem">No tienes notificaciones</p>
                    <p class="sget-help">Aquí te avisaremos si se cancela un viaje, si se confirma tu reserva
                        o si tienes una salida en los próximos minutos.</p>
                </div>
            <?php else: ?>
                <div class="sget-vacio" data-sget-noti-vacio hidden
                     style="border:none;background:transparent;padding:2rem 1rem">
                    <span class="sget-vacio__icono"><i class="fas fa-bell-slash"></i></span>
                    <p class="sget-label" style="font-size:.8125rem">No tienes notificaciones</p>
                    <p class="sget-help">Aquí te avisaremos si se cancela un viaje o si se confirma tu reserva.</p>
                </div>
            <?php endif; ?>

            <ul class="sget-noti-lista" data-sget-noti-lista>
                <?php foreach ($__notis as $n):
                    $p       = NotificacionService::presentacion((string)$n['tipo']);
                    $leida   = (int)$n['leida'] === 1;
                    $obsoleto = !empty($n['obsoleto']);
                ?>
                    <li class="sget-noti-item<?= $leida ? ' es-leida' : '' ?>">
                        <span class="sget-noti-item__icono sget-noti-item__icono--<?= $__esc($p['tono']) ?>">
                            <i class="fas <?= $__esc($p['icono']) ?>"></i>
                        </span>
                        <div class="sget-noti-item__cuerpo">
                            <div class="sget-noti-item__cabecera">
                                <span class="<?= $__esc(NotificacionService::claseTipo((string)$n['tipo'])) ?>"><?= $__esc($p['etiqueta']) ?></span>
                                <time class="sget-help sget-mono"><?= $__esc(Fecha::legible($n['fec_envio'])) ?></time>
                            </div>
                            <p class="sget-noti-item__titulo"><?= $__esc((string)$n['titulo']) ?></p>
                            <p class="sget-noti-item__texto"><?= $__esc((string)$n['cuerpo']) ?></p>
                            <?php if (!empty($n['id_via'])): ?>
                                <a class="sget-help" href="<?= $__esc($__baseRuta . $__rutaViaje((int)$rolUsuario)) ?>">
                                    Ver el viaje #<?= (int)$n['id_via'] ?> <i class="fas fa-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($obsoleto): ?>
                                <span class="sget-badge sget-badge--neutro">Este aviso ya no aplica</span>
                            <?php endif; ?>
                        </div>
                        <div class="sget-noti-item__acciones">
                            <?php if (!$leida): ?>
                                <button type="button" class="sget-icon-btn sget-icon-btn--exito"
                                        title="Marcar como leída" aria-label="Marcar como leída"
                                        data-sget-noti="leer" data-id="<?= (int)$n['id_not'] ?>">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="sget-icon-btn sget-icon-btn--peligro"
                                    title="Eliminar aviso" aria-label="Eliminar aviso"
                                    data-sget-noti="eliminar" data-id="<?= (int)$n['id_not'] ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-noti="vaciar"
                    data-sget-confirmar="Vaciar buzón"
                    data-sget-texto="Se eliminarán <strong>todos</strong> los avisos de tu buzón. Esta acción no se puede deshacer.">
                <i class="fas fa-broom"></i> Vaciar buzón
            </button>
            <button type="button" class="sget-btn sget-btn--primario" data-sget-noti-todas>
                <i class="fas fa-check-double"></i> Marcar todas como leídas
            </button>
        </footer>
    </div>
</div>

<!--
    El buzón viaja con su propio JS (no depende de sget-page.js, que no se carga
    en las páginas de Pasajero ni de Conductor: por eso sus botones de "marcar
    como leída" no hacían nada allí).
    La ruta se resuelve con Config::basePath() porque la cabecera se incluye
    desde módulos en carpetas distintas (/, /Admin, /Conductor, /Pasajero).
-->
<script src="<?= Config::basePath() ?>/assets/js/sget-notificaciones.js?v=<?= @filemtime(Config::raiz('assets/js/sget-notificaciones.js')) ?: '1' ?>"></script>

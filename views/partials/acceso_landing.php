<?php
/**
 * views/partials/acceso_landing.php
 * -----------------------------------------------------------------------------
 * BOTÓN FLOTANTE DE ACCESO A SGET  (landing)
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   El header ya tiene «Iniciar sesión», pero:
 *     · en móvil la barra de navegación se oculta y el acceso queda dentro del
 *       menú desplegable, a un clic extra;
 *     · al bajar por la página, la cabecera sigue fija pero el menú con cinco
 *       entradas y dos botones compite por la atención.
 *
 *   Este botón acompaña el scroll: siempre está a mano, sin importar dónde esté
 *   el visitante. Refleja el estado REAL de la sesión:
 *
 *     · Sin sesión -> «Acceder a SGET»   (abre el modal de inicio de sesión)
 *     · Con sesión -> «Mi panel»        (lleva al panel del rol) + «Salir»
 *
 * COLISIÓN CON EL AVISO DE COOKIES
 *   El aviso de cookies ocupa la esquina inferior derecha (y toda la base en
 *   móvil). Este botón vive abajo a la izquierda, y además se OCULTA mientras el
 *   aviso está abierto: dos cajas superpuestas en la misma esquina son un
 *   problema de usabilidad, no un detalle de maquetación.
 *
 * NOTA SOBRE EL MOTOR DE MODALES
 *   La landing NO carga `assets/js/sget-modal.js` (solo lo necesitan los módulos
 *   internos), así que aquí no se usa `data-sget-modal` sino
 *   `data-sget-ir-a`, que es el enrutador propio de la portada.
 * -----------------------------------------------------------------------------
 */
$__sesion = Auth::estaLogueado();
?>
<aside class="sget-acceso-flotante"
       id="accesoFlotante"
       data-sget-oculto="<?= (int)$__sesion ?>"
       aria-label="Acceso al sistema">

    <?php if (!$__sesion): ?>
        <button type="button"
                class="sget-acceso-flotante__btn"
                data-sget-ir-a="panelLogin">
            <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
            <span>Acceder a SGET</span>
        </button>
    <?php else: ?>
        <a class="sget-acceso-flotante__btn sget-acceso-flotante__btn--panel"
           href="<?= htmlspecialchars(Auth::inicioPorRol(), ENT_QUOTES, 'UTF-8') ?>"
           title="Ir a mi panel">
            <i class="fas fa-gauge-high" aria-hidden="true"></i>
            <span>Mi panel</span>
        </a>

        <form method="POST" action="<?= htmlspecialchars(Config::basePath(), ENT_QUOTES, 'UTF-8') ?>/assets/cerrar.php"
              class="sget-acceso-flotante__salir">
            <?= Auth::campoToken() ?>
            <button type="submit" class="sget-acceso-flotante__btn sget-acceso-flotante__btn--salir"
                    title="Cerrar sesión">
                <i class="fas fa-power-off" aria-hidden="true"></i>
                <span>Salir</span>
            </button>
        </form>
    <?php endif; ?>
</aside>
<?php
/**
 * views/modals/ayuda.php
 * -----------------------------------------------------------------------------
 * AYUDA CONTEXTUAL DEL MÓDULO ACTUAL
 * -----------------------------------------------------------------------------
 * Un solo modal de ayuda para todo el sistema. El contenido cambia según la
 * página en la que se abre, de modo que el botón de ayuda del header y el del
 * sidebar dejan de dar error ("abrirModalAyuda is not defined") y siempre
 * muestran la guía del módulo que el usuario está viendo.
 *
 * Para añadir la ayuda de una página nueva basta con registrar su clave aquí.
 * -----------------------------------------------------------------------------
 */
$__pagina = basename($_SERVER['PHP_SELF'] ?? 'index', '.php');

$__guias = [
    'rutas' => [
        'icono'  => 'fa-route',
        'titulo' => 'Guía de Gestión de Rutas',
        'intro'  => 'Cada ruta describe un trayecto de extremo a extremo.',
        'pasos'  => [
            ['fa-plus-circle',  'text-emerald-400', 'Nueva ruta', 'Indica el nombre, la <b>ciudad de salida</b>, la <b>ciudad de destino</b>, la distancia, la tarifa y la hora de salida por defecto.'],
            ['fa-pen',          'text-amber-400',   'Editar',        'Los mismos campos, precargados. Si cambias la placa, recuerda marcar la ruta como Activa para que vuelva a aparecer en el despacho.'],
            ['fa-pause',        'text-amber-400',   'Suspender',     'La ruta deja de ofrecerse al crear viajes, sin borrar su historial.'],
            ['fa-trash',        'text-rose-400',    'Eliminar',      'Solo se permite si no tiene viajes activos ni programados.'],
        ],
        'nota'  => 'La hora de salida de la ruta se usa para prellenar el formulario de viajes; la fecha se elige viaje por viaje.',
    ],
    'viajes' => [
        'icono'  => 'fa-truck-fast',
        'titulo' => 'Guía de Despacho de Viajes',
        'intro'  => 'Un viaje/programación une una ruta, un conductor, un vehículo y una fecha y hora de salida.',
        'pasos'  => [
            ['fa-plus-circle',  'text-emerald-400', 'Programar',    'Al guardar, el conductor y el vehículo pasan a <b>Ocupado</b> y se liberan solos al finalizar o cancelar.'],
            ['fa-pen',          'text-amber-400',   'Editar',       'Si cambias de conductor o unidad, el recurso anterior se libera automáticamente.'],
            ['fa-truck-fast',   'text-amber-400',   'En curso',     'Solo disponible cuando ya pasó la hora de salida programada.'],
            ['fa-flag-checkered','text-emerald-400','Terminar',     'Marca el viaje como Finalizado y libera conductor y vehículo.'],
            ['fa-ban',          'text-rose-400',    'Cancelar',     'Si el viaje <b>ainda no sale</b>, el sistema exige una anotación de al menos 15 caracteres y notifica a todos los pasajeros reservados.'],
        ],
        'nota'  => 'Los viajes cuya salida+vence se cierran automáticamente como Finalizados al entrar a este módulo.',
    ],
    'vehiculos' => [
        'icono'  => 'fa-bus',
        'titulo' => 'Guía de Control de Flota',
        'intro'  => 'Las unidades de transporte y su disponibilidad operativa.',
        'pasos'  => [
            ['fa-plus',      'text-emerald-400', 'Agregar',          'Registra placa, línea/modelo, capacidad y estado inicial.'],
            ['fa-pen',       'text-amber-400',   'Editar',          'No se puede dejar la placa repetida ni la capacidad en cero.'],
            ['fa-toggle-on', 'text-rose-400',    'Fuera de servicio','No se permite si la unidad tiene un viaje asignado.'],
            ['fa-trash',     'text-rose-400',    'Eliminar',        'Solo si no tiene viajes asignados.'],
        ],
        'nota'  => 'Al asignar la unidad a un viaje su estado cambia solo: esto evita que dos viajes compitan por el mismo vehículo.',
    ],
    'usuarios' => [
        'icono'  => 'fa-users-gear',
        'titulo' => 'Guía de Administración de Usuarios',
        'intro'  => 'Altas, edición, suspensión y roles del personal.',
        'pasos'  => [
            ['fa-user-plus',    'text-emerald-400', 'Nuevo usuario', 'El número de documento es la llave del sistema: no se puede duplicar.'],
            ['fa-pen',          'text-amber-400',   'Editar',        'Si dejas la contraseña vacía se conserva la actual.'],
            ['fa-user-slash',   'text-rose-400',    'Suspender',     'Bloquea el ingreso sin borrar viajes ni reservas.'],
            ['fa-user-check',   'text-emerald-400', 'Reactivar',     'Restaura el acceso inmediatamente.'],
        ],
        'nota'  => 'No puedes suspender ni eliminar tu propia cuenta. Un conductor con viaje en curso tampoco se puede suspender.',
    ],
];

$__clave = null;
foreach (['rutas', 'viajes', 'vehiculos', 'usuarios'] as $c) {
    if (str_contains(strtolower($__pagina), $c)) { $__clave = $c; break; }
}

$__g = $__guias[$__clave] ?? [
    'icono'  => 'fa-circle-question',
    'titulo' => 'Ayuda del sistema SGET',
    'intro'  => 'Guía rápida de la pantalla que estás viendo.',
    'pasos'  => [
        ['fa-magnifying-glass', 'text-sky-400',   'Buscador',     'Usa la barra superior o pulsa <b>Ctrl + K</b> para filtrar la información de la pantalla.'],
        ['fa-keyboard',         'text-sky-400',   'Atajos',       '<b>Esc</b> cierra el modal abierto · <b>Ctrl + Enter</b> guarda el formulario.'],
        ['fa-moon',             'text-amber-400', 'Modo oscuro',  'El botón de la luna en la cabecera cambia el tema; la preferencia queda guardada.'],
        ['fa-globe',            'text-emerald-400','Idioma',      'Puedes cambiar entre español e inglés desde el selector de la cabecera.'],
    ],
    'nota'  => 'Todas las acciones sensibles piden confirmación y quedan registradas en la auditoría del sistema.',
];
?>
<div class="sget-modal-wrap" id="modalAyuda" data-sget-capa data-titulo="Ayuda">
    <div class="sget-overlay"></div>
    <div class="sget-modal" role="dialog" aria-modal="true" aria-labelledby="tituloAyuda">
        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloAyuda">
                    <span class="sget-modal__icono"><i class="fas <?= htmlspecialchars($__g['icono'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                    <span><?= htmlspecialchars($__g['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                </h2>
                <p class="sget-modal__sub"><?= htmlspecialchars($__g['intro'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <ul style="display:flex;flex-direction:column;gap:1rem">
                <?php foreach ($__g['pasos'] as [$icono, $color, $titulo, $texto]): ?>
                    <li style="display:flex;gap:.875rem;align-items:flex-start">
                        <span style="width:2.25rem;height:2.25rem;flex-shrink:0;display:grid;place-items:center;
                                     border-radius:var(--sget-radio-sm);background:color-mix(in srgb,currentColor 12%,transparent);color:<?= $color ?>">
                            <i class="fas <?= $icono ?>"></i>
                        </span>
                        <div>
                            <p class="sget-label" style="margin-bottom:.25rem"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="sget-help" style="font-size:.75rem"><?= $texto ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="sget-help" style="margin-top:1.25rem;padding:.875rem;background:var(--sget-superficie-2);
                                      border-radius:var(--sget-radius);border:1px solid var(--sget-borde)">
                <i class="fas fa-circle-info"></i> <?= $__g['nota'] ?>
            </p>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--primario" data-sget-cerrar>Entendido</button>
        </footer>
    </div>
</div>

<script>
    // Los botones de ayuda del header y del sidebar usan el motor común.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-sget-modal="modalAyuda"]').forEach(function (b) {
            b.removeAttribute('onclick');
        });
    });
</script>

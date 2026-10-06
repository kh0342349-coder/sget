<?php
/**
 * views/modals/ayuda.php
 * -----------------------------------------------------------------------------
 * AYUDAS DEL SISTEMA · un único acceso global
 * -----------------------------------------------------------------------------
 * QUÉ CAMBIA
 *   Antes cada módulo pintaba su propio botón «?» y su propio modal
 *   (`id="modalAyuda"` repetido en admin.php, gestion_permisos.php y
 *   ranking_conductores.php, con el MISMO id: el que se abría dependía del
 *   orden en el DOM y el contenido era el del último que se注册ó). El sidebar
 *   tenía otro, la cabecera otro, y cada copia con su propio JavaScript.
 *
 *   Ahora hay UN solo botón flotante «?» en la esquina inferior derecha de todas
 *   las pantallas del panel y UN solo modal, cuyo contenido se elige por rol y
 *   módulo. El sistema es declarativo: para documentar una pantalla nueva basta
 *   con añadir una entrada al catálogo de abajo; no hay que tocar ni la página
 *   ni ningún JavaScript.
 *
 *   Criterio de coincidencia, de más específico a más general:
 *       rol + módulo  →  módulo  →  rol  →  general
 * -----------------------------------------------------------------------------
 */
if (!class_exists('Auth')) {
    require_once dirname(__DIR__, 2) . '/core/bootstrap.php';
}

$__pagina = basename($_SERVER['PHP_SELF'] ?? 'index', '.php');
$__rol    = Auth::rol();

/* ------------------------------------------------------------------------- */
/* Catálogo de ayudas                                                        */
/* ------------------------------------------------------------------------- */
/*
 * Estructura de cada entrada:
 *   icono   → clase de FontAwesome del encabezado
 *   titulo  → nombre de la ayuda
 *   intro   → frase de arranque
 *   pasos   → [ [icono, color, título, texto], … ]
 *   nota    → pie con el detalle que más confunde
 *
 * El texto de los pasos es HTML mínimo y de confianza (viene de aquí, no del
 * usuario): se permite <b> para destacar, que es lo que hace legible la guía.
 */
$__guias = [

    /* ===================== ADMINISTRADOR ================================ */
    'admin:usuarios' => [
        'icono'  => 'fa-users-gear',
        'titulo' => 'Gestión de Usuarios',
        'intro'  => 'Altas, edición, roles y estado de las cuentas de SGET.',
        'pasos'  => [
            ['fa-user-plus',    'text-emerald-500', 'Crear un usuario',  'El <b>número de documento</b> es la llave con la que esa persona entra al sistema: no puede repetirse. El correo también es único.'],
            ['fa-pen',          'text-amber-500',   'Editar',            'Si dejas la contraseña vacía se conserva la actual. Al cambiarla se guarda siempre cifrada.'],
            ['fa-user-slash',   'text-rose-500',    'Suspender',          'Bloquea el ingreso sin borrar viajes, reservas ni historial.'],
            ['fa-user-check',   'text-emerald-500', 'Reactivar',         'Restaura el acceso de inmediato.'],
        ],
        'nota'  => 'No puedes suspender ni eliminar tu propia cuenta. Un conductor con un viaje en curso tampoco se puede suspender.',
    ],
    'admin:rutas' => [
        'icono'  => 'fa-route',
        'titulo' => 'Gestión de Rutas',
        'intro'  => 'Cada ruta describe un trayecto de extremo a extremo.',
        'pasos'  => [
            ['fa-plus-circle',  'text-emerald-500', 'Nueva ruta',   'Indica el nombre, la <b>ciudad de salida</b>, la <b>ciudad de destino</b>, la distancia, la tarifa, la hora de salida por defecto y la duración estimada del trayecto.'],
            ['fa-image',        'text-sky-500',    'Imagen',       'La foto es la que aparece en las tarjetas de la landing. Panorámica y sinCopyright.'],
            ['fa-pen',          'text-amber-500',  'Editar',       'Si cambias la foto, vuelve a marcar la ruta como <b>Activa</b> para que vuelva a mostrarse.'],
            ['fa-pause',        'text-amber-500',  'Suspender',    'La ruta deja de ofrecerse al crear viajes, sin borrar su historial.'],
            ['fa-trash',        'text-rose-500',   'Eliminar',     'Solo se permite si no tiene viajes activos ni programados.'],
        ],
        'nota'  => 'La hora de salida de la ruta sirve para prellenar el formulario de viajes; la fecha se elige viaje por viaje.',
    ],
    'admin:viajes' => [
        'icono'  => 'fa-truck-fast',
        'titulo' => 'Control de Viajes',
        'intro'  => 'Un viaje une una ruta, un conductor, un vehículo, y una fecha y hora de salida.',
        'pasos'  => [
            ['fa-plus-circle',   'text-emerald-500', 'Programar',     'Al guardar, el conductor y el vehículo pasan a <b>Asignado</b> y se liberan solos al finalizar o cancelar.'],
            ['fa-pen',           'text-amber-500',   'Editar',        'Si cambias de conductor o de unidad, el recurso anterior se libera automáticamente y se comprueba que el nuevo esté libre.'],
            ['fa-truck-fast',    'text-amber-500',   'En curso',      'Se activa cuando ya pasó la hora de salida programada.'],
            ['fa-flag-checkered','text-emerald-500', 'Finalizar',      'Cierra el viaje y libera conductor y vehículo.'],
            ['fa-ban',           'text-rose-500',    'Cancelar',      'Si el viaje <b>ainda no sale</b> el sistema exige una anotación de al menos 15 caracteres y notifica a todos los pasajeros reservados.'],
        ],
        'nota'  => 'Los viajes cuya salida y duración ya se cumplieron se cierran solos como <b>Finalizados</b> al entrar a este módulo.',
    ],
    'admin:vehiculos' => [
        'icono'  => 'fa-bus',
        'titulo' => 'Flota de Vehículos',
        'intro'  => 'Las unidades de transporte y su disponibilidad operativa.',
        'pasos'  => [
            ['fa-plus',        'text-emerald-500', 'Agregar',            'Registra placa, modelo, capacidad y estado inicial.'],
            ['fa-pen',         'text-amber-500',   'Editar',            'No se puede dejar la placa repetida ni la capacidad en cero.'],
            ['fa-wrench',      'text-amber-500',   'En mantenimiento',   'La unidad existe pero no se puede asignar a un viaje nuevo.'],
            ['fa-ban',         'text-rose-500',    'Fuera de servicio',  'Se retira de la operación (avería, venta, baja).'],
            ['fa-trash',       'text-rose-500',    'Eliminar',          'Solo si no tiene viajes asignados.'],
        ],
        'nota'  => 'El estado <b>Asignado</b> lo pone y lo quita el sistema al crear o cerrar un viaje: un vehículo no puede quedar en dos viajes a la vez.',
    ],
    'admin:asignaciones' => [
        'icono'  => 'fa-cash-register',
        'titulo' => 'Recaudo y Abordaje',
        'intro'  => 'Cobro en terminal y control de quién embarcó.',
        'pasos'  => [
            ['fa-user-plus',    'text-emerald-500', 'Pasajero sin cuenta', 'Registra nombre y documento: se crea una ficha mínima para que la reserva quede trazable.'],
            ['fa-ticket-alt',   'text-sky-500',    'Registrar reserva',   'Indica cuántos puestos toma. El valor pactado se guarda siempre con la tarifa del viaje.'],
            ['fa-money-bill-wave','text-emerald-500','Cobrar',            'Confirma el pago y el pasajero recibe el aviso al instante.'],
            ['fa-user-check',   'text-sky-500',    'Embarque',           'El conductor marca quién subió y quién no se presentó.'],
            ['fa-bell',         'text-amber-500',  'Avisar perdidos',    'Notifica a quien tenía puesto y no embarcó.'],
        ],
        'nota'  => 'Los ingresos del sistema solo cuentan reservas <b>Confirmadas</b>: una reserva pendiente nunca infla la caja.',
    ],
    'admin:anuncios' => [
        'icono'  => 'fa-images',
        'titulo' => 'Anuncios de la Landing',
        'intro'  => 'Promociones y avisos que se ven en la página de inicio.',
        'pasos'  => [
            ['fa-plus',          'text-emerald-500', 'Subir',            'Image panorámica, título, subtítulo y botón. Se ve en la portada de inmediato.'],
            ['fa-eye',           'text-sky-500',    'Ver landing',      'Abre una <b>vista previa</b> en un modal: así ves el resultado sin salir del módulo.'],
            ['fa-star',          'text-amber-500',  'Destacar',         'El destacado aparece primero en el carrusel.'],
            ['fa-eye-slash',     'text-slate-400',  'Publicar / ocultar','Ocultar no borra nada: conserva el contenido y su fecha.'],
            ['fa-trash',         'text-rose-500',   'Eliminar',         'Borra también la imagen del servidor.'],
        ],
        'nota'  => 'Un anuncio solo se ve si está <b>publicado</b>, dentro de su <b>fecha de vigencia</b> y con la imagen realmente en el servidor.',
    ],
    'admin:gestion_permisos' => [
        'icono'  => 'fa-key',
        'titulo' => 'Gestión de Permisos',
        'intro'  => 'Qué puede hacer cada persona dentro de SGET.',
        'pasos'  => [
            ['fa-user-check',      'text-emerald-500', 'Conceder',    'Marca el permiso para ese usuario: la opción aparece en su menú y sus endpoints lo permiten.'],
            ['fa-ban',             'text-rose-500',    'Denegar',     'Una denegación explícita manda sobre el rol.'],
            ['fa-shield-halved',   'text-sky-500',     'Mínimo privilegio', 'Lo que no está concedido <b>no se concede</b>: si no hay decisión, el acceso se rechaza.'],
        ],
        'nota'  => 'Ocultar un botón NO es seguridad: aunque no se vea, el endpoint vuelve a comprobar el permiso en el servidor.',
    ],
    'admin:comunicados' => [
        'icono'  => 'fa-bullhorn',
        'titulo' => 'Comunicados',
        'intro'  => 'Avisos masivos al buzón de pasajeros o conductores.',
        'pasos'  => [
            ['fa-pen',         'text-sky-500',    'Redactar',   'Asunto de hasta 150 caracteres y mensaje de al menos 15.'],
            ['fa-users',       'text-emerald-500','Destinatarios', 'Puedes marcar uno o varios roles a la vez.'],
            ['fa-paper-plane', 'text-emerald-500','Enviar',     'Cada persona recibe un aviso en su propio buzón.'],
        ],
        'nota'  => 'Los comunicados no modifican datos: solo escriben avisos. Para cambiar estados hay que usar el módulo correspondiente.',
    ],
    'admin:reportes' => [
        'icono'  => 'fa-chart-column',
        'titulo' => 'Panel de Información',
        'intro'  => 'Métricas del negocio: viajes, ingresos y ocupación.',
        'pasos'  => [
            ['fa-filter',     'text-sky-500',    'Filtrar',        'Acota los datos por rango de fechas antes de mirar las cifras.'],
            ['fa-file-pdf',   'text-emerald-500','Descargar PDF', 'El archivo se genera con el nombre del módulo y la fecha del reporte.'],
            ['fa-print',      'text-sky-500',    'Imprimir',       'Abre la versión imprimible del reporte.'],
        ],
        'nota'  => 'Las cifras de ingresos suman únicamente reservas <b>Confirmadas</b>: es el mismo criterio que usa el recaudo.',
    ],
    'admin:reportes_pasajeros' => [
        'icono'  => 'fa-comment-dots',
        'titulo' => 'Reportes de Pasajeros',
        'intro'  => 'Quejas y avisos que deja la gente sobre un viaje.',
        'pasos'  => [
            ['fa-list',       'text-sky-500',    'Revisar',       'Cada reporte indica el viaje y el pasajero que lo escribió.'],
            ['fa-check',      'text-emerald-500','Marcar resuelto','Cambia el estado a <b>Resuelto</b> y queda registrado quién lo hizo.'],
            ['fa-ban',        'text-rose-500',   'Eliminar',      'Solo para los que no sirven: el resto se resuelve, no se borra.'],
        ],
        'nota'  => 'Un pasajero solo puede dejar un reporte sobre un viaje en el que realmenteasekugó.',
    ],
    'admin:ranking_conductores' => [
        'icono'  => 'fa-star',
        'titulo' => 'Calificaciones',
        'intro'  => 'Rendimiento y reseñas de los conductores.',
        'pasos'  => [
            ['fa-chart-line', 'text-emerald-500', 'Ranking',   'Ordena a los conductores por promedio de estrellas.'],
            ['fa-comment',    'text-sky-500',     'Reseñas',   'Lee lo que escribieron los pasajeros en cada viaje.'],
        ],
        'nota'  => 'Cada pasajero puede calificar <b>una sola vez</b> cada viaje en el que Viajó.',
    ],
    'admin:logs' => [
        'icono'  => 'fa-file-alt',
        'titulo' => 'Logs de Auditoría',
        'intro'  => 'Traza de todas las acciones sensibles del sistema.',
        'pasos'  => [
            ['fa-magnifying-glass','text-sky-500',    'Buscar',   'Filtra por usuario, acción o fecha.'],
            ['fa-file-alt',        'text-emerald-500','Exportar', 'Descarga el registro filtrado.'],
        ],
        'nota'  => 'Se registran los ingresos a sesión, los cambios de contraseña, las reservas, los viajes y los permisos.',
    ],

    /* ========================= CONDUCTOR ================================ */
    'conductor:viaje_asignado' => [
        'icono'  => 'fa-bus',
        'titulo' => 'Viaje Asignado',
        'intro'  => 'Todo lo que necesitas del viaje que te toca conducir.',
        'pasos'  => [
            ['fa-users',        'text-sky-500',    'Manifiesto',   'La lista de pasajeros con puesto: documento, teléfono y si ya paying.'],
            ['fa-user-check',   'text-emerald-500','Marcar embarque','Registra quién subió y quién no se presentó.'],
            ['fa-truck-fast',   'text-amber-500',  'Marcar «En curso»', 'Avisa a los pasajeros de que la unidad salió.'],
            ['fa-flag-checkered','text-emerald-500','Finalizar',    'Cierra el viaje y te devuelve a disponible.'],
        ],
        'nota'  => 'Solo puedes ver y marcar pasajeros de los viajes <b>tuyos</b>: el servidor lo comprueba en cada acción.',
    ],
    'conductor:viajes_conductor' => [
        'icono'  => 'fa-route',
        'titulo' => 'Mis Viajes',
        'intro'  => 'El histórico y la programación de tus trayectos.',
        'pasos'  => [
            ['fa-list',      'text-sky-500',    'Listar',     'Solo aparecen tus viajes, con su estado actual.'],
            ['fa-filter',    'text-sky-500',    'Filtrar',    'Acota por fecha o por estado para encontrar el tuyo rápido.'],
            ['fa-eye',       'text-emerald-500','Ver detalle','Ruta, horario, vehículo y pasajeros reservados.'],
        ],
        'nota'  => 'Tu estado (<b>Disponible</b> u <b>Ocupado</b>) lo actualiza el sistema al crear y cerrar viajes.',
    ],

    /* ========================== PASAJERO ================================ */
    'pasajero:viajes_pasajero' => [
        'icono'  => 'fa-bus',
        'titulo' => 'Ver Viajes',
        'intro'  => 'Rutas disponibles, horarios, precios y cupos libres.',
        'pasos'  => [
            ['fa-magnifying-glass','text-sky-500',    'Buscar',     'Filtra por ruta o por hora de salida.'],
            ['fa-chair',           'text-emerald-500','Reservar',   'Aparta tu puesto. Solo puedes apartar <b>una vez por viaje</b>.'],
            ['fa-calendar-xmark',  'text-rose-500',  'Cancelar',   'Desde tu historial, mientras el viaje no haya salido.'],
        ],
        'nota'  => 'El sistema no te deja tener puesto en dos viajes que salen a la misma fecha y hora: no se puede estar en dos buses.',
    ],
    'pasajero:historial_pasajero' => [
        'icono'  => 'fa-history',
        'titulo' => 'Historial de Reservas',
        'intro'  => 'Todos tus viajes, con su estado de pago y comprobante.',
        'pasos'  => [
            ['fa-list',       'text-sky-500',    'Ver viajes',     'Programados, confirmados y finalizados.'],
            ['fa-receipt',    'text-emerald-500','Comprobante',    'Genera el tiquete de tu reserva.'],
            ['fa-star',       'text-amber-500',  'Calificar',      'Cuando el viaje termina puedes calificar al conductor.'],
        ],
        'nota'  => 'Si el viaje se cancela, la reserva pasa a <b>Cancelada</b> y no se realiza ningún cobro.',
    ],
    'pasajero:calificar' => [
        'icono'  => 'fa-star',
        'titulo' => 'Calificar Servicio',
        'intro'  => 'Tu opinión ayuda a mejorar el servicio.',
        'pasos'  => [
            ['fa-list',   'text-sky-500',    'Elegir viaje','Solo aparecen los viajes en los que realmente subiste.'],
            ['fa-star',   'text-amber-500',  'Puntuar',     'De una a cinco estrellas.'],
            ['fa-pen',    'text-sky-500',    'Comentario',  'Opcional, hasta 255 caracteres.'],
        ],
        'nota'  => 'Cada viaje se califica una sola vez por pasajero.',
    ],

    /* ======================= GENERALES POR ROL ========================== */
    'general:conductor' => [
        'icono'  => 'fa-id-card',
        'titulo' => 'Ayudas del sistema · Conductor',
        'intro'  => 'Estas son las cosas que puedes hacer con tu cuenta.',
        'pasos'  => [
            ['fa-chart-pie',    'text-sky-500',    'Consultar tus métricas',    'El resumen de tu jornada y tus viajes.'],
            ['fa-route',        'text-sky-500',    'Consultar tus viajes',     'Histórico y próximos trayectos.'],
            ['fa-gauge-high',   'text-emerald-500','Gestionar estados',        'En curso y finalizar, solo de los viajes que conduces.'],
            ['fa-bell',         'text-amber-500',  'Revisar tu buzón',         'Avisos de cambios, de salidas y de viajes perdidos.'],
            ['fa-user-gear',    'text-sky-500',    'Editar tu perfil',         'Nombre, correo y contraseña desde la rueda de la cabecera.'],
        ],
        'nota'  => 'Cada acción sensible queda registrada en la auditoría del sistema.',
    ],
    'general:pasajero' => [
        'icono'  => 'fa-user',
        'titulo' => 'Ayudas del sistema · Pasajero',
        'intro'  => 'Estas son las cosas que puedes hacer con tu cuenta.',
        'pasos'  => [
            ['fa-bus',          'text-sky-500',    'Consultar viajes',     'Rutas, horarios, precios y cupos disponibles.'],
            ['fa-ticket-alt',   'text-emerald-500','Reservar',             'Aparta tu puesto en el viaje que quieras.'],
            ['fa-history',      'text-sky-500',    'Consultar reservas',   'Histórico, comprobantes y cancelación.'],
            ['fa-star',         'text-amber-500',  'Calificar',            'Valorar el servicio una vez terminado.'],
            ['fa-bell',         'text-amber-500',  'Revisar tu buzón',     'Avisos de cambios de horario o de cancelación.'],
        ],
        'nota'  => 'Los avisos del buzón también se ven sin conexión si la página ya estaba cargada.',
    ],
    'general:admin' => [
        'icono'  => 'fa-shield-halved',
        'titulo' => 'Ayudas del sistema · Administrador',
        'intro'  => 'Guía rápida de SGET. El contenido se ajusta al módulo en el que estés.',
        'pasos'  => [
            ['fa-magnifying-glass', 'text-sky-500',    'Buscador',    'Escribe arriba o pulsa <b>Ctrl + K</b> para ir a cualquier módulo.'],
            ['fa-keyboard',         'text-sky-500',    'Atajos',      '<b>Esc</b> cierra el modal abierto · <b>Ctrl + Enter</b> guarda el formulario.'],
            ['fa-moon',             'text-amber-500',  'Modo oscuro', 'El botón de la luna en la cabecera cambia el tema y la preferencia queda guardada.'],
            ['fa-globe',            'text-emerald-500','Idioma',      'Selector <b>ESP / ENG</b> en la cabecera.'],
            ['fa-bell',             'text-amber-500',  'Notificaciones', 'Avisos de cambios y de viajes perdidos en tu buzón.'],
        ],
        'nota'  => 'Todas las acciones sensibles piden confirmación y quedan registradas en la auditoría.',
    ],
];

/* ------------------------------------------------------------------------- */
/* Selección de la guía                                                      */
/* ------------------------------------------------------------------------- */
$__prefijoRol = match ($__rol) {
    Config::ROL_ADMIN     => 'admin',
    Config::ROL_CONDUCTOR => 'conductor',
    Config::ROL_PASAJERO  => 'pasajero',
    default               => 'general',
};

$__clave = $__guias[$__prefijoRol . ':' . $__pagina]
        ?? $__guias[$__pagina]
        ?? $__guias['general:' . $__prefijoRol]
        ?? $__guias['general:admin'];

$__g = $__clave;

/** Escapa el texto y deja pasar solo el <b> de énfasis de la propia guía. */
$__texto = static function (?string $html): string {
    $s = htmlspecialchars((string) $html, ENT_QUOTES, 'UTF-8');
    return str_replace(['&lt;b&gt;', '&lt;/b&gt;'], ['<b>', '</b>'], $s);
};
?>
<!-- ==========================================================================
     BOTÓN GLOBAL «?»  ·  esquina inferior derecha
     ==========================================================================
     Es el ÚNICO acceso a la ayuda del sistema. Sustituye a los botones que
     estaban repartidos por los módulos, que además llegaban a abrir cuatro
     modales distintos con el mismo id.
     Se sube a <body> por el motor común para que ningún ancestro con
     `transform` o `filter` pueda dejarlo por detrás de otro elemento. -->
<button type="button"
        id="btnAyudaGlobal"
        class="sget-ayuda-fab"
        data-sget-flotante
        data-sget-modal="modalAyuda"
        aria-label="Ayudas del sistema"
        title="Ayudas del sistema (F1)">
    <i class="fas fa-question" aria-hidden="true"></i>
</button>

<!-- ==========================================================================
     MODAL · AYUDAS DEL SISTEMA
     ========================================================================== -->
<div class="sget-modal-wrap" id="modalAyuda" data-sget-capa data-titulo="Ayudas del sistema">
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
            <p class="sget-label" style="margin-bottom:.875rem">En este módulo puedes:</p>

            <ul class="sget-ayuda-lista">
                <?php foreach ($__g['pasos'] as [$icono, $color, $titulo, $texto]): ?>
                    <li class="sget-ayuda-item">
                        <span class="sget-ayuda-item__icono <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas <?= htmlspecialchars($icono, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                        </span>
                        <div class="sget-ayuda-item__texto">
                            <p class="sget-label"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="sget-help"><?= $__texto($texto) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="sget-nota sget-nota--info" style="margin-top:1.25rem">
                <i class="fas fa-circle-info"></i> <?= htmlspecialchars($__g['nota'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--primario" data-sget-cerrar>Entendido</button>
        </footer>
    </div>
</div>

<style>
    /* Botón flotante global. Se fija al viewport, no al contenido, así que
       acompaña al usuario aunque haga scroll dentro de una tabla larga. */
    .sget-ayuda-fab {
        position: fixed;
        right: 1.25rem;
        bottom: 1.25rem;
        z-index: 45;
        display: grid;
        place-items: center;
        width: 3.25rem;
        height: 3.25rem;
        border-radius: 999px;
        border: 1px solid var(--sget-borde);
        background: var(--sget-superficie);
        color: var(--sget-azul);
        font-size: 1.05rem;
        cursor: pointer;
        box-shadow: var(--sget-sombra-fuerte);
        transition: transform var(--sget-transicion), background-color var(--sget-transicion),
                    color var(--sget-transicion), border-color var(--sget-transicion);
    }
    .sget-ayuda-fab:hover {
        transform: translateY(-2px) scale(1.04);
        background: var(--sget-azul);
        border-color: var(--sget-azul);
        color: #fff;
    }
    .sget-ayuda-fab:focus-visible { outline: 2px solid var(--sget-azul); outline-offset: 3px; }

    /* Con un modal abierto el botón se aparta para no quedar detrás del velo
       ni deambular sobre el contenido. */
    body.sget-modal-abierto .sget-ayuda-fab { opacity: 0; pointer-events: none; }

    @media (max-width: 640px) {
        .sget-ayuda-fab { right: .875rem; bottom: .875rem; width: 2.875rem; height: 2.875rem; }
    }

    /* Lista de la ayuda: rejilla de icono + texto, legible en ambos temas. */
    .sget-ayuda-lista { display: flex; flex-direction: column; gap: .875rem; margin: 0; padding: 0; list-style: none; }
    .sget-ayuda-item { display: flex; gap: .875rem; align-items: flex-start; }
    .sget-ayuda-item__icono {
        flex: none;
        display: grid;
        place-items: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: var(--sget-radio-sm);
        background: color-mix(in srgb, currentColor 14%, transparent);
        border: 1px solid color-mix(in srgb, currentColor 25%, transparent);
    }
    .sget-ayuda-item__texto { min-width: 0; }
    .sget-ayuda-item__texto .sget-label { margin-bottom: .125rem; }
    .sget-ayuda-item__texto .sget-help { font-size: .8125rem; line-height: 1.55; }
</style>

<script>
    /* Atajo F1: la ayuda del módulo, desde cualquier pantalla del panel. */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'F1') return;
        e.preventDefault();
        if (typeof SGETModal !== 'undefined') SGETModal.toggle('modalAyuda');
    });
</script>
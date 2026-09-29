<?php
/**
 * Admin/anuncios.php
 * -----------------------------------------------------------------------------
 * MÓDULO: ANUNCIOS DE LA LANDING  (Admin)
 * -----------------------------------------------------------------------------
 * QUÉ HACE
 *   Antes los banners de la página de inicio estaban escritos a mano en el
 *   HTML de index.php: para anunciar una promoción había que editar código y
 *   subir la página. Aquí el administrador sube una imagen, escribe los
 *   textos, define el enlace del botón, la vigencia y el orden, y decide si el
 *   anuncio está publicado o destacado.
 *
 *   · Vista previa real de cómo se verá en la landing.
 *   · Publicar / ocultar, destacar y eliminar sin recargar el listado completo.
 *   · Contador de visualizaciones por anuncio para saber cuál funciona.
 *
 * Las reglas de validación y el borrado de archivos viven en
 * services/AnuncioService.php; esta página solo dibuja.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirAdmin();
Auth::requerirAcceso('anuncios');

if (!empty($_GET['ok']))     Flash::exito((string)$_GET['ok']);
elseif (!empty($_GET['error'])) Flash::error((string)$_GET['error']);

$anuncios = AnuncioService::todos();
$resumen  = AnuncioService::resumen();

$tituloPagina = 'Anuncios';
include __DIR__ . '/../views/partials/head.php';
?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="sget-shell">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="sget-main">
        <header class="sget-page-head">
            <div>
                <h1 class="sget-page-title">
                    <i class="fas fa-images text-sky-500"></i> Anuncios de la página de inicio
                </h1>
                <p class="sget-page-sub">
                    Sube promociones y avisos con imagen. El carrusel de la landing muestra los anuncios
                    <strong>publicados</strong> y dentro de su <strong>fecha de vigencia</strong>.
                </p>
            </div>
            <div class="sget-page-actions">
                <a class="sget-btn sget-btn--neutro" href="../index.php" target="_blank" rel="noopener">
                    <i class="fas fa-eye"></i> Ver la landing
                </a>
                <button type="button" class="sget-btn sget-btn--primario" data-sget-modal="modalAnuncio"
                        data-sget-nuevo="Subir nuevo anuncio">
                    <i class="fas fa-plus"></i> Nuevo anuncio
                </button>
            </div>
        </header>

        <?= Flash::render() ?>

        <!-- KPIs -->
        <section class="sget-grid sget-grid--kpi">
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-azul) 12%,transparent);color:var(--sget-azul)"><i class="fas fa-image"></i></span>
                <div><p class="sget-label">Anuncios</p><p class="sget-kpi__valor"><?= (int)$resumen['total'] ?></p></div>
            </div>
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-emerald) 12%,transparent);color:var(--sget-emerald)"><i class="fas fa-circle-check"></i></span>
                <div><p class="sget-label">Visibles ahora</p><p class="sget-kpi__valor"><?= (int)$resumen['vigentes'] ?></p></div>
            </div>
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-ambars) 14%,transparent);color:var(--sget-ambars)"><i class="fas fa-star"></i></span>
                <div><p class="sget-label">Destacados</p><p class="sget-kpi__valor"><?= (int)$resumen['destacados'] ?></p></div>
            </div>
            <div class="sget-card sget-kpi">
                <span class="sget-kpi__icono" style="background:color-mix(in srgb,var(--sget-morado) 12%,transparent);color:var(--sget-morado)"><i class="fas fa-eye"></i></span>
                <div><p class="sget-label">Visualizaciones</p><p class="sget-kpi__valor"><?= number_format((float)$resumen['vistas'], 0, ',', '.') ?></p></div>
            </div>
        </section>

        <?php if ((int)$resumen['sin_imagen'] > 0): ?>
            <?php
            /* Aviso de imágenes ausentes.
               Una fila puede apuntar a un archivo que ya no está en
               `uploads/anuncios` (se borró a mano, se restauró una base de datos
               antigua…). El carrusel los salta, así que sin este aviso el
               administrador ve un anuncio «publicado» que no sale en la landing
               y no hay forma de saber por qué. */
            ?>
            <div class="sget-nota sget-nota--peligro" style="margin-bottom:1rem">
                <i class="fas fa-image"></i>
                <div>
                    <strong><?= (int)$resumen['sin_imagen'] ?> anuncio(s) no se muestran porque su imagen no está en el servidor.</strong>
                    Vuelve a editar cada uno y sube el archivo otra vez: mientras tanto, la landing los salta por completo.
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($anuncios) && (int)$resumen['vigentes'] === 0): ?>
            <div class="sget-nota sget-nota--aviso" style="margin-bottom:1rem">
                <i class="fas fa-eye-slash"></i>
                <div>
                    <strong>Ahora mismo no hay ningún anuncio visible en la landing.</strong>
                    Para que uno aparezca tienen que cumplirse las tres cosas: estar <strong>publicado</strong>,
                    estar dentro de su <strong>fecha de vigencia</strong> y tener la <strong>imagen en el servidor</strong>.
                    <?php if ((int)$resumen['activos'] > 0): ?>
                        Tienes <?= (int)$resumen['activos'] ?> publicado(s): revisa si están fuera de fecha.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($anuncios)): ?>
            <div class="sget-vacio">
                <span class="sget-vacio__icono"><i class="fas fa-image"></i></span>
                <h2 class="sget-label" style="font-size:.875rem">Todavía no hay anuncios</h2>
                <p class="sget-page-sub" style="margin:0">
                    Sube el primero y aparecerá de inmediato en la página de inicio de SGET.
                </p>
                <button type="button" class="sget-btn sget-btn--primario" style="margin-top:1rem" data-sget-modal="modalAnuncio"
                        data-sget-nuevo="Subir mi primer anuncio">
                    <i class="fas fa-plus"></i> Subir mi primer anuncio
                </button>
            </div>
        <?php else: ?>

            <!-- Buscador + filtros -->
            <div class="sget-toolbar">
                <div class="sget-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" id="buscarAnuncio" class="sget-input" data-sget-buscar
                           placeholder="Buscar por título o enlace… (Ctrl+K)">
                </div>
                <button type="button" class="sget-btn sget-btn--fantasma sget-btn--sm" data-sget-filtro="*" aria-pressed="true">Todos</button>
                <button type="button" class="sget-btn sget-btn--fantasma sget-btn--sm" data-sget-filtro="1">Visibles</button>
                <button type="button" class="sget-btn sget-btn--fantasma sget-btn--sm" data-sget-filtro="0">Ocultos</button>
                <button type="button" class="sget-btn sget-btn--fantasma sget-btn--sm"
                        data-sget-filtro="2" data-sget-filtro-de="data-publication">Fuera de fecha</button>
                <p class="sget-sin-resultados" data-sget-sin-resultados hidden>Ningún anuncio coincide.</p>
            </div>

            <section class="sget-grid sget-grid--ancho">
                <?php foreach ($anuncios as $a):
                    $id      = (int)$a['id_ann'];
                    $activo  = (int)$a['activo'] === 1;
                    $destac  = (int)$a['destacado'] === 1;
                    $vigente = !empty($a['vigente']);
                    $sinImg  = !empty($a['sin_imagen']);
                    $visible = $activo && $vigente && !$sinImg;

                    // Motivo por el que NO se ve. Se calcula una sola vez y se
                    // pinta en la tarjeta: era la pregunta que el módulo no
                    // respondía («lo subí y no aparece»).
                    $motivo = $sinImg
                        ? 'Falta la imagen en el servidor: vuelve a subirla.'
                        : (!$activo ? 'Está oculto: actívalo para publicarlo.'
                           : (!$vigente ? 'Está fuera de su fecha de vigencia.' : ''));

                    $datos   = [
                        'id_ann'     => $id,
                        'titulo'     => 'Editar anuncio #' . $id,   // cabecera del modal
                        'titulo_ann' => $a['titulo'],             // campo del formulario
                        'subtitulo'  => $a['subtitulo'] ?? '',
                        'descripcion'=> $a['descripcion'] ?? '',
                        'imagen'     => $a['imagen'] ?? '',
                        'enlace'     => $a['enlace'] ?? '',
                        'boton_texto'=> $a['boton_texto'] ?? '',
                        'color_tema' => (string)($a['color_tema'] ?? 'azul'),
                        'orden'      => (string)(int)($a['orden'] ?? 0),
                        'fec_inicio' => $a['fec_inicio'] ?? '',
                        'fec_fin'    => $a['fec_fin'] ?? '',
                        'img_actual' => $a['imagen'] ?? '',
                        // URL pública de la imagen guardada: la usa la vista previa
                        // del modal (data-sget-src). Antes el modal no recibía la URL
                        // y al editar un anuncio la previsualización salía vacía.
                        'url_imagen' => $a['url_imagen'] ?? '',
                        'activo'     => (string)(int)$activo,
                        'destacado'  => (string)(int)$destac,
                    ];
                ?>
                    <article class="sget-card sget-fila" data-sget-fila data-estado="<?= $visible ? '1' : '0' ?>"
                             data-publication="<?= ($activo && $vigente) ? '1' : '2' ?>"
                             style="<?= $visible ? '' : 'opacity:.68;' ?>">

                        <!-- Imagen con los textos encima: así se ve el resultado real -->
                        <div class="sget-anuncio-card sget-anuncio-card--<?= htmlspecialchars((string)($a['color_tema'] ?? 'azul'), ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (!empty($a['url_imagen']) && !$sinImg): ?>
                                <img src="<?= htmlspecialchars((string)$a['url_imagen'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars((string)$a['titulo'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                            <?php else: ?>
                                <?php /* Sin archivo en el servidor no se pinta un <img> roto:
                                         se enseña el hueco, que es la información útil. */ ?>
                                <span class="sget-anuncio-card__vacio"><i class="fas fa-image"></i></span>
                            <?php endif; ?>

                            <div class="sget-anuncio-card__texto">
                                <p class="sget-anuncio-card__titulo"><?= htmlspecialchars((string)$a['titulo'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (!empty($a['subtitulo'])): ?>
                                    <p class="sget-anuncio-card__sub"><?= htmlspecialchars((string)$a['subtitulo'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:.375rem;flex-wrap:wrap;margin-top:.875rem">
                            <?php if ($sinImg): ?>
                                <span class="sget-badge sget-badge--error"><i class="fas fa-triangle-exclamation"></i> Sin imagen</span>
                                <span class="sget-badge sget-badge--neutro"><i class="fas fa-eye-slash"></i> No se muestra</span>
                            <?php elseif (!$activo): ?>
                                <span class="sget-badge sget-badge--neutro"><i class="fas fa-eye-slash"></i> Oculto</span>
                            <?php elseif (!$vigente): ?>
                                <span class="sget-badge sget-badge--aviso"><i class="fas fa-clock"></i> Fuera de vigencia</span>
                            <?php else: ?>
                                <span class="sget-badge sget-badge--exito"><i class="fas fa-circle-check"></i> Visible</span>
                            <?php endif; ?>

                            <?php if ($destac): ?>
                                <span class="sget-badge sget-badge--info"><i class="fas fa-star"></i> Destacado</span>
                            <?php endif; ?>

                            <span class="sget-badge sget-badge--neutro"><i class="fas fa-eye"></i>
                                <?= (int)$a['veces_vista'] === 1 ? '1 vista' : number_format((float)$a['veces_vista'], 0, ',', '.') . ' vistas' ?>
                            </span>

                            <?php if (!empty($a['fec_inicio']) || !empty($a['fec_fin'])): ?>
                                <span class="sget-help sget-mono">
                                    <?= !empty($a['fec_inicio']) ? htmlspecialchars((string)$a['fec_inicio'], ENT_QUOTES, 'UTF-8') : 'ahora' ?>
                                    →
                                    <?= !empty($a['fec_fin']) ? htmlspecialchars((string)$a['fec_fin'], ENT_QUOTES, 'UTF-8') : 'sin fin' ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($motivo !== ''): ?>
                            <p class="sget-help" style="margin-top:.5rem;color:var(--sget-rojo)">
                                <i class="fas fa-circle-info"></i> <?= htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($a['enlace']) || !empty($a['boton_texto'])): ?>
                            <p class="sget-help sget-linea-1" style="margin-top:.5rem">
                                <?php if (!empty($a['boton_texto'])): ?>
                                    <strong><?= htmlspecialchars((string)$a['boton_texto'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($a['enlace'])): ?>
                                    → <span class="sget-mono"><?= htmlspecialchars((string)$a['enlace'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <footer style="display:flex;gap:.5rem;margin-top:1rem;padding-top:.875rem;border-top:1px solid var(--sget-borde)">
                            <button type="button" class="sget-btn sget-btn--neutro sget-btn--sm" style="flex:1"
                                    data-sget-modal="modalAnuncio"
                                    data-sget-datos='<?= htmlspecialchars(json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="fas fa-pen"></i> Editar
                            </button>

                            <button type="button" class="sget-icon-btn sget-icon-btn--editar"
                                    title="<?= $destac ? 'Quitar destacado' : 'Destacar (aparece primero)' ?>"
                                    aria-label="Destacar anuncio"
                                    data-sget-accion="anuncioDestacado" data-sget-modulo="anuncio"
                                    data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="fas fa-star"></i>
                            </button>

                            <button type="button" class="sget-icon-btn <?= $activo ? 'sget-icon-btn--peligro' : 'sget-icon-btn--exito' ?>"
                                    title="<?= $activo ? 'Ocultar de la landing' : 'Publicar en la landing' ?>"
                                    aria-label="<?= $activo ? 'Ocultar anuncio' : 'Publicar anuncio' ?>"
                                    data-sget-accion="anuncioEstado" data-sget-modulo="anuncio"
                                    data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="fas <?= $activo ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                            </button>

                            <button type="button" class="sget-icon-btn sget-icon-btn--peligro"
                                    title="Eliminar anuncio" aria-label="Eliminar anuncio"
                                    data-sget-accion="anuncioEliminar" data-sget-modulo="anuncio"
                                    data-sget-dato='<?= htmlspecialchars(json_encode(['id' => $id], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                                    data-sget-titulo="Eliminar anuncio"
                                    data-sget-texto='Se eliminará <strong><?= htmlspecialchars((string)$a['titulo'], ENT_QUOTES, 'UTF-8') ?></strong> y su imagen desaparecerán de la landing. Esta acción no se puede deshacer.'
                                    data-sget-ok="Sí, eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/../views/modals/anuncio.php'; ?>

<?php
$jsExtra = ['sget-page.js'];
include __DIR__ . '/../views/partials/foot.php';

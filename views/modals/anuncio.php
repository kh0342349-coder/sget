<?php
/**
 * views/modals/anuncio.php
 * -----------------------------------------------------------------------------
 * MODAL DE ANUNCIOS · Crear / Editar
 * -----------------------------------------------------------------------------
 * Es el módulo que permite al administrador cambiar los banners de la landing
 * sin tocar código: imagen, textos, enlace, botón, color, vigencia y orden.
 *
 * Decisiones de diseño:
 *   · La imagen se sube con el mismo `data-sget-form` del resto de módulos (el
 *     FormData ya viaja con el archivo, no hace falta un endpoint aparte).
 *   · Previsualización inmediata del archivo elegido y de la imagen ya guardada,
 *     para no subir un archivo y descubrir después que no encaja en el carrusel.
 *   · Los campos de vigencia y el interruptor de "destacado" se explican solos:
 *     son los que más confunden a quien administra sin ser desarrollador.
 * -----------------------------------------------------------------------------
 */
// OJO con las claves de población (`data-sget-campo` / `data-sget-texto`):
//   · la cabecera del modal usa la clave "titulo", que es la que escribe
//     sget-page.js al abrir el formulario en modo "nuevo";
//   · el campo del título del anuncio usa "titulo_ann" para no pisar la cabecera.
$__anuncioModal = $__anuncioModal ?? [];
$__esEdicion     = !empty($__anuncioModal['id_ann']);
$v = fn(string $k, $def = '') => htmlspecialchars((string)($__anuncioModal[$k] ?? $def), ENT_QUOTES, 'UTF-8');
$imgActual = AnuncioService::urlImagen($__anuncioModal['imagen'] ?? null);

/* Id del anuncio en edición. La vista previa se sirve desde un endpoint real
   (`procesos/anuncio_vista.php`) en vez de montar una maqueta aquí dentro: así
   se ve exactamente lo que verá el visitante, con la misma hoja de estilos que
   la portada. `0` = anuncio nuevo, donde todavía no hay nada que previsualizar. */
$__idPreview  = (int)($__anuncioModal['id_ann'] ?? 0);
$__urlPreview = Config::basePath() . '/procesos/anuncio_vista.php?id=' . $__idPreview;
?>
<!-- ==========================================================================
     VISTA PREVIA DEL ANUNCIO EN LA LANDING
     ==========================================================================
     Modal con un <iframe> que carga `procesos/anuncio_vista.php`.

     ANTES el botón «Ver landing» mandaba al administrador a la portada real en
     otra pestaña: se perdía el listado y el contexto, y no se distinguía lo que
     se iba a ver de lo que ya estaba publicado.

     Ahora se ve aquí mismo, sin navegar, y se conserva «Abrir la vista previa
     aparte» para quien quiera comprobarla en su contexto. -->
<div class="sget-modal-wrap" id="modalPreviewAnuncio" data-sget-capa data-titulo="Vista previa del anuncio">
    <div class="sget-overlay"></div>

    <div class="sget-modal sget-modal--xl" role="dialog" aria-modal="true" aria-labelledby="tituloPreviewAnuncio">
        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloPreviewAnuncio">
                    <span class="sget-modal__icono"><i class="fas fa-eye"></i></span>
                    <span>Vista previa del anuncio</span>
                </h2>
                <p class="sget-modal__sub">Así se verá en la página de inicio de SGET. Es una maqueta: el botón no lleva a ninguna parte.</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll" style="padding:0">
            <iframe id="iframePreviewAnuncio"
                    title="Vista previa del anuncio en la landing"
                    src="<?= htmlspecialchars($__urlPreview, ENT_QUOTES, 'UTF-8') ?>"
                    loading="lazy"
                    style="display:block;width:100%;height:min(60dvh,34rem);border:0;background:#020617"></iframe>
        </div>

        <footer class="sget-modal__foot">
            <a class="sget-btn sget-btn--neutro" href="<?= htmlspecialchars($__urlPreview, ENT_QUOTES, 'UTF-8') ?>"
               target="_blank" rel="noopener">
                <i class="fas fa-up-right-from-square"></i> Abrir aparte
            </a>
            <button type="button" class="sget-btn sget-btn--primario" data-sget-cerrar>Cerrar</button>
        </footer>
    </div>
</div>

<div class="sget-modal-wrap" id="modalAnuncio" data-sget-capa data-titulo="<?= $__esEdicion ? 'Editar anuncio' : 'Nuevo anuncio' ?>">
    <div class="sget-overlay"></div>

    <form class="sget-modal sget-modal--lg" data-sget-panel novalidate
          data-sget-form data-sget-cerrar-al-guardar="modalAnuncio"
          role="dialog" aria-modal="true" aria-labelledby="tituloModalAnuncio">

        <header class="sget-modal__head">
            <div>
                <h2 class="sget-modal__titulo" id="tituloModalAnuncio">
                    <span class="sget-modal__icono"><i class="fas fa-image"></i></span>
                    <span data-sget-texto="titulo"><?= $__esEdicion ? 'Editar anuncio #' . (int)$__anuncioModal['id_ann'] : 'Subir nuevo anuncio' ?></span>
                </h2>
                <p class="sget-modal__sub">Este anuncio aparecerá en la página de inicio, junto al carrusel de viajes.</p>
            </div>
            <button type="button" class="sget-modal__cerrar" data-sget-cerrar aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="sget-modal__body sget-scroll">
            <?= Auth::campoToken() ?>
            <input type="hidden" name="modulo" value="anuncio">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_ann" data-sget-campo="id_ann" value="<?= $v('id_ann', '0') ?>">
            <input type="hidden" name="img_actual" data-sget-campo="img_actual" value="<?= $v('imagen') ?>">

            <!-- IMAGEN -->
            <div class="sget-field" data-campo="imagen">
                <label class="sget-label" for="anuncio_imagen">
                    <i class="fas fa-image"></i> Imagen del anuncio
                    <span class="sget-label__req">*</span>
                </label>
                <input type="file" id="anuncio_imagen" name="imagen" class="sget-input"
                       accept=".jpg,.jpeg,.png,.webp,.gif,.svg,image/*"
                       data-sget-preview="#anuncio_preview">
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                <p class="sget-help">
                    Recomendada <?= (int)(AnuncioService::IMAGEN_ANCHO / 8) * 8 ?>×<?= AnuncioService::IMAGEN_ALTO ?> px
                    (panorámica), hasta <?= (int)(AnuncioService::MAX_BYTES / 1048576) ?> MB.
                    Formatos: <?= strtoupper(implode(', ', AnuncioService::EXTENSIONES)) ?>.
                    <?= $__esEdicion ? 'Si no eliges un archivo, se conserva la imagen actual.' : '' ?>
                </p>

                <div class="sget-anuncio-preview" data-sget-preview-caja data-sget-mostrar="url_imagen"
                     style="margin-top:.75rem;<?= $imgActual ? '' : 'display:none' ?>">
                    <img id="anuncio_preview" alt="Vista previa del anuncio" data-sget-src="url_imagen"
                         src="<?= $imgActual ? htmlspecialchars($imgActual, ENT_QUOTES, 'UTF-8') : '' ?>">
                    <span class="sget-help" data-sget-preview-nombre>
                        <?= $v('imagen') ?>
                    </span>
                </div>
            </div>

            <!-- TEXTOS -->
            <div class="sget-field" data-campo="titulo" style="margin-top:1rem">
                <label class="sget-label" for="anuncio_titulo">
                    <i class="fas fa-heading"></i> Título <span class="sget-label__req">*</span>
                </label>
                <input type="text" id="anuncio_titulo" name="titulo" class="sget-input" maxlength="150" required
                       data-sget-autofocus data-sget-campo="titulo_ann"
                       placeholder="Ej.: Promo 20% en rutas al aeropuerto"
                       value="<?= $v('titulo') ?>">
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <div class="sget-field" data-campo="subtitulo" style="margin-top:1rem">
                <label class="sget-label" for="anuncio_subtitulo">
                    <i class="fas fa-align-left"></i> Subtítulo
                </label>
                <input type="text" id="anuncio_subtitulo" name="subtitulo" class="sget-input" maxlength="255"
                       data-sget-campo="subtitulo"
                       placeholder="Una frase que acompañe al título" value="<?= $v('subtitulo') ?>">
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <div class="sget-field" data-campo="descripcion" style="margin-top:1rem">
                <label class="sget-label" for="anuncio_descripcion">
                    <i class="fas fa-paragraph"></i> Descripción
                </label>
                <textarea id="anuncio_descripcion" name="descripcion" class="sget-textarea" rows="3"
                          data-sget-campo="descripcion" maxlength="800"
                          placeholder="Detalle del aviso, condiciones o contacto"
                          ><?= $v('descripcion') ?></textarea>
                <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
            </div>

            <!-- ENLACE + BOTÓN -->
            <div class="sget-form-2col" style="margin-top:1rem">
                <!--
                    `DIRECCIÓN`: el campo conserva el diseño que ya tenía —input
                    + botón a la derecha— porque funciona bien. Lo que se corrige
                    es el COMPORTAMIENTO:

                    · El botón no llevaba `type="button"`, así que dentro de este
                      `<form>` se enviaba como submit y recargaba la página
                      perdiendo todo lo escrito.
                    · Ahora abre la PORTADA PÚBLICA en una pestaña nueva. Si
                      quien administra aún no ha iniciado sesión, la lleva a la
                      pantalla de acceso con un aviso, porque los enlaces
                      internos válidos (`Admin/viajes.php`…) solo se ven con
                      sesión iniciada.
                -->
                <div class="sget-field" data-campo="enlace">
                    <label class="sget-label" for="anuncio_enlace">
                        <i class="fas fa-link"></i> Dirección del botón
                    </label>
                    <div style="display:flex;gap:.5rem;align-items:stretch">
                        <input type="text" id="anuncio_enlace" name="enlace" class="sget-input" maxlength="255"
                               data-sget-campo="enlace"
                               placeholder="Admin/viajes.php o https://…"
                               value="<?= $v('enlace') ?>">
                        <button type="button" class="sget-btn sget-btn--neutro" data-sget-abrir-landing
                                title="Abrir la portada de SGET en una pestaña nueva"
                                aria-label="Abrir la portada de SGET">
                            <i class="fas fa-up-right-from-square"></i> Abrir
                        </button>
                    </div>
                    <p class="sget-help">
                        Admite un módulo del panel (<code>Admin/viajes.php</code>)
                        o una dirección web completa (<code>https://…</code>).
                    </p>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <div class="sget-field" data-campo="boton_texto">
                    <label class="sget-label" for="anuncio_boton">
                        <i class="fas fa-hand-pointer"></i> Texto del botón
                    </label>
                    <input type="text" id="anuncio_boton" name="boton_texto" class="sget-input" maxlength="60"
                           data-sget-campo="boton_texto"
                           placeholder="Ej.: Ver viajes" value="<?= $v('boton_texto') ?>">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>
            </div>

            <!-- COLOR + ORDEN -->
            <div class="sget-form-2col" style="margin-top:1rem">
                <div class="sget-field" data-campo="color_tema">
                    <label class="sget-label" for="anuncio_color">
                        <i class="fas fa-palette"></i> Color del aviso
                    </label>
                    <select id="anuncio_color" name="color_tema" class="sget-select" data-sget-campo="color_tema">
                        <?php foreach (['azul', 'emerald', 'ambars', 'morado', 'rojo'] as $color): ?>
                            <option value="<?= $color ?>" <?= $v('color_tema', 'azul') === $color ? 'selected' : '' ?>>
                                <?= ucfirst($color) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                </div>

                <div class="sget-field" data-campo="orden">
                    <label class="sget-label" for="anuncio_orden">
                        <i class="fas fa-arrow-down-1-9"></i> Orden
                    </label>
                    <input type="number" id="anuncio_orden" name="orden" class="sget-input" min="0" max="999"
                           data-sget-campo="orden"
                           placeholder="1 = primero" value="<?= $v('orden', '0') ?>">
                    <span class="sget-help">Menor número, antes en el carrusel.</span>
                </div>
            </div>

            <!-- VIGENCIA -->
            <p class="sget-label" style="margin-top:1.25rem"><i class="fas fa-calendar-days"></i> Vigencia</p>
            <div class="sget-form-2col" style="margin-top:.5rem">
                <div class="sget-field" data-campo="fec_inicio">
                    <label class="sget-label" for="anuncio_inicio">Visible desde</label>
                    <input type="date" id="anuncio_inicio" name="fec_inicio" class="sget-input"
                           data-sget-campo="fec_inicio" value="<?= $v('fec_inicio') ?>">
                    <span class="sget-help">Vacío = desde ya.</span>
                </div>
                <div class="sget-field" data-campo="fec_fin">
                    <label class="sget-label" for="anuncio_fin">Visible hasta</label>
                    <input type="date" id="anuncio_fin" name="fec_fin" class="sget-input"
                           data-sget-campo="fec_fin" value="<?= $v('fec_fin') ?>">
                    <span class="sget-error"><i class="fas fa-circle-exclamation"></i><span></span></span>
                    <span class="sget-help">Vacío = sin vencimiento.</span>
                </div>
            </div>

            <!-- INTERRUPTORES -->
            <div class="sget-form-2col" style="margin-top:1rem">
                <label class="sget-check" for="anuncio_activo">
                    <input type="checkbox" id="anuncio_activo" name="activo" value="1" data-sget-campo="activo"
                        <?= (int)($__anuncioModal['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span><strong>Publicado</strong><br>
                        <span class="sget-help">Desmárcalo para ocultarlo sin borrarlo.</span></span>
                </label>

                <label class="sget-check" for="anuncio_destacado">
                    <input type="checkbox" id="anuncio_destacado" name="destacado" value="1" data-sget-campo="destacado"
                        <?= (int)($__anuncioModal['destacado'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <span><strong>Destacado</strong><br>
                        <span class="sget-help">Aparece primero en el carrusel.</span></span>
                </label>
            </div>
        </div>

        <footer class="sget-modal__foot">
            <button type="button" class="sget-btn sget-btn--neutro" data-sget-cerrar>Cancelar</button>
            <button type="submit" class="sget-btn sget-btn--primario">
                <i class="fas fa-floppy-disk"></i> Guardar anuncio
            </button>
        </footer>
    </form>
</div>

<script>
/* Previsualización del archivo elegido: el administrador ve la imagen ANTES de
   subirla, que es cuando más cuesta pillar un error de recorte o de formato. */
/* El botón de DIRECCIÓN abre la portada en una pestaña nueva; sin sesión, lleva
   a la pantalla de acceso con un aviso (los enlaces internos del panel solo
   existen para quien ya entró). */
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-sget-abrir-landing]');
    if (!btn) return;

    var url = <?= json_encode(Config::basePath()) ?> + '/index.php'<?php
        if (!Auth::estaLogueado()) echo ' + "?aviso_portal=1"';
    ?>;

    window.open(url, '_blank', 'noopener');
});

document.addEventListener('change', function (e) {
    var input = e.target.closest('input[type="file"][data-sget-preview]');
    if (!input) return;

    var img  = document.querySelector(input.dataset.sgetPreview);
    var caja = input.closest('[data-campo]')?.querySelector('[data-sget-preview-caja]');
    var nombre = input.closest('[data-campo]')?.querySelector('[data-sget-preview-nombre]');
    var archivo = input.files && input.files[0];
    if (!archivo) return;

    if (img) img.src = URL.createObjectURL(archivo);
    if (caja) caja.style.display = '';
    if (nombre) nombre.textContent = archivo.name + ' · ' + Math.round(archivo.size / 1024) + ' KB';
});
</script>

<?php
/**
 * procesos/anuncio_vista.php
 * -----------------------------------------------------------------------------
 * VISTA PREVIA de un anuncio tal y como se verá en la landing.
 * -----------------------------------------------------------------------------
 * Se carga dentro de un `<iframe>` del modal «Vista previa del anuncio» de
 * `Admin/anuncios.php`, y se puede abrir también en una pestaña aparte.
 *
 * POR QUÉ ES UN ARCHIVO PROPIO Y NO UN FRAGMENTO
 *   La landing real se monta con Tailwind por CDN y con los tokens del tema
 *   (`assets/css/index.css`). Un fragmento suelto dentro del panel se vería con
 *   la tipografía y los colores del panel, que es justo lo que el administrador
 *   quiere comprobar: «¿cómo va a quedar esto en mi portada?».
 *
 * SEGURIDAD
 *   · Solo un Administrador con permiso de anuncios llega hasta aquí.
 *   · Los textos se escapan con `htmlspecialchars` (un título con `<script>`
 *     debe verse escrito en la vista previa, no ejecutarse).
 *   · La imagen se sirve por su ruta pública ya validada en
 *     `AnuncioService::urlImagen()` (que rechaza `..`, `/` y `\`).
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

// Misma condición que el módulo: si no puede administrar anuncios, no puede
// ver la vista previa. Un preview de un borrador no es información sensible,
// pero el endpoint está detrás de la misma puerta que el resto.
Auth::requerirSesion();
if (!Auth::tieneAcceso('anuncios')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Sin permisos para ver anuncios.");
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="font:15px/1.6 system-ui;padding:2rem;color:#64748b">'
       . 'No se indicó qué anuncio previsualizar.</body>';
    exit;
}

$anuncio = AnuncioService::porId($id);
if (!$anuncio) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="font:15px/1.6 system-ui;padding:2rem;color:#64748b">'
       . 'Ese anuncio ya no existe.</body>';
    exit;
}

/* El enlace del botón se decide aquí, igual que en la landing. La vista previa
   muestra el texto real pero NO navega: se intercepta en el marco padre. */
$titulo  = (string) ($anuncio['titulo'] ?? '');
$sub     = (string) ($anuncio['subtitulo'] ?? '');
$desc    = (string) ($anuncio['descripcion'] ?? '');
$boton   = (string) ($anuncio['boton_texto'] ?: 'Más información');
$tono    = (string) ($anuncio['color_tema'] ?? 'azul');
$imagen  = (string) ($anuncio['url_imagen'] ?? '');
$sinImg  = !empty($anuncio['sin_imagen']);
$vigente = (int) ($anuncio['activo'] ?? 0) === 1
    && ($anuncio['fec_inicio'] === null || $anuncio['fec_inicio'] <= date('Y-m-d'))
    && ($anuncio['fec_fin'] === null || $anuncio['fec_fin'] >= date('Y-m-d'));

/* Los mismos tonos que usa el carrusel real (assets/css/index.css), para que la
   vista previa no tenga su propia paleta. */
$tonos = [
    'azul'    => ['#38bdf8', '#7dd3fc'],
    'emerald' => ['#34d399', '#6ee7b7'],
    'ambars'  => ['#fbbf24', '#fcd34d'],
    'morado'  => ['#a78bfa', '#c4b5fd'],
    'rojo'    => ['#fb7185', '#fda4af'],
];
[$fondoBoton, $colorTitulo] = $tonos[$tono] ?? $tonos['azul'];

$e = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vista previa · <?= $e($titulo) ?></title>

<!-- Se reutiliza la hoja REAL de la landing: si el carrusel cambia, la vista
     previa cambia con él, sin una segunda paleta que mantener. -->
<link rel="stylesheet" href="<?= Config::basePath() ?>/assets/css/index.css?v=<?= @filemtime(Config::raiz('assets/css/index.css')) ?: '1' ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    /* El marco no debe verse: en el modal es un «trozo» de la portada, no una
       página con barras de dirección. */
    html, body { margin: 0; padding: 0; background: #020617; }
    body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }

    /* Aviso de que esto NO es la landing real, para que nadie lo confunda con
       una publicación ya hecha. */
    .pv-aviso {
        display: flex; align-items: center; gap: .5rem;
        padding: .5rem .875rem;
        font-size: .6875rem; font-weight: 700;
        background: #0b1220; color: #94a3b8;
        border-bottom: 1px solid rgb(148 163 184 / .2);
    }
    .pv-aviso--off { background: #450a0a; color: #fca5a5; }
</style>
</head>
<body>

<div class="pv-aviso<?= $vigente ? '' : ' pv-aviso--off' ?>">
    <i class="fas <?= $vigente ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
    <?php if ($vigente): ?>
        Así se verá este anuncio en la portada de SGET.
    <?php else: ?>
        Este anuncio <strong>no se está mostrando</strong> en la landing
        <?= (int) ($anuncio['activo'] ?? 0) === 0 ? 'porque está oculto' : 'porque está fuera de su fecha de vigencia' ?>.
        La vista previa muestra igualmente su diseño.
    <?php endif; ?>
</div>

<section class="sget-anuncio-carrusel" style="border-radius:0;border:0;box-shadow:none">
    <article class="sget-anuncio-carrusel__item es-activo" style="min-height:22rem">

        <?php if ($imagen !== '' && !$sinImg): ?>
            <img src="<?= $e($imagen) ?>" alt="<?= $e($titulo) ?>">
        <?php else: ?>
            <div style="position:absolute;inset:0;display:grid;place-items:center;background:#0b1220;color:#475569">
                <i class="fas fa-image" style="font-size:2.5rem"></i>
            </div>
        <?php endif; ?>

        <div class="sget-anuncio-carrusel__velo"></div>

        <div class="sget-anuncio-carrusel__texto">
            <p class="sget-anuncio-carrusel__titulo" style="color:<?= $e($colorTitulo) ?>">
                <?= $e($titulo) ?>
            </p>
            <?php if ($sub !== ''): ?>
                <p class="sget-anuncio-carrusel__sub"><?= $e($sub) ?></p>
            <?php endif; ?>
            <?php if ($desc !== ''): ?>
                <p class="sget-anuncio-carrusel__desc"><?= $e($desc) ?></p>
            <?php endif; ?>
            <span class="sget-anuncio-carrusel__boton"
                  style="background:<?= $e($fondoBoton) ?>;pointer-events:none">
                <?= $e($boton) ?> <i class="fas fa-arrow-right"></i>
            </span>
        </div>
    </article>
</section>

<script>
    /* En el marco de la vista previa el botón no navega: es una maqueta. */
    document.addEventListener('click', function (e) {
        if (e.target.closest('.sget-anuncio-carrusel__boton')) e.preventDefault();
    });
</script>
</body>
</html>
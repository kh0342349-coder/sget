<?php
/**
 * set_language.php
 * -----------------------------------------------------------------------------
 * Cambio de idioma (Español / Inglés) del panel.
 * -----------------------------------------------------------------------------
 * Acepta POST (formulario real de la cabecera, con token anti-CSRF) y, cuando no
 * hay sesión iniciada, también GET con `?idioma=en` para que la portada pública
 * responda por la misma vía que el panel.
 *
 * ANTES: aceptaba el valor de `$_POST['idioma']` sin comprobar nada y devolvía
 * JSON a un `<form>` normal, así que el formulario se descargaba en crudo si el
 * JavaScript de i18n fallaba. Ahora valida la lista blanca y devuelve un 303
 * hacia la página de origen cuando no espera JSON.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

$quiereJson = str_contains(strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')), 'xmlhttprequest')
    || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

$volver = static function (array $datos) use ($quiereJson): void {
    if ($quiereJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }
    $destino = (string)($_POST['volver'] ?? $_GET['volver'] ?? '');
    // Solo rutas internas relativas: nunca un redirector abierto.
    if ($destino === '' || str_contains($destino, '..') || str_starts_with($destino, '//')) {
        $destino = Config::basePath() . '/index.php';
    }
    header('Location: ' . $destino, true, 303);
    exit;
};

$idioma = (string)($_POST['idioma'] ?? $_GET['idioma'] ?? '');

if ($idioma === '') {
    $volver(['ok' => false, 'mensaje' => 'No se indicó el idioma.']);
}

// Lista blanca estricta: nada de lo que venga del cliente se guarda tal cual.
if (!in_array($idioma, ['es', 'en'], true)) {
    $volver(['ok' => false, 'mensaje' => 'Idioma no válido.']);
}

/* El cambio de idioma es un POST, así que va protegido por CSRF igual que el
   resto de acciones que modifican algo. */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !Auth::validarToken((string)($_POST['_token'] ?? ''))) {
    $volver(['ok' => false, 'mensaje' => 'La sesión caducó. Recarga la página e inténtalo de nuevo.']);
}

$_SESSION['sget_idioma'] = $idioma;

$volver(['ok' => true, 'idioma' => $idioma]);
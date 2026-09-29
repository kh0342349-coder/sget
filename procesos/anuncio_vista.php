<?php
/**
 * procesos/anuncio_vista.php
 * -----------------------------------------------------------------------------
 * Contador de vistas del carrusel de anuncios de la landing.
 *
 * POR QUÉ EXISTE
 *   El módulo de anuncios muestra un KPI de "Visualizaciones", pero nada lo
 *   incrementaba: el número se quedaba siempre en 0 y el contador parecía
 *   decorativo. Aquí se suma una vista por cada anuncio que entra de verdad en
 *   pantalla (lo decide assets/js/sget-anuncios.js con un IntersectionObserver).
 *
 * POR QUÉ ES UN ARCHIVO PROPIO Y NO UN `accion` DEL API
 *   api/index.php exige POST + token anti-CSRF, y la landing es pública: no hay
 *   sesión ni formulario al que colgarle la petición. Este endpoint solo suma
 *   un número, no lee ni escribe nada sensible, y no puede devolver datos.
 *
 * REGLAS
 *   · Es público a propósito (lo llama cualquiera que abra la portada) pero no
 *     filtra información: responde siempre 204 con cuerpo vacío.
 *   · La deduplicación por sesión y hora la hace AnuncioService::registrarVista(),
 *     que además no deja que un fallo de base de datos tumbe la landing.
 *   · Sin `anuncio` legible por la vista: si algo falla, es un 204 silencioso.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

header('Content-Type: image/gif');   // un 1x1 transparente: ni error ni descarga
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        // No se cuenta nada que no sea un anuncio vigente y con su imagen en
        // disco: si no se está viendo en la landing, no es una visita.
        $anuncio = AnuncioService::vigentes();
        foreach ($anuncio as $a) {
            if ((int)$a['id_ann'] === $id) {
                AnuncioService::registrarVista($id);
                break;
            }
        }
    }
} catch (Throwable $e) {
    // Una métrica no puede romper la portada.
}

echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

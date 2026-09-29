<?php
/**
 * DESC: Banner por defecto del módulo de anuncios + reparación de imágenes rotas
 *
 * CONTEXTO
 *   La migración 006 insertaba un anuncio de ejemplo cuyo campo `imagen` valía
 *   'portada.svg', pero ese archivo NUNCA se copió a `uploads/anuncios`: solo se
 *   creaba la carpeta (con su .htaccess). Resultado en producción:
 *
 *     · la landing pintaba un rectángulo negro con el texto `alt` en lugar del
 *       banner, es decir el anuncio "existía" pero no se veía nada;
 *     · el módulo de administración mostraba una tarjeta sin foto, sin explicar
 *       por qué, y el contador de "Visibles ahora" decía 1 con la portada vacía.
 *
 *   Además, cualquier anuncio guardado cuyo archivo se borrara a mano de
 *   `uploads/anuncios` quedaba en la base de datos apuntando a la nada: el
 *   carrusel lo seguía contando como visible.
 *
 * QUÉ HACE
 *   1) Copia `img/anuncios/portada.svg` (el banner de marca, ~4 KB) a
 *      `uploads/anuncios/portada.svg` y se asegura el `.htaccess` de bloqueo.
 *   2) Si la tabla `anuncio` está vacía, crea el anuncio de ejemplo con la
 *      imagen YA verificada en disco. Si no está vacía, no toca los datos: son
 *      del administrador.
 *   3) INFORMA de los anuncios cuya imagen no está en el servidor, sin tocar sus
 *      datos. Es preferible avisar a apagar: un `activo = 0` puesto por una
 *      comprobación de disco equivocada (un archivo bloqueado por el antivirus,
 *      un contenedor de permisos) dejaría un anuncio bueno fuera de la portada
 *      sin explicación. El módulo de administración ya muestra el aviso y el
 *      botón para volver a subir la imagen.
 *
 * IDEMPOTENTE: se puede ejecutar tantas veces como haga falta.
 */
declare(strict_types=1);

$migrar = function (): void {
    $log = fn(string $m) => imprimir('      ' . $m);

    $raiz    = dirname(__DIR__);
    $carpeta = $raiz . '/uploads/anuncios';

    /* ================================================================== */
    /* 1) Carpeta, .htaccess y banner por defecto                          */
    /* ================================================================== */
    if (!is_dir($carpeta) && !@mkdir($carpeta, 0755, true)) {
        $log('[!!] No se pudo crear uploads/anuncios: revisa los permisos.');
        return;
    }

    $htaccess = $carpeta . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents(
            $htaccess,
            "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi)$\">\n    Require all denied\n</FilesMatch>\n"
        );
        $log('[OK] .htaccess de bloqueo escrito en uploads/anuncios.');
    } else {
        $log('[--] El .htaccess de bloqueo ya existía.');
    }

    $portada = $carpeta . '/portada.svg';
    $origen  = $raiz . '/img/anuncios/portada.svg';

    if (is_file($portada)) {
        $log('[--] El banner por defecto ya estaba copiado.');
    } elseif (!is_file($origen)) {
        $log('[!!] No existe ' . $origen . ': no se puede crear el banner por defecto.');
    } elseif (!@copy($origen, $portada)) {
        // copy() falla sin avisar por mucho que se le ponga @. Sin este detalle
        // el mensaje decía «no se encontró el archivo», que era mentira: el
        // archivo estaba ahí y lo que no se podía era escribir encima (por
        // ejemplo, si otro proceso lo tenía bloqueado en Windows).
        $log('[!!] No se pudo copiar el banner a ' . $portada . '.');
        $log('     Suele ser un bloqueo del antivirus o del explorador sobre ese');
        $log('     archivo: ciérralo y ejecuta de nuevo `php migraciones/migrar.php`.');
    } else {
        clearstatcache(true, $portada);
        $log('[OK] Banner por defecto copiado a uploads/anuncios/portada.svg.');
    }

    /* ================================================================== */
    /* 2) Anuncio de ejemplo (solo si la tabla está vacía)                 */
    /* ================================================================== */
    if (!is_file($portada)) {
        $log('[--] Sin banner en disco: no se crea el anuncio de ejemplo.');    } elseif ((int) Database::scalar("SELECT COUNT(*) FROM anuncio") === 0) {
        Database::query(
            "INSERT INTO anuncio (titulo, subtitulo, descripcion, imagen, enlace, boton_texto, color_tema, activo, destacado, orden)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, 1)",
            [
                'Bienvenido a SGET',
                'Reserva tu cupo en línea y monitorea tu viaje en tiempo real',
                'Consulta los viajes disponibles, asegura tu puesto y recibe avisos si algo cambia.',
                'portada.svg',
                'Admin/viajes.php',
                'Ver viajes',
                'azul',
            ]
        );
        $log('[OK] Anuncio de ejemplo creado (portada.svg).');
    } else {
        $log('[--] Ya hay anuncios: no se crea el de ejemplo.');
    }

    /* ================================================================== */
    /* 3) Aviso de anuncios con la imagen ausente (NO se modifican)          */
    /* ================================================================== */
    /* OJO: migrar.php solo carga Config y Database, no los servicios, así que
       AnuncioService no está disponible aquí. El filtro de path traversal es
       el MISMO que aplica urlImagen() al servir el archivo. */
    $nombreSeguro = static function (string $archivo): bool {
        $archivo = trim($archivo);
        return $archivo !== ''
            && !str_contains($archivo, '..')
            && !str_contains($archivo, '/')
            && !str_contains($archivo, '\\');
    };

    $huerfanos = [];
    foreach (Database::all("SELECT id_ann, titulo, imagen FROM anuncio") as $a) {
        $archivo = trim((string)($a['imagen'] ?? ''));
        $ruta    = $carpeta . '/' . $archivo;

        if ($nombreSeguro($archivo)) clearstatcache(true, $ruta);

        $roto = !$nombreSeguro($archivo) || !is_file($ruta);
        if ($roto) {
            $huerfanos[] = sprintf('#%d «%s» (%s)',
                (int)$a['id_ann'], (string)$a['titulo'],
                $archivo === '' ? 'sin archivo' : $archivo);
        }
    }

    if (empty($huerfanos)) {
        $log('[OK] Todos los anuncios tienen su imagen en el servidor.');
    } else {
        $log('[!!] ' . count($huerfanos) . ' anuncio(s) apuntan a una imagen que no está en uploads/anuncios:');
        foreach ($huerfanos as $h) $log('       · ' . $h);
        $log('     No se ha tocado ninguno. Sube la imagen desde Admin/anuncios.php y quedará visible.');
    }
};

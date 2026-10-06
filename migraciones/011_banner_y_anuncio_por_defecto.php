<?php
/**
 * migraciones/011_banner_y_anuncio_por_defecto.php
 * DESC: Restaura el banner de la landing y el anuncio de ejemplo
 *
 * -----------------------------------------------------------------------------
 * QUÉ ARREGLA
 * -----------------------------------------------------------------------------
 * El módulo de anuncios estaba VACÍO y el banner no se veía en la landing:
 *
 *   · `uploads/anuncios/portada.svg` no existía. Ese archivo es el único que se
 *     puede copiar con SQL, así que la migración 007 lo copia en disco… pero si
 *     en ese momento la copia falló (permisos, carpeta bloqueada), la 007 quedó
 *     REGISTRADA como aplicada y nunca se volvió a intentar. El resultado era
 *     permanente: anuncio sin imagen = rectángulo negro en la portada.
 *
 *   · Sin el banner, la propia 007 tampoco creaba el anuncio de ejemplo
 *     (`si (!is_file($portada)) { no se crea }`). Por eso la tabla `anuncio`
 *     quedó sin ninguna fila y el módulo mostraba el estado vacío.
 *
 * ESTA migración lo deja como debe estar:
 *   1. Copia el banner si falta.
 *   2. Crea el anuncio de ejemplo si la tabla sigue vacía.
 *   3. Avisa de anuncios cuya imagen no existe (sin borrarlos: la decisión es
 *      del administrador).
 *
 * IDEMPOTENCIA
 *   Se puede aplicar varias veces sin efectos duplicados.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

$migrar = static function (): void {
    $log = static function (string $texto): void {
        echo "   · $texto\n";
    };

    $raiz    = dirname(__DIR__);
    $carpeta = $raiz . '/uploads/anuncios';
    $portada = $carpeta . '/portada.svg';
    $origen  = $raiz . '/img/anuncios/portada.svg';

    /* ---------------------------------------------------------------- */
    /* 1) Carpeta y bloqueo de ejecución                                */
    /* ---------------------------------------------------------------- */
    if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
        $log('[!!] No se pudo crear uploads/anuncios: revisa los permisos.');
        return;
    }

    /* Un `.htaccess` en la carpeta de subidas evita que un archivo preparado
       por error se ejecute si alguien lo pide por URL. */
    $htaccess = $carpeta . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, implode("\n", [
            '# SGET · carpeta de imágenes',
            '# Nada de esto debe ejecutarse nunca.',
            'php_flag engine off',
            'Options -ExecCGI -Indexes',
            '<FilesMatch "\.(php|phtml|phar|php3|php4|php5|php7|php8)$">',
            '  Require all denied',
            '</FilesMatch>',
            '',
        ]));
        $log('Bloqueo de ejecución añadido en uploads/anuncios/.htaccess');
    }

    /* ---------------------------------------------------------------- */
    /* 2) Banner por defecto                                             */
    /* ---------------------------------------------------------------- */
    clearstatcache(true, $portada);

    if (is_file($portada)) {
        $log('El banner por defecto ya estaba en su sitio.');
    } elseif (!is_file($origen)) {
        $log('[!!] No existe img/anuncios/portada.svg: no se puede restaurar el banner.');
        return;
    } elseif (@copy($origen, $portada)) {
        clearstatcache(true, $portada);
        $log(is_file($portada)
            ? 'Banner por defecto restaurado en uploads/anuncios/portada.svg.'
            : '[!!] La copia del banner no se pudo verificar.');
    } else {
        $log('[!!] No se pudo copiar el banner a uploads/anuncios/.');
    }

    /* ---------------------------------------------------------------- */
    /* 3) Anuncio de ejemplo                                            */
    /* ---------------------------------------------------------------- */
    $total = (int) Database::scalar('SELECT COUNT(*) FROM anuncio');

    if ($total > 0) {
        $log('Ya hay anuncios: no se crea el de ejemplo.');
    } elseif (!is_file($portada)) {
        $log('[--] Sin banner en disco: no se crea el anuncio de ejemplo.');
    } else {
        Database::query(
            "INSERT INTO anuncio
                (titulo, subtitulo, descripcion, imagen, enlace, boton_texto, color_tema, activo, destacado, orden)
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
        $log('Anuncio de ejemplo creado (portada.svg).');
    }

    /* ---------------------------------------------------------------- */
    /* 4) Aviso de imágenes ausentes (NO se borran)                     */
    /* ---------------------------------------------------------------- */
    $nombreSeguro = static function (?string $archivo): bool {
        $archivo = trim((string)$archivo);
        return $archivo !== ''
            && !str_contains($archivo, '..')
            && !str_contains($archivo, '/')
            && !str_contains($archivo, '\\');
    };

    $huerfanos = [];
    foreach (Database::all('SELECT id_ann, titulo, imagen FROM anuncio') as $a) {
        if (!$nombreSeguro($a['imagen'] ?? null)) {
            continue;
        }
        if (!is_file($carpeta . '/' . $a['imagen'])) {
            $huerfanos[] = sprintf('#%d %s', (int)$a['id_ann'], (string)$a['titulo']);
        }
    }

    if ($huerfanos !== []) {
        $log('[!!] Anuncios con la imagen ausente (corrígelos en Anuncios.php): '
            . implode(', ', $huerfanos));
    }
};

<?php
/**
 * services/AnuncioService.php
 * -----------------------------------------------------------------------------
 * MÓDULO: ANUNCIOS DE LA LANDING (banners y avisos promocionales)
 * -----------------------------------------------------------------------------
 * QUÉ RESUELVE
 *   La landing mostraba contenido fijo escrito a mano en el HTML. Para
 *   anunciar una promoción, una novedad o un aviso de temporada había que
 *   editar el código y subir la página. Con este módulo el administrador sube
 *   una imagen, escribe los textos, decide el enlace y la fecha de vigencia, y
 *   el carrusel de la landing se actualiza solo.
 *
 * REGLAS
 *   · Solo imágenes (jpg, png, webp, gif, svg). El SVG se acepta porque las
 *     piezas de marca son vectoriales, pero se sube con el mismo validación de
 *     tamaño que el resto.
 *   · Se guarda en `uploads/anuncios`, fuera de la raíz de código, con un
 *     `.htaccess` que bloquea la ejecución de scripts.
 *   · Un anuncio solo se publica si: activo = 1, y la fecha actual está dentro
 *     de su ventana de publicación (fec_inicio / fec_fin pueden venir vacíos).
 *   · Un anuncio caduca SOLO, nunca se borra el histórico: el admin decide.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class AnuncioService
{
    public const CARPETA       = 'uploads/anuncios';
    public const MAX_BYTES     = 3 * 1024 * 1024;   // 3 MB
    public const EXTENSIONES    = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    public const IMAGEN_ANCHO  = 1600;
    public const IMAGEN_ALTO   = 700;

    public const COLORES = [
        'azul'    => 'from-sky-500/20 via-sky-500/10',
        'emerald' => 'from-emerald-500/20 via-emerald-500/10',
        'ambars'  => 'from-amber-500/20 via-amber-500/10',
        'morado'  => 'from-violet-500/20 via-violet-500/10',
        'rojo'    => 'from-rose-500/20 via-rose-500/10',
    ];

    /* ================================================================== */
    /* Rutas                                                               */
    /* ================================================================== */

    public static function urlBase(): string
    {
        return Config::raiz(self::CARPETA);
    }

    /** URL pública de un anuncio (sirve la imagen y evita el path traversal). */
    public static function urlImagen(?string $archivo): ?string
    {
        $archivo = self::archivoSeguro($archivo);
        if ($archivo === null) return null;

        $base = Config::basePath();           // '' o '/sget'
        return $base . '/' . self::CARPETA . '/' . rawurlencode($archivo);
    }

    /** Un nombre de archivo con '../' o una ruta absoluta no se sirve jamás. */
    private static function archivoSeguro(?string $archivo): ?string
    {
        $archivo = trim((string)$archivo);
        if ($archivo === '') return null;
        if (str_contains($archivo, '..') || str_contains($archivo, '/') || str_contains($archivo, '\\')) {
            return null;
        }
        return $archivo;
    }

    /**
     * ¿La imagen del anuncio está realmente en el disco?
     *
     * QUÉ ES Y QUÉ NO ES
     *   Es un DIAGNÓSTICO para el módulo de administración, no un filtro de la
     *   landing. A propósito NO se usa en `vigentes()`: si el sistema de
     *   archivos devuelve un falso negativo (un archivo bloqueado por el
     *   antivirus, un contenedor de permisos, la caché de stat de PHP), el
     *   carrusel se quedaría vacío sin avisar, que es justo el fallo que este
     *   módulo viene a arreglar. Es preferible un banner con la foto rota
     *   (y el aviso en el panel) que una portada vacía sin explicación.
     *
     * POR QUÉ EXISTE
     *   Una fila puede apuntar a un archivo que ya no está (se borró a mano de
     *   `uploads/anuncios`, se restauró una base de datos antigua, o el anuncio
     *   de ejemplo de la migración se creó apuntando a un `portada.svg` que
     *   nunca estuvo copiado). En ese caso la landing pintaba un marco negro
     *   con el texto `alt`: el anuncio "existía" pero no se veía nada.
     *   Con esta comprobación el módulo lo dice con palabras claras.
     */
    public static function imagenExiste(?string $archivo): bool
    {
        $seguro = self::archivoSeguro($archivo);
        if ($seguro === null) return false;

        $ruta = self::urlBase() . '/' . $seguro;
        // La caché de stat de PHP guarda los negativos: un archivo recién
        // escrito se daba por inexistente durante el resto de la petición.
        clearstatcache(true, $ruta);

        return is_file($ruta);
    }

    /* ================================================================== */
    /* Lectura                                                             */
    /* ================================================================== */

    /**
     * Anuncios VIGENTES, ordenados para el carrusel.
     *
     * Se fía de la base de datos y NO comprueba el disco: si `imagenExiste()`
     * mintiera por un bloqueo o un problema de permisos, filtrar aquí dejaría
     * la portada vacía sin decir por qué. El diagnóstico de imágenes ausentes
     * vive en el módulo de administración, que es donde se puede arreglar.
     *
     * @param bool $soloDestacados  limita a los destacados
     */
    public static function vigentes(bool $soloDestacados = false): array
    {
        $sql = "SELECT * FROM anuncio
                 WHERE activo = 1
                   AND (fec_inicio IS NULL OR fec_inicio <= CURDATE())
                   AND (fec_fin    IS NULL OR fec_fin    >= CURDATE())";
        if ($soloDestacados) $sql .= ' AND destacado = 1';

        $filas = Database::all($sql . ' ORDER BY destacado DESC, orden ASC, id_ann DESC');

        foreach ($filas as &$f) {
            $f['url_imagen'] = self::urlImagen($f['imagen'] ?? null);
            $f['enlace']     = self::enlaceSeguro($f['enlace'] ?? null);
        }
        unset($f);

        return $filas;
    }

    /** Todos los anuncios para el módulo de administración. */
    public static function todos(): array
    {
        $filas = Database::all(
            "SELECT a.*, u.nom_usu AS autor,
                    (a.fec_inicio IS NULL OR a.fec_inicio <= CURDATE())
                      AND (a.fec_fin IS NULL OR a.fec_fin >= CURDATE()) AS vigente
               FROM anuncio a
               LEFT JOIN usuario u ON u.id_usu = a.creado_por
              ORDER BY a.activo DESC, a.destacado DESC, a.orden ASC, a.id_ann DESC"
        );

        foreach ($filas as &$f) {
            $f['url_imagen'] = self::urlImagen($f['imagen'] ?? null);
            $f['sin_imagen'] = !self::imagenExiste($f['imagen'] ?? null);
        }
        unset($f);

        return $filas;
    }

    public static function porId(int $id): ?array
    {
        $f = Database::one("SELECT * FROM anuncio WHERE id_ann = ?", [$id]);
        if (!$f) return null;
        $f['url_imagen'] = self::urlImagen($f['imagen'] ?? null);
        $f['sin_imagen'] = !self::imagenExiste($f['imagen'] ?? null);
        return $f;
    }

    /**
     * Un enlace solo puede ser interno o una URL http(s): nada de `javascript:`
     * ni `data:` (el anuncio se pinta sin escapar en algunos puntos).
     */
    public static function enlaceSeguro(?string $enlace): ?string
    {
        $enlace = trim((string)$enlace);
        if ($enlace === '') return null;

        if (preg_match('~^(https?://|/)~i', $enlace)) return $enlace;
        if (preg_match('~^[a-z0-9_\-/]+\.php(\?[\w=&%.\-]*)?$~i', $enlace)) {
            return $enlace;
        }
        return null;
    }

    /* ================================================================== */
    /* Validación                                                          */
    /* ================================================================== */

    /**
     * @return array{ok:bool, mensaje:string, errores:array}
     */
    public static function validar(array $post, array $archivo = null): array
    {
        $errores = [];

        $titulo = trim((string)($post['titulo'] ?? ''));
        if ($titulo === '') {
            $errores['titulo'] = 'Escribe el título del anuncio.';
        } elseif (mb_strlen($titulo) > 150) {
            $errores['titulo'] = 'El título no puede pasar de 150 caracteres.';
        }

        if (mb_strlen((string)($post['subtitulo'] ?? '')) > 255) {
            $errores['subtitulo'] = 'El subtítulo no puede pasar de 255 caracteres.';
        }

        if (trim((string)($post['enlace'] ?? '')) !== '' && self::enlaceSeguro((string)$post['enlace']) === null) {
            $errores['enlace'] = 'El enlace debe ser una URL (http/https) o un módulo interno (viajes.php).';
        }

        $color = (string)($post['color_tema'] ?? 'azul');
        if (!isset(self::COLORES[$color])) {
            $errores['color_tema'] = 'El tema de color no es válido.';
        }

        $inicio = self::fechaOpcional($post['fec_inicio'] ?? null);
        $fin    = self::fechaOpcional($post['fec_fin'] ?? null);
        if ($inicio && $fin && $inicio > $fin) {
            $errores['fec_fin'] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }

        if ($archivo && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $errores += self::validarImagen($archivo);
        }

        return [
            'ok'      => !$errores,
            'errores' => $errores,
            'mensaje' => $errores ? 'Revisa los campos marcados.' : 'Anuncio válido.',
        ];
    }

    /** @return array<string,string> mapa campo => mensaje de error */
    private static function validarImagen(array $archivo): array
    {
        $errores = [];

        if (($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errores['imagen'] = match ((int)$archivo['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño permitido.',
                UPLOAD_ERR_NO_FILE   => 'Selecciona una imagen.',
                UPLOAD_ERR_PARTIAL    => 'La imagen se subió incompleta; inténtalo de nuevo.',
                default              => 'No se pudo subir la imagen.',
            };
            return $errores;
        }

        $tamano = (int)($archivo['size'] ?? 0);
        if ($tamano <= 0) {
            $errores['imagen'] = 'El archivo está vacío.';
        } elseif ($tamano > self::MAX_BYTES) {
            $errores['imagen'] = 'La imagen supera los ' . (self::MAX_BYTES / 1048576) . ' MB.';
        }

        $extension = strtolower(pathinfo((string)($archivo['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONES, true)) {
            $errores['imagen'] = 'Formatos permitidos: ' . strtoupper(implode(', ', self::EXTENSIONES)) . '.';
        }

        // Se comprueba el tipo real declarado por el servidor, no solo la extensión.
        $info = @getimagesize((string)$archivo['tmp_name']);
        $svg  = $extension === 'svg';
        if (!$svg && $info === false) {
            $errores['imagen'] = 'El archivo no es una imagen válida.';
        }
        if ($info !== false && ($info[0] > self::IMAGEN_ANCHO || $info[1] > self::IMAGEN_ALTO)) {
            $errores['imagen'] = sprintf(
                'La imagen mide %dx%d px. El máximo es %dx%d px.',
                $info[0], $info[1], self::IMAGEN_ANCHO, self::IMAGEN_ALTO
            );
        }

        // getimagesize() NO sabe leer un SVG, así que un .svg podría pasar la
        // validación y luego no pintarse nunca. Dos fallos distintos, y el
        // segundo era el que dejaba el banner por defecto como un rectángulo
        // negro con el texto alt en la portada:
        //   a) XML mal formado -> el navegador lo ignora en silencio.
        //   b) SVG con script   -> se sirve con 200 y puede ejecutarse si alguien
        //      abre la URL directamente.
        if ($svg) {
            $errores += self::validarSvg((string)$archivo['tmp_name'], $tamano);
        }

        return $errores;
    }

    /** @return array<string,string> */
    private static function validarSvg(string $ruta, int $tamano): array
    {
        $errores = [];

        $contenido = @file_get_contents($ruta, false, null, 0, 512 * 1024);
        if ($contenido === false || trim($contenido) === '') {
            return ['imagen' => 'El archivo SVG está vacío o no se puede leer.'];
        }
        if ($tamano > 256 * 1024) {
            $errores['imagen'] = 'Un SVG no debería pesar más de 256 KB.';
        }

        // (a) XML bien formado
        $previo = libxml_use_internal_errors(true);
        $xml    = simplexml_load_string($contenido);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        if ($xml === false) {
            $errores['imagen'] = 'El SVG está mal formado y el navegador no lo dibujaría. '
                               . 'Ojo: un comentario XML no puede llevar dos guiones seguidos (--).';
            return $errores;
        }

        // (b) sin código ejecutable
        if (preg_match('~<\s*script|on[a-z]+\s*=|javascript\s*:~i', $contenido)) {
            $errores['imagen'] = 'El SVG contiene código (script). Sube la imagen en PNG o JPG.';
        }

        return $errores;
    }

    private static function fechaOpcional($v): ?string
    {
        $v = trim((string)$v);
        if ($v === '') return null;
        $ts = strtotime($v);
        if ($ts === false) return null;
        return date('Y-m-d', $ts);
    }

    /* ================================================================== */
    /* Escritura                                                           */
    /* ================================================================== */

    /**
     * Crea o actualiza un anuncio. Si el POST trae imagen, la sube; si no, en
     * una edición se conserva la anterior.
     *
     * @return array{ok:bool, mensaje:string, id:int, errores:array}
     */
    public static function guardar(array $post, ?array $archivo = null): array
    {
        $id = (int)($post['id_ann'] ?? 0);
        $previo = $id > 0 ? self::porId($id) : null;

        $v = self::validar($post, $archivo);
        if (!$v['ok']) {
            return ['ok' => false, 'mensaje' => $v['mensaje'], 'id' => $id, 'errores' => $v['errores']];
        }

        $imagen = (string)($previo['imagen'] ?? '');
        $nuevaImagen = null;

        if ($archivo && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $nuevaImagen = self::guardarImagen($archivo);
            if ($nuevaImagen === null) {
                return ['ok' => false, 'id' => $id, 'errores' => ['imagen' => 'No se pudo guardar la imagen en el servidor.'],
                        'mensaje' => 'No se pudo guardar la imagen.'];
            }
            $imagen = $nuevaImagen;
        }

        if ($imagen === '') {
            return ['ok' => false, 'id' => $id, 'errores' => ['imagen' => 'El anuncio necesita una imagen.'],
                    'mensaje' => 'El anuncio necesita una imagen.'];
        }
        // OJO: aquí NO se bloquea el guardado si `imagenExiste()` dice que no.
        // Perder un anuncio ya escrito porque un stat falló sería peor que
        // guardarlo con la foto pendiente; el módulo de administración avisa de
        // los que tienen la imagen ausente, que es donde se arregla.

        $datos = [
            trim((string)$post['titulo']),
            trim((string)($post['subtitulo'] ?? '')) !== '' ? trim((string)$post['subtitulo']) : null,
            trim((string)($post['descripcion'] ?? '')) !== '' ? trim((string)$post['descripcion']) : null,
            $imagen,
            self::enlaceSeguro($post['enlace'] ?? null),
            trim((string)($post['boton_texto'] ?? '')) !== '' ? trim((string)$post['boton_texto']) : null,
            (string)($post['color_tema'] ?? 'azul'),
            isset($post['activo']) ? 1 : 0,
            isset($post['destacado']) ? 1 : 0,
            self::fechaOpcional($post['fec_inicio'] ?? null),
            self::fechaOpcional($post['fec_fin'] ?? null),
            (int)($post['orden'] ?? 0),
        ];

        try {
            if ($id > 0 && $previo) {
                Database::query(
                    "UPDATE anuncio SET
                        titulo = ?, subtitulo = ?, descripcion = ?, imagen = ?, enlace = ?,
                        boton_texto = ?, color_tema = ?, activo = ?, destacado = ?,
                        fec_inicio = ?, fec_fin = ?, orden = ?
                      WHERE id_ann = ?",
                    array_merge($datos, [$id])
                );
            } else {
                $id = Database::insert(
                    "INSERT INTO anuncio
                        (titulo, subtitulo, descripcion, imagen, enlace, boton_texto, color_tema,
                         activo, destacado, fec_inicio, fec_fin, orden, creado_por)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    array_merge($datos, [Auth::id()])
                );
            }
        } catch (Throwable $e) {
            error_log('[SGET][AnuncioService::guardar] ' . $e->getMessage());
            return ['ok' => false, 'id' => $id, 'errores' => [], 'mensaje' => 'No se pudo guardar el anuncio.'];
        }

        // La imagen anterior se borra SOLO si el anuncio se guardó bien: si el
        // guardado falla, el anuncio sigue teniendo la que tenía.
        if ($nuevaImagen !== null && $previo && !empty($previo['imagen']) && $previo['imagen'] !== $nuevaImagen) {
            self::eliminarImagen((string)$previo['imagen']);
        }

        Logger::registrar(Database::pdo(), $id > 0 && $previo ? 'EDITAR_ANUNCIO' : 'CREAR_ANUNCIO',
            sprintf('Anuncio #%d «%s» guardado por %s.', $id, $datos[0], Auth::nombre()));

        return ['ok' => true, 'id' => $id, 'errores' => [],
                'mensaje' => $previo ? 'Anuncio actualizado correctamente.' : 'Anuncio creado correctamente.'];
    }

    /** Activa o desactiva sin pasar por el formulario completo. */
    public static function alternarEstado(int $id): array
    {
        $a = self::porId($id);
        if (!$a) return ['ok' => false, 'mensaje' => 'El anuncio no existe.'];

        $nuevo = (int)$a['activo'] === 1 ? 0 : 1;
        Database::query("UPDATE anuncio SET activo = ? WHERE id_ann = ?", [$nuevo, $id]);

        Logger::registrar(Database::pdo(), 'ALTERNAR_ANUNCIO',
            sprintf('Anuncio #%d «%s» %s por %s.', $id, $a['titulo'], $nuevo ? 'activado' : 'desactivado', Auth::nombre()));

        return ['ok' => true, 'mensaje' => 'Anuncio ' . ($nuevo ? 'publicado' : 'oculto') . ' en la landing.'];
    }

    /** Alterna el destacado (aparece primero en el carrusel). */
    public static function alternarDestacado(int $id): array
    {
        $a = self::porId($id);
        if (!$a) return ['ok' => false, 'mensaje' => 'El anuncio no existe.'];

        $nuevo = (int)$a['destacado'] === 1 ? 0 : 1;
        Database::query("UPDATE anuncio SET destacado = ? WHERE id_ann = ?", [$nuevo, $id]);

        return ['ok' => true, 'mensaje' => $nuevo ? 'Anuncio destacado.' : 'Anuncio sin destacar.'];
    }

    /** Elimina el anuncio y su imagen del disco. */
    public static function eliminar(int $id): array
    {
        $a = self::porId($id);
        if (!$a) return ['ok' => false, 'mensaje' => 'El anuncio no existe.'];

        try {
            Database::query("DELETE FROM anuncio WHERE id_ann = ?", [$id]);
        } catch (Throwable $e) {
            error_log('[SGET][AnuncioService::eliminar] ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo eliminar el anuncio.'];
        }

        if (!empty($a['imagen'])) self::eliminarImagen((string)$a['imagen']);

        Logger::registrar(Database::pdo(), 'ELIMINAR_ANUNCIO',
            sprintf('Anuncio #%d «%s» eliminado por %s.', $id, $a['titulo'], Auth::nombre()));

        return ['ok' => true, 'mensaje' => 'Anuncio eliminado de la landing.'];
    }

    /** Sube la imagen y devuelve el nombre generado. */
    private static function guardarImagen(array $archivo): ?string
    {
        $extension = strtolower(pathinfo((string)$archivo['name'], PATHINFO_EXTENSION));
        $nombre    = sprintf('anuncio_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(4)), $extension);

        $destino = self::urlBase() . '/' . $nombre;
        if (!is_dir(self::urlBase())) {
            @mkdir(self::urlBase(), 0755, true);
        }

        $movido = is_uploaded_file((string)$archivo['tmp_name'])
            ? @move_uploaded_file((string)$archivo['tmp_name'], $destino)
            : @rename((string)$archivo['tmp_name'], $destino);

        return $movido ? $nombre : null;
    }

    /** Borra la imagen del disco si existe. */
    public static function eliminarImagen(string $archivo): void
    {
        $seguro = self::archivoSeguro($archivo);
        if ($seguro === null) return;

        $ruta = self::urlBase() . '/' . $seguro;
        if (is_file($ruta)) @unlink($ruta);
    }

    /* ================================================================== */
    /* Métricas                                                            */
    /* ================================================================== */

    public static function resumen(): array
    {
        $total     = (int) Database::scalar("SELECT COUNT(*) FROM anuncio");
        $sinImagen = 0;
        foreach (Database::all("SELECT imagen FROM anuncio") as $f) {
            if (!self::imagenExiste($f['imagen'] ?? null)) $sinImagen++;
        }

        return [
            'total'      => $total,
            'activos'    => (int) Database::scalar("SELECT COUNT(*) FROM anuncio WHERE activo = 1"),
            'vigentes'   => (int) Database::scalar(
                "SELECT COUNT(*) FROM anuncio
                  WHERE activo = 1
                    AND (fec_inicio IS NULL OR fec_inicio <= CURDATE())
                    AND (fec_fin IS NULL OR fec_fin >= CURDATE())"),
            'destacados' => (int) Database::scalar("SELECT COUNT(*) FROM anuncio WHERE destacado = 1"),
            'vistas'     => (int) Database::scalar("SELECT COALESCE(SUM(veces_vista), 0) FROM anuncio"),
            // Anuncios que apuntan a un archivo que ya no está en el disco: son
            // los que el administrador juraría haber "subido" y no aparecen.
            'sin_imagen' => $sinImagen,
        ];
    }

    /**
     * Registra una vista (para saber qué anuncio funciona).
     *
     * Se descuenta una vista por anuncio y por hora de sesión: recargar la
     * portada cinco veces no es "cinco personas vieron el anuncio", y un
     * contador inflado hace que el módulo parezca tomado de datos inventados.
     * Nunca puede romper la landing: es una métrica.
     */
    public static function registrarVista(int $id): void
    {
        if ($id <= 0) return;

        $vistas = $_SESSION['sget_vistas_anuncio'] ?? [];
        if (!is_array($vistas)) $vistas = [];
        $clave = $id . ':' . date('YmdH');
        if (isset($vistas[$clave])) return;

        $vistas[$clave] = true;
        // La sesión no crece sin límite: se conservan solo las últimas 24 horas.
        if (count($vistas) > 48) $vistas = array_slice($vistas, -48, null, true);
        $_SESSION['sget_vistas_anuncio'] = $vistas;

        try {
            Database::query("UPDATE anuncio SET veces_vista = veces_vista + 1 WHERE id_ann = ?", [$id]);
        } catch (Throwable $e) {
            // Una métrica no puede romper la landing.
        }
    }
}

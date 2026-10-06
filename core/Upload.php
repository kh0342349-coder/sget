<?php
/**
 * core/Upload.php
 * -----------------------------------------------------------------------------
 * VALIDACIÓN DE IMÁGENES SUBIDAS
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   Antes cada módulo repetía su propio chequeo y solo miraba la EXTENSIÓN del
 *   nombre (`RutaService::guardarImagen`). Eso deja pasar todo lo que importa:
 *   un `.jpg` que en realidad es un `.php`, un SVG con <script>, una imagen de
 *   40 MB o de 30 000 px de ancho que tumba el navegador de todos los que la
 *   ven. La extensión la elige quien sube el archivo.
 *
 *   Aquí vive UNA sola validación, y todo el sistema la usa:
 *     1. error de subida y tamaño en disco;
 *     2. extensión dentro de lista blanca;
 *     3. `finfo` (tipo REAL detectado por contenido, no por nombre);
 *     4. `getimagesize()` — la imagen se puede decodificar y NO es políglota;
 *     5. dimensiones dentro de los límites;
 *     6. nombre generado por el servidor (nunca el del usuario).
 *
 *   `AnuncioService` ya hacía esto bien; `RutaService` no. Ahora los dos usan
 *   la misma clase y no pueden divergir.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/Config.php';

final class Upload
{
    /** Formatos admitidos por defecto (Config::IMG_EXTENSIONES). */
    private array $extensiones;

    private int $maxBytes;
    private int $maxAncho;
    private int $maxAlto;
    private int $minAncho;
    private int $minAlto;

    public function __construct(
        ?array $extensiones = null,
        int $maxBytes = 0,
        int $maxAncho = 0,
        int $maxAlto = 0
    ) {
        $this->extensiones = $extensiones ?: Config::IMG_EXTENSIONES;
        $this->maxBytes   = $maxBytes   > 0 ? $maxBytes   : Config::IMG_MAX_BYTES;
        $this->maxAncho   = $maxAncho   > 0 ? $maxAncho   : Config::IMG_MAX_ANCHO;
        $this->maxAlto    = $maxAlto    > 0 ? $maxAlto    : Config::IMG_MAX_ALTO;
        $this->minAncho   = Config::IMG_MIN_ANCHO;
        $this->minAlto    = Config::IMG_MIN_ALTO;
    }

    /** Constructor con los valores por defecto de `Config`. */
    public static function imagenes(): self
    {
        return new self();
    }

    /**
     * Valida un `$_FILES` y devuelve un nombre de archivo SEGURO.
     *
     * @param  array  $archivo   una entrada de `$_FILES`
     * @param  string $carpeta   ruta ABSOLUTA de destino (ya creada)
     * @param  string $prefijo   prefijo del nombre generado (p. ej. `ruta`)
     * @return array{ok:bool, nombre?:string, error?:string}
     */
    public function guardar(array $archivo, string $carpeta, string $prefijo = 'img'): array
    {
        $errores = $this->errores($archivo);
        if ($errores !== []) {
            return ['ok' => false, 'error' => reset($errores)];
        }

        $extension = strtolower(pathinfo((string)$archivo['name'], PATHINFO_EXTENSION));

        if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            return ['ok' => false, 'error' => 'No se pudo preparar la carpeta de imágenes en el servidor.'];
        }

        // Nombre generado por el servidor: nada del usuario sobrevive en la ruta.
        $nombre = $prefijo . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $destino = $carpeta . '/' . $nombre;

        // `basename()` neutraliza cualquier intento de ruta relativa en el nombre.
        if (!@move_uploaded_file((string)$archivo['tmp_name'], $destino)) {
            return ['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.'];
        }

        @chmod($destino, 0644);
        return ['ok' => true, 'nombre' => basename($destino)];
    }

    /**
     * Comprueba la subida SIN escribir nada. Devuelve un mapa campo => error.
     *
     * @return array<string,string>
     */
    public function errores(array $archivo): array
    {
        $errores = [];

        $code = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code !== UPLOAD_ERR_OK) {
            $errores['imagen'] = match ($code) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño permitido por el servidor.',
                UPLOAD_ERR_PARTIAL                        => 'La imagen se subió incompleta; inténtalo de nuevo.',
                UPLOAD_ERR_NO_FILE                        => 'Selecciona una imagen.',
                default                                   => 'No se pudo subir la imagen.',
            };
            return $errores;
        }

        $tmp = (string)($archivo['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $errores['imagen'] = 'La subida no es válida.';
            return $errores;
        }

        $extension = strtolower(pathinfo((string)($archivo['name'] ?? ''), PATHINFO_EXTENSION));
        $tamano    = (int)filesize($tmp);

        if ($tamano <= 0) {
            $errores['imagen'] = 'El archivo está vacío.';
        } elseif ($tamano > $this->maxBytes) {
            $errores['imagen'] = sprintf('La imagen supera el máximo de %d MB.', intdiv($this->maxBytes, 1048576));
        }

        if (!in_array($extension, $this->extensiones, true)) {
            $errores['imagen'] = 'Formatos permitidos: ' . strtoupper(implode(', ', $this->extensiones)) . '.';
            // Sin una extensión admitida no tiene sentido seguir: el resto de
            // comprobaciones se harían contra un formato que ya se descartó.
            return $errores;
        }

        // 1) Tipo REAL detectado por contenido. La extensión la elige el
        //    atacante; el magic number no.
        $mime = $this->mimeReal($tmp);
        $permitidos = Config::IMG_MIMES[$extension] ?? [];
        if ($mime === '' || !in_array($mime, $permitidos, true)) {
            $errores['imagen'] = sprintf(
                'El contenido del archivo (%s) no corresponde a un %s válido.',
                $mime !== '' ? $mime : 'tipo desconocido',
                strtoupper($extension)
            );
            return $errores;
        }

        // 2) La imagen se decodifica de verdad y tenemos sus dimensiones.
        $info = @getimagesize($tmp);
        if ($info === false) {
            $errores['imagen'] = 'El archivo no es una imagen válida o está dañada.';
            return $errores;
        }

        [$ancho, $alto] = [(int)$info[0], (int)$info[1]];

        if ($ancho > $this->maxAncho || $alto > $this->maxAlto) {
            $errores['imagen'] = sprintf(
                'La imagen mide %dx%d px. El máximo es %dx%d px.',
                $ancho, $alto, $this->maxAncho, $this->maxAlto
            );
        }
        if ($ancho < $this->minAncho || $alto < $this->minAlto) {
            $errores['imagen'] = sprintf(
                'La imagen es demasiado pequeña (%dx%d px). El mínimo es %dx%d px.',
                $ancho, $alto, $this->minAncho, $this->minAlto
            );
        }

        // 3) Una imagen políglota (un JPEG con un PHP pegado detrás) sigue
        //    siendo un JPEG para `getimagesize`, así que se revisa la COLA: si
        //    aparecen marcas de otro lenguaje antes del final, se rechaza.
        if ($this->parecePoliglotas($tmp)) {
            $errores['imagen'] = 'El archivo contiene código incrustado y fue rechazado.';
        }

        return $errores;
    }

    /** Tipo MIME detectado por contenido. Requiere ext/fileinfo. */
    private function mimeReal(string $ruta): string
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $ruta);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        // Respaldo si la extensión fileinfo no está disponible: `getimagesize`
        // también deduce el tipo de la cabecera.
        $info = @getimagesize($ruta);
        if ($info !== false && isset($info['mime']) && is_string($info['mime'])) {
            return strtolower($info['mime']);
        }

        return '';
    }

    /**
     * Detecta una imagen a la que le han pegado código ejecutable.
     * Solo mira la cola del archivo: es donde se esconde el payload.
     */
    private function parecePoliglotas(string $ruta): bool
    {
        $tamano = (int)filesize($ruta);
        if ($tamano <= 512) {
            return false;
        }

        $manejador = @fopen($ruta, 'rb');
        if ($manejador === false) {
            return false;
        }

        $cola = min(4096, $tamano);
        fseek($manejador, -$cola, SEEK_END);
        $trozo = (string)fread($manejador, $cola);
        fclose($manejador);

        // Firmas de archivos ejecutables / de plantilla.
        return (bool)preg_match('/<\?php|<\?=|<script[\s>]|%!PS-Adobe|<%\s*@/i', $trozo);
    }

    /** Borra un archivopreviousdentro de una carpeta, sin salir de ella. */
    public static function borrar(string $carpeta, ?string $nombre): void
    {
        $nombre = basename((string)$nombre);
        if ($nombre === '' || $nombre === '.' || $nombre === '..') {
            return;
        }
        $ruta = rtrim($carpeta, '/\\') . '/' . $nombre;
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
}

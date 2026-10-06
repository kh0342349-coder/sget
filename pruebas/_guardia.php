<?php
/**
 * pruebas/_guardia.php
 * -----------------------------------------------------------------------------
 * PORTAÓN DE LAS PRUEBAS
 * -----------------------------------------------------------------------------
 * En esta carpeta hay scripts que INSERTAN, ACTUALIZAN, BORRAN, abren sesiones
 * falsas (`$_SESSION['id_usu'] = 1`) y ejecutan casos de prueba. Con la carpeta
 * dentro del webroot, cualquiera podía alcanzar
 * `/sget/pruebas/_reserva_test.php` desde el navegador y modificar la base de
 * datos de producción con una sola petición GET.
 *
 * Este archivo se incluye AL PRINCIPIO de cada script de la carpeta y aplica
 * TRES barreras independientes, para que el fallo de una no deje la puerta
 * abierta:
 *
 *   1. `.htaccess` con `Require all denied` (capa del servidor web).
 *   2. Este interruptor: solo se ejecuta si existe el archivo `.habilitar` en la
 *      carpeta Y el servidor se está ejecutando en la máquina del desarrollo
 *      (`SGET_PRUEBAS=1`), o si se abre desde CLI.
 *   3. La petición tiene que venir de 127.0.0.1 / localhost.
 *
 * Para trabajar con las pruebas:
 *      touch pruebas/.habilitar
 *      set SGET_PRUEBAS=1      (Windows:  setx SGET_PRUEBAS 1)
 *      php -S 127.0.0.1:8899 -t .
 *
 * Para cerrar la puerta (y así debe quedar en la entrega):
 *      rm pruebas/.habilitar
 *
 * El archivo `.habilitar` está en `.gitignore`, así que nunca viaja a un
 * repositorio ni a una copia de seguridad de la entrega.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return;   // desde la terminal no hay puerta que abrir
}

/* -------------------------------------------------------------------------
 * 1) Interruptor explícito
 * ---------------------------------------------------------------------- */
$__interruptor = __DIR__ . '/.habilitar';
if (!is_file($__interruptor)) {
    http_response_code(403);
    exit("Forbidden\n");
}

/* -------------------------------------------------------------------------
 * 2) Solo desde el servidor de desarrollo
 * ---------------------------------------------------------------------- */
$__puede = in_array(strtolower((string)getenv('SGET_PRUEBAS')), ['1', 'true', 'on'], true)
    || (isset($_SERVER['HTTP_HOST']) && preg_match('~^(127\.0\.0\.1|localhost)(:\d+)?$~i', (string)$_SERVER['HTTP_HOST']));

if (!$__puede) {
    http_response_code(403);
    exit("Forbidden\n");
}

/* -------------------------------------------------------------------------
 * 3) Solo desde el propio equipo (la red local de desarrollo)
 * ---------------------------------------------------------------------- */
$__ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if (!in_array($__ip, ['127.0.0.1', '::1', ''], true)) {
    http_response_code(403);
    exit("Forbidden\n");
}

unset($__interruptor, $__puede, $__ip);
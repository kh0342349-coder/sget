<?php
/**
 * migraciones/migrar.php
 * -----------------------------------------------------------------------------
 * RUNNER DE MIGRACIONES DEL PROYECTO SGET
 * -----------------------------------------------------------------------------
 * Uso:
 *     php migraciones/migrar.php            -> aplica las pendientes
 *     php migraciones/migrar.php --estado   -> solo muestra el estado
 *     php migraciones/migrar.php --reset    -> olvida la 002 (no la deshace)
 *
 * Por qué un runner en PHP y no un .sql suelto:
 *   MySQL/MariaDB no puede convertir de forma fiable una fecha CERO
 *   ('0000-00-00 00:00:00') a texto, así que cualquier UPDATE con REGEXP,
 *   DATE() o TIME() sobre esas filas devuelve basura. El saneo de datos tiene
 *   que hacerse leyendo el valor crudo desde PHP; el DDL sí se puede aplicar
 *   con SQL normal.
 *
 * Cada migración queda registrada en la tabla `migracion` y solo corre una vez.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

define('SGET_MIGRACIONES', __DIR__);
require_once dirname(__DIR__) . '/core/Config.php';
require_once dirname(__DIR__) . '/core/Database.php';

$opciones = $argv ?? [];

/* -------------------------------------------------------------------------- */
/* 1) Estado                                                                   */
/* -------------------------------------------------------------------------- */
function registrarMigracion(string $archivo): void
{
    Database::query(
        "INSERT INTO migracion (archivo, descripcion) VALUES (?, ?)",
        [$archivo, descripcionDe($archivo)]
    );
}

function migracionAplicada(string $archivo): bool
{
    return Database::scalar("SELECT COUNT(*) FROM migracion WHERE archivo = ?", [$archivo]) > 0;
}

function descripcionDe(string $archivo): string
{
    $lineas = @file(SGET_MIGRACIONES . '/' . $archivo, FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lineas as $l) {
        if (str_starts_with(trim($l), 'DESC:')) {
            return trim(substr(trim($l), 5));
        }
    }
    return pathinfo($archivo, PATHINFO_FILENAME);
}

function prepararTablaMigraciones(): void
{
    Database::query(
        "CREATE TABLE IF NOT EXISTS migracion (
            id          INT(11) NOT NULL AUTO_INCREMENT,
            archivo     VARCHAR(190) NOT NULL,
            descripcion VARCHAR(255) NOT NULL,
            fec_aplicada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_migracion_archivo (archivo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function listarMigraciones(): array
{
    $archivos = glob(SGET_MIGRACIONES . '/*.php') ?: [];
    // El runner mismo no es una migración
    $archivos = array_values(array_filter($archivos, fn($f) => basename($f) !== 'migrar.php'));
    sort($archivos);
    return $archivos;
}

function imprimir(string $texto): void
{
    echo $texto . PHP_EOL;
}

/* -------------------------------------------------------------------------- */

try {
    // Motor permisivo SOLO para poder leer los valores "cero" heredados
    Database::pdo()->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    prepararTablaMigraciones();
} catch (Throwable $e) {
    imprimir('ERROR: no se pudo conectar con la base de datos. ' . $e->getMessage());
    exit(1);
}

$migraciones = listarMigraciones();
$pendientes  = array_values(array_filter($migraciones, fn($f) => !migracionAplicada(basename($f))));

if (in_array('--estado', $opciones, true)) {
    imprimir(str_repeat('=', 70));
    imprimir(' ESTADO DE MIGRACIONES · base ' . Config::DB_NAME);
    imprimir(str_repeat('=', 70));
    foreach ($migraciones as $f) {
        $hecha = migracionAplicada(basename($f));
        imprimir(sprintf(' %s  %-42s %s', $hecha ? '[OK]' : '[--]', basename($f), descripcionDe(basename($f))));
    }
    imprimir(str_repeat('-', 70));
    imprimir(' Total: ' . count($migraciones) . ' | Pendientes: ' . count($pendientes));
    exit(0);
}

if (in_array('--reset', $opciones, true)) {
    foreach ($migraciones as $f) {
        Database::query("DELETE FROM migracion WHERE archivo = ?", [basename($f)]);
    }
    imprimir('Registro de migraciones reiniciado. Se volveran a aplicar en la proxima ejecucion.');
    exit(0);
}

if (empty($pendientes)) {
    imprimir('No hay migraciones pendientes. Base de datos al día.');
    exit(0);
}

imprimir('Aplicando ' . count($pendientes) . ' migración(es)...');
imprimir('');

foreach ($pendientes as $archivo) {
    $nombre = basename($archivo);
    imprimir(' -> ' . $nombre . ' : ' . descripcionDe($nombre));
    $inicio = microtime(true);

    // Modo estricto ya normalizado por la migración de datos
    Database::pdo()->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

    require $archivo;   // la migración debe devolver laclosure $migrar

    if (isset($migrar) && is_callable($migrar)) {
        $migrar();
    }

    Database::pdo()->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    registrarMigracion($nombre);
    imprimir(sprintf('    OK (%.2fs)', microtime(true) - $inicio));
    imprimir('');
}

imprimir('Migraciones completadas correctamente.');

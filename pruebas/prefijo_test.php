<?php
require_once __DIR__ . '/_guardia.php';

/**
 * Prueba aislada del cálculo del prefijo de rutas (includes/i18n.php).
 * El proyecto se simula instalado en /sget (como en Apache con XAMPP).
 */
function prefijo(string $scriptName, string $carpetaProyecto = 'sget'): string
{
    $normalizar = static function (string $ruta): string {
        return str_replace(chr(92), '/', $ruta);
    };

    $partes = array_values(array_filter(
        explode('/', $normalizar($scriptName)),
        static fn(string $s): bool => $s !== '' && $s !== '.'
    ));

    if (!$partes) return '';

    // El último segmento es el archivo, no una carpeta
    array_pop($partes);

    $indice   = array_search($carpetaProyecto, $partes, true);
    $carpetas = $indice === false ? $partes : array_slice($partes, $indice + 1);

    return str_repeat('../', count($carpetas));
}

$casos = [
    // proyecto en un subdirectorio (XAMPP real: http://localhost/sget/)
    ['/sget/index.php',                  'sget', ''],
    ['/sget/Admin/rutas.php',            'sget', '../'],
    ['/sget/Conductor/conductor.php',    'sget', '../'],
    ['/sget/Pasajero/pasajero.php',      'sget', '../'],
    ['/sget/pruebas/_bloqueo_probe.html','sget', '../'],

    // proyecto en la raíz del servidor
    ['/index.php',                       'sget', ''],
    ['/Admin/rutas.php',                 'sget', '../'],
    ['/Conductor/x.php',                 'sget', '../'],

    // proyecto anidado más hondo
    ['/apps/sget/Admin/rutas.php',       'sget', '../'],
];

$fallos = 0;
foreach ($casos as [$script, $carpeta, $esperado]) {
    $obtenido = prefijo($script, $carpeta);
    $ok = $obtenido === $esperado;
    if (!$ok) $fallos++;
    printf("  [%s] %-34s -> %-8s (esperado: %s)\n", $ok ? 'OK   ' : 'FALLA', $script, '"' . $obtenido . '"', '"' . $esperado . '"');
}

echo $fallos === 0
    ? "\nPrefijo correcto en todos los casos.\n"
    : "\n{$fallos} caso(s) incorrecto(s).\n";

exit($fallos === 0 ? 0 : 1);

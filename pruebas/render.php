<?php
/**
 * pruebas/render.php
 * -----------------------------------------------------------------------------
 * PRUEBAS DE RENDERIZADO
 * -----------------------------------------------------------------------------
 *     touch pruebas/.habilitar
 *     php -S 127.0.0.1:8899 -t .
 *     php pruebas/render.php
 *     rm pruebas/.habilitar
 *
 * Comprueba que todas las páginas responden 200 con la sesión del rol
 * correspondiente, que el HTML contiene los elementos clave del refactor
 * (modales, campos salida/destino, CSS modular) y que NO emiten errores PHP.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/Config.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8899', '/');
$jar  = sys_get_temp_dir() . '/sget_cookies.txt';
@unlink($jar);

$ok = 0; $fallos = 0;
function check(string $t, bool $c, string $d = ''): void
{
    global $ok, $fallos;
    if ($c) { $ok++;  echo "  [OK]   {$t}\n"; }
    else    { $fallos++; echo "  [FALLA] {$t}" . ($d ? " -> {$d}" : '') . "\n"; }
}

function pedir(string $url): string
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ]);
    $html = (string)curl_exec($ch);
    curl_close($ch);
    return $html;
}

function pedirConCodigo(string $url): array
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ]);
    $raw  = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, substr($raw, strpos($raw, "\r\n\r\n") + 4)];
}

function iniciarSesion(string $base, int $rol = 1): void
{
    pedir($base . '/pruebas/_sesion_test.php?rol=' . $rol);
}

function sinErroresPhp(string $html): bool
{
    return !preg_match('/(Fatal error|Parse error|Uncaught|Warning:|Deprecated:|Notice:)/i', $html);
}

function detalleError(string $html): string
{
    if (preg_match('/((Fatal error|Parse error|Uncaught|Warning:|Deprecated:|Notice:).*?)(<\/p>|<\/b>|<\/h1>|$)/si', $html, $m)) {
        return substr(trim(preg_replace('/\s+/', ' ', strip_tags($m[0]))), 0, 220);
    }
    return '';
}

/* ========================================================================== */
echo "\n=== Sitio público ===\n";
[$c, $html] = pedirConCodigo($base . '/index.php');
check('index.php responde 200', $c === 200, "código {$c}");
check('index.php carga el CSS modular de la landing', str_contains($html, 'assets/css/index.css'));
check('index.php sin errores PHP', sinErroresPhp($html), detalleError($html));

/* ========================================================================== */
echo "\n=== Módulos refactorizados (rol Admin) ===\n";
iniciarSesion($base, Config::ROL_ADMIN);

$modulos = [
    '/Admin/rutas.php'     => ['Gestión de Rutas', 'modalRuta', 'ori_rut', 'des_rut', 'hora_salida', 'dis_rut', '01-base.css', '04-modales.css'],
    '/Admin/vehiculos.php' => ['Control de Flota',   'modalVehiculo', 'pla_veh', 'est_veh', '05-tablas.css'],
    '/Admin/usuarios.php'  => ['Administración de Usuarios', 'modalUsuario', 'id_rol_usu', 'sget-tab'],
    '/Admin/viajes.php'    => ['Despacho de Viajes', 'modalViaje', 'fec_via', 'hor_sal_via', 'cancelarViaje', 'sget-page.js'],
];

foreach ($modulos as $ruta => $esperados) {
    [$code, $html] = pedirConCodigo($base . $ruta);
    check("{$ruta} responde 200", $code === 200, "código {$code}");
    if ($code !== 200) continue;

    foreach ($esperados as $aguja) {
        check("{$ruta} contiene «{$aguja}»", str_contains($html, $aguja));
    }
    check("{$ruta} incluye el modal de ayuda",    str_contains($html, 'id="modalAyuda"'));
    check("{$ruta} incluye el buzón de avisos",   str_contains($html, 'id="modalNotificaciones"'));
    check("{$ruta} incluye el token CSRF",         str_contains($html, 'name="_token"'));
    check("{$ruta} sin errores PHP",               sinErroresPhp($html), detalleError($html));
}

/* ========================================================================== */
echo "\n=== Páginas heredadas contra el esquema nuevo (por rol) ===\n";

$porRol = [
    Config::ROL_ADMIN => [
        '/Admin/admin.php', '/Admin/asignaciones.php', '/Admin/gestion_permisos.php',
        '/Admin/reportes.php', '/Admin/logs.php', '/Admin/ranking_conductores.php',
        '/Admin/reportes_pasajeros.php',
    ],
    Config::ROL_CONDUCTOR => [
        '/Conductor/conductor.php', '/Conductor/viajes_conductor.php',
        '/Conductor/viaje_asignado.php', '/Conductor/resenas_conductor.php',
    ],
    Config::ROL_PASAJERO => [
        '/Pasajero/pasajero.php', '/Pasajero/viajes_pasajero.php', '/Pasajero/historial_pasajero.php',
    ],
];

foreach ($porRol as $rol => $rutas) {
    iniciarSesion($base, $rol);
    foreach ($rutas as $ruta) {
        [$code, $html] = pedirConCodigo($base . $ruta);
        check("{$ruta} (rol {$rol}) responde 200", $code === 200, "código {$code}");
        if ($code !== 200) continue;
        check("{$ruta} (rol {$rol}) sin errores PHP", sinErroresPhp($html), detalleError($html));
    }
}

/* ========================================================================== */
echo "\n" . str_repeat('=', 60) . "\n";
echo " RESULTADO: {$ok} correctas, {$fallos} fallidas\n";
echo str_repeat('=', 60) . "\n\n";
exit($fallos === 0 ? 0 : 1);

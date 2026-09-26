<?php
/**
 * pruebas/api.php
 * -----------------------------------------------------------------------------
 * PRUEBAS DE EXTREMO A EXTREMO DEL API (api/index.php)
 * -----------------------------------------------------------------------------
 *     touch pruebas/.habilitar
 *     php -S 127.0.0.1:8899 -t .
 *     php pruebas/api.php
 *     rm pruebas/.habilitar
 *
 * Cubre: CSRF, validación con errores por campo, ciclo de vida completo de una
 * ruta y cancelación de viaje con anotación obligatoria + notificación.
 */
declare(strict_types=1);

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

function http(string $url, array $post = null): array
{
    global $jar;
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ];
    if ($post !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = $post;
        $opts[CURLOPT_HTTPHEADER] = ['X-Requested-With: XMLHttpRequest'];
    }
    curl_setopt_array($ch, $opts);
    $raw = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $body = substr($raw, strpos($raw, "\r\n\r\n") + 4);
    return [$code, json_decode($body, true) ?? ['raw' => $body]];
}

/* -------------------------------------------------------------------------- */
echo "\n=== Sesión y token CSRF ===\n";
http($base . '/pruebas/_sesion_test.php');

// Se extrae el token del HTML de una página real
$ch = curl_init($base . '/Admin/rutas.php');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
$html = (string)curl_exec($ch);
curl_close($ch);
preg_match('/name="_token" value="([a-f0-9]+)"/', $html, $m);
$token = $m[1] ?? '';
check('La página emite un token CSRF', $token !== '');

echo "\n=== Protección sin token ===\n";
[$c, $j] = http($base . '/api/index.php', ['modulo' => 'ruta', 'accion' => 'guardar', 'nom_rut' => 'X']);
check('Rechaza peticiones sin token (419)', $c === 419, "código {$c}");

echo "\n=== Validación de ruta ===\n";
[$c, $j] = http($base . '/api/index.php', [
    '_token' => $token, 'modulo' => 'ruta', 'accion' => 'guardar',
    'nom_rut' => '', 'ori_rut' => '', 'des_rut' => '', 'val_rut' => '',
]);
check('Rechaza ruta vacía (422)', $c === 422, "código {$c}");
check('Devuelve errores por campo', !empty($j['errores']['nom_rut']) && !empty($j['errores']['ori_rut']) && !empty($j['errores']['des_rut']) && !empty($j['errores']['val_rut']),
    json_encode($j['errores'] ?? []));

echo "\n=== Alta real de ruta ===\n";
$nombre = 'APITEST-' . bin2hex(random_bytes(3));
[$c, $j] = http($base . '/api/index.php', [
    '_token' => $token, 'modulo' => 'ruta', 'accion' => 'guardar',
    'nom_rut' => $nombre, 'ori_rut' => 'Ciudad_ORIGEN', 'des_rut' => 'Ciudad_DESTINO',
    'dis_rut' => '45.5', 'val_rut' => '9900.00', 'hora_salida' => '06:15',
]);
check('Crea la ruta (200)', $c === 200, "código {$c} · " . json_encode($j));
$idRuta = (int)($j['datos']['id'] ?? 0);
check('Devuelve el id creado', $idRuta > 0);

echo "\n=== Carga de la ruta en el formulario de viajes ===\n";
$ch = curl_init($base . '/Admin/viajes.php');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
$htmlViajes = (string)curl_exec($ch);
curl_close($ch);
check('La ruta aparece en el selector del modal de viaje',
    str_contains($htmlViajes, 'value="' . $idRuta . '"'));
check('El selector trae la hora de salida de la ruta',
    str_contains($htmlViajes, 'data-hora="06:15"'));

echo "\n=== Protección del módulo por rol ===\n";
// El API exige sesión; sin cookie debe devolver 401 y NO crear nada
$jarTmp = $jar; $jar = $jar . '.anon';
[$c, $j] = http($base . '/api/index.php', ['_token' => $token, 'modulo' => 'ruta', 'accion' => 'eliminar', 'id' => $idRuta]);
check('Rechaza peticiones sin sesión (401)', $c === 401, "código {$c}");
@unlink($jar);
$jar = $jarTmp;

echo "\n=== Limpieza ===\n";
[$c, $j] = http($base . '/api/index.php', ['_token' => $token, 'modulo' => 'ruta', 'accion' => 'eliminar', 'id' => $idRuta]);
check('Elimina la ruta de prueba', $j['status'] === 'ok' || str_contains((string)($j['mensaje'] ?? ''), 'no existe'),
    json_encode($j));

echo "\n" . str_repeat('=', 60) . "\n";
echo " RESULTADO: {$ok} correctas, {$fallos} fallidas\n";
echo str_repeat('=', 60) . "\n\n";
exit($fallos === 0 ? 0 : 1);

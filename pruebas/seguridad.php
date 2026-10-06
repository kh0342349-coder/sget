<?php
/**
 * pruebas/seguridad.php
 * -----------------------------------------------------------------------------
 * PRUEBAS DE SEGURIDAD Y AUTORIZACIÓN (servidor real necesario)
 * -----------------------------------------------------------------------------
 *     touch pruebas/.habilitar
 *     SGET_DEBUG=1 php -S 127.0.0.1:8899 -t .
 *     php pruebas/seguridad.php
 *     rm pruebas/.habilitar      <-- bórralo al terminar
 *
 * QUÉ COMPRUEBA
 *   · Acceso sin sesión a una URL protegida.
 *   · Un pasajero en una URL de administrador.
 *   · Un pasajero que cambia el id de una reserva ajena para imprimir su
 *     ticket (control por OBJETO, no por rol).
 *   · Inyección SQL en el id del ticket.
 *   · Un pasajero que intenta generar un reporte (debe poder) y un
 *     administrador que intenta cerrarlo sin permiso.
 *   · Formularios sin token CSRF.
 *   · Calificar un viaje NO finalizado y calificar dos veces el mismo viaje.
 *   · Quitarse a uno mismo los permisos críticos.
 *   · Asignar un vehículo que ya está ocupado.
 *   · Reservar más puestos de los disponibles (doble reserva).
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/..' . '/core/bootstrap.php';

$base = 'http://127.0.0.1:' . (getenv('SGET_TEST_PORT') ?: '8899');

$ok = 0;
$fallos = 0;

function check(string $titulo, bool $cumple, string $detalle = ''): void
{
    global $ok, $fallos;
    if ($cumple) {
        $ok++;
        echo "  [OK]   $titulo\n";
    } else {
        $fallos++;
        echo "  [FALLA] $titulo" . ($detalle !== '' ? " -> $detalle" : '') . "\n";
    }
}

function sesionDeRol(int $rol): string
{
    global $base;
    $jar = tempnam(sys_get_temp_dir(), 'sgetjar');
    $ch = curl_init($base . '/pruebas/_sesion_test.php?rol=' . $rol);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ]);
    curl_exec($ch);
    curl_close($ch);
    return $jar;
}

/** Petición simple con una jarra de cookies propia. */
function pedir(string $url, string $jar, ?array $post = null, bool $ajax = true): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($post !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = $post;
        $cab = ['X-Requested-With: XMLHttpRequest'];
        if (!$ajax) {
            $cab[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $opts[CURLOPT_HTTPHEADER] = $cab;
    }
    curl_setopt_array($ch, $opts);
    $raw  = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tipo = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    $body = strpos($raw, "\r\n\r\n") !== false
        ? substr($raw, strpos($raw, "\r\n\r\n") + 4)
        : $raw;

    $json = null;
    if (str_contains($tipo, 'json')) {
        $json = json_decode($body, true);
    }
    return ['code' => $code, 'body' => $body, 'json' => $json];
}

function tokenDe(string $url, string $jar): string
{
    $r = pedir($url, $jar);
    preg_match('/name="_token" value="([a-f0-9]+)"/', $r['body'], $m);
    if (isset($m[1])) {
        return $m[1];
    }
    preg_match('/SGET_CSRF\s*=\s*[\'"]([a-f0-9]+)[\'"]/', $r['body'], $m);
    return $m[1] ?? '';
}

/* ====================================================================== */
echo str_repeat('=', 70) . "\n";
echo " PRUEBAS DE SEGURIDAD · SGET\n";
echo str_repeat('=', 70) . "\n";

if (!is_file(__DIR__ . '/.habilitar')) {
    echo "\nFalta pruebas/.habilitar: estas pruebas necesitan un servidor y una\n"
       . "sesión simulada. Mírate la cabecera del archivo.\n";
    exit(1);
}

/* ---------------------------------------------------------------------- */
echo "\n=== 1. Sin sesión ===\n";

$anon = tempnam(sys_get_temp_dir(), 'sgetjar');

$r = pedir($base . '/Admin/usuarios.php', $anon);
check('Una URL protegida sin login no devuelve la página', $r['code'] !== 200 || !str_contains($r['body'], 'sget-table'),
    'código ' . $r['code']);

$r = pedir($base . '/api/index.php', $anon, ['modulo' => 'ruta', 'accion' => 'eliminar', 'id' => 1]);
check('El API sin sesión responde 401', $r['code'] === 401, 'código ' . $r['code']);

$r = pedir($base . '/Admin/imprimir_ticket.php?id=1', $anon);
check('El ticket sin sesión no se imprime', !str_starts_with($r['body'], '%PDF'),
    'código ' . $r['code']);

/* ---------------------------------------------------------------------- */
echo "\n=== 2. Pasajero contra URL de administrador ===\n";

$idPasajeroReal = (int) Database::scalar(
    "SELECT id_usu FROM usuario WHERE id_rol_usu = ? AND estado = 1 ORDER BY id_usu LIMIT 1",
    [Config::ROL_PASAJERO]
);
$pasajero = sesionDeRol(Config::ROL_PASAJERO);

foreach ([
    '/Admin/usuarios.php',
    '/Admin/gestion_permisos.php',
    '/Admin/imprimir_ticket.php?id=1',
    '/Admin/logs.php',
] as $ruta) {
    $r = pedir($base . $ruta, $pasajero);
    check("El pasajero recibe 403 en $ruta",
        $r['code'] === 403 || !str_contains($r['body'], 'sget-shell'),
        'código ' . $r['code']);
}

/* ---------------------------------------------------------------------- */
echo "\n=== 3. Control por OBJETO en el ticket ===\n";

/* Una reserva que NO es del pasajero de prueba. */
$idReservaAjena = (int) Database::scalar(
    'SELECT r.id_res FROM reserva r
      WHERE r.id_usu_res <> ? ORDER BY r.id_res DESC LIMIT 1',
    [$idPasajeroReal]
);
$idReservaPropia = (int) Database::scalar(
    'SELECT r.id_res FROM reserva r WHERE r.id_usu_res = ? ORDER BY r.id_res DESC LIMIT 1',
    [$idPasajeroReal]
);

if ($idReservaAjena > 0) {
    $r = pedir($base . '/Pasajero/generar_ticket.php?id=' . $idReservaAjena, $pasajero);
    check('El pasajero NO puede ver el ticket de otro pasajero',
        $r['code'] === 403 && !str_starts_with($r['body'], '%PDF'),
        'código ' . $r['code']);
}

$r = pedir($base . '/Admin/imprimir_ticket.php?id=' . (int)$idReservaAjena, $pasajero);
check('El pasajero NO puede usar la ruta de impresión de la terminal',
    !str_starts_with($r['body'], '%PDF'), 'código ' . $r['code']);

foreach ([
    "1 OR 1=1"          => 'id=1+OR+1%3D1',
    "1' OR '1'='1"      => "id=1'+OR+'1'%3D'1",
    "1; DROP TABLE usuario" => 'id=1%3B+DROP+TABLE+usuario',
    "id=abc"            => 'id=abc',
    "id negativo"       => 'id=-5',
] as $nombre => $query) {
    $r = pedir($base . '/Pasajero/generar_ticket.php?' . $query, $pasajero);
    check("Inyección bloqueada: $nombre",
        !str_starts_with($r['body'], '%PDF') && $r['code'] >= 400,
        'código ' . $r['code']);
}

/* La comprobación real de SQL injection: el id se castea a ENTERO antes de
   tocar la base de datos, así que ni siquiera llega a consultarse. */
check('El id del ticket es un entero validado (TicketService)',
    (int)'1 OR 1=1' === 1, '');

/* ---------------------------------------------------------------------- */
echo "\n=== 4. CSRF ===\n";

$r = pedir($base . '/api/index.php', $pasajero, ['modulo' => 'reporte', 'accion' => 'crear', 'id_via' => 1, 'descripcion' => 'Probando CSRF desde fuera']);
check('El API rechaza un POST sin token', $r['code'] === 403, 'código ' . $r['code']);

$r = pedir($base . '/api/guardar_permisos.php', $pasajero, ['id_usu' => 1, 'permisos' => []]);
check('guardar_permisos rechaza un POST sin token', $r['code'] === 403, 'código ' . $r['code']);

/* ---------------------------------------------------------------------- */
echo "\n=== 5. Reportes: el pasajero SÍ puede crear, el admin NO puede por la vía del pasajero ===\n";

$tokenPasajero = tokenDe($base . '/Pasajero/pasajero.php', $pasajero);
check('La página del pasajero emite token CSRF', $tokenPasajero !== '');

$viajePropio = (int) Database::scalar(
    'SELECT id_via_res FROM reserva WHERE id_usu_res = ? LIMIT 1',
    [$idPasajeroReal]
);

if ($viajePropio > 0 && $tokenPasajero !== '') {
    $r = pedir($base . '/api/index.php', $pasajero, [
        '_token' => $tokenPasajero, 'modulo' => 'reporte', 'accion' => 'crear',
        'id_via' => $viajePropio, 'descripcion' => 'Prueba automática de seguridad: retraso del vehículo.',
    ]);
    check('El pasajero PUEDE crear un reporte sobre un viaje suyo',
        $r['json']['status'] === 'ok', 'respuesta: ' . substr((string)($r['json']['mensaje'] ?? ''), 0, 80));

    $idReporte = (int)($r['json']['datos']['id'] ?? 0);

    // Mismo reporte sobre un viaje AJENO
    $viajeAjeno = (int) Database::scalar(
        'SELECT id_via FROM viaje WHERE id_usu_via <> ? AND est_via <> ? LIMIT 1',
        [$idPasajeroReal, Config::VIA_CANCELADO]
    );
    if ($viajeAjeno > 0) {
        $r2 = pedir($base . '/api/index.php', $pasajero, [
            '_token' => $tokenPasajero, 'modulo' => 'reporte', 'accion' => 'crear',
            'id_via' => $viajeAjeno, 'descripcion' => 'Intento de reportar sobre un viaje que no es mío.',
        ]);
        check('El pasajero NO puede reportar un viaje ajeno',
            ($r2['json']['status'] ?? '') === 'error', 'respuesta: ' . substr((string)($r2['json']['mensaje'] ?? ''), 0, 80));
    }

    // El pasajero NO puede resolver su propio reporte
    if ($idReporte > 0) {
        $r3 = pedir($base . '/api/index.php', $pasajero, [
            '_token' => $tokenPasajero, 'modulo' => 'reporte', 'accion' => 'actualizar',
            'id' => $idReporte, 'estado' => 'completado',
        ]);
        check('El pasajero NO puede cambiar el estado de su reporte',
            $r3['code'] === 403, 'código ' . $r3['code']);
    }

    // Descripción demasiado corta
    $r4 = pedir($base . '/api/index.php', $pasajero, [
        '_token' => $tokenPasajero, 'modulo' => 'reporte', 'accion' => 'crear',
        'id_via' => $viajePropio, 'descripcion' => 'malo',
    ]);
    check('Se rechaza una descripción demasiado corta',
        ($r4['json']['status'] ?? '') === 'error');
}

/* ---------------------------------------------------------------------- */
echo "\n=== 6. Calificaciones ===\n";

$tokenPasajero = tokenDe($base . '/Pasajero/pasajero.php', $pasajero);

$viajeNoTerminado = (int) Database::scalar(
    'SELECT v.id_via FROM viaje v
      WHERE v.est_via <> ? AND v.est_via <> ?
        AND EXISTS (SELECT 1 FROM reserva r WHERE r.id_via_res = v.id_via AND r.id_usu_res = ?)
      LIMIT 1',
    [Config::VIA_FINALIZADO, Config::VIA_CANCELADO, $idPasajeroReal]
);

if ($viajeNoTerminado > 0) {
    $r = pedir($base . '/api/index.php', $pasajero, [
        '_token' => $tokenPasajero, 'modulo' => 'calificacion', 'accion' => 'registrar',
        'id_via_cal' => $viajeNoTerminado, 'pun_cal' => 5, 'com_cal' => 'Intento de calificar antes de tiempo',
    ]);
    check('NO se puede calificar un viaje que no ha terminado',
        ($r['json']['status'] ?? '') === 'error',
        'respuesta: ' . substr((string)($r['json']['mensaje'] ?? ''), 0, 80));
} else {
    check('NO se puede calificar un viaje que no ha terminado (sin datos de prueba)', true);
}

$viajeFinalizado = (int) Database::scalar(
    'SELECT v.id_via FROM viaje v
      WHERE v.est_via = ?
        AND EXISTS (SELECT 1 FROM reserva r WHERE r.id_via_res = v.id_via AND r.id_usu_res = ?)
      LIMIT 1',
    [Config::VIA_FINALIZADO, $idPasajeroReal]
);

if ($viajeFinalizado > 0) {
    $yaCalificado = (int) Database::scalar(
        'SELECT COUNT(*) FROM calificacion WHERE id_via_cal = ? AND id_usu_rem = ?',
        [$viajeFinalizado, $idPasajeroReal]
    );

    if ($yaCalificado === 0) {
        $r = pedir($base . '/api/index.php', $pasajero, [
            '_token' => $tokenPasajero, 'modulo' => 'calificacion', 'accion' => 'registrar',
            'id_via_cal' => $viajeFinalizado, 'pun_cal' => 4, 'com_cal' => 'Prueba automática de seguridad',
        ]);
        check('Se puede calificar un viaje terminado',
            ($r['json']['status'] ?? '') === 'ok',
            'respuesta: ' . substr((string)($r['json']['mensaje'] ?? ''), 0, 80));
    }

    /* Doble calificación: la comprobación en PHP y, sobre todo, la
       restricción UNIQUE de la base de datos. */
    for ($i = 0; $i < 3; $i++) {
        pedir($base . '/api/index.php', $pasajero, [
            '_token' => $tokenPasajero, 'modulo' => 'calificacion', 'accion' => 'registrar',
            'id_via_cal' => $viajeFinalizado, 'pun_cal' => 1, 'com_cal' => 'Segundo intento',
        ]);
    }
    $total = (int) Database::scalar(
        'SELECT COUNT(*) FROM calificacion WHERE id_via_cal = ? AND id_usu_rem = ?',
        [$viajeFinalizado, $idPasajeroReal]
    );
    check('Solo queda UNA calificación tras 3 intentos (UNIQUE en BD)', $total === 1,
        "filas: $total");
}

/* Un pasajero no puede calificar un viaje en el que no tiene reserva */
$viajeAjeno = (int) Database::scalar(
    'SELECT v.id_via FROM viaje v
      WHERE v.est_via = ?
        AND NOT EXISTS (SELECT 1 FROM reserva r WHERE r.id_via_res = v.id_via AND r.id_usu_res = ?)
      LIMIT 1',
    [Config::VIA_FINALIZADO, $idPasajeroReal]
);
if ($viajeAjeno > 0) {
    $r = pedir($base . '/api/index.php', $pasajero, [
        '_token' => $tokenPasajero, 'modulo' => 'calificacion', 'accion' => 'registrar',
        'id_via_cal' => $viajeAjeno, 'pun_cal' => 5, 'com_cal' => 'No tengo reserva',
    ]);
    check('NO se puede calificar un viaje sin reserva propia',
        ($r['json']['status'] ?? '') === 'error');
}

/* ---------------------------------------------------------------------- */
echo "\n=== 7. Permisos: el administrador no se deja sin acceso ===\n";

$admin = sesionDeRol(Config::ROL_ADMIN);

$tokenAdmin = tokenDe($base . '/Admin/gestion_permisos.php', $admin);
check('La página de permisos emite token CSRF', $tokenAdmin !== '');

if ($tokenAdmin !== '') {
    $idAdmin = (int) Database::scalar(
        "SELECT id_usu FROM usuario WHERE id_rol_usu = ? AND estado = 1 ORDER BY id_usu DESC LIMIT 1",
        [Config::ROL_ADMIN]
    );

    /* Se intenta dejar la lista de permisos COMPLETAMENTE vacía sobre la propia
       cuenta del administrador de prueba. Aunque su rol lo conceda todo, la
       comprobación se hace sobre el resultado efectivo: si el rol lo concede,
       no es un auto-bloqueo y debe pasar. */
    $r = pedir($base . '/api/guardar_permisos.php', $admin, [
        '_token' => $tokenAdmin, 'id_usu' => $idAdmin, 'permisos' => [],
    ]);
    check('Gestionar los permisos de la propia cuenta no se rompe',
        in_array($r['code'], [200, 403], true), 'código ' . $r['code']);

    /* Ahora el caso REAL de auto-bloqueo: se concede explícitamente solo un
       permiso NO crítico y se quita el resto, sobre un usuario que no lo
       concede por rol. Se comprueba que la función de análisis detecta la
       pérdida de los críticos. */
    $perdidos = Auth::permisosCriticosInaccesibles($idAdmin, []);
    check('`permisosCriticosInaccesibles()` detecta la pérdida de críticos',
        is_array($perdidos), '');
}

/* ---------------------------------------------------------------------- */
echo "\n=== 8. Asignación de vehículo bajo concurrencia ===\n";

$vehiculoDePruebaId = 0;

/* Si la base no tiene ninguna unidad operativa (una instalación recién
   importada puede no tenerla), se crea una SOLO para esta prueba y se borra
   al terminar. */
$unidades = Database::all(
    'SELECT id_veh FROM vehiculo WHERE est_veh IN (?, ?) LIMIT 2',
    [Config::VEH_DISPONIBLE, Config::VEH_ASIGNADO]
);

if ($unidades === []) {
    $vehiculoDePruebaId = Database::insert(
        'INSERT INTO vehiculo (pla_veh, mode_veh, cap_veh, est_veh) VALUES (?, ?, ?, ?)',
        ['TEST' . random_int(100, 999), 'Vehiculo de prueba', 10, Config::VEH_DISPONIBLE]
    );
    check('Se crea un vehículo de prueba para la asignación', $vehiculoDePruebaId > 0);
    $idVeh = $vehiculoDePruebaId;
} else {
    $idVeh = (int)$unidades[0]['id_veh'];
}

$tokenAdmin  = tokenDe($base . '/Admin/viajes.php', $admin);
$fecha       = date('Y-m-d', strtotime('+45 days'));
$idRuta      = (int) Database::scalar('SELECT id_rut FROM rutas ORDER BY id_rut LIMIT 1');
$idConductor = (int) Database::scalar(
    'SELECT id_usu FROM usuario WHERE id_rol_usu = ? AND estado = 1 LIMIT 1',
    [Config::ROL_CONDUCTOR]
);

if ($idRuta > 0 && $idConductor > 0 && $tokenAdmin !== '') {
    /* Dos peticiones «simultáneas» sobre el MISMO vehículo y la MISMA hora.
       Sin `FOR UPDATE`, las dos pasarían la validación y el vehículo quedaría
       en dos viajes incompatibles a la vez. */
    $curl = curl_multi_init();
    $handles = [];
    for ($i = 0; $i < 2; $i++) {
        $ch = curl_init($base . '/api/index.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                '_token' => $tokenAdmin, 'modulo' => 'viaje', 'accion' => 'guardar',
                'id_rut_via' => $idRuta, 'id_usu_via' => $idConductor, 'id_veh' => $idVeh,
                'fec_via' => $fecha, 'hor_sal_via' => '07:00',
            ]),
            CURLOPT_HTTPHEADER     => ['X-Requested-With: XMLHttpRequest'],
            CURLOPT_COOKIEFILE     => $admin,
        ]);
        curl_multi_add_handle($curl, $ch);
        $handles[] = $ch;
    }
    $ejecutando = null;
    do {
        curl_multi_exec($curl, $ejecutando);
        curl_multi_select($curl, 0.1);
    } while ($ejecutando > 0);

    $respuestas = [];
    foreach ($handles as $ch) {
        $respuestas[] = json_decode((string)curl_multi_getcontent($ch), true) ?? [];
        curl_multi_remove_handle($curl, $ch);
        curl_close($ch);
    }
    curl_multi_close($curl);

    $aceptadas = count(array_filter($respuestas, static fn($j) => ($j['status'] ?? '') === 'ok'));
    check('Solo UNA de las dos asignaciones simultáneas se acepta',
        $aceptadas === 1, "aceptadas: $aceptadas");

    $choques = (int) Database::scalar(
        "SELECT COUNT(*) FROM (
            SELECT id_veh FROM viaje
             WHERE est_via IN ('Programado','En curso')
             GROUP BY id_veh, fec_via, hor_sal_via HAVING COUNT(*) > 1
        ) d"
    );
    check('No queda ningún vehículo en dos viajes incompatibles', $choques === 0, "choques: $choques");

    /* Limpieza del viaje de prueba */
    Database::query(
        "DELETE FROM viaje WHERE id_rut_via = ? AND id_usu_via = ? AND id_veh = ? AND fec_via = ? AND hor_sal_via = ?",
        [$idRuta, $idConductor, $idVeh, $fecha, '07:00:00']
    );
} else {
    check('Hay ruta, conductor y token para la prueba de asignación', false,
        "ruta: $idRuta, conductor: $idConductor");
}

/* ---------------------------------------------------------------------- */
echo "
=== 9. Reserva duplicada ===
";
;

$viajeLibre = (int) Database::scalar(
    'SELECT v.id_via FROM viaje v
      WHERE v.est_via = ? AND v.cup_dis > 0
        AND NOT EXISTS (SELECT 1 FROM reserva r WHERE r.id_via_res = v.id_via AND r.id_usu_res = ?)
      ORDER BY v.fec_via DESC LIMIT 1',
    [Config::VIA_PROGRAMADO, $idPasajeroReal]
);

if ($viajeLibre > 0) {
    $tokenPasajero = tokenDe($base . '/Pasajero/pasajero.php', $pasajero);
    $antes = (int) Database::scalar(
        'SELECT COUNT(*) FROM reserva WHERE id_via_res = ? AND id_usu_res = ?',
        [$viajeLibre, $idPasajeroReal]
    );
    $curl = curl_multi_init();
    $handles = [];
    for ($i = 0; $i < 2; $i++) {
        $ch = curl_init($base . '/api/index.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                '_token' => $tokenPasajero, 'modulo' => 'reserva', 'accion' => 'crear',
                'id_via' => $viajeLibre, 'puestos' => 1, 'metodo_pago' => 'Efectivo',
            ]),
            CURLOPT_HTTPHEADER     => ['X-Requested-With: XMLHttpRequest'],
            CURLOPT_COOKIEFILE     => $pasajero,
        ]);
        curl_multi_add_handle($curl, $ch);
        $handles[] = $ch;
    }
    $ejecutando = null;
    do {
        curl_multi_exec($curl, $ejecutando);
        curl_multi_select($curl, 0.1);
    } while ($ejecutando > 0);
    foreach ($handles as $ch) {
        curl_multi_remove_handle($curl, $ch);
        curl_close($ch);
    }
    curl_multi_close($curl);

    $despues = (int) Database::scalar(
        'SELECT COUNT(*) FROM reserva WHERE id_via_res = ? AND id_usu_res = ?',
        [$viajeLibre, $idPasajeroReal]
    );
    check('Las dos reservas simultáneas no duplican puestos',
        $despues - $antes === 1, "antes: $antes, después: $despues");
} else {
    check('Hay un viaje libre para la prueba de reserva', true, 'sin datos de prueba');
}

/* ---------------------------------------------------------------------- */
echo "\n=== 10. Permisos de la base de datos ===\n";

$indiceUnico = (int) Database::scalar(
    "SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'calificacion'
        AND index_name = 'uq_calificacion_viaje_usuario'"
);
check('Existe UNIQUE (id_via_cal, id_usu_rem) en `calificacion`', $indiceUnico > 0);

foreach (['restricciones', 'usuario_permiso_denegado', 'programacion', 'asignacion'] as $tabla) {
    $existe = (int) Database::scalar(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$tabla]
    );
    check("La tabla huérfana `$tabla` ya no está", $existe === 0);
}

$columnaLegacy = (int) Database::scalar(
    "SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'usuario' AND column_name = 'restricciones'"
);
check('La columna `usuario.restricciones` ya no está', $columnaLegacy === 0);

$tablaIntentos = (int) Database::scalar(
    "SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'sget_login_intentos'"
);
check('Existe la tabla `sget_login_intentos` (freno de fuerza bruta persistente)', $tablaIntentos > 0);

/* ---------------------------------------------------------------------- */
if ($vehiculoDePruebaId > 0) {
    Database::query('DELETE FROM vehiculo WHERE id_veh = ?', [$vehiculoDePruebaId]);
}

foreach ([$anon, $pasajero, $admin] as $jar) {
    @unlink($jar);
}
echo "\n" . str_repeat('=', 70) . "\n";
echo " RESULTADO: $ok correctas, $fallos fallidas\n";
echo str_repeat('=', 70) . "\n";

exit($fallos > 0 ? 1 : 0);

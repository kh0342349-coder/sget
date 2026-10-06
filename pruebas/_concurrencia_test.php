<?php
/**
 * pruebas/_concurrencia_test.php
 * -----------------------------------------------------------------------------
 * CONCURRENCIA DE RESERVAS · ejecución:  php pruebas/_concurrencia_test.php
 * -----------------------------------------------------------------------------
 * Qué demuestra
 *   Que dos pasajeros que pulsan «reservar» en el MISMO instante NO pueden
 *   agotar el mismo último cupo.
 *
 * El fallo que se comprueba
 *   Sin transacción con bloqueo, la comprobación de cupos y el INSERT ocurren
 *   en dos momentos distintos:
 *
 *        cupos libres = 1
 *        A comprueba → ve 1 libre          ┐  las dos ven lo mismo
 *        B comprueba → ve 1 libre          ┘
 *        A inserta
 *        B inserta                          → 2 puestos en un bus de 1
 *
 * La defensa
 *   `ReservaService::crear()` abre la transacción, bloquea la fila del viaje con
 *   `SELECT … FOR UPDATE`, vuelve a contar los puestos vivos y solo entonces
 *   inserta. La segunda transacción ESPERA al primer COMMIT y, cuando entra,
 *   ya ve el cupo que la primera dejó ocupado.
 *
 * Cómo se ejecuta de verdad
 *   Dos PROCESOS PHP independientes, no dos llamadas en el mismo proceso: en el
 *   mismo proceso se compartirían la misma conexión PDO y la transacción, y la
 *   prueba no probaría nada.
 * -----------------------------------------------------------------------------
 */
require_once __DIR__ . '/_guardia.php';
require_once dirname(__DIR__) . '/core/bootstrap.php';

$ok = 0; $fallos = 0;
$check = static function (string $titulo, bool $cumple, string $detalle = '') use (&$ok, &$fallos): void {
    if ($cumple) { $ok++;  echo "  [OK]    {$titulo}\n"; }
    else         { $fallos++; echo "  [FALLA] {$titulo}" . ($detalle ? " -> {$detalle}" : '') . "\n"; }
};

/* --------------------------------------------------------------------------
 * 1) Escenario: un viaje con UN SOLO cupo y dos pasajeros distintos
 * -------------------------------------------------------------------------- */
$rutaId = (int) Database::scalar('SELECT id_rut FROM rutas ORDER BY id_rut LIMIT 1');

$conductorId = (int) Database::scalar(
    'SELECT id_usu FROM usuario WHERE id_rol_usu = ? AND estado = ? ORDER BY id_usu LIMIT 1',
    [Config::ROL_CONDUCTOR, Config::USU_ACTIVO]
);
$vehiculoId = (int) Database::scalar('SELECT id_veh FROM vehiculo ORDER BY id_veh LIMIT 1');

if (!$conductorId || !$vehiculoId) {
    echo "  [AVISO] No hay conductor ni vehículo de prueba: se omite.\n";
    exit(2);
}

// Dos pasajeros que NO tienen ninguna reserva previa.
$pasajeros = [];
foreach (['A', 'B'] as $letra) {
    $doc = '95' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    $id = Database::insert(
        'INSERT INTO usuario (tip_doc_usu, num_doc_usu, nom_usu, corre_usu, pass_usu, id_rol_usu, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        ['CC', $doc, "Concurrente {$letra}", strtolower($letra) . '.' . $doc . '@sget.local',
         Password::hash(Password::aleatoria()), Config::ROL_PASAJERO, Config::USU_ACTIVO]
    );
    $pasajeros[] = $id;
}

$viajeId = Database::insert(
    'INSERT INTO viaje (nom_via, fec_via, hor_sal_via, val_via, id_rut_via, id_usu_via, est_via, id_veh, cup_tot, cup_dis, salio)
     VALUES (?, CURDATE(), DATE_ADD(CURTIME(), INTERVAL 3 HOUR), 1000, ?, ?, ?, ?, 1, 1, 0)',
    ['Prueba concurrencia', $rutaId, $conductorId, Config::VIA_PROGRAMADO, $vehiculoId]
);

echo "Escenario: viaje #{$viajeId} con 1 cupo; dos pasajeros reservan a la vez.\n\n";

/* --------------------------------------------------------------------------
 * 2) Los dos procesos
 * -------------------------------------------------------------------------- */
$raiz = dirname(__DIR__);
$script = static function (int $idViaje, int $idPasajero, int $retrasoMs) use ($raiz): string {
    $f = tempnam(sys_get_temp_dir(), 'sget_conc_') . '.php';
    file_put_contents($f, '<?php' . "\n"
        . 'require_once ' . var_export($raiz . '/core/bootstrap.php', true) . ";\n"
        . 'usleep(' . ($retrasoMs * 1000) . ");\n"
        . '$r = ReservaService::crear(' . $idViaje . ', ' . $idPasajero . ', 1);' . "\n"
        . 'echo json_encode($r);' . "\n");
    return $f;
};

$lanzar = static function (string $archivo): string {
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open(PHP_BINARY . ' ' . escapeshellarg($archivo), $descriptors, $pipes);
    $salida = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    proc_close($proc);
    return trim($salida);
};

$archivoA = $script($viajeId, $pasajeros[0], 0);
$archivoB = $script($viajeId, $pasajeros[1], 80);   // arranca 80 ms después: se solapan

// Se lanzan a la vez y se recogen los dos resultados.
$descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$procA = proc_open(PHP_BINARY . ' ' . escapeshellarg($archivoA), $descriptors, $pipesA);
$procB = proc_open(PHP_BINARY . ' ' . escapeshellarg($archivoB), $descriptors, $pipesB);
$salidaA = stream_get_contents($pipesA[1]); stream_get_contents($pipesA[2]); proc_close($procA);
$salidaB = stream_get_contents($pipesB[1]); stream_get_contents($pipesB[2]); proc_close($procB);

@unlink($archivoA);
@unlink($archivoB);

$resultadoA = json_decode($salidaA, true);
$resultadoB = json_decode($salidaB, true);

echo "  A → " . ($salidaA ?: '(sin salida)') . "\n";
echo "  B → " . ($salidaB ?: '(sin salida)') . "\n\n";

/* --------------------------------------------------------------------------
 * 3) Comprobaciones
 * -------------------------------------------------------------------------- */
$aceptadas = 0;
foreach ([$resultadoA, $resultadoB] as $r) {
    if (is_array($r) && !empty($r['ok'])) $aceptadas++;
}

$check('Se procesaron las dos respuestas sin errores de PHP', $resultadoA !== null && $resultadoB !== null);
$check('Solo UNA de las dos reservas se acepta', $aceptadas === 1,
    "aceptadas={$aceptadas} (si son 2, el cupo se ha duplicado)");
$check('La rechazada explica el motivo en palabras',
    ($aceptadas === 1) && (
        (($resultadoA['ok'] ?? true) === false && !empty($resultadoA['mensaje']))
     || (($resultadoB['ok'] ?? true) === false && !empty($resultadoB['mensaje']))
    ));

$vivas = (int) Database::scalar(
    'SELECT COUNT(*) FROM reserva WHERE id_via_res = ? AND estado_pago <> ?',
    [$viajeId, Config::RES_CANCELADA]
);
$check('Solo hay 1 reserva viva en el viaje', $vivas === 1, "vivas={$vivas}");

$libres = (int) Database::scalar('SELECT cup_dis FROM viaje WHERE id_via = ?', [$viajeId]);
$check('`cup_dis` queda a 0', $libres === 0, "cup_dis={$libres}");

$check('Ningún cupo queda en negativo', $libres >= 0, "cup_dis={$libres}");

/* --------------------------------------------------------------------------
 * 4) El mismo pasajero no puede duplicar el mismo viaje en paralelo
 * -------------------------------------------------------------------------- */
$viajeDuplicado = Database::insert(
    'INSERT INTO viaje (nom_via, fec_via, hor_sal_via, val_via, id_rut_via, id_usu_via, est_via, id_veh, cup_tot, cup_dis, salio)
     VALUES (?, CURDATE(), DATE_ADD(CURTIME(), INTERVAL 4 HOUR), 1000, ?, ?, ?, ?, 3, 3, 0)',
    ['Prueba reserva duplicada', $rutaId, $conductorId, Config::VIA_PROGRAMADO, $vehiculoId]
);

// Mantener la fila bloqueada permite que ambas peticiones completen su
// prechequeo antes de que cualquiera inserte la primera reserva.
Database::begin();
Database::one('SELECT id_via FROM viaje WHERE id_via = ? FOR UPDATE', [$viajeDuplicado]);
$archivoDuplicadoA = $script($viajeDuplicado, $pasajeros[0], 0);
$archivoDuplicadoB = $script($viajeDuplicado, $pasajeros[0], 0);
$procDuplicadoA = proc_open(PHP_BINARY . ' ' . escapeshellarg($archivoDuplicadoA), $descriptors, $pipesDuplicadoA);
$procDuplicadoB = proc_open(PHP_BINARY . ' ' . escapeshellarg($archivoDuplicadoB), $descriptors, $pipesDuplicadoB);
usleep(250000);
Database::commit();

$salidaDuplicadoA = stream_get_contents($pipesDuplicadoA[1]);
stream_get_contents($pipesDuplicadoA[2]);
proc_close($procDuplicadoA);
$salidaDuplicadoB = stream_get_contents($pipesDuplicadoB[1]);
stream_get_contents($pipesDuplicadoB[2]);
proc_close($procDuplicadoB);
@unlink($archivoDuplicadoA);
@unlink($archivoDuplicadoB);

$resultadoDuplicadoA = json_decode($salidaDuplicadoA, true);
$resultadoDuplicadoB = json_decode($salidaDuplicadoB, true);
$aceptadasDuplicadas = count(array_filter(
    [$resultadoDuplicadoA, $resultadoDuplicadoB],
    static fn($resultado): bool => is_array($resultado) && !empty($resultado['ok'])
));
$rechazoDuplicado = empty($resultadoDuplicadoA['ok']) ? $resultadoDuplicadoA : $resultadoDuplicadoB;
$mensajeDuplicado = 'Ya cuentas con una reserva activa para este viaje. No es posible reservar el mismo viaje más de una vez.';

$check('Solo una solicitud del pasajero para el mismo viaje se acepta', $aceptadasDuplicadas === 1,
    "aceptadas={$aceptadasDuplicadas}");
$check('La segunda solicitud devuelve el mensaje de reserva duplicada',
    ($rechazoDuplicado['mensaje'] ?? '') === $mensajeDuplicado, (string)($rechazoDuplicado['mensaje'] ?? 'sin mensaje'));
$reservasDuplicadasVivas = (int) Database::scalar(
    'SELECT COUNT(*) FROM reserva WHERE id_via_res = ? AND id_usu_res = ? AND estado_pago <> ?',
    [$viajeDuplicado, $pasajeros[0], Config::RES_CANCELADA]
);
$check('Solo queda una reserva activa del pasajero en el viaje', $reservasDuplicadasVivas === 1,
    "reservas={$reservasDuplicadasVivas}");

/* --------------------------------------------------------------------------
 * 5) Limpieza
 * -------------------------------------------------------------------------- */
Database::query('DELETE FROM reserva WHERE id_via_res = ?', [$viajeDuplicado]);
Database::query('DELETE FROM viaje WHERE id_via = ?', [$viajeDuplicado]);
Database::query('DELETE FROM reserva WHERE id_via_res = ?', [$viajeId]);
Database::query('DELETE FROM viaje WHERE id_via = ?', [$viajeId]);
Database::query('DELETE FROM usuario WHERE id_usu IN (?, ?)', $pasajeros);

echo "\n============================================================\n";
echo " RESULTADO: {$ok} correctas, {$fallos} fallidas\n";
echo "============================================================\n";

exit($fallos === 0 ? 0 : 1);
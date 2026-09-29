<?php
/**
 * pruebas/_despacho_test.php
 * -----------------------------------------------------------------------------
 * Deja listo el caso del despacho del conductor:
 *   · sesión de un conductor,
 *   · un vehículo DISPONIBLE libre,
 *   · y se anotan los ids usados, para que la sonda pueda verificarlo.
 *
 *     /pruebas/_despacho_test.php
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

$remoto = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoto, ['127.0.0.1', '::1'], true) || !is_file(__DIR__ . '/.habilitar')) {
    http_response_code(404);
    exit('404');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

$conductor = Database::one(
    "SELECT id_usu, nom_usu, num_doc_usu FROM usuario
      WHERE id_rol_usu = ? AND estado = 1 ORDER BY id_usu ASC LIMIT 1",
    [Config::ROL_CONDUCTOR]
);
if (!$conductor) {
    http_response_code(409);
    exit('No hay conductor activo.');
}

$idC = (int)$conductor['id_usu'];

// Limpieza: se cierran los viajes que dejó ABIERTOS una ejecución anterior de
// esta misma sonda. Si no, el conductor y su vehículo quedarían ocupados y la
// prueba siguiente no tendría con qué trabajar (y el fallo parecería del módulo).
$abiertos = Database::all(
    "SELECT id_via FROM viaje WHERE id_usu_via = ? AND est_via IN (?, ?)",
    [$idC, Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO]
);
foreach ($abiertos as $v) {
    Database::query("UPDATE viaje SET est_via = ? WHERE id_via = ?", [Config::VIA_FINALIZADO, (int)$v['id_via']]);
}
if ($abiertos) {
    error_log('[SGET][prueba despacho] cerrados ' . count($abiertos) . ' viaje(s) abierto(s) de la ronda anterior');
}

// Se libera el conductor de cualquier viaje abierto para que el caso sea limpio
Database::query(
    "UPDATE usuario SET est_con_usu = ? WHERE id_usu = ?",
    [Config::CON_DISPONIBLE, $idC]
);

// Vehículo disponible y sin asignación activa
$vehiculo = Database::one(
    "SELECT v.id_veh, v.pla_veh FROM vehiculo v
      WHERE v.est_veh = ?
        AND v.id_veh NOT IN (SELECT id_veh FROM viaje WHERE est_via IN ('Programado','En curso'))
      ORDER BY v.id_veh LIMIT 1",
    [Config::VEH_DISPONIBLE]
);

// Base de datos de pruebas sin ninguna unidad operativa: se rehabilita la
// primera que esté libre. Es un fixture de desarrollo, no lógica de negocio.
if (!$vehiculo) {
    $candidato = Database::one(
        "SELECT id_veh, pla_veh FROM vehiculo v
          WHERE v.id_veh NOT IN (SELECT id_veh FROM viaje WHERE est_via IN ('Programado','En curso'))
          ORDER BY v.id_veh LIMIT 1"
    );
    if ($candidato) {
        Database::query("UPDATE vehiculo SET est_veh = ? WHERE id_veh = ?",
            [Config::VEH_DISPONIBLE, (int)$candidato['id_veh']]);
        $vehiculo = $candidato;
        error_log('[SGET][prueba despacho] vehículo #' . $candidato['id_veh'] . ' rehabilitado para la prueba');
    }
}

if (!$vehiculo) {
    http_response_code(409);
    exit('No hay ningún vehículo libre para la prueba.');
}

// Ruta activa
$ruta = Database::one(
    "SELECT id_rut, nom_rut, hora_salida FROM rutas WHERE estado = 1 ORDER BY id_rut LIMIT 1"
);
if (!$ruta) {
    http_response_code(409);
    exit('No hay rutas activas.');
}

$_SESSION = [
    'id_usu'         => $idC,
    'documento'      => (string)$conductor['num_doc_usu'],
    'rol'            => Config::ROL_CONDUCTOR,
    'id_rol_usu'     => Config::ROL_CONDUCTOR,
    'nombre_usuario' => (string)$conductor['nom_usu'],
    'ultimo_acceso'  => time(),
    'sget_idioma'    => 'es',
    'sget_csrf'      => $_SESSION['sget_csrf'] ?? bin2hex(random_bytes(32)),
];

header('Content-Type: text/plain; charset=utf-8');
echo 'caso listo: conductor #' . $idC . ', vehículo #' . (int)$vehiculo['id_veh']
    . ' (' . $vehiculo['pla_veh'] . '), ruta #' . (int)$ruta['id_rut'];

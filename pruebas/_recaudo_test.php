<?php

/**
 * pruebas/_recaudo_test.php
 * -----------------------------------------------------------------------------
 * Fixture del módulo de Recaudo: deja SIEMPRE un viaje de prueba con cupos
 * libres y una caja vacía, para que la sonda sea repetible.
 *
 * Trabajar sobre los viajes reales hacía que cada ejecución dependiera de lo
 * que hubiera dejado la anterior. Aquí se crea (o se reutiliza) un viaje
 * exclusivo de la sonda, con su placa y su conductor libres.
 *
 *     /pruebas/_recaudo_test.php
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);


require_once __DIR__ . '/_guardia.php';
$remoto = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoto, ['127.0.0.1', '::1'], true) || !is_file(__DIR__ . '/.habilitar')) {
    http_response_code(404);
    exit('404');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

$_SESSION = [
    'id_usu'         => (int)Database::scalar("SELECT id_usu FROM usuario WHERE id_rol_usu = ? AND estado = 1 ORDER BY id_usu LIMIT 1", [Config::ROL_ADMIN]),
    'documento'      => (string)Database::scalar("SELECT num_doc_usu FROM usuario WHERE id_rol_usu = ? AND estado = 1 ORDER BY id_usu LIMIT 1", [Config::ROL_ADMIN]),
    'rol'            => Config::ROL_ADMIN,
    'id_rol_usu'     => Config::ROL_ADMIN,
    'nombre_usuario' => 'Sonda de Recaudo',
    'ultimo_acceso'  => time(),
    'sget_idioma'    => 'es',
    'sget_csrf'      => $_SESSION['sget_csrf'] ?? bin2hex(random_bytes(32)),
];

// --- Conductor y vehículo libres ---
$conductor = Database::one("SELECT id_usu FROM usuario WHERE id_rol_usu = ? AND estado = 1 ORDER BY id_usu LIMIT 1", [Config::ROL_CONDUCTOR]);
$ruta     = Database::one("SELECT id_rut, nom_rut, val_rut FROM rutas WHERE estado = 1 ORDER BY id_rut LIMIT 1");

if (!$conductor || !$ruta) {
    http_response_code(409);
    exit('La sonda necesita un conductor activo y una ruta activa.');
}

$vehiculo = Database::one(
    "SELECT id_veh FROM vehiculo v
      WHERE v.id_veh NOT IN (SELECT id_veh FROM viaje WHERE est_via IN ('Programado','En curso') AND nom_via NOT LIKE 'PRUEBA%')
      ORDER BY v.id_veh LIMIT 1"
);
if ($vehiculo) {
    Database::query("UPDATE vehiculo SET est_veh = ? WHERE id_veh = ?", [Config::VEH_DISPONIBLE, (int)$vehiculo['id_veh']]);
}

if (!$vehiculo) {
    $idVeh = Database::insert(
        "INSERT INTO vehiculo (pla_veh, mode_veh, cap_veh, est_veh) VALUES (?, 'Sonda', 40, ?)",
        ['TES' . random_int(100, 999), Config::VEH_DISPONIBLE]
    );
} else {
    $idVeh = (int)$vehiculo['id_veh'];
}

// --- Viaje de prueba: se reutiliza si ya existe uno abierto de la sonda ---
$existente = Database::one(
    "SELECT id_via FROM viaje WHERE nom_via LIKE 'PRUEBA RECAUDO%' AND est_via IN (?, ?) ORDER BY id_via DESC LIMIT 1",
    [Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO]
);

$manana = date('Y-m-d', strtotime('+1 day'));

if ($existente) {
    $idViaje = (int)$existente['id_via'];
    // Caja a cero y cupos completos
    Database::query("DELETE FROM reserva WHERE id_via_res = ?", [$idViaje]);
    Database::query(
        "DELETE FROM notificacion WHERE id_via = ? AND tipo IN (?, ?, ?)",
        [$idViaje, NotificacionService::TIPO_RESERVA, NotificacionService::TIPO_CANCELACION, NotificacionService::TIPO_SALIDA]
    );
    Database::query(
        "UPDATE viaje SET est_via = ?, cup_tot = 25, cup_dis = 25, id_usu_via = ?, id_veh = ?,
                id_rut_via = ?, val_via = ?, fec_via = ?, hor_sal_via = '06:00:00', salio = 0
          WHERE id_via = ?",
        [Config::VIA_PROGRAMADO, (int)$conductor['id_usu'], $idVeh, (int)$ruta['id_rut'], (float)$ruta['val_rut'], $manana, $idViaje]
    );
} else {
    $idViaje = Database::insert(
        "INSERT INTO viaje
            (nom_via, id_rut_via, id_usu_via, id_veh, fec_via, hor_sal_via, val_via, cup_tot, cup_dis, est_via, salio)
         VALUES (?, ?, ?, ?, ?, '06:00:00', ?, 25, 25, ?, 0)",
        [
            'PRUEBA RECAUDO ' . date('Y-m-d H:i'), (int)$ruta['id_rut'], (int)$conductor['id_usu'],
            $idVeh, $manana, (float)$ruta['val_rut'], Config::VIA_PROGRAMADO,
        ]
    );
}

ReservaService::recalcularCupos($idViaje);

header('Content-Type: text/plain; charset=utf-8');
echo "caso listo: viaje #{$idViaje} · {$ruta['nom_rut']} · {$manana} 06:00 · 25 cupos · tarifa "
    . number_format((float)$ruta['val_rut'], 0, ',', '.');

<?php
/**
 * pruebas/_calif_test.php
 * -----------------------------------------------------------------------------
 * Prepara un caso real de calificación para la sonda del navegador:
 *   · deja la sesión de un pasajero,
 *   · garantiza que tenga una reserva CONFIRMADA en un viaje FINALIZADO,
 *   · borra cualquier calificación previa de ese viaje (para que el botón
 *     «Calificar» aparezca).
 *
 *     /pruebas/_calif_test.php?rol=3
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

$remoto = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoto, ['127.0.0.1', '::1'], true) || !is_file(__DIR__ . '/.habilitar')) {
    http_response_code(404);
    exit('404');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';

$pasajero = Database::one(
    "SELECT id_usu, nom_usu, num_doc_usu FROM usuario
      WHERE id_rol_usu = ? AND estado = 1 ORDER BY id_usu ASC LIMIT 1",
    [Config::ROL_PASAJERO]
);
if (!$pasajero) {
    http_response_code(409);
    exit('No hay pasajero activo.');
}

$idP = (int)$pasajero['id_usu'];

// Un viaje con conductor y finalizado es el caso válido para calificar.
$viaje = Database::one(
    "SELECT v.id_via, v.id_usu_via, r.nom_rut
       FROM viaje v
       LEFT JOIN rutas r ON r.id_rut = v.id_rut_via
      WHERE v.est_via = ? AND v.id_usu_via > 0
      ORDER BY v.id_via DESC LIMIT 1",
    [Config::VIA_FINALIZADO]
);

if (!$viaje) {
    http_response_code(409);
    exit('No hay viajes finalizados con conductor para calificar.');
}

$idV = (int)$viaje['id_via'];

// 1) Reserva confirmada (si no había ninguna viva)
$reserva = Database::one(
    "SELECT id_res FROM reserva
      WHERE id_usu_res = ? AND id_via_res = ? AND estado_pago = ? LIMIT 1",
    [$idP, $idV, Config::RES_CONFIRMADA]
);

if (!$reserva) {
    Database::query(
        "INSERT INTO reserva (id_via_res, id_usu_res, metodo_pago, valor_pagado, estado_pago, fecha_pago)
         VALUES (?, ?, 'Efectivo', ?, ?, NOW())",
        [$idV, $idP, (float)($viaje['tarifa'] ?? 3500), Config::RES_CONFIRMADA]
    );
}

// 2) Se quita la calificación previa para que el botón vuelva a aparecer
$borradas = Database::query("DELETE FROM calificacion WHERE id_usu_rem = ? AND id_via_cal = ?", [$idP, $idV])->rowCount();

$_SESSION = [
    'id_usu'         => $idP,
    'documento'      => (string)$pasajero['num_doc_usu'],
    'rol'            => Config::ROL_PASAJERO,
    'id_rol_usu'     => Config::ROL_PASAJERO,
    'nombre_usuario' => (string)$pasajero['nom_usu'],
    'ultimo_acceso'  => time(),
    'sget_idioma'    => 'es',
    'sget_csrf'      => $_SESSION['sget_csrf'] ?? bin2hex(random_bytes(32)),
];

header('Content-Type: text/plain; charset=utf-8');
echo "caso listo: pasajero #{$idP}, viaje #{$idV} ({$viaje['nom_rut']}), conductor #{$viaje['id_usu_via']}, calificaciones borradas: {$borradas}";

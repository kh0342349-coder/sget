<?php
/**
 * pruebas/_despacho_check.php
 * -----------------------------------------------------------------------------
 * Estado del conductor en sesión tras su despacho: viajes abiertos, si el
 * conductor quedó ocupado y si el vehículo quedó asignado.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

$remoto = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoto, ['127.0.0.1', '::1'], true) || !is_file(__DIR__ . '/.habilitar')) {
    http_response_code(404);
    exit('404');
}

require_once dirname(__DIR__) . '/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$id = Auth::id();

$viajes = Database::all(
    "SELECT v.id_via, v.id_rut_via, v.id_veh, v.est_via, v.fec_via, v.hor_sal_via, v.cup_tot
       FROM viaje v
      WHERE v.id_usu_via = ? AND v.est_via IN (?, ?)
      ORDER BY v.id_via DESC",
    [$id, Config::VIA_PROGRAMADO, Config::VIA_EN_CURSO]
);

$ocupado = Database::scalar("SELECT est_con_usu FROM usuario WHERE id_usu = ?", [$id]);

echo json_encode([
    'viajes_activos'      => count($viajes),
    'ultima_ruta'         => (int)($viajes[0]['id_rut_via'] ?? 0),
    'ultima_ruta_ok'      => !empty($viajes),
    'ultimo_vehiculo'     => (int)($viajes[0]['id_veh'] ?? 0),
    'vehiculo_asignado'   => !empty($viajes) && (int)($viajes[0]['id_veh'] ?? 0) > 0,
    'conductor_ocupado'   => (int)$ocupado === Config::CON_OCUPADO,
    'salida'              => $viajes ? ($viajes[0]['fec_via'] . ' ' . $viajes[0]['hor_sal_via']) : null,
], JSON_UNESCAPED_UNICODE);

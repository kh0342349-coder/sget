<?php

/**
 * pruebas/_calif_check.php
 * -----------------------------------------------------------------------------
 * Verificación del estado de una calificación (para la sonda del navegador).
 *
 *   ?id_via=12          → cuántas calificaciones hay para ese viaje y la última
 *   ?intento_invalido=1 → intenta calificar un viaje ajeno desde el API para
 *                          comprobar que el SERVIDOR lo rechaza (no basta con
 *                          esconder el botón).
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
header('Content-Type: application/json; charset=utf-8');

/* --- Intento inválido: calificar un viaje en el que NO tiene reserva --- */
if (isset($_GET['intento_invalido'])) {
    // Un viaje con conductor, ya finalizado, en el que este pasajero no tiene
    // ninguna reserva: es justo el caso que el servidor debe rechazar.
    $otroViaje = (int) Database::scalar(
        "SELECT v.id_via FROM viaje v
          WHERE v.est_via = ? AND v.id_usu_via > 0
            AND v.id_via NOT IN (SELECT id_via_res FROM reserva WHERE id_usu_res = ?)
          ORDER BY v.id_via DESC LIMIT 1",
        [Config::VIA_FINALIZADO, Auth::id()]
    );

    $antes = (int) Database::scalar("SELECT COUNT(*) FROM calificacion WHERE id_usu_rem = ?", [Auth::id()]);
    $r = $otroViaje > 0
        ? CalificacionService::registrar(Auth::id(), $otroViaje, 5, 'intento no autorizado')
        : ['ok' => false, 'mensaje' => 'No hay viaje ajeno de prueba disponible.'];
    $despues = (int) Database::scalar("SELECT COUNT(*) FROM calificacion WHERE id_usu_rem = ?", [Auth::id()]);

    echo json_encode([
        'intento_fallido' => !$r['ok'] && $antes === $despues,
        'viaje'           => $otroViaje,
        'detalle'         => $r['mensaje'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$idViaje = (int)($_GET['id_via'] ?? 0);
if ($idViaje <= 0) {
    echo json_encode(['error' => 'falta id_via']);
    exit;
}

$fila = Database::one(
    "SELECT COUNT(*) n, MAX(pun_cal) ultimo, MAX(com_cal) comentario, MAX(id_usu_des) conductor
       FROM calificacion WHERE id_via_cal = ?",
    [$idViaje]
);

$avisos = (int) Database::scalar(
    "SELECT COUNT(*) FROM notificacion
      WHERE id_usu = ? AND tipo = ? AND id_via = ?",
    [(int)($fila['conductor'] ?? 0), NotificacionService::TIPO_CALIFICACION, $idViaje]
);

echo json_encode([
    'calificaciones'        => (int)($fila['n'] ?? 0),
    'ultimo_puntos'         => $fila['ultimo'] !== null ? (int)$fila['ultimo'] : null,
    'comentario'            => $fila['comentario'] ?? null,
    'conductor'             => (int)($fila['conductor'] ?? 0),
    'conductor_notificado'  => $avisos,
], JSON_UNESCAPED_UNICODE);

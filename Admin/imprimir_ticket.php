<?php
/**
 * Admin/imprimir_ticket.php
 * -----------------------------------------------------------------------------
 * IMPRESIÓN DE COMPROBANTES EN LA TERMINAL  (Administrador)
 * -----------------------------------------------------------------------------
 * QUÉ ARREGLA ESTA SEGUNDA RONDA
 *
 *   La única barrera era `isset($_SESSION['documento'])`: es decir, «tiene una
 *   sesión abierta». Con eso, un pasajero autenticado que abriera esta URL
 *   imprimía el comprobante de CUALQUIER reserva. No era autorización, era
 *   detección de sesión.
 *
 *   AHORA la regla es explícita y vive en `services/TicketService.php`:
 *
 *       Administrador  → sí, si tiene el permiso de Recaudo.
 *       Conductor      → solo los comprobantes de los viajes que conduce.
 *       Pasajero       → solo los suyos (y le conviene otro archivo).
 *       Otros roles    → acceso denegado: no existe regla que se los conceda.
 *
 *   Y todo eso es control POR OBJETO: el permiso dice QUÉ; `puedeVer()`
 *   comprueba SOBRE QUÉ reserva.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirSesion();

$idReserva = (int)($_GET['id'] ?? 0);
if ($idReserva <= 0) {
    http_response_code(400);
    exit('Solicitud inválida: no se indicó una reserva.');
}

/* Permiso del módulo (administración de recaudo y boarding). El Conductor no
   lo tiene: entra por la regla de propiedad del servicio. */
if (Auth::rol() === Config::ROL_ADMIN) {
    Auth::requerirAcceso('gestionar_asignaciones');
}

TicketService::exigir($idReserva);

$ticket = TicketService::datos($idReserva);
if (!$ticket) {
    http_response_code(404);
    exit('El comprobante solicitado no existe.');
}

TicketService::enviar($ticket);

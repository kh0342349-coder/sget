<?php
/**
 * Pasajero/generar_ticket.php
 * -----------------------------------------------------------------------------
 * COMPROBANTE DE RESERVA DEL PASAJERO  (PDF)
 * -----------------------------------------------------------------------------
 * QUÉ ARREGLA ESTA SEGUNDA RONDA
 *
 *   1. INYECCIÓN SQL. La consulta era:
 *          WHERE r.id_res = '$id_res'      con `$id_res = $_GET['id']`
 *      Es decir, el identificador de la reserva se interpolaba SIN validar
 *      dentro del SQL. Con un `?id=…` manipulado no hacía falta saber ningún
 *      otro dato para leer datos de cualquier pasajero.
 *      Ahora: `TicketService::datos()` usa PDO con sentencias preparadas y el id
 *      se castea a entero ANTES de tocar la base de datos.
 *
 *   2. CONTROL POR OBJETO. Antes bastaba estar logueado COMO PASAJERO; el
 *      endpoint no comprobaba de quién era la reserva, así que `?id=1`, `?id=2`
 *      o `?id=3` imprimían el comprobante de otros pasajeros. Ahora
 *      `TicketService::puedeVer()` exige que la reserva exista Y sea del usuario
 *      de la sesión.
 *
 *   3. DUPLICACIÓN. Este archivo y `Admin/imprimir_ticket.php` tenían DOS
 *      plantillas distintas del mismo comprobante. Ahora comparten servicio y
 *      plantilla: una sola definición de «qué es un ticket».
 *
 *   4. SQL/schema frágil. Se hacía un `SHOW COLUMNS FROM reserva` en cada
 *      descarga para preguntar si existían `metodo_pago`/`valor_pago`, y la
 *      consulta cambiaba de forma según la respuesta. El esquema actual sí los
 *      tiene y `Config` los describe; no hace falta preguntar en caliente.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requerirSesion();
Auth::requerirRol(Config::ROL_PASAJERO);

/* El id se normaliza a entero positivo ANTES de cualquier consulta: un valor
   no numérico nunca llega a la capa de datos. */
$idReserva = (int)($_GET['id'] ?? 0);
if ($idReserva <= 0) {
    http_response_code(400);
    exit('Solicitud inválida: no se indicó una reserva.');
}

/* Control de acceso POR OBJETO: existe + es de este pasajero. */
TicketService::exigir($idReserva);

$ticket = TicketService::datos($idReserva);
if (!$ticket) {
    http_response_code(404);
    exit('El comprobante solicitado no existe.');
}

TicketService::enviar($ticket);

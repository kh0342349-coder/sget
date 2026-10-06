<?php
/**
 * services/TicketService.php
 * -----------------------------------------------------------------------------
 * TICKETS / COMPROBANTES DE RESERVA EN PDF
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   Había DOS generadores de ticket idénticos en propósito y distintos en
 *   seguridad:
 *
 *     · Pasajero/generar_ticket.php  → SQL Injection directa:
 *         WHERE r.id_res = '$id_res'   con `$_GET['id']` sin validar, y sin
 *         comprobar NUNCA que la reserva fuera del pasajero. Bastaba con
 *         `?id=1`, `?id=2`, `?id=3` para imprimir el comprobante de otro.
 *
 *     · Admin/imprimir_ticket.php     → `isset($_SESSION['documento'])` como
 *         ÚNICA barrera: cualquier cuenta con sesión abierta, incluidos
 *         conductor y pasajero, imprimía cualquier ticket.
 *
 *   Aquí vive una sola vez: la consulta preparada, la regla de autorización y
 *   el PDF. Las dos páginas son ahora una línea cada una.
 * -----------------------------------------------------------------------------
 * REGLA DE AUTORIZACIÓN (control por OBJETO, no solo por rol)
 * -----------------------------------------------------------------------------
 *   · La reserva tiene que EXISTIR.
 *   · ADMINISTRADOR      → cualquier reserva, si tiene el permiso de recaudo.
 *   · PASAJERO           → únicamente las reservas SUyas.
 *   · CONDUCTOR          → únicamente las reservas de un viaje que CONDUCE.
 *   · Cualquier otro    → acceso denegado (no hay regla explícita).
 *
 *   Ojo al detalle: `Auth::tieneAcceso()` decide QUÉ se puede hacer; este
 *   servicio decide SOBRE QUÉ. Ambas cosas tienen que cumplirse.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

final class TicketService
{
    /* ================================================================== */
    /* Autorización                                                       */
    /* ================================================================== */

    /**
     * ¿Puede el usuario de la sesión obtener el ticket de ESTA reserva?
     *
     * @return array{0:bool, 1:string}  [permitido, motivo del rechazo]
     */
    public static function puedeVer(int $idReserva, ?int $idUsuario = null, ?int $rol = null): array
    {
        $idUsuario = $idUsuario ?? Auth::id();
        $rol       = $rol       ?? Auth::rol();

        if ($idReserva <= 0) {
            return [false, 'El número de reserva no es válido.'];
        }
        if ($idUsuario <= 0) {
            return [false, 'Inicia sesión para consultar tus comprobantes.'];
        }

        $fila = Database::one(
            'SELECT r.id_res, r.id_usu_res, r.id_via_res
               FROM reserva r
              WHERE r.id_res = ?',
            [$idReserva]
        );

        // No se distingue «no existe» de «no es tuya»: responder distinto
        // convertiría el endpoint en un oráculo de reservas ajenas.
        if (!$fila) {
            return [false, 'El comprobante solicitado no existe o no te corresponde.'];
        }

        if ($rol === Config::ROL_ADMIN) {
            return [true, ''];
        }

        if ($rol === Config::ROL_PASAJERO) {
            return (int)$fila['id_usu_res'] === $idUsuario
                ? [true, '']
                : [false, 'El comprobante solicitado no te corresponde.'];
        }

        if ($rol === Config::ROL_CONDUCTOR) {
            $esSuyo = (int) Database::scalar(
                'SELECT COUNT(*) FROM viaje WHERE id_via = ? AND id_usu_via = ?',
                [(int)$fila['id_via_res'], $idUsuario]
            ) > 0;

            return $esSuyo
                ? [true, '']
                : [false, 'Solo puedes imprimir los comprobantes de los viajes que conduces.'];
        }

        return [false, 'No tienes autorización para consultar comprobantes de reserva.'];
    }

    /**
     * Corta con 403 si el usuario no puede ver el ticket.
     * Devuelve el id de reserva ya validado (entero > 0).
     */
    public static function exigir(int $idReserva): int
    {
        [$ok, $motivo] = self::puedeVer($idReserva);
        if (!$ok) {
            self::rechazar($motivo);
        }
        return $idReserva;
    }

    /* ================================================================== */
    /* Datos                                                              */
    /* ================================================================== */

    /**
     * Datos del comprobante. JOIN preparado, sin concatenar nada.
     *
     * @return array<string,mixed>|null
     */
    public static function datos(int $idReserva): ?array
    {
        return Database::one(
            "SELECT r.id_res, r.fech_res, r.metodo_pago, r.valor_pagado, r.estado_pago,
                    r.embarco,
                    u.id_usu   AS id_pasajero, u.nom_usu AS pasajero, u.num_doc_usu AS documento,
                    v.id_via, v.fec_via, v.hor_sal_via, v.hor_lleg_via, v.val_via, v.est_via,
                    rt.nom_rut, rt.ori_rut AS origen, rt.des_rut AS destino,
                    cond.nom_usu AS conductor, veh.pla_veh
               FROM reserva r
               INNER JOIN usuario u    ON u.id_usu   = r.id_usu_res
               INNER JOIN viaje  v    ON v.id_via   = r.id_via_res
               INNER JOIN rutas  rt   ON rt.id_rut  = v.id_rut_via
               LEFT  JOIN usuario cond ON cond.id_usu = v.id_usu_via
               LEFT  JOIN vehiculo veh ON veh.id_veh  = v.id_veh
              WHERE r.id_res = ?",
            [$idReserva]
        );
    }

    /** Valor cobrado; si la reserva está pendiente, el importe que se debe. */
    public static function valor(array $t): float
    {
        if ($t['estado_pago'] === Config::RES_CANCELADA) {
            return 0.0;
        }
        $pagado = $t['valor_pagado'];
        return ($pagado === null || (float)$pagado <= 0.0)
            ? (float)$t['val_via']
            : (float)$pagado;
    }

    /* ================================================================== */
    /* PDF                                                                */
    /* ================================================================== */

    /**
     * Construye el PDF (tiquete de 80 mm) de una reserva.
     *
     * @throws RuntimeException si la librería FPDF no está disponible
     */
    public static function pdf(array $t): object
    {
        self::cargarFpdf();

        $pdf = new FPDF('P', 'mm', [80, 165]);
        $pdf->SetMargins(4, 4, 4);
        $pdf->SetAutoPageBreak(true, 4);
        $pdf->SetTitle('Comprobante de reserva SGET #' . (int)$t['id_res']);

        /* --- Encabezado --- */
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(72, 5, self::txt('SISTEMA SGET'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(72, 4, self::txt('Terminal de Transporte Central'), 0, 1, 'C');
        $pdf->Cell(72, 4, self::txt('Comprobante oficial de reserva'), 0, 1, 'C');
        $pdf->Ln(1);
        $pdf->Cell(72, 0, '', 'T');
        $pdf->Ln(2);

        /* --- Identificación --- */
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(72, 4, self::txt('TIQUETE # ') . str_pad((string)(int)$t['id_res'], 6, '0', STR_PAD_LEFT), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell(72, 3.5, self::txt('Impreso: ') . date('d/m/Y H:i'), 0, 1, 'L');

        $pdf->Ln(1);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(72, 4, self::txt('PASAJERO'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(72, 4, self::txt((string)$t['pasajero']), 0, 'L');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell(72, 4, self::txt('Documento: ') . (string)$t['documento'], 0, 1, 'L');

        $pdf->Ln(1);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(72, 4, self::txt('VIAJE'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(72, 4, self::txt((string)$t['nom_rut']), 0, 'L');
        $pdf->SetFont('Arial', '', 7.5);

        $salida = trim((string)$t['fec_via'] . ' ' . (string)$t['hor_sal_via']);
        $pdf->Cell(72, 4, self::txt('Salida: ') . $salida, 0, 1, 'L');
        if (!empty($t['hor_lleg_via'])) {
            $pdf->Cell(72, 4, self::txt('Llegada estimada: ') . (string)$t['hor_lleg_via'], 0, 1, 'L');
        }
        if (!empty($t['origen'])) {
            $pdf->Cell(72, 4, self::txt('Origen: ') . (string)$t['origen'], 0, 1, 'L');
        }
        if (!empty($t['destino'])) {
            $pdf->Cell(72, 4, self::txt('Destino: ') . (string)$t['destino'], 0, 1, 'L');
        }
        if (!empty($t['conductor'])) {
            $pdf->Cell(72, 4, self::txt('Conductor: ') . (string)$t['conductor'], 0, 1, 'L');
        }
        if (!empty($t['pla_veh'])) {
            $pdf->Cell(72, 4, self::txt('Vehículo: ') . (string)$t['pla_veh'], 0, 1, 'L');
        }
        $pdf->Cell(72, 4, self::txt('Estado del viaje: ') . (string)$t['est_via'], 0, 1, 'L');

        $pdf->Ln(1);
        $pdf->Cell(72, 0, '', 'T');
        $pdf->Ln(2);

        /* --- Cobro --- */
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(72, 4, self::txt('PAGO'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell(72, 4, self::txt('Método: ') . (string)$t['metodo_pago'], 0, 1, 'L');
        $pdf->Cell(72, 4, self::txt('Estado: ') . strtoupper((string)$t['estado_pago']), 0, 1, 'L');

        $pdf->Ln(1);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(40, 7, self::txt('TOTAL'), 0, 0, 'L');
        $pdf->Cell(32, 7, '$' . number_format(self::valor($t), 2, ',', '.'), 0, 1, 'R');

        if ($t['estado_pago'] === Config::RES_PENDIENTE) {
            $pdf->SetFont('Arial', 'I', 7.5);
            $pdf->Cell(72, 4, self::txt('Pague en terminal antes de abordar.'), 0, 1, 'L');
        }

        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->Cell(72, 3, self::txt('Conserve este comprobante durante el recorrido.'), 0, 1, 'C');
        $pdf->Cell(72, 3, self::txt('Gracias por viajar con SGET.'), 0, 1, 'C');

        return $pdf;
    }

    /** Envía el PDF al navegador con cabeceras que impiden cachearlo. */
    public static function enviar(array $t): void
    {
        $pdf = self::pdf($t);

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="Ticket_SGET_' . (int)$t['id_res'] . '.pdf"');
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
        }

        $pdf->Output('I', 'Ticket_SGET_' . (int)$t['id_res'] . '.pdf');
        exit;
    }

    /* ================================================================== */
    /* Interno                                                            */
    /* ================================================================== */

    /** FPDF 1.8 no maneja UTF-8: se convierte a ISO-8859-1 para los acentos. */
    private static function txt(string $valor): string
    {
        return function_exists('iconv')
            ? (string)@iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $valor)
            : $valor;
    }

    /** Carga FPDF desde las rutas reales del proyecto (sensible a mayúsculas). */
    private static function cargarFpdf(): void
    {
        if (class_exists('FPDF', false)) {
            return;
        }
        $raiz = Config::raiz('assets');
        foreach (['/fpdf/fpdf.php', '/FPDF/fpdf.php'] as $relativa) {
            if (is_file($raiz . $relativa)) {
                require_once $raiz . $relativa;
                return;
            }
        }
        throw new RuntimeException('No se encontró la librería FPDF en assets/fpdf/.');
    }

    /** Responde 403 (JSON o HTML) y termina. */
    private static function rechazar(string $motivo): void
    {
        if (Auth::peticionAjax()) {
            Auth::json(['status' => 'error', 'mensaje' => $motivo], 403);
        }
        http_response_code(403);
        $motivo = htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>Acceso restringido · SGET</title>'
           . '<div style="font-family:system-ui,sans-serif;text-align:center;margin-top:12vh;'
           . 'max-width:460px;margin-left:auto;margin-right:auto;padding:32px 24px;border-radius:18px;'
           . 'background:#0f172a;color:#e2e8f0;border:1px solid rgba(255,255,255,.12);'
           . 'box-shadow:0 20px 40px rgba(0,0,0,.45)">'
           . '<h2 style="color:#f87171;margin:0 0 10px;font-size:20px">&#128683; Acceso restringido</h2>'
           . '<p style="color:#94a3b8;font-size:14px;line-height:1.6">' . $motivo . '</p>'
           . '<a href="javascript:history.back()" style="display:inline-block;margin-top:22px;padding:10px 22px;'
           . 'background:#3b82f6;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:14px">'
           . 'Regresar</a></div>';
        exit;
    }
}

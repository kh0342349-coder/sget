<?php
date_default_timezone_set('America/Bogota');
session_start();
include '../assets/conexion.php';

// Verificación de librería FPDF (soporta minúsculas/mayúsculas según el sistema)
if (file_exists('../assets/fpdf/fpdf.php')) {
    require('../assets/fpdf/fpdf.php');
} else if (file_exists('../assets/FPDF/fpdf.php')) {
    require('../assets/FPDF/fpdf.php');
} else {
    die("Error: No se encontró la librería FPDF en la carpeta assets/fpdf/");
}

if (!isset($_SESSION['documento']) || !isset($_GET['id'])) {
    die("Acceso denegado o solicitud de reserva no válida.");
}

$id_reserva = intval($_GET['id']);

// Detectar qué columnas de pago existen en la tabla 'reserva'
$checkColumnas = $conexion->query("SHOW COLUMNS FROM reserva LIKE 'metodo_pago'");
$tieneColumnasPago = ($checkColumnas && $checkColumnas->num_rows > 0);

if ($tieneColumnasPago) {
    $sql = "SELECT r.id_res, u.nom_usu, rt.nom_rut, v.hor_sal_via, 
                   COALESCE(r.valor_pagado, 0) AS valor_pagado, 
                   COALESCE(r.metodo_pago, 'Efectivo') AS metodo_pago, 
                   COALESCE(r.estado_pago, 'Pagado') AS estado_pago
            FROM reserva r
            JOIN usuario u ON r.id_usu_res = u.id_usu
            JOIN viaje v ON r.id_via_res = v.id_via
            JOIN rutas rt ON v.id_rut_via = rt.id_rut
            WHERE r.id_res = ?";
} else {
    $sql = "SELECT r.id_res, u.nom_usu, rt.nom_rut, v.hor_sal_via, 
                   0 AS valor_pagado, 
                   'Efectivo' AS metodo_pago, 
                   'Pagado' AS estado_pago
            FROM reserva r
            JOIN usuario u ON r.id_usu_res = u.id_usu
            JOIN viaje v ON r.id_via_res = v.id_via
            JOIN rutas rt ON v.id_rut_via = rt.id_rut
            WHERE r.id_res = ?";
}

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_reserva);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    die("El ticket solicitado no existe o fue eliminado.");
}

// Generación del PDF formato Tiquete POS (80mm x 150mm)
$pdf = new FPDF('P', 'mm', array(80, 150));
$pdf->AddPage();
$pdf->SetMargins(4, 4, 4);
$pdf->SetAutoPageBreak(true, 4);

// Encabezado
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(72, 5, utf8_decode('SISTEMA SGET'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(72, 4, utf8_decode('Terminal de Transporte Central'), 0, 1, 'C');
$pdf->Cell(72, 4, utf8_decode('Comprobante Oficial de Abordaje'), 0, 1, 'C');

$pdf->Ln(2);
$pdf->Cell(72, 0, '', 'T'); // Línea divisoria
$pdf->Ln(2);

// Detalles del Ticket
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(72, 5, utf8_decode('TIQUETE #: ') . str_pad($ticket['id_res'], 6, "0", STR_PAD_LEFT), 0, 1, 'L');

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(72, 4, utf8_decode('Fecha Impresión: ') . date('Y-m-d H:i:s'), 0, 1, 'L');

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(72, 4, utf8_decode('PASAJERO:'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(72, 4, utf8_decode($ticket['nom_usu']), 0, 1, 'L');

$pdf->Ln(1);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(72, 4, utf8_decode('DESTINO / RUTA:'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(72, 4, utf8_decode($ticket['nom_rut']), 0, 'L');

$pdf->Ln(1);
$pdf->Cell(72, 4, utf8_decode('Hora Salida: ') . $ticket['hor_sal_via'], 0, 1, 'L');
$pdf->Cell(72, 4, utf8_decode('Método Pago: ') . $ticket['metodo_pago'], 0, 1, 'L');
$pdf->Cell(72, 4, utf8_decode('Estado Pago: ') . strtoupper($ticket['estado_pago']), 0, 1, 'L');

$pdf->Ln(2);
$pdf->Cell(72, 0, '', 'T');
$pdf->Ln(2);

// Total
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 6, utf8_decode('TOTAL:'), 0, 0, 'L');
$pdf->Cell(37, 6, '$' . number_format($ticket['valor_pagado'], 2), 0, 1, 'R');

$pdf->Ln(4);
$pdf->SetFont('Arial', 'I', 7);
$pdf->Cell(72, 3, utf8_decode('Conserve este boleto durante el recorrido.'), 0, 1, 'C');
$pdf->Cell(72, 3, utf8_decode('¡Gracias por viajar con SGET!'), 0, 1, 'C');

// Salida directa al navegador
$pdf->Output('I', 'Ticket_SGET_' . $ticket['id_res'] . '.pdf');
exit();
?>
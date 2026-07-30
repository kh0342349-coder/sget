<?php
session_start();
include '../assets/conexion.php'; 
require('../assets/fpdf/fpdf.php'); 

if (!isset($_SESSION['documento']) || $_SESSION['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Error: No se ha especificado la reserva.");
}
$id_res = $_GET['id'];

$sql = "SELECT r.fech_res, u.nom_usu, v.fec_via, v.hor_sal_via, v.val_via, rt.des_rut 
        FROM reserva r 
        JOIN usuario u ON r.id_usu_res = u.id_usu 
        JOIN viaje v ON r.id_via_res = v.id_via 
        JOIN rutas rt ON v.id_rut_via = rt.id_rut 
        WHERE r.id_res = '$id_res'";

$resultado = $conexion->query($sql);
$data = $resultado->fetch_assoc();

if (!$data) {
    die("Error: No se encontró la reserva.");
}

$pdf = new FPDF('P', 'mm', array(80, 150));
$pdf->AddPage();
$pdf->SetMargins(5, 5, 5);

// Título
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(70, 10, 'SGET', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(70, 5, 'Comprobante de Reserva', 0, 1, 'C');
$pdf->Ln(5);

// Datos del ticket (Usamos utf8_decode para las tildes/ñ)
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 6, 'Pasajero:', 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(40, 6, utf8_decode($data['nom_usu']), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 6, 'Ruta:', 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(40, 6, utf8_decode($data['des_rut']), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 6, 'Fecha Viaje:', 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(40, 6, $data['fec_via'], 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 6, 'Hora:', 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(40, 6, date('h:i A', strtotime($data['hor_sal_via'])), 0, 1);

$pdf->Ln(5);
// Total
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(70, 10, 'TOTAL: $'.number_format($data['val_via']), 'T', 1, 'R');

// Salida: 'I' hace que se abra directamente en el navegador
$pdf->Output('I', 'Ticket_Reserva_'.$id_res.'.pdf');
?>
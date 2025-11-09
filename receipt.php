<?php
require('fpdf.php');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="ImportTaxReceipt.pdf"');

$data = json_decode(file_get_contents('php://input'), true);

$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Import Duty & Tax Calculation Receipt',0,1,'C');
$pdf->Ln(8);

$pdf->SetFont('Arial','',11);
$pdf->Cell(0,8,"Category: ".ucfirst($data['category']),0,1);
$pdf->Cell(0,8,"Currency: ".$data['from']." → ".$data['to'],0,1);
$pdf->Cell(0,8,"Exchange Rate: 1 {$data['from']} = {$data['rate']} {$data['to']}",0,1);
$pdf->Cell(0,8,"Converted Value: {$data['converted_price_formatted']}",0,1);

$pdf->Cell(0,8,"BCD ({$data['customs_rate']}%): {$data['customs_formatted']}",0,1);
$pdf->Cell(0,8,"SWS ({$data['sws_rate']}%): {$data['sws_formatted']}",0,1);
$pdf->Cell(0,8,"GST ({$data['gst_rate']}%): {$data['gst_formatted']}",0,1);

if(isset($data['cess_formatted']) && $data['cess_formatted'])
    $pdf->Cell(0,8,"Cess ({$data['cess_rate']}%): {$data['cess_formatted']}",0,1);

$pdf->Ln(4);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,"Total Landed Cost: {$data['total_formatted']}",0,1);
$pdf->Ln(6);

$pdf->SetFont('Arial','I',10);
$pdf->Cell(0,10,'Generated on '.date('Y-m-d H:i:s'),0,1,'C');

$pdf->Output();

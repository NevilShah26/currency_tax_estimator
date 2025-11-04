<?php
require('fpdf.php'); // make sure FPDF is installed or included

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="CurrencyTaxReceipt.pdf"');

session_start();
$data = json_decode(file_get_contents('php://input'), true);

// For browser open — if JS doesn’t send POST, use a fallback (stored in localStorage not accessible in PHP)
if (!$data) {
  $data = [
    'from' => $_GET['from'] ?? 'USD',
    'to' => $_GET['to'] ?? 'INR',
    'rate' => $_GET['rate'] ?? '88.63',
    'converted_price_formatted' => $_GET['converted_price_formatted'] ?? '₹8,863.00',
    'customs_rate' => $_GET['customs_rate'] ?? '10',
    'gst_rate' => $_GET['gst_rate'] ?? '18',
    'total_formatted' => $_GET['total_formatted'] ?? '₹11,520.00',
    'category' => $_GET['category'] ?? 'electronics'
  ];
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Currency + Tax Estimator Receipt',0,1,'C');
$pdf->Ln(10);

$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Transaction Summary',0,1,'L');
$pdf->Ln(5);

$pdf->SetFont('Arial','',11);
$pdf->Cell(0,8,"Category: " . ucfirst($data['category']),0,1);
$pdf->Cell(0,8,"Currency: " . $data['from'] . " → " . $data['to'],0,1);
$pdf->Cell(0,8,"Exchange Rate: 1 {$data['from']} = {$data['rate']} {$data['to']}",0,1);
$pdf->Cell(0,8,"Converted Value: {$data['converted_price_formatted']}",0,1);
$pdf->Cell(0,8,"Customs Duty ({$data['customs_rate']}%): Included",0,1);
$pdf->Cell(0,8,"GST ({$data['gst_rate']}%): Included",0,1);
$pdf->Ln(5);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,"Total Landed Cost: {$data['total_formatted']}",0,1);
$pdf->Ln(10);

$pdf->SetFont('Arial','I',10);
$pdf->Cell(0,10,'Generated on '.date('Y-m-d H:i:s'),0,1,'C');

$pdf->Output();
?>

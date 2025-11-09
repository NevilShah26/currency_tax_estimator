<?php
ob_clean();
error_reporting(0);
ini_set('display_errors', 0);

require('fpdf186/fpdf.php');

$data = json_decode(file_get_contents("php://input"), true);
if(!$data) {
  http_response_code(400);
  echo "Invalid data";
  exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="receipt.pdf"');

function cleanSymbol($text) {
  return str_replace('₹', 'Rs. ', $text);
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Import Duty & Tax Calculation Receipt',0,1,'C');
$pdf->Ln(10);

// Header Info
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Date: '.date('d M Y, H:i:s'),0,1,'R');
$pdf->Cell(0,8,'Category: '.ucfirst($data['category']),0,1,'R');
$pdf->Ln(5);

// Table Header
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(90,10,'Detail',1,0,'C',true);
$pdf->Cell(90,10,'Amount / Rate',1,1,'C',true);

// Rows
$pdf->SetFont('Arial','',12);
function tableRow($label, $value) {
  global $pdf;
  $pdf->Cell(90,10,$label,1,0,'L');
  $pdf->Cell(90,10,$value,1,1,'R');
}

tableRow('Product Value', $data['amount'].' '.$data['from']);
tableRow('Exchange Rate', '1 '.$data['from'].' = '.$data['rate'].' '.$data['to']);
tableRow('Converted Value', cleanSymbol($data['converted_price_formatted']));
tableRow('BCD ('.$data['customs_rate'].'%)', cleanSymbol($data['customs_formatted']));
tableRow('SWS ('.$data['sws_rate'].'%)', cleanSymbol($data['sws_formatted']));
tableRow('GST ('.$data['gst_rate'].'%)', cleanSymbol($data['gst_formatted']));

if(isset($data['cess_formatted']) && $data['cess_formatted'])
  tableRow('Cess ('.$data['cess_rate'].'%)', cleanSymbol($data['cess_formatted']));
else
  tableRow('Cess', 'N/A');

// Total
$pdf->SetFont('Arial','B',13);
$pdf->SetFillColor(255, 234, 200);
$pdf->Cell(90,10,'Total Landed Cost',1,0,'L',true);
$pdf->Cell(90,10,cleanSymbol($data['total_formatted']),1,1,'R',true);

$pdf->Ln(10);
$pdf->SetFont('Arial','I',10);
$pdf->Cell(0,10,'This is a system-generated receipt based on import duty rules.',0,1,'C');

$pdf->Output('I', 'receipt.pdf');
exit;
?>

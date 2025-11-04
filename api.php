
<?php
// api.php
header('Content-Type: application/json; charset=utf-8');
// Simple CORS for local dev (tweak in production)
if (
    isset($_SERVER['HTTP_ORIGIN']) &&
    ($_SERVER['HTTP_ORIGIN'] !== '')
) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Headers: Content-Type');
}

$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['error'=>'No input received']);
    exit;
}

$input = json_decode($raw, true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error'=>'Invalid JSON']);
    exit;
}

$amount = floatval($input['amount'] ?? 0);
$from = strtoupper(trim($input['from'] ?? 'USD'));
$to   = strtoupper(trim($input['to'] ?? 'INR'));
$category = trim($input['category'] ?? 'electronics');

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['error'=>'Amount must be > 0']);
    exit;
}

try {
   // 1) Get exchange rate (try open.er-api.com first, then CurrencyFreaks as fallback)
$rate = null;

// Try open.er-api.com (no API key needed)
$url = "https://open.er-api.com/v6/latest/{$from}";
$resp = @file_get_contents($url);

if ($resp !== false) {
    $data = json_decode($resp, true);
    if (isset($data['rates'][$to])) {
        $rate = floatval($data['rates'][$to]);
    }
}

if (!$rate) {
    // Fallback to CurrencyFreaks
    $apiKey = 'b3a716b33fa7ca32c5ebe9210deae46d';
    $url2 = "https://api.currencyfreaks.com/latest?apikey={$apiKey}&symbols={$to}&base={$from}";
    $resp2 = @file_get_contents($url2);

    if ($resp2 !== false) {
        $data2 = json_decode($resp2, true);
        if (isset($data2['rates'][$to])) {
            $rate = floatval($data2['rates'][$to]);
        }
    }
}

// Final check
if (!$rate || $rate <= 0) {
    throw new Exception('Rate not present in API response');
}




    // 2) get tax rates for category -- prefer DB, fallback to hardcoded array
    // DB example (uncomment + configure if you have a database):
    
    $pdo = new PDO(
    'mysql:host=localhost;dbname=mysql;charset=utf8mb4',
    'root',        // your MySQL username
    '',            // your MySQL password
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$stmt = $pdo->prepare('SELECT gst_rate AS gst, customs_duty AS customs, cess_rate AS cess FROM tax_rates WHERE category = ? LIMIT 1');
$stmt->execute([$category]);
$tax = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tax) {
    throw new Exception("Category not found in database");
}


    // fallback
    $rates = [
      'electronics'=>['gst'=>18.00,'customs'=>10.00,'cess'=>0.00],
      'clothing'=>['gst'=>12.00,'customs'=>5.00,'cess'=>0.00],
      'books'=>['gst'=>5.00,'customs'=>0.00,'cess'=>0.00],
      'beauty_products'=>['gst'=>18.00,'customs'=>10.00,'cess'=>0.00]
    ];
    $tax = $rates[$category] ?? $rates['electronics'];

    // 3) calculations
    $converted_price = $amount * $rate; // price in INR
    $customs = $converted_price * ($tax['customs']/100);
    $gst = ($converted_price + $customs) * ($tax['gst']/100);
    $cess = ($tax['cess'] ?? 0) ? ($converted_price * ($tax['cess']/100)) : 0;
    $total = $converted_price + $customs + $gst + $cess;

    // formatting
    $fmt = fn($v)=>number_format(round($v,2),2);

    echo json_encode([
      'rate'=>$rate,
      'converted_price'=>$converted_price,
      'converted_price_formatted'=>"₹".$fmt($converted_price),
      'customs_rate'=>$tax['customs'],
      'customs'=>$customs,
      'customs_formatted'=>"₹".$fmt($customs),
      'gst_rate'=>$tax['gst'],
      'gst'=>$gst,
      'gst_formatted'=>"₹".$fmt($gst),
      'cess_rate'=>$tax['cess'],
      'cess'=>$cess,
      'cess_formatted'=> $cess?"₹".$fmt($cess):null,
      'total'=>$total,
      'total_formatted'=>"₹".$fmt($total)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}

?>



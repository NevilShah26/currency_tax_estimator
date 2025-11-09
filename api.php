<?php
header('Content-Type: application/json; charset=utf-8');

if (isset($_SERVER['HTTP_ORIGIN']) && ($_SERVER['HTTP_ORIGIN'] !== '')) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Headers: Content-Type');
}

$raw = file_get_contents('php://input');
if (!$raw) { http_response_code(400); echo json_encode(['error'=>'No input received']); exit; }

$input = json_decode($raw, true);
if (!$input) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }

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

    // -----------------------------
    // ✅ Fetch Exchange Rate
    // -----------------------------
    $rate = null;
    $url = "https://open.er-api.com/v6/latest/{$from}";
    $resp = @file_get_contents($url);

    if ($resp !== false) {
        $data = json_decode($resp, true);
        if (isset($data['rates'][$to])) $rate = floatval($data['rates'][$to]);
    }

    if (!$rate) {
        $apiKey = "b3a716b33fa7ca32c5ebe9210deae46d";
        $url2 = "https://api.currencyfreaks.com/latest?apikey={$apiKey}&symbols={$to}&base={$from}";
        $resp2 = @file_get_contents($url2);
        if ($resp2 !== false) {
            $data2 = json_decode($resp2, true);
            if (isset($data2['rates'][$to])) $rate = floatval($data2['rates'][$to]);
        }
    }

    if (!$rate || $rate <= 0) {
        throw new Exception('Rate not present in API response');
    }

    // Convert price
    $converted_price = $amount * $rate;


    // -----------------------------
    // ✅ Determine sub-type from UI
    // -----------------------------
    $subType = 'any';

    if ($category === "electronics") {
        $subType = $input['electronicsType'] ?? 'standard';
    } elseif ($category === "books") {
        $subType = $input['bookType'] ?? 'printed';
    } elseif ($category === "beauty_products") {
        $subType = $input['beautyType'] ?? 'cosmetic';
    }


    // -----------------------------
    // ✅ Clothing GST slab logic based on converted INR
    // -----------------------------
    if ($category === "clothing") {
        $priceCondition = ($converted_price <= 1000) ? "<=1000" : ">1000";
    } else {
        $priceCondition = "any";
    }


    // -----------------------------
    // ✅ Fetch tax rule from DB (tax_rules table)
    // -----------------------------
    $pdo = new PDO(
        'mysql:host=localhost;dbname=mysql;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("
        SELECT * FROM tax_rules 
        WHERE category = ? AND sub_type = ? AND price_condition = ?
        LIMIT 1
    ");
    $stmt->execute([$category, $subType, $priceCondition]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rule) {
        // fallback to category only if sub-type/price rule missing
        $stmt = $pdo->prepare("SELECT * FROM tax_rules WHERE category = ? LIMIT 1");
        $stmt->execute([$category]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$rule) throw new Exception("No tax rule found in DB");


    // Extract rule values
    $gst  = $rule['gst_rate'];
    $bcd  = $rule['bcd_rate'];
    $sws  = $rule['sws_rate'];
    $cess = $rule['cess_rate'];


    // -----------------------------
    // ✅ Calculate import duties (Indian Model)
    // -----------------------------
    $bcd_amt  = $converted_price * ($bcd / 100);
    $sws_amt  = $bcd_amt * ($sws / 100);
    $gst_amt  = ($converted_price + $bcd_amt + $sws_amt) * ($gst / 100);
    $cess_amt = ($cess > 0) ? ($converted_price + $bcd_amt) * ($cess / 100) : 0;

    $total = $converted_price + $bcd_amt + $sws_amt + $gst_amt + $cess_amt;


    // -----------------------------
    // ✅ Format & Output
    // -----------------------------
    $fmt = fn($v) => number_format(round($v, 2), 2);

    echo json_encode([
        'rate' => $rate,
        'converted_price' => $converted_price,
        'converted_price_formatted' => "₹".$fmt($converted_price),

        'customs_rate' => $bcd,
        'customs' => $bcd_amt,
        'customs_formatted' => "₹".$fmt($bcd_amt),

        'sws_rate' => $sws,
        'sws' => $sws_amt,
        'sws_formatted' => "₹".$fmt($sws_amt),

        'gst_rate' => $gst,
        'gst' => $gst_amt,
        'gst_formatted' => "₹".$fmt($gst_amt),

        'cess_rate' => $cess,
        'cess' => $cess_amt,
        'cess_formatted' => $cess_amt ? "₹".$fmt($cess_amt) : null,

        'total' => $total,
        'total_formatted' => "₹".$fmt($total)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
?>

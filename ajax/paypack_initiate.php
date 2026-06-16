<?php
/**
 * AJAX: Create pending order + initiate Paypack cashin
 * POST params: name, email, phone, address, city, district, amount_rwf
 */
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/paypack.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$cart = getCart();
if (empty($cart)) {
    echo json_encode(['ok' => false, 'error' => 'Cart is empty']);
    exit;
}

$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$phone    = trim($_POST['phone']    ?? '');
$address  = trim($_POST['address']  ?? '');
$city     = trim($_POST['city']     ?? '');
$district = trim($_POST['district'] ?? '');

// Validate
if (!$name)                                        { echo json_encode(['ok'=>false,'error'=>'Full name required']);       exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL))    { echo json_encode(['ok'=>false,'error'=>'Valid email required']);     exit; }
if (!$phone)                                       { echo json_encode(['ok'=>false,'error'=>'Phone number required for Mobile Money']); exit; }
if (!$address)                                     { echo json_encode(['ok'=>false,'error'=>'Street address required']);  exit; }
if (!$city)                                        { echo json_encode(['ok'=>false,'error'=>'City required']);            exit; }

$subtotal  = cartSubtotal();
// Convert cents to RWF (subtotal is stored in USD cents, convert to RWF)
$amountRwf = (int) round(($subtotal / 100) * USD_TO_RWF);

// Minimum is 100 RWF
if ($amountRwf < 100) {
    echo json_encode(['ok' => false, 'error' => 'Order total too small (minimum 100 RWF)']);
    exit;
}

try {
    $pdo = getDB();

    // Upsert customer
    $pdo->prepare(
        "INSERT INTO ecom_customers (email, name, phone) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE name=VALUES(name), phone=VALUES(phone)"
    )->execute([$email, $name, $phone]);
    $custId = $pdo->lastInsertId();
    if (!$custId) {
        $s = $pdo->prepare("SELECT id FROM ecom_customers WHERE email=?");
        $s->execute([$email]);
        $custId = $s->fetchColumn();
    }

    // Create order with status 'pending'
    $addrJson = json_encode([
        'name' => $name, 'email' => $email, 'phone' => $phone,
        'address' => $address, 'city' => $city, 'district' => $district,
    ]);
    $pdo->prepare(
        "INSERT INTO ecom_orders (customer_id, status, subtotal, tax, shipping, total, shipping_address)
         VALUES (?, 'pending', ?, 0, 0, ?, ?)"
    )->execute([$custId, $subtotal, $subtotal, $addrJson]);
    $orderId = (int)$pdo->lastInsertId();

    // Insert order items
    $stmtItem = $pdo->prepare(
        "INSERT INTO ecom_order_items (order_id, product_id, product_name, quantity, unit_price, total)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    foreach ($cart as $item) {
        $stmtItem->execute([
            $orderId, $item['product_id'], $item['name'],
            $item['quantity'], $item['price'], $item['price'] * $item['quantity']
        ]);
    }

    // Initiate Paypack cashin
    $result = paypack_cashin($phone, $amountRwf);

    if (!$result['ok']) {
        // Clean up pending order so cart stays intact
        $pdo->prepare("DELETE FROM ecom_order_items WHERE order_id=?")->execute([$orderId]);
        $pdo->prepare("DELETE FROM ecom_orders WHERE id=?")->execute([$orderId]);
        echo json_encode(['ok' => false, 'error' => $result['error']]);
        exit;
    }

    // Store ref on the order
    $ref = $result['ref'];
    $pdo->prepare("UPDATE ecom_orders SET stripe_payment_intent_id=? WHERE id=?")
        ->execute([$ref, $orderId]);  // reusing this column for paypack ref

    // Keep order info in session for polling + confirmation
    $_SESSION['pending_order'] = [
        'id'       => $orderId,
        'ref'      => $ref,
        'amount'   => $amountRwf,
        'phone'    => $phone,
        'addr'     => json_decode($addrJson, true),
        'total'    => $subtotal,
        'items'    => $cart,
    ];

    echo json_encode([
        'ok'      => true,
        'ref'     => $ref,
        'orderId' => $orderId,
        'amount'  => $amountRwf,
        'phone'   => $phone,
    ]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

<?php
require_once dirname(__DIR__) . '/functions.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$qty       = max(1, (int)($_POST['qty'] ?? 1));

if (!$productId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid product']);
    exit;
}

addToCart($productId, $qty);
echo json_encode(['ok' => true, 'count' => cartCount()]);

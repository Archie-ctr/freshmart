<?php
require_once dirname(__DIR__) . '/functions.php';
session_start();
header('Content-Type: application/json');

$productId = (int)($_POST['product_id'] ?? 0);
$qty       = (int)($_POST['qty'] ?? 0);

updateCartQty($productId, $qty);
echo json_encode(['ok' => true, 'count' => cartCount()]);

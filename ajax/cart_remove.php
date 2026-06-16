<?php
require_once dirname(__DIR__) . '/functions.php';
session_start();
header('Content-Type: application/json');

$productId = (int)($_POST['product_id'] ?? 0);
removeFromCart($productId);
echo json_encode(['ok' => true, 'count' => cartCount()]);

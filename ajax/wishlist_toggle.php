<?php
require_once dirname(__DIR__) . '/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) { echo json_encode(['ok'=>false,'error'=>'Login required']); exit; }

$productId = (int)($_POST['product_id'] ?? 0);
if (!$productId) { echo json_encode(['ok'=>false,'error'=>'Invalid product']); exit; }

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id FROM wishlists WHERE user_id=? AND product_id=?");
    $stmt->execute([$user['id'], $productId]);
    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM wishlists WHERE user_id=? AND product_id=?")->execute([$user['id'], $productId]);
        echo json_encode(['ok'=>true,'wishlisted'=>false]);
    } else {
        $pdo->prepare("INSERT INTO wishlists (user_id,product_id) VALUES(?,?)")->execute([$user['id'], $productId]);
        echo json_encode(['ok'=>true,'wishlisted'=>true]);
    }
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>'Server error']);
}

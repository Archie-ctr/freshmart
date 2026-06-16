<?php
/**
 * AJAX: Poll Paypack to check if a transaction was successful
 * GET param: ref
 */
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/paypack.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$ref = trim($_GET['ref'] ?? '');
if (!$ref) {
    echo json_encode(['ok' => false, 'error' => 'No transaction ref']);
    exit;
}

$result = paypack_check($ref);
$status = $result['status']; // pending | successful | failed

if ($status === 'successful') {
    // Mark order as paid in DB
    $pending = $_SESSION['pending_order'] ?? null;
    if ($pending && $pending['ref'] === $ref) {
        try {
            $pdo = getDB();
            $pdo->prepare("UPDATE ecom_orders SET status='paid' WHERE id=?")
                ->execute([$pending['id']]);

            // Move to confirmed session and clear cart
            $_SESSION['last_order'] = [
                'id'      => $pending['id'],
                'addr'    => $pending['addr'],
                'total'   => $pending['total'],
                'subtotal'=> $pending['total'],
                'tax'     => 0,
                'items'   => $pending['items'],
                'rwf'     => $pending['amount'],
            ];
            unset($_SESSION['pending_order']);
            clearCart();

        } catch (Exception $e) {
            // Log but still report success to frontend
            error_log('Paypack verify DB error: ' . $e->getMessage());
        }
    }
    echo json_encode(['ok' => true, 'status' => 'successful']);
    exit;
}

if ($status === 'failed') {
    // Clean up the pending order so user can retry
    $pending = $_SESSION['pending_order'] ?? null;
    if ($pending && $pending['ref'] === $ref) {
        try {
            $pdo = getDB();
            $pdo->prepare("UPDATE ecom_orders SET status='cancelled' WHERE id=?")
                ->execute([$pending['id']]);
        } catch (Exception $e) {}
        unset($_SESSION['pending_order']);
    }
    echo json_encode(['ok' => true, 'status' => 'failed']);
    exit;
}

// Still pending
echo json_encode(['ok' => true, 'status' => 'pending']);

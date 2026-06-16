<?php
/**
 * AJAX: Poll Paypack to check if a transaction was successful
 * GET param: ref
 */
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/paypack.php';
require_once dirname(__DIR__) . '/mailer.php';

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

            // Award loyalty points if user is logged in
            $pointsEarned = 0;
            if (!empty($_SESSION['user_id'])) {
                $pointsEarned = awardLoyaltyPoints((int)$_SESSION['user_id'], $pending['id'], $pending['total']);
            }

            // Move to confirmed session and clear cart
            $lastOrder = [
                'id'           => $pending['id'],
                'email'        => $pending['addr']['email'] ?? '',
                'addr'         => $pending['addr'],
                'total'        => $pending['total'],
                'subtotal'     => $pending['total'],
                'tax'          => 0,
                'items'        => $pending['items'],
                'rwf'          => $pending['amount'],
                'points_earned'=> $pointsEarned,
                'points_total' => !empty($_SESSION['user_id']) ? getLoyaltyPoints((int)$_SESSION['user_id']) : 0,
            ];
            $_SESSION['last_order'] = $lastOrder;
            unset($_SESSION['pending_order']);
            clearCart();

            // Send order confirmation email to customer
            if (!empty($lastOrder['email'])) {
                sendOrderConfirmationEmail(
                    $lastOrder['email'],
                    $lastOrder['addr']['name'] ?? 'Customer',
                    $lastOrder
                );
            }

            // Notify admin of new order
            $adminEmail = getSetting('store_email', 'freshmartstore4@gmail.com');
            sendAdminNewOrderEmail(
                $adminEmail,
                $pending['id'],
                $pending['addr'],
                $pending['total'],
                $pending['items']
            );

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

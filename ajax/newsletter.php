<?php
// Newsletter subscribe – just store in DB or silently succeed
require_once dirname(__DIR__) . '/functions.php';
session_start();
header('Content-Type: application/json');

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
if (!$email) { echo json_encode(['ok' => false]); exit; }

// Optionally store subscriber — uses ecom_customers table
try {
    $pdo = getDB();
    $pdo->prepare(
        "INSERT INTO ecom_customers (email, name, phone) VALUES (?, '', NULL)
         ON DUPLICATE KEY UPDATE email=email"
    )->execute([$email]);
} catch (Exception $e) { /* silently ignore */ }

echo json_encode(['ok' => true]);

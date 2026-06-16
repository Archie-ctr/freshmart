<?php
// ==============================
// Database Configuration
// Works for Docker + XAMPP + InfinityFree
// ==============================

// Load production config if it exists (InfinityFree deployment)
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

define('DB_HOST', defined('CFG_DB_HOST') ? CFG_DB_HOST : (getenv('DB_HOST') ?: 'localhost'));
define('DB_USER', defined('CFG_DB_USER') ? CFG_DB_USER : (getenv('DB_USER') ?: 'root'));
define('DB_PASS', defined('CFG_DB_PASS') ? CFG_DB_PASS : (getenv('DB_PASS') ?: ''));
define('DB_NAME', defined('CFG_DB_NAME') ? CFG_DB_NAME : (getenv('DB_NAME') ?: 'freshmart'));

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {

        $attempts  = 10;
        $connected = false;

        while ($attempts > 0 && !$connected) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                $connected = true;

            } catch (PDOException $e) {
                $attempts--;
                if ($attempts > 0) {
                    sleep(2); // wait for MySQL to finish starting (Docker)
                } else {
                    die("
                        <h3 style='color:red;'>Database Connection Failed</h3>
                        <p>MySQL is not ready or credentials are incorrect.</p>
                        <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
                    ");
                }
            }
        }
    }

    return $pdo;
}

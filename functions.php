<?php
require_once __DIR__ . '/db.php';

// ── Auth helpers ──────────────────────────────────────────────
function getCurrentUser(): ?array {
    if (!empty($_SESSION['user_id'])) {
        static $cached = null;
        if ($cached === null) {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT id, email, full_name, role FROM profiles WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $cached = $stmt->fetch() ?: null;
        }
        return $cached;
    }
    return null;
}

function isAdmin(): bool {
    $u = getCurrentUser();
    return $u && $u['role'] === 'admin';
}

function requireAdmin(): void {
    if (!isAdmin()) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/login.php');
        exit;
    }
}

// ── Price helpers ─────────────────────────────────────────────
define('USD_TO_RWF', 1400);

/**
 * Returns an HTML snippet showing the price in both USD and RWF.
 */
function formatPrice(int $cents): string {
    $rate = (int)(getSetting('usd_to_rwf_rate', '1400') ?: 1400);
    $usd  = '$' . number_format($cents / 100, 2);
    $rwf  = 'RWF ' . number_format(round(($cents / 100) * $rate));
    return '<span class="dual-price">'
         . '<span class="price-usd">' . $usd . '</span>'
         . '<span class="price-rwf">' . $rwf . '</span>'
         . '</span>';
}

// ── Cart helpers (session-based) ──────────────────────────────
function getCart(): array {
    return $_SESSION['cart'] ?? [];
}

function cartCount(): int {
    return array_sum(array_column(getCart(), 'quantity'));
}

function cartSubtotal(): int {
    return array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], getCart()));
}

function addToCart(int $productId, int $qty = 1): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, name, price, images, handle FROM ecom_products WHERE id = ? AND status = "active"');
    $stmt->execute([$productId]);
    $p = $stmt->fetch();
    if (!$p) return;

    $images = json_decode($p['images'] ?? '[]', true);
    $image  = $images[0] ?? '';

    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] === $productId) {
            $item['quantity'] += $qty;
            return;
        }
    }
    unset($item);

    $_SESSION['cart'][] = [
        'product_id' => $productId,
        'name'       => $p['name'],
        'price'      => $p['price'],
        'image'      => $image,
        'quantity'   => $qty,
    ];
}

function updateCartQty(int $productId, int $qty): void {
    if (!isset($_SESSION['cart'])) return;
    if ($qty < 1) {
        removeFromCart($productId);
        return;
    }
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] === $productId) {
            $item['quantity'] = $qty;
            return;
        }
    }
}

function removeFromCart(int $productId): void {
    if (!isset($_SESSION['cart'])) return;
    $_SESSION['cart'] = array_values(
        array_filter($_SESSION['cart'], fn($i) => $i['product_id'] !== $productId)
    );
}

function clearCart(): void {
    $_SESSION['cart'] = [];
}

// ── Slug generator ────────────────────────────────────────────
function makeHandle(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-') . '-' . substr((string)time(), -4);
}

// ── HTML helpers ──────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $key, string $message = ''): string {
    if ($message !== '') {
        $_SESSION['flash'][$key] = $message;
        return '';
    }
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

// ── Settings helper ───────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = getDB()->query("SELECT setting_key, setting_val FROM shop_settings")->fetchAll();
            $cache = array_column($rows, 'setting_val', 'setting_key');
        } catch (Exception $e) { $cache = []; }
    }
    return $cache[$key] ?? $default;
}

function saveSetting(string $key, string $value): void {
    getDB()->prepare(
        "INSERT INTO shop_settings (setting_key, setting_val) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_val=VALUES(setting_val)"
    )->execute([$key, $value]);
}

function calculateTax(string $state, int $subtotalCents): int {
    $rates = [
        'CA' => 0.0725, 'NY' => 0.08, 'TX' => 0.0625, 'FL' => 0.06,
        'WA' => 0.065,  'IL' => 0.0625, 'PA' => 0.06, 'OH' => 0.0575,
    ];
    $rate = $rates[strtoupper(trim($state))] ?? 0.05;
    return (int)round($subtotalCents * $rate);
}

// ── Security: Rate limiting ─────────────────────────────────────
function checkRateLimit(string $action, int $maxHits = 10, int $windowSeconds = 60): bool {
    $ip   = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now  = date('Y-m-d H:i:s');
    $from = date('Y-m-d H:i:s', time() - $windowSeconds);
    try {
        $pdo = getDB();
        // Clean old entries
        $pdo->prepare("DELETE FROM rate_limit WHERE window_start < ?")->execute([$from]);
        // Count hits in current window
        $stmt = $pdo->prepare("SELECT SUM(hits) FROM rate_limit WHERE ip_hash=? AND action=? AND window_start>=?");
        $stmt->execute([$ip, $action, $from]);
        $hits = (int)$stmt->fetchColumn();
        if ($hits >= $maxHits) return false;
        // Insert or update
        $pdo->prepare("INSERT INTO rate_limit (ip_hash,action,hits,window_start) VALUES(?,?,1,?)
                       ON DUPLICATE KEY UPDATE hits=hits+1")->execute([$ip, $action, $now]);
        return true;
    } catch (Exception $e) { return true; } // fail open if table missing
}

// ── Analytics: track page view ─────────────────────────────────
function trackPageView(string $page, ?int $productId = null): void {
    $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    try {
        getDB()->prepare("INSERT INTO page_views (page, product_id, ip_hash) VALUES (?,?,?)")
               ->execute([$page, $productId, $ip]);
    } catch (Exception $e) {}
}

// ── CSRF Protection ─────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfVerify(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// ── Loyalty Points ────────────────────────────────────────────────────
function getLoyaltyPoints(int $userId): int {
    try {
        $stmt = getDB()->prepare('SELECT COALESCE(SUM(points),0) FROM loyalty_points WHERE user_id=?');
        $stmt->execute([$userId]);
        return max(0, (int)$stmt->fetchColumn());
    } catch (Exception $e) { return 0; }
}

function awardLoyaltyPoints(int $userId, int $orderId, int $totalCents): int {
    // 1 point per $1 spent
    $points = max(1, (int)floor($totalCents / 100));
    try {
        getDB()->prepare(
            'INSERT IGNORE INTO loyalty_points (user_id, order_id, points, note) VALUES (?,?,?,?)'
        )->execute([$userId, $orderId, $points, 'Earned from order #' . $orderId]);
    } catch (Exception $e) {}
    return $points;
}

// ── Security Audit Log ───────────────────────────────────────────────
function securityLog(string $action, ?int $userId = null, string $detail = ''): void {
    $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    try {
        getDB()->prepare(
            'INSERT INTO security_log (user_id, action, ip_hash, detail) VALUES (?,?,?,?)'
        )->execute([$userId, $action, $ip, $detail]);
    } catch (Exception $e) {}
}

// ── OTP helpers ──────────────────────────────────────────────────────
function generateOtp(int $userId, string $purpose = '2fa'): string {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $exp  = date('Y-m-d H:i:s', time() + 600); // 10 min
    $pdo  = getDB();
    $pdo->prepare("DELETE FROM otp_tokens WHERE user_id=? AND purpose=?")->execute([$userId, $purpose]);
    $pdo->prepare("INSERT INTO otp_tokens (user_id, otp_hash, purpose, expires_at) VALUES (?,?,?,?)")
        ->execute([$userId, $hash, $purpose, $exp]);
    // Send via email if mailer is loaded
    if (function_exists('sendOtpEmail') && $purpose === '2fa') {
        $u = getDB()->prepare('SELECT email, full_name FROM profiles WHERE id=?');
        $u->execute([$userId]);
        $u = $u->fetch();
        if ($u) sendOtpEmail($u['email'], $u['full_name'] ?: 'Customer', $code);
    }
    return $code;
}

function verifyOtp(int $userId, string $code, string $purpose = '2fa'): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT id, otp_hash FROM otp_tokens WHERE user_id=? AND purpose=? AND used=0 AND expires_at > NOW()'
    );
    $stmt->execute([$userId, $purpose]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($code, $row['otp_hash'])) return false;
    $pdo->prepare('UPDATE otp_tokens SET used=1 WHERE id=?')->execute([$row['id']]);
    return true;
}

// ── Flash Deals ───────────────────────────────────────────────────────
function getActiveFlashDeal(int $productId): ?array {
    try {
        $stmt = getDB()->prepare(
            'SELECT * FROM flash_deals WHERE product_id=? AND starts_at<=NOW() AND ends_at>=NOW() LIMIT 1'
        );
        $stmt->execute([$productId]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) { return null; }
}

function applyFlashDeal(int $priceCents, ?array $deal): int {
    if (!$deal) return $priceCents;
    return (int)round($priceCents * (1 - $deal['discount_pct'] / 100));
}

// ── Referrals ─────────────────────────────────────────────────────────
function getReferralCode(int $userId): string {
    return 'REF' . strtoupper(substr(hash('sha256', $userId . 'freshmart'), 0, 8));
}

function applyReferral(int $referrerId, int $newUserId): void {
    $pts = (int)(getSetting('referral_reward_points', '100') ?: 100);
    try {
        $pdo = getDB();
        $pdo->prepare(
            'INSERT IGNORE INTO referrals (referrer_id, referred_id, reward_points) VALUES (?,?,?)'
        )->execute([$referrerId, $newUserId, $pts]);
        // Award points immediately
        $pdo->prepare(
            'INSERT IGNORE INTO loyalty_points (user_id, points, note) VALUES (?,?,?)'
        )->execute([$referrerId, $pts, 'Referral bonus for inviting a friend']);
        $pdo->prepare(
            'UPDATE referrals SET rewarded=1 WHERE referrer_id=? AND referred_id=?'
        )->execute([$referrerId, $newUserId]);
    } catch (Exception $e) {}
}

// ── Vendor helpers ────────────────────────────────────────────────────
function getVendor(int $userId): ?array {
    try {
        $stmt = getDB()->prepare('SELECT * FROM vendors WHERE user_id=?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) { return null; }
}

function isApprovedVendor(): bool {
    $u = getCurrentUser();
    if (!$u) return false;
    $v = getVendor($u['id']);
    return $v && $v['status'] === 'approved';
}

// ── AI: update purchase affinity matrix ───────────────────────────────
function updateProductAffinity(int $orderId): void {
    try {
        $pdo   = getDB();
        $items = $pdo->prepare('SELECT product_id FROM ecom_order_items WHERE order_id=? AND product_id IS NOT NULL');
        $items->execute([$orderId]);
        $ids   = array_column($items->fetchAll(), 'product_id');
        foreach ($ids as $a) {
            foreach ($ids as $b) {
                if ($a === $b) continue;
                $pdo->prepare(
                    'INSERT INTO product_affinity (product_a, product_b, score) VALUES (?,?,1)
                     ON DUPLICATE KEY UPDATE score=score+1'
                )->execute([$a, $b]);
            }
        }
    } catch (Exception $e) {}
}

// ── AI Recommendations: collaborative + category based ──────────────
function getRecommendations(int $productId, string $productType, int $limit = 4): array {
    try {
        $pdo = getDB();
        // Step 1: purchase-based affinity (highest quality signal)
        $aff = $pdo->prepare(
            "SELECT product_b AS product_id, score FROM product_affinity
             WHERE product_a=? ORDER BY score DESC LIMIT ?"
        );
        $aff->execute([$productId, $limit]);
        $ids = array_column($aff->fetchAll(), 'product_id');

        // Step 2: fill with view-based collaborative filtering
        if (count($ids) < $limit) {
            $collab = $pdo->prepare(
                "SELECT pv2.product_id, COUNT(*) AS score
                 FROM page_views pv1
                 JOIN page_views pv2 ON pv2.ip_hash = pv1.ip_hash
                     AND pv2.product_id != pv1.product_id
                     AND ABS(TIMESTAMPDIFF(MINUTE, pv1.created_at, pv2.created_at)) <= 30
                 WHERE pv1.product_id = ?
                 AND pv2.product_id NOT IN (" . implode(',', array_fill(0, max(1, count($ids)+1), '?')) . ")
                 GROUP BY pv2.product_id ORDER BY score DESC LIMIT ?"
            );
            $collab->execute(array_merge([$productId], [$productId], $ids, [$limit - count($ids)]));
            $ids = array_merge($ids, array_column($collab->fetchAll(), 'product_id'));
        }

        // Step 2: fill remaining with same-category products
        $needed = $limit - count($ids);
        $exclude = array_merge([$productId], $ids);
        $placeholders = implode(',', array_fill(0, count($exclude), '?'));
        $cat = $pdo->prepare(
            "SELECT id FROM ecom_products
             WHERE product_type=? AND status='active' AND id NOT IN ($placeholders)
             ORDER BY RAND() LIMIT ?"
        );
        $cat->execute(array_merge([$productType], $exclude, [$needed]));
        $ids = array_merge($ids, array_column($cat->fetchAll(), 'id'));

        if (empty($ids)) return [];
        $pl = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM ecom_products WHERE id IN ($pl) AND status='active'");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

<?php
/**
 * Paypack API helper — pure PHP cURL, no Node.js needed
 * Base URL: https://payments.paypack.rw/api/
 */

define('PAYPACK_APP_ID',     'e1121f46-68eb-11f1-9d8c-deadd43720af');
define('PAYPACK_APP_SECRET', '497bed007d31a80fa92151a870598c50da39a3ee5e6b4b0d3255bfef95601890afd80709');
define('PAYPACK_BASE',       'https://payments.paypack.rw/api/');

/**
 * Low-level cURL wrapper for Paypack API.
 */
function paypack_request(string $method, string $endpoint, array $body = [], string $token = ''): array {
    $url = PAYPACK_BASE . $endpoint;
    $ch  = curl_init($url);

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = 'Authorization: ' . $token;

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'error' => 'cURL error: ' . $err, 'code' => 0];

    $data = json_decode($raw, true);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'data' => $data, 'raw' => $raw];
}

/**
 * Authenticate with Paypack and return access token.
 * Token is cached in session for reuse.
 */
function paypack_token(): string {
    // Return cached token if still valid
    if (!empty($_SESSION['paypack_token']) && !empty($_SESSION['paypack_token_exp'])
        && time() < $_SESSION['paypack_token_exp']) {
        return $_SESSION['paypack_token'];
    }

    $res = paypack_request('POST', 'auth/agents/authorize', [
        'client_id'     => PAYPACK_APP_ID,
        'client_secret' => PAYPACK_APP_SECRET,
    ]);

    if (!$res['ok'] || empty($res['data']['access'])) {
        throw new RuntimeException('Paypack auth failed: ' . ($res['data']['message'] ?? $res['raw']));
    }

    // Cache token for 55 minutes (tokens typically valid 1 hour)
    $_SESSION['paypack_token']     = $res['data']['access'];
    $_SESSION['paypack_token_exp'] = time() + 3300;

    return $res['data']['access'];
}

/**
 * Initiate a Mobile Money cashin (USSD push to customer phone).
 *
 * @param string $phone  Rwandan phone e.g. 0781234567 or +250781234567
 * @param int    $amount Amount in RWF (minimum 100)
 * @return array  ['ok'=>bool, 'ref'=>string, 'error'=>string]
 */
function paypack_cashin(string $phone, int $amount): array {
    try {
        $token = paypack_token();

        // Normalize phone to 07x format the API expects
        $phone = preg_replace('/^\+?250/', '0', trim($phone));

        $res = paypack_request('POST', 'transactions/cashin', [
            'number' => $phone,
            'amount' => $amount,
        ], $token);

        if (!$res['ok']) {
            $msg = $res['data']['message'] ?? $res['raw'] ?? 'Payment initiation failed';
            return ['ok' => false, 'error' => $msg];
        }

        $ref = $res['data']['ref'] ?? $res['data']['data']['ref'] ?? null;
        return ['ok' => true, 'ref' => $ref, 'data' => $res['data']];

    } catch (RuntimeException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Check transaction status by ref.
 * Queries events endpoint and returns status: pending | successful | failed
 *
 * @param string $ref Transaction reference from cashin response
 * @return array ['status'=>string, 'event'=>array|null]
 */
function paypack_check(string $ref): array {
    try {
        $token = paypack_token();

        $res = paypack_request('GET', 'events/transactions?ref=' . urlencode($ref) . '&limit=5', [], $token);

        if (!$res['ok']) {
            return ['status' => 'pending', 'error' => $res['data']['message'] ?? 'check failed'];
        }

        $transactions = $res['data']['transactions'] ?? $res['data']['data'] ?? [];

        // Look for a terminal status event
        foreach ($transactions as $tx) {
            $status = strtolower($tx['status'] ?? '');
            if ($status === 'successful' || $status === 'success') return ['status' => 'successful', 'event' => $tx];
            if ($status === 'failed')                               return ['status' => 'failed',     'event' => $tx];
        }

        return ['status' => 'pending', 'event' => $transactions[0] ?? null];

    } catch (RuntimeException $e) {
        return ['status' => 'pending', 'error' => $e->getMessage()];
    }
}

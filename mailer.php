<?php
/**
 * FreshMart Mailer — lightweight SMTP over TLS, no Composer required.
 * Uses constants from config.php: CFG_MAIL_HOST, CFG_MAIL_PORT,
 * CFG_MAIL_USER, CFG_MAIL_PASS, CFG_MAIL_FROM, CFG_MAIL_FROM_NAME
 */

function sendMail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
    // Fallback constants so it never crashes when config is missing
    $host     = defined('CFG_MAIL_HOST')      ? CFG_MAIL_HOST      : '';
    $port     = defined('CFG_MAIL_PORT')      ? (int)CFG_MAIL_PORT  : 587;
    $user     = defined('CFG_MAIL_USER')      ? CFG_MAIL_USER      : '';
    $pass     = defined('CFG_MAIL_PASS')      ? CFG_MAIL_PASS      : '';
    $from     = defined('CFG_MAIL_FROM')      ? CFG_MAIL_FROM      : $user;
    $fromName = defined('CFG_MAIL_FROM_NAME') ? CFG_MAIL_FROM_NAME : 'FreshMart';

    if (!$host || !$user || !$pass) return false;

    $textBody = $textBody ?: strip_tags($htmlBody);
    $boundary = '=_' . md5(uniqid('', true));

    // Build MIME message
    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: FreshMart/1.0\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($textBody)) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $body .= "--{$boundary}--";

    try {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]]);

        $sock = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) return false;

        $read = function() use ($sock): string { return fgets($sock, 512); };
        $send = function(string $cmd) use ($sock): void { fwrite($sock, $cmd . "\r\n"); };

        $read(); // 220 greeting
        $send("EHLO freshmart");
        while (($line = $read()) && substr($line, 3, 1) === '-');   // read multi-line EHLO

        // STARTTLS
        $send("STARTTLS");
        $read(); // 220
        stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        $send("EHLO freshmart");
        while (($line = $read()) && substr($line, 3, 1) === '-');

        $send("AUTH LOGIN");
        $read(); // 334
        $send(base64_encode($user));
        $read(); // 334
        $send(base64_encode($pass));
        $r = $read(); // 235 or error
        if (substr(trim($r), 0, 3) !== '235') { fclose($sock); return false; }

        $send("MAIL FROM:<{$from}>");
        $read();
        $send("RCPT TO:<{$to}>");
        $read();
        $send("DATA");
        $read(); // 354

        $msg  = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= $headers . "\r\n" . $body . "\r\n.";
        $send($msg);
        $read(); // 250

        $send("QUIT");
        fclose($sock);
        return true;

    } catch (Throwable $e) {
        return false;
    }
}

// ── Pre-built email templates ─────────────────────────────────

function sendOtpEmail(string $to, string $name, string $code): bool {
    $subject = 'Your FreshMart verification code';
    $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f9fafb;padding:2rem">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:.75rem;padding:2rem;box-shadow:0 1px 4px rgba(0,0,0,.08)">
  <div style="text-align:center;margin-bottom:1.5rem">
    <span style="font-size:2rem">🌿</span>
    <h2 style="margin:.5rem 0 0;color:#166534">FreshMart</h2>
  </div>
  <p style="color:#374151">Hi ' . htmlspecialchars($name) . ',</p>
  <p style="color:#374151">Your one-time verification code is:</p>
  <div style="text-align:center;margin:1.5rem 0">
    <span style="font-size:2.5rem;font-weight:700;letter-spacing:.5rem;color:#15803d;background:#f0fdf4;padding:.75rem 1.5rem;border-radius:.5rem;display:inline-block">' . $code . '</span>
  </div>
  <p style="color:#6b7280;font-size:.875rem">This code expires in <strong>10 minutes</strong>. Never share it with anyone.</p>
  <hr style="border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0">
  <p style="color:#9ca3af;font-size:.75rem;text-align:center">FreshMart · Fresh groceries delivered fast in Rwanda</p>
</div></body></html>';
    return sendMail($to, $subject, $html);
}

function sendOrderConfirmationEmail(string $to, string $name, array $order): bool {
    $orderId  = strtoupper(str_pad($order['id'], 6, '0', STR_PAD_LEFT));
    $total    = formatPrice($order['total']);
    $subject  = "Order #{$orderId} confirmed — FreshMart";

    $itemsHtml = '';
    foreach ($order['items'] as $it) {
        $unitPrice = $it['price'] ?? $it['unit_price'] ?? 0;
        $itemsHtml .= '<tr>
          <td style="padding:.4rem .75rem;border-bottom:1px solid #f3f4f6">' . htmlspecialchars($it['name'] ?? $it['product_name'] ?? '') . ' &times;' . $it['quantity'] . '</td>
          <td style="padding:.4rem .75rem;border-bottom:1px solid #f3f4f6;text-align:right">' . formatPrice($unitPrice * $it['quantity']) . '</td>
        </tr>';
    }

    $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f9fafb;padding:2rem">
<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:.75rem;padding:2rem;box-shadow:0 1px 4px rgba(0,0,0,.08)">
  <div style="text-align:center;margin-bottom:1.5rem">
    <span style="font-size:2rem">🌿</span>
    <h2 style="margin:.5rem 0 0;color:#166534">Order Confirmed!</h2>
  </div>
  <p style="color:#374151">Hi ' . htmlspecialchars($name) . ', thank you for your order!</p>
  <div style="background:#f0fdf4;border-radius:.5rem;padding:.75rem 1rem;margin-bottom:1.25rem">
    <strong style="color:#15803d">Order #' . $orderId . '</strong>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:.9rem">
    <thead><tr style="background:#f9fafb">
      <th style="padding:.4rem .75rem;text-align:left;font-weight:600;color:#374151">Item</th>
      <th style="padding:.4rem .75rem;text-align:right;font-weight:600;color:#374151">Amount</th>
    </tr></thead>
    <tbody>' . $itemsHtml . '</tbody>
    <tfoot><tr>
      <td style="padding:.75rem;font-weight:700;color:#111827">Total</td>
      <td style="padding:.75rem;font-weight:700;color:#15803d;text-align:right">' . $total . '</td>
    </tr></tfoot>
  </table>
  <p style="color:#6b7280;font-size:.875rem;margin-top:1.25rem">We&rsquo;ll have your order ready for delivery soon. Estimated: <strong>1&ndash;2 business days</strong>.</p>
  <hr style="border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0">
  <p style="color:#9ca3af;font-size:.75rem;text-align:center">FreshMart &middot; Fresh groceries delivered fast in Rwanda</p>
</div></body></html>';

    return sendMail($to, $subject, $html);
}

function sendWelcomeEmail(string $to, string $name): bool {
    $subject = 'Welcome to FreshMart! 🌿';
    $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f9fafb;padding:2rem">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:.75rem;padding:2rem;box-shadow:0 1px 4px rgba(0,0,0,.08)">
  <div style="text-align:center;margin-bottom:1.5rem">
    <span style="font-size:2.5rem">🌿</span>
    <h2 style="margin:.5rem 0 0;color:#166534">Welcome to FreshMart!</h2>
  </div>
  <p style="color:#374151">Hi ' . htmlspecialchars($name) . ',</p>
  <p style="color:#374151">Your account is ready. Shop fresh fruits, vegetables, dairy and more — delivered straight to your door.</p>
  <div style="text-align:center;margin:1.5rem 0">
    <a href="https://freshmartstore.gt.tc/shop.php"
       style="background:#16a34a;color:#fff;padding:.75rem 2rem;border-radius:.5rem;text-decoration:none;font-weight:600;display:inline-block">
      Start Shopping →
    </a>
  </div>
  <p style="color:#6b7280;font-size:.875rem">🎁 Tip: Share your referral link to earn loyalty points!</p>
  <hr style="border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0">
  <p style="color:#9ca3af;font-size:.75rem;text-align:center">FreshMart · Fresh groceries delivered fast in Rwanda</p>
</div></body></html>';
    return sendMail($to, $subject, $html);
}

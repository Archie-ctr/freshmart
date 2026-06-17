<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/mailer.php';

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    if (!checkRateLimit('contact', 3, 300)) {
        $error = 'Too many submissions. Please wait a few minutes before trying again.';
    } else {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$name)                                        $error = 'Your name is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'A valid email address is required.';
        elseif (!$subject)                                 $error = 'Please enter a subject.';
        elseif (strlen($message) < 10)                     $error = 'Message must be at least 10 characters.';
        else {
            $adminEmail = getSetting('store_email', 'archiekgonwoe@gmail.com') ?: 'archiekgonwoe@gmail.com';
            $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f9fafb;padding:2rem">
<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:.75rem;padding:2rem;box-shadow:0 1px 4px rgba(0,0,0,.08)">
  <div style="text-align:center;margin-bottom:1.25rem">
    <span style="font-size:2rem">✉️</span>
    <h2 style="margin:.5rem 0 0;color:#166534">New Contact Message</h2>
  </div>
  <p style="color:#374151;font-size:.9rem;margin:.25rem 0"><strong>Name:</strong> ' . htmlspecialchars($name) . '</p>
  <p style="color:#374151;font-size:.9rem;margin:.25rem 0"><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
  <p style="color:#374151;font-size:.9rem;margin:.25rem 0"><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>
  <div style="margin-top:1rem;background:#f9fafb;border-radius:.5rem;padding:1rem;font-size:.9rem;color:#374151;line-height:1.7">
    ' . nl2br(htmlspecialchars($message)) . '
  </div>
  <hr style="border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0">
  <p style="color:#9ca3af;font-size:.75rem;text-align:center">FreshMart Contact Form</p>
</div></body></html>';

            sendMail($adminEmail, 'Contact: ' . $subject, $html);

            // Auto-reply to sender
            $replyHtml = '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f9fafb;padding:2rem">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:.75rem;padding:2rem;box-shadow:0 1px 4px rgba(0,0,0,.08)">
  <div style="text-align:center;margin-bottom:1.5rem">
    <span style="font-size:2rem">🌿</span>
    <h2 style="margin:.5rem 0 0;color:#166534">We received your message!</h2>
  </div>
  <p style="color:#374151">Hi ' . htmlspecialchars($name) . ',</p>
  <p style="color:#374151;margin-top:.5rem">Thank you for reaching out to FreshMart. We have received your message and will get back to you within <strong>24 hours</strong>.</p>
  <div style="background:#f0fdf4;border-radius:.5rem;padding:.75rem 1rem;margin:1.25rem 0;font-size:.875rem;color:#374151">
    <strong>Your subject:</strong> ' . htmlspecialchars($subject) . '
  </div>
  <p style="color:#6b7280;font-size:.875rem">While you wait, feel free to browse our store:</p>
  <div style="text-align:center;margin-top:1.25rem">
    <a href="https://freshmartstore.gt.tc/shop.php"
       style="background:#16a34a;color:#fff;padding:.65rem 1.5rem;border-radius:.5rem;text-decoration:none;font-weight:600;display:inline-block">
      Browse Products &rarr;
    </a>
  </div>
  <hr style="border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0">
  <p style="color:#9ca3af;font-size:.75rem;text-align:center">FreshMart · Fresh groceries delivered fast in Rwanda</p>
</div></body></html>';

            sendMail($email, 'We received your message — FreshMart', $replyHtml);
            $success = true;
        }
    }
}

startPage('Contact Us');
?>

<!-- Hero -->
<div style="background:linear-gradient(135deg,var(--navy) 60%,#2d5a8e);padding:3.5rem 1rem;text-align:center;color:#fff">
  <div style="max-width:560px;margin:0 auto">
    <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(72,187,120,.2);border:1px solid rgba(72,187,120,.4);border-radius:9999px;padding:.35rem 1rem;font-size:.875rem;margin-bottom:1.25rem">
      ✉️ Get in Touch
    </div>
    <h1 style="font-size:clamp(1.75rem,4vw,2.75rem);font-weight:800;line-height:1.2;margin-bottom:.75rem">We'd Love to Hear From You</h1>
    <p style="color:rgba(255,255,255,.75);font-size:1rem;line-height:1.7">
      Have a question, feedback, or need help with an order? Send us a message and we'll get back to you within 24 hours.
    </p>
  </div>
</div>

<div class="section">
  <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:2.5rem;align-items:start">

    <!-- Contact info -->
    <div style="display:flex;flex-direction:column;gap:1.25rem">

      <div style="background:#fff;border:1px solid var(--gray-200);border-radius:1.25rem;padding:1.5rem">
        <h2 style="font-size:1.25rem;font-weight:700;color:var(--navy);margin-bottom:1.25rem">Contact Information</h2>

        <?php
        $info = [
          ['📍', 'Address',       getSetting('store_address', 'Kigali, Rwanda')],
          ['📞', 'Phone',         getSetting('store_phone',   '+250 780 000 000')],
          ['✉️', 'Email',         getSetting('store_email',   'hello@freshmart.com')],
          ['⏰', 'Working Hours', 'Mon – Sat: 7:00 AM – 8:00 PM'],
        ];
        foreach ($info as [$icon, $label, $value]):
        ?>
        <div style="display:flex;gap:1rem;align-items:flex-start;padding:.75rem 0;border-bottom:1px solid var(--gray-100)">
          <div style="width:2.5rem;height:2.5rem;background:rgba(72,187,120,.1);border-radius:.6rem;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0"><?= $icon ?></div>
          <div>
            <div style="font-size:.75rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;font-weight:600"><?= $label ?></div>
            <div style="font-weight:500;color:var(--navy);margin-top:.15rem"><?= h($value) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Quick links -->
      <div style="background:#fff;border:1px solid var(--gray-200);border-radius:1.25rem;padding:1.5rem">
        <h3 style="font-weight:600;color:var(--navy);margin-bottom:1rem">Quick Links</h3>
        <?php
        $links = [
          [BASE_URL . '/shop.php',          '🛒', 'Browse Products'],
          [BASE_URL . '/orders.php',        '📦', 'Track My Order'],
          [BASE_URL . '/about.php',         '🌿', 'About FreshMart'],
          [BASE_URL . '/register.php',      '👤', 'Create an Account'],
        ];
        foreach ($links as [$href, $icon, $label]):
        ?>
        <a href="<?= $href ?>" style="display:flex;align-items:center;gap:.75rem;padding:.6rem 0;color:var(--gray-700);border-bottom:1px solid var(--gray-100);font-size:.9rem">
          <span><?= $icon ?></span> <?= $label ?>
          <span style="margin-left:auto;color:var(--gray-400)">→</span>
        </a>
        <?php endforeach; ?>
      </div>

    </div>

    <!-- Contact form -->
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:1.25rem;padding:2rem">

      <?php if ($success): ?>
      <div style="text-align:center;padding:2rem 0">
        <div style="font-size:4rem">✅</div>
        <h2 style="font-size:1.5rem;font-weight:700;color:var(--navy);margin-top:1rem">Message Sent!</h2>
        <p style="color:var(--gray-500);margin-top:.5rem">Thank you for reaching out. We'll reply to your email within 24 hours.</p>
        <p style="color:var(--gray-400);font-size:.875rem;margin-top:.5rem">A confirmation has been sent to your email.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="btn btn-green" style="margin-top:1.5rem">Send Another Message</a>
      </div>
      <?php else: ?>

      <h2 style="font-size:1.25rem;font-weight:700;color:var(--navy);margin-bottom:1.5rem">Send Us a Message</h2>

      <?php if ($error): ?>
        <div class="alert alert-red"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= BASE_URL ?>/contact.php" class="form-group">
        <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>" />

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div>
            <label style="font-size:.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:.35rem">Your Name *</label>
            <input type="text" name="name" required placeholder="John Doe"
                   value="<?= h($_POST['name'] ?? '') ?>" />
          </div>
          <div>
            <label style="font-size:.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:.35rem">Email Address *</label>
            <input type="email" name="email" required placeholder="you@example.com"
                   value="<?= h($_POST['email'] ?? '') ?>" />
          </div>
        </div>

        <div>
          <label style="font-size:.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:.35rem">Subject *</label>
          <select name="subject">
            <?php
            $subjects = ['General Inquiry','Order Issue','Delivery Question','Product Feedback','Payment Problem','Partnership / Vendor','Other'];
            $selected = $_POST['subject'] ?? '';
            foreach ($subjects as $s):
            ?>
            <option value="<?= h($s) ?>" <?= $selected === $s ? 'selected' : '' ?>><?= h($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="font-size:.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:.35rem">Message *</label>
          <textarea name="message" required rows="5"
                    placeholder="Tell us how we can help you..."><?= h($_POST['message'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-green" style="width:100%;border-radius:.6rem">
          Send Message →
        </button>

        <p style="font-size:.78rem;color:var(--gray-400);text-align:center;margin-top:-.25rem">
          We'll reply to your email within 24 hours.
        </p>
      </form>
      <?php endif; ?>
    </div>

  </div>
</div>

<style>
@media (max-width: 768px) {
  .contact-grid { grid-template-columns: 1fr !important; }
}
</style>

<?php endPage(); ?>

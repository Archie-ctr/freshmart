<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/mailer.php';

if (getCurrentUser()) { header('Location: ' . BASE_URL . '/'); exit; }

$error = $info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    if (!checkRateLimit('forgot_password', 3, 300)) {
        $error = 'Too many requests. Please wait 5 minutes.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT id, full_name FROM profiles WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                $code = generateOtp($user['id'], 'password_reset');
                sendPasswordResetEmail($email, $user['full_name'] ?: 'Customer', $code);
            }
            // Always show success to prevent email enumeration
            $info = 'If that email is registered, a reset code has been sent.';
        }
    }
}

startPage('Forgot Password');
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="center">
      <span class="auth-icon">🔑</span>
      <h1>Forgot Password</h1>
      <p>Enter your email and we'll send a reset code</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-red"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($info): ?>
      <div class="alert alert-green"><?= h($info) ?></div>
      <p class="info-msg" style="text-align:center">
        <a href="<?= BASE_URL ?>/reset-password.php" class="link-green">Enter your reset code →</a>
      </p>
    <?php else: ?>
    <form method="post" action="<?= BASE_URL ?>/forgot-password.php" class="form-group">
      <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>" />
      <input type="email" name="email" required placeholder="Your account email"
             value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email" />
      <button type="submit" class="btn btn-green">Send Reset Code</button>
    </form>
    <?php endif; ?>

    <p class="info-msg">
      <a href="<?= BASE_URL ?>/login.php" class="link-green">← Back to Sign In</a>
    </p>
  </div>
</div>

<?php endPage(); ?>

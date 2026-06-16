<?php
require_once __DIR__ . '/layout.php';

if (getCurrentUser()) { header('Location: ' . BASE_URL . '/'); exit; }

$error = '';
$step  = 'code'; // 'code' or 'password'
$uid   = (int)($_SESSION['reset_uid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    if (!empty($_POST['code']) && empty($_POST['new_password'])) {
        // Step 1 — verify code
        $email = trim($_POST['email'] ?? '');
        $code  = trim($_POST['code']  ?? '');
        $pdo   = getDB();
        $stmt  = $pdo->prepare('SELECT id FROM profiles WHERE email = ?');
        $stmt->execute([$email]);
        $user  = $stmt->fetch();
        if ($user && verifyOtp((int)$user['id'], $code, 'password_reset')) {
            $_SESSION['reset_uid']   = $user['id'];
            $_SESSION['reset_email'] = $email;
            $step = 'password';
            $uid  = $user['id'];
        } else {
            $error = 'Invalid or expired code. Please try again.';
        }
    } elseif (!empty($_POST['new_password']) && $uid) {
        // Step 2 — save new password
        $pass    = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters.';
            $step  = 'password';
        } elseif ($pass !== $confirm) {
            $error = 'Passwords do not match.';
            $step  = 'password';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            getDB()->prepare('UPDATE profiles SET password=? WHERE id=?')->execute([$hash, $uid]);
            securityLog('password_reset', $uid, $_SESSION['reset_email'] ?? '');
            unset($_SESSION['reset_uid'], $_SESSION['reset_email']);
            flash('success', 'Password updated! Please sign in with your new password.');
            header('Location: ' . BASE_URL . '/login.php'); exit;
        }
    }
} elseif ($uid) {
    $step = 'password';
}

startPage('Reset Password');
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="center">
      <span class="auth-icon">🔑</span>
      <h1>Reset Password</h1>
      <p><?= $step === 'password' ? 'Choose a new password' : 'Enter the 6-digit code from your email' ?></p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-red"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 'password'): ?>
    <form method="post" action="<?= BASE_URL ?>/reset-password.php" class="form-group">
      <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>" />
      <div class="pw-wrap" style="position:relative">
        <input type="password" name="new_password" id="new_password" required minlength="6"
               placeholder="New password (min 6 chars)" autocomplete="new-password" />
        <button type="button" class="pw-eye" onclick="togglePw('new_password',this)" tabindex="-1">👁</button>
      </div>
      <div class="pw-wrap" style="position:relative">
        <input type="password" name="confirm_password" id="confirm_password" required minlength="6"
               placeholder="Confirm new password" autocomplete="new-password" />
        <button type="button" class="pw-eye" onclick="togglePw('confirm_password',this)" tabindex="-1">👁</button>
      </div>
      <button type="submit" class="btn btn-green">Set New Password</button>
    </form>
    <?php else: ?>
    <form method="post" action="<?= BASE_URL ?>/reset-password.php" class="form-group">
      <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>" />
      <input type="email" name="email" required placeholder="Your email address"
             value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email" />
      <input type="text" name="code" required maxlength="6" placeholder="6-digit reset code"
             style="letter-spacing:.4rem;font-size:1.25rem;text-align:center"
             autocomplete="one-time-code" inputmode="numeric" />
      <button type="submit" class="btn btn-green">Verify Code</button>
    </form>
    <?php endif; ?>

    <p class="info-msg">
      <a href="<?= BASE_URL ?>/forgot-password.php" class="link-green">← Request a new code</a>
    </p>
  </div>
</div>

<style>
.pw-eye {
  position:absolute; right:.75rem; top:50%; transform:translateY(-50%);
  background:none; border:none; cursor:pointer; font-size:1rem; padding:0; line-height:1;
}
</style>
<script>
function togglePw(id, btn) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
  btn.textContent = el.type === 'password' ? '👁' : '🙈';
}
</script>

<?php endPage(); ?>

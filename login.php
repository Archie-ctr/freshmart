<?php
require_once __DIR__ . '/layout.php';

if (getCurrentUser()) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    if (!checkRateLimit('login', 5, 60)) {
        $error = 'Too many login attempts. Please wait 1 minute.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM profiles WHERE email = ?");
        $stmt->execute([$email]);
        $row  = $stmt->fetch();
        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            securityLog('login', $row['id'], $email);
            header('Location: ' . BASE_URL . '/');
            exit;
        } else {
            $error = 'Invalid email or password.';
            securityLog('login_fail', null, $email);
        }
    }
}

startPage('Sign In');
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="center">
      <span class="auth-icon">🌿</span>
      <h1>Welcome Back</h1>
      <p>Sign in to your FreshMart account</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-red"><?= h($error) ?></div>
    <?php endif; ?>
    <?php $success = flash('success'); if ($success): ?>
      <div class="alert alert-green"><?= h($success) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/login.php" class="form-group">
      <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>" />
      <input type="email" name="email" required placeholder="Email"
             value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email" />

      <div class="pw-wrap" style="position:relative">
        <input type="password" name="password" id="login-pw" required
               placeholder="Password" autocomplete="current-password" />
        <button type="button" class="pw-eye" onclick="togglePw('login-pw',this)" tabindex="-1">👁</button>
      </div>

      <div style="text-align:right;margin-top:-.25rem;margin-bottom:.25rem">
        <a href="<?= BASE_URL ?>/forgot-password.php" class="link-green" style="font-size:.85rem">Forgot password?</a>
      </div>

      <button type="submit" class="btn btn-green">Sign In</button>
    </form>

    <p class="info-msg">No account? <a href="<?= BASE_URL ?>/register.php" class="link-green">Create one</a></p>
    <p class="info-msg" style="font-size:.8rem;color:#9ca3af">Demo: admin@freshmart.com / admin123</p>
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

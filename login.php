<?php
require_once __DIR__ . '/layout.php';

if (getCurrentUser()) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            header('Location: ' . BASE_URL . '/');
            exit;
        } else {
            $error = 'Invalid email or password.';
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
      <input type="email"    name="email"    required placeholder="Email"
             value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email" />
      <input type="password" name="password" required placeholder="Password" autocomplete="current-password" />
      <button type="submit" class="btn btn-green">Sign In</button>
    </form>

    <p class="info-msg">No account? <a href="<?= BASE_URL ?>/register.php" class="link-green">Create one</a></p>
    <p class="info-msg">Admin demo: admin@freshmart.com / admin123</p>
  </div>
</div>

<?php endPage(); ?>

<?php
require_once __DIR__ . '/layout.php';

if (getCurrentUser()) {
    header('Location: /store-php/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name)    $error = 'Full name is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Valid email is required.';
    elseif (strlen($password) < 6) $error = 'Password must be at least 6 characters.';
    else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $role = ($email === 'admin@freshmart.com') ? 'admin' : 'customer';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                "INSERT INTO profiles (email, full_name, password, role) VALUES (?, ?, ?, ?)"
            )->execute([$email, $name, $hash, $role]);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            header('Location: /store-php/');
            exit;
        }
    }
}

startPage('Create Account');
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="center">
      <span class="auth-icon">🌿</span>
      <h1>Create Account</h1>
      <p>Join FreshMart for fresh deliveries</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-red"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/store-php/register.php" class="form-group">
      <input type="text"     name="name"     required placeholder="Full Name"
             value="<?= h($_POST['name'] ?? '') ?>" autocomplete="name" />
      <input type="email"    name="email"    required placeholder="Email"
             value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email" />
      <input type="password" name="password" required minlength="6"
             placeholder="Password (min 6 chars)" autocomplete="new-password" />
      <button type="submit" class="btn btn-green">Create Account</button>
    </form>

    <p class="info-msg">Have an account? <a href="/store-php/login.php" class="link-green">Sign in</a></p>
  </div>
</div>

<?php endPage(); ?>

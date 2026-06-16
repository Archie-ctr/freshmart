<?php
require_once __DIR__ . '/layout.php';

if (getCurrentUser()) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$error  = '';
$refCode = trim($_GET['ref'] ?? $_POST['ref'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
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
            $newId = (int)$pdo->lastInsertId();
            $_SESSION['user_id'] = $newId;
            securityLog('register', $newId, $email);
            // Apply referral if code is valid
            if ($refCode) {
                $allUsers = $pdo->query('SELECT id FROM profiles')->fetchAll(PDO::FETCH_COLUMN);
                foreach ($allUsers as $uid) {
                    if (getReferralCode((int)$uid) === $refCode) {
                        applyReferral((int)$uid, $newId);
                        break;
                    }
                }
            }
            header('Location: ' . BASE_URL . '/');
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

    <form method="post" action="<?= BASE_URL ?>/register.php" class="form-group">
      <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>" />
      <input type="hidden" name="ref"   value="<?= h($refCode) ?>" />
      <input type="text"     name="name"     required placeholder="Full Name"
             value="<?= h($_POST['name'] ?? '') ?>" autocomplete="name" />
      <input type="email"    name="email"    required placeholder="Email"
             value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email" />
      <input type="password" name="password" required minlength="6"
             placeholder="Password (min 6 chars)" autocomplete="new-password" />
      <button type="submit" class="btn btn-green">Create Account</button>
    </form>

    <p class="info-msg">Have an account? <a href="<?= BASE_URL ?>/login.php" class="link-green">Sign in</a></p>
  </div>
</div>

<?php endPage(); ?>

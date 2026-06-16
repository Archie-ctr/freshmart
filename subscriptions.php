<?php
require_once __DIR__ . '/layout.php';
$user = getCurrentUser();
$pdo  = getDB();

// Handle subscribe / cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $act   = $_POST['action'] ?? '';
    $boxId = (int)($_POST['box_id'] ?? 0);
    if ($act === 'subscribe' && $boxId) {
        $box = $pdo->prepare('SELECT * FROM subscription_boxes WHERE id=? AND is_active=1');
        $box->execute([$boxId]);
        $box = $box->fetch();
        if ($box) {
            $freq = ['weekly'=>'+1 week','biweekly'=>'+2 weeks','monthly'=>'+1 month'];
            $next = date('Y-m-d H:i:s', strtotime($freq[$box['frequency']] ?? '+1 week'));
            $pdo->prepare(
                'INSERT INTO subscriptions (user_id,box_id,next_billing) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE status="active", next_billing=?'
            )->execute([$user['id'], $boxId, $next, $next]);
            flash('success', 'Subscribed to ' . $box['name'] . '!');
        }
    } elseif ($act === 'cancel') {
        $pdo->prepare("UPDATE subscriptions SET status='cancelled' WHERE user_id=? AND box_id=?")
            ->execute([$user['id'], $boxId]);
        flash('success', 'Subscription cancelled.');
    }
    header('Location: ' . BASE_URL . '/subscriptions.php'); exit;
}

$boxes = $pdo->query('SELECT * FROM subscription_boxes WHERE is_active=1 ORDER BY price_cents ASC')->fetchAll();

// User's active subscriptions
$mySubs = [];
if ($user) {
    $s = $pdo->prepare('SELECT box_id, status, next_billing FROM subscriptions WHERE user_id=?');
    $s->execute([$user['id']]);
    foreach ($s->fetchAll() as $row) $mySubs[$row['box_id']] = $row;
}

startPage('📦 Subscription Boxes');
$msg = flash('success');
?>

<div class="section">
  <h1 class="section-title">📦 Subscription Boxes</h1>
  <p style="color:var(--gray-500);margin-top:-.5rem;margin-bottom:2rem">
    Never run out — get fresh groceries delivered on your schedule. Pause or cancel any time.
  </p>

  <?php if ($msg): ?><div class="alert" style="background:#f0fdf4;color:#15803d;padding:.8rem 1rem;border-radius:.5rem;margin-bottom:1.5rem">✅ <?= h($msg) ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem">
    <?php foreach ($boxes as $box):
      $sub = $mySubs[$box['id']] ?? null;
      $active = $sub && $sub['status'] === 'active';
      $freqLabel = ['weekly'=>'Every week','biweekly'=>'Every 2 weeks','monthly'=>'Every month'];
    ?>
    <div class="card" style="border:2px solid <?= $active ? 'var(--green)' : 'var(--gray-200)' ?>;border-radius:.8rem;padding:1.5rem">
      <div style="font-size:2.5rem;margin-bottom:.75rem">
        <?= $box['name'] === 'Fresh Fruit Box' ? '🍎' : ($box['name'] === 'Veggie Boost Box' ? '🥦' : '🛒') ?>
      </div>
      <h3 style="margin:0 0 .4rem"><?= h($box['name']) ?></h3>
      <p style="color:var(--gray-500);font-size:.875rem;margin:0 0 1rem"><?= h($box['description'] ?? '') ?></p>
      <div style="font-size:1.4rem;font-weight:700;color:var(--green)"><?= formatPrice($box['price_cents']) ?></div>
      <div style="font-size:.82rem;color:var(--gray-400);margin-bottom:1.25rem"><?= $freqLabel[$box['frequency']] ?? $box['frequency'] ?></div>

      <?php if ($active): ?>
        <div style="font-size:.8rem;color:var(--green);margin-bottom:.75rem">✅ Active · Next: <?= date('M j', strtotime($sub['next_billing'])) ?></div>
        <form method="post">
          <input type="hidden" name="action"  value="cancel">
          <input type="hidden" name="box_id"  value="<?= $box['id'] ?>">
          <button class="btn btn-outline btn-sm" type="submit" style="width:100%" onclick="return confirm('Cancel subscription?')">Cancel Subscription</button>
        </form>
      <?php elseif ($user): ?>
        <form method="post">
          <input type="hidden" name="action"  value="subscribe">
          <input type="hidden" name="box_id"  value="<?= $box['id'] ?>">
          <button class="btn btn-green" type="submit" style="width:100%">Subscribe →</button>
        </form>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-green" style="display:block;text-align:center;width:100%">Sign in to Subscribe</a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php endPage(); ?>

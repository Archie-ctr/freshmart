<?php
require_once __DIR__ . '/layout.php';

$order = $_SESSION['last_order'] ?? null;
if (!$order) {
    header('Location: ' . BASE_URL . '/shop.php');
    exit;
}
unset($_SESSION['last_order']);

// Update AI purchase-affinity matrix
if (!empty($order['id'])) {
    updateProductAffinity($order['id']);
}

startPage('Order Confirmed');
?>

<div class="confirm-wrap">
  <div class="confirm-icon">✅</div>
  <h1>Order Confirmed!</h1>
  <p>Thank you for your order. We'll have it ready for delivery soon.</p>
  <?php if ($order['id']): ?>
    <p class="confirm-order-id">Order ID: <span style="font-family:monospace"><?= strtoupper(substr(str_pad($order['id'], 8, '0', STR_PAD_LEFT), 0, 8)) ?></span></p>
  <?php endif; ?>

  <div class="order-detail-card">
    <div style="display:flex;align-items:center;gap:.5rem;color:var(--navy);font-weight:600;margin-bottom:1rem">
      📦 Order Details
    </div>
    <?php foreach ($order['items'] as $i): ?>
    <div class="order-item-row" style="display:flex;justify-content:space-between;font-size:.875rem;padding:.25rem 0">
      <span><?= h($i['name']) ?> × <?= $i['quantity'] ?></span>
      <span><?= formatPrice($i['price'] * $i['quantity']) ?></span>
    </div>
    <?php endforeach; ?>
    <div style="border-top:1px solid var(--gray-200);margin-top:.75rem;padding-top:.75rem;display:flex;justify-content:space-between;font-weight:700;color:var(--navy)">
      <span>Total</span><span><?= formatPrice($order['total']) ?></span>
    </div>
    <?php if ($order['addr']): $a = $order['addr']; ?>
    <div style="margin-top:1rem;font-size:.875rem;color:var(--gray-500)">
      <p style="color:var(--gray-700);font-weight:500">Delivering to:</p>
      <p><?= h($a['name']) ?></p>
      <p><?= h($a['address']) ?>, <?= h($a['city']) ?></p>
      <p style="margin-top:.5rem;color:var(--green)">Estimated delivery: 1-2 business days</p>
    </div>
    <?php endif; ?>
  </div>

  <div class="confirm-btns">
    <a href="<?= BASE_URL ?>/shop.php" class="btn btn-green">Continue Shopping</a>
    <a href="<?= BASE_URL ?>/orders.php" class="btn btn-outline">My Orders</a>
  </div>

  <?php if (!empty($order['points_earned'])): ?>
  <div class="loyalty-banner">
    <span class="loyalty-star">⭐</span>
    <div>
      <strong>+<?= $order['points_earned'] ?> points earned!</strong>
      <span>You now have <strong><?= $order['points_total'] ?></strong> loyalty points.</span>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php endPage(); ?>

<?php
require_once __DIR__ . '/layout.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: /store-php/login.php');
    exit;
}

$pdo   = getDB();
$stmt  = $pdo->prepare(
    "SELECT o.*, c.name as cust_name, c.email as cust_email
     FROM ecom_orders o
     JOIN ecom_customers c ON c.id = o.customer_id
     WHERE c.email = ?
     ORDER BY o.created_at DESC"
);
$stmt->execute([$user['email']]);
$orders = $stmt->fetchAll();

// Load items for each order
foreach ($orders as &$o) {
    $s = $pdo->prepare("SELECT * FROM ecom_order_items WHERE order_id=?");
    $s->execute([$o['id']]);
    $o['items'] = $s->fetchAll();
}
unset($o);

startPage('My Orders');
?>

<div class="section">
  <h1 class="section-title">My Orders</h1>

  <?php if (empty($orders)): ?>
  <div class="empty-state">
    <div class="empty-icon">📦</div>
    <h2 style="font-size:1.4rem">No orders yet.</h2>
    <a href="/store-php/shop.php" style="display:inline-block;margin-top:1rem;color:var(--green);font-weight:500">Start shopping</a>
  </div>
  <?php else: ?>
  <div class="space-y-lg">
    <?php foreach ($orders as $o):
      $addr = json_decode($o['shipping_address'] ?? '{}', true);
    ?>
    <div class="order-card">
      <div class="order-card-header">
        <div>
          <div class="order-id">Order #<?= strtoupper(substr(str_pad($o['id'], 8, '0', STR_PAD_LEFT), 0, 8)) ?></div>
          <div class="order-date"><?= date('M j, Y', strtotime($o['created_at'])) ?></div>
        </div>
        <span class="status-badge"><?= h($o['status']) ?></span>
      </div>
      <div class="order-items-list">
        <?php foreach ($o['items'] as $it): ?>
        <div class="order-item-row">
          <span><?= h($it['product_name']) ?> × <?= $it['quantity'] ?></span>
          <span><?= formatPrice($it['total']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="order-total-row">
        <span>Total</span>
        <span><?= formatPrice($o['total']) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php endPage(); ?>

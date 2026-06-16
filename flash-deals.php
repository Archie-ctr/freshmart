<?php
require_once __DIR__ . '/layout.php';
startPage('⚡ Flash Deals');
trackPageView('flash-deals');

$pdo = getDB();
$deals = $pdo->query(
    "SELECT fd.*, p.name, p.handle, p.price, p.images, p.product_type, p.description, p.inventory_qty,
            fd.discount_pct
     FROM flash_deals fd
     JOIN ecom_products p ON p.id = fd.product_id
     WHERE fd.starts_at <= NOW() AND fd.ends_at >= NOW() AND p.status='active'
     ORDER BY fd.ends_at ASC"
)->fetchAll();
?>

<div class="section">
  <h1 class="section-title">⚡ Flash Deals</h1>
  <p style="color:var(--gray-500);margin-top:-.5rem;margin-bottom:2rem">
    Limited-time offers — grab them before they're gone!
  </p>

  <?php if (empty($deals)): ?>
    <div class="empty-state">
      <div class="empty-icon">⚡</div>
      <h2>No active flash deals right now</h2>
      <p>Check back soon for time-limited offers.</p>
      <a href="<?= BASE_URL ?>/shop.php" class="btn btn-green" style="margin-top:1rem">Browse Products</a>
    </div>
  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($deals as $d):
      $imgs = json_decode($d['images'] ?? '[]', true);
      $img  = $imgs[0] ?? '';
      $salePrice = applyFlashDeal($d['price'], $d);
    ?>
    <a href="<?= BASE_URL ?>/product.php?handle=<?= h($d['handle']) ?>" class="product-card" style="position:relative">
      <div class="product-card-img">
        <img src="<?= h($img) ?>" alt="<?= h($d['name']) ?>" loading="lazy" />
        <span class="product-badge" style="background:#ef4444">⚡ <?= $d['discount_pct'] ?>% OFF</span>
      </div>
      <div class="product-card-body">
        <span class="product-type"><?= h($d['product_type'] ?? '') ?></span>
        <h3 class="product-name"><?= h($d['name']) ?></h3>
        <p class="product-desc"><?= h($d['description'] ?? '') ?></p>
        <div class="product-footer">
          <span>
            <span class="product-price"><?= formatPrice($salePrice) ?></span>
            <span style="text-decoration:line-through;font-size:.8rem;color:var(--gray-400);margin-left:.3rem"><?= formatPrice($d['price']) ?></span>
          </span>
          <button class="add-btn" onclick="event.preventDefault();addToCart(<?= $d['id'] ?>)" aria-label="Add to cart">+</button>
        </div>
        <div style="font-size:.78rem;color:#ef4444;margin-top:.4rem">
          ⏱ Ends: <?= date('M j, g:ia', strtotime($d['ends_at'])) ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php endPage(); ?>

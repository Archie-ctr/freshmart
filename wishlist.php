<?php
require_once __DIR__ . '/layout.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare(
    "SELECT p.*, w.created_at AS wishlisted_at
     FROM wishlists w
     JOIN ecom_products p ON p.id = w.product_id
     WHERE w.user_id = ? AND p.status = 'active'
     ORDER BY w.created_at DESC"
);
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();

startPage('My Wishlist');
?>

<div class="section">
  <h1 class="section-title">❤️ My Wishlist</h1>

  <?php if (empty($items)): ?>
  <div class="empty-state">
    <div class="empty-icon">❤️</div>
    <h2>Your wishlist is empty</h2>
    <p>Save products you love to find them easily later.</p>
    <a href="<?= BASE_URL ?>/shop.php" class="btn btn-green" style="margin-top:1rem">Browse Products</a>
  </div>
  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($items as $p):
      $images = json_decode($p['images'] ?? '[]', true);
      $img    = $images[0] ?? '';
    ?>
    <a href="<?= BASE_URL ?>/product.php?handle=<?= h($p['handle']) ?>" class="product-card">
      <div class="product-card-img">
        <img src="<?= h($img) ?>" alt="<?= h($p['name']) ?>" loading="lazy" />
      </div>
      <div class="product-card-body">
        <span class="product-type"><?= h($p['product_type'] ?? '') ?></span>
        <h3 class="product-name"><?= h($p['name']) ?></h3>
        <p class="product-desc"><?= h($p['description'] ?? '') ?></p>
        <div class="product-footer">
          <span class="product-price"><?= formatPrice($p['price']) ?></span>
          <button class="add-btn" onclick="event.preventDefault();addToCart(<?= $p['id'] ?>)" aria-label="Add to cart">+</button>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php endPage(); ?>

<?php
require_once __DIR__ . '/layout.php';

$handle = trim($_GET['handle'] ?? '');
if (!$handle) { header('Location: /store-php/shop.php'); exit; }

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM ecom_collections WHERE handle = ?");
$stmt->execute([$handle]);
$col  = $stmt->fetch();

if (!$col) {
    startPage('Not Found');
    echo '<div class="section"><div class="empty-state"><div class="empty-icon">😕</div><h2>Collection not found</h2><a href="/store-php/shop.php" class="btn btn-green" style="margin-top:1rem">Back to shop</a></div></div>';
    endPage();
    exit;
}

// Products via junction table — with avg rating
$stmt = $pdo->prepare(
    "SELECT p.*, ROUND(AVG(r.rating),1) AS avg_rating, COUNT(r.id) AS review_count
     FROM ecom_products p
     JOIN ecom_product_collections pc ON pc.product_id = p.id
     LEFT JOIN product_reviews r ON r.product_id = p.id
     WHERE pc.collection_id = ? AND p.status = 'active'
     GROUP BY p.id
     ORDER BY pc.position, p.name"
);
$stmt->execute([$col['id']]);
$products = $stmt->fetchAll();

$catIcons = [
    'Fruits'=>'🍎','Vegetables'=>'🥦','Dairy'=>'🥛',
    'Beverages'=>'🧃','Bakery'=>'🍞','Snacks'=>'🍿',
];
$icon = $catIcons[$col['title']] ?? '🛒';

startPage($col['title']);
?>

<div class="section">
  <div class="collection-header">
    <div class="collection-hero">
      <span class="collection-hero-icon"><?= $icon ?></span>
      <div>
        <h1><?= h($col['title']) ?></h1>
        <p><?= h($col['description'] ?? '') ?> · <strong><?= count($products) ?></strong> product<?= count($products) !== 1 ? 's' : '' ?></p>
      </div>
    </div>
  </div>

  <?php if (empty($products)): ?>
    <div class="empty-state">
      <div class="empty-icon"><?= $icon ?></div>
      <h2>No products here yet</h2>
      <a href="/store-php/shop.php" class="btn btn-green" style="margin-top:1rem">Browse all products</a>
    </div>
  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $p):
      $images = json_decode($p['images'] ?? '[]', true);
      $img    = $images[0] ?? '';
      $tags   = json_decode($p['tags'] ?? '[]', true);
    ?>
    <a href="/store-php/product.php?handle=<?= h($p['handle']) ?>" class="product-card">
      <div class="product-card-img">
        <img src="<?= h($img) ?>" alt="<?= h($p['name']) ?>" loading="lazy" />
        <?php if (in_array('featured', $tags)): ?>
          <span class="product-badge">Featured</span>
        <?php endif; ?>
      </div>
      <div class="product-card-body">
        <span class="product-type"><?= h($p['product_type'] ?? '') ?></span>
        <h3 class="product-name"><?= h($p['name']) ?></h3>
        <?php if ($p['review_count'] > 0): ?>
        <div class="product-stars">
          <span class="star-filled">★</span>
          <?= $p['avg_rating'] ?> <span>(<?= $p['review_count'] ?>)</span>
        </div>
        <?php endif; ?>
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

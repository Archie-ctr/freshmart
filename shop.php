<?php
require_once __DIR__ . '/layout.php';

$q          = trim($_GET['q'] ?? '');
$typeFilter = $_GET['type'] ?? 'all';
$sort       = $_GET['sort'] ?? 'default';

$pdo = getDB();

$where  = ["p.status = 'active'"];
$params = [];

if ($q !== '') {
    $where[]  = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($typeFilter !== 'all') {
    $where[]  = "p.product_type = ?";
    $params[] = $typeFilter;
}

$orderBy = match($sort) {
    'price-asc'  => 'p.price ASC',
    'price-desc' => 'p.price DESC',
    'name'       => 'p.name ASC',
    default      => 'p.created_at DESC',
};

$sql = "SELECT p.*, ROUND(AVG(r.rating),1) AS avg_rating, COUNT(r.id) AS review_count
        FROM ecom_products p
        LEFT JOIN product_reviews r ON r.product_id = p.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY p.id
        ORDER BY $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$types = $pdo->query(
    "SELECT DISTINCT product_type FROM ecom_products WHERE status='active' AND product_type IS NOT NULL ORDER BY product_type"
)->fetchAll(PDO::FETCH_COLUMN);

$catIcons = [
    'Fruits'=>'🍎','Vegetables'=>'🥦','Dairy'=>'🥛',
    'Beverages'=>'🧃','Bakery'=>'🍞','Snacks'=>'🍿',
];

$title = $q ? "Search: \"$q\"" : 'All Products';
startPage($title);
trackPageView('shop');
?>

<div class="section">
  <h1 class="section-title"><?= h($title) ?></h1>
  <p class="product-count"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?></p>

  <div class="shop-layout">
    <aside class="shop-sidebar">
      <h3>Categories</h3>
      <div class="filter-btns">
        <a href="<?= BASE_URL ?>/shop.php?<?= $q ? 'q='.urlencode($q).'&' : '' ?>sort=<?= h($sort) ?>"
           class="filter-btn <?= $typeFilter === 'all' ? 'active' : '' ?>">🛒 All</a>
        <?php foreach ($types as $t): ?>
        <a href="<?= BASE_URL ?>/shop.php?<?= $q ? 'q='.urlencode($q).'&' : '' ?>type=<?= urlencode($t) ?>&sort=<?= h($sort) ?>"
           class="filter-btn <?= $typeFilter === $t ? 'active' : '' ?>">
          <?= $catIcons[$t] ?? '' ?> <?= h($t) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </aside>

    <div class="shop-main">
      <div class="shop-top">
        <form method="get" action="<?= BASE_URL ?>/shop.php">
          <?php if ($q): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
          <?php if ($typeFilter !== 'all'): ?><input type="hidden" name="type" value="<?= h($typeFilter) ?>"><?php endif; ?>
          <select name="sort" onchange="this.form.submit()">
            <option value="default"   <?= $sort === 'default'   ? 'selected' : '' ?>>Sort: Latest</option>
            <option value="price-asc" <?= $sort === 'price-asc' ? 'selected' : '' ?>>Price: Low → High</option>
            <option value="price-desc"<?= $sort === 'price-desc'? 'selected' : '' ?>>Price: High → Low</option>
            <option value="name"      <?= $sort === 'name'      ? 'selected' : '' ?>>Name: A–Z</option>
          </select>
        </form>
      </div>

      <?php if (empty($products)): ?>
        <div class="empty-state">
          <div class="empty-icon">🔍</div>
          <h2>No products found</h2>
          <p>Try a different search or category.</p>
          <a href="<?= BASE_URL ?>/shop.php" class="btn btn-green" style="margin-top:1rem">Clear filters</a>
        </div>
      <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $p):
          $images = json_decode($p['images'] ?? '[]', true);
          $img    = $images[0] ?? '';
          $tags   = json_decode($p['tags'] ?? '[]', true);
        ?>
        <a href="<?= BASE_URL ?>/product.php?handle=<?= h($p['handle']) ?>" class="product-card">
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
  </div>
</div>

<?php endPage(); ?>

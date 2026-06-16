<?php
require_once __DIR__ . '/layout.php';
startPage('Home');
trackPageView('home');

$featured = [];
$collections = [];

try {
    $pdo = getDB();

    // Featured products with rating
    $featured = $pdo->query(
        "SELECT p.*, ROUND(AVG(r.rating),1) AS avg_rating, COUNT(r.id) AS review_count
         FROM ecom_products p
         LEFT JOIN product_reviews r ON r.product_id = p.id
         WHERE p.status='active'
         AND p.tags IS NOT NULL
         AND JSON_CONTAINS(p.tags, '\"featured\"')
         GROUP BY p.id
         ORDER BY p.created_at DESC
         LIMIT 8"
    )->fetchAll();

    // Collections
    $collections = $pdo->query(
        "SELECT * FROM ecom_collections
         WHERE is_visible = 1
         ORDER BY title"
    )->fetchAll();

} catch (Exception $e) {
    // Prevent 500 error in Docker startup phase
    $featured = [];
    $collections = [];
}

// Category emoji map
$catIcons = [
    'Fruits'     => '🍎',
    'Vegetables' => '🥦',
    'Dairy'      => '🥛',
    'Beverages'  => '🧃',
    'Bakery'     => '🍞',
    'Snacks'     => '🍿',
];
?>

<!-- ================= HERO SECTION ================= -->
<section class="hero">
  <img class="hero-img"
       src="<?= h(getSetting('hero_image_url','https://d64gsuwffb70l.cloudfront.net/6a2866e45ccfcbde90098277_1781032877059_964d5e34.jpg')) ?>"
       alt="Fresh groceries" />

  <div class="hero-overlay"></div>

  <div class="hero-content">
    <div class="hero-badge">🌿 Farm Fresh Daily</div>

    <h1><?= h(getSetting('hero_title','Fresh Groceries, Delivered Fast')) ?></h1>

    <p><?= h(getSetting('hero_subtitle','Shop quality fruits, vegetables, dairy and more — delivered straight to your door with free shipping.')) ?></p>

    <div class="hero-btns">
      <a href="<?= BASE_URL ?>/shop.php" class="btn btn-green">
        <?= h(getSetting('hero_btn1_text','Shop Now')) ?>
      </a>

      <a href="<?= BASE_URL ?>/collection.php?handle=fruits" class="btn btn-white">
        <?= h(getSetting('hero_btn2_text','Browse Fruits')) ?>
      </a>
    </div>
  </div>
</section>

<!-- ================= BENEFITS BAR ================= -->
<div class="benefits-bar">
  <div class="benefits-card">

    <div class="benefit-item">
      <span class="benefit-icon">🚚</span>
      <div>
        <div class="benefit-title">Free Shipping</div>
        <div class="benefit-text">On all orders, no minimum</div>
      </div>
    </div>

    <div class="benefit-item">
      <span class="benefit-icon">✅</span>
      <div>
        <div class="benefit-title">Quality Guarantee</div>
        <div class="benefit-text">Fresh or your money back</div>
      </div>
    </div>

    <div class="benefit-item">
      <span class="benefit-icon">⚡</span>
      <div>
        <div class="benefit-title">Same-Day Delivery</div>
        <div class="benefit-text">Order before 2pm</div>
      </div>
    </div>

  </div>
</div>

<!-- ================= CATEGORIES ================= -->
<div class="section">
  <h2 class="section-title">Shop by Category</h2>

  <div class="category-grid">
    <?php foreach ($collections as $c):
      $icon = $catIcons[$c['title']] ?? '🛒';
    ?>
      <a href="<?= BASE_URL ?>/collection.php?handle=<?= h($c['handle']) ?>" class="category-card">
        <span class="category-card-icon"><?= $icon ?></span>
        <?= h($c['title']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ================= FEATURED PRODUCTS ================= -->
<div class="section" style="padding-top:0">
  <div class="section-header">
    <h2 class="section-title" style="margin:0">Featured Products</h2>
    <a href="<?= BASE_URL ?>/shop.php">View all →</a>
  </div>

  <div class="product-grid">

    <?php foreach ($featured as $p):
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

          <?php if (!empty($p['review_count'])): ?>
            <div class="product-stars">
              <span class="star-filled">★</span>
              <?= $p['avg_rating'] ?>
              <span>(<?= $p['review_count'] ?>)</span>
            </div>
          <?php endif; ?>

          <p class="product-desc"><?= h($p['description'] ?? '') ?></p>

          <div class="product-footer">
            <span class="product-price"><?= formatPrice($p['price']) ?></span>

            <button class="add-btn"
              onclick="event.preventDefault();addToCart(<?= (int)$p['id'] ?>)"
                    aria-label="Add to cart">
              +
            </button>
          </div>

        </div>
      </a>

    <?php endforeach; ?>

  </div>
</div>

<?php endPage(); ?>
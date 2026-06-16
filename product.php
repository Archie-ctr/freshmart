<?php
require_once __DIR__ . '/layout.php';

$handle = trim($_GET['handle'] ?? '');
if (!$handle) { header('Location: ' . BASE_URL . '/shop.php'); exit; }

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM ecom_products WHERE handle = ? AND status = 'active'");
$stmt->execute([$handle]);
$p = $stmt->fetch();

if (!$p) {
    startPage('Not Found');
    echo '<div class="section"><p>Product not found.</p></div>';
    endPage();
    exit;
}

$images  = json_decode($p['images'] ?? '[]', true);
$img     = $images[0] ?? '';
$inStock = ($p['inventory_qty'] === null || $p['inventory_qty'] > 0);
$user    = getCurrentUser();

$stmt = $pdo->prepare("SELECT * FROM ecom_products WHERE product_type=? AND status='active' AND id<>? LIMIT 4");
$stmt->execute([$p['product_type'], $p['id']]);
$related = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM product_reviews WHERE product_id=? ORDER BY created_at DESC");
$stmt->execute([$p['id']]);
$reviews = $stmt->fetchAll();

$avgRating = count($reviews) ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$myReview  = null;
if ($user) {
    foreach ($reviews as $r) {
        if ($r['user_id'] == $user['id']) { $myReview = $r; break; }
    }
}

$reviewError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    if (!$user) {
        $reviewError = 'You must be signed in to leave a review.';
    } else {
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            $reviewError = 'Please select a star rating.';
        } else {
            $pdo->prepare(
                "INSERT INTO product_reviews (product_id, user_id, author_name, rating, comment)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment)"
            )->execute([$p['id'], $user['id'], $user['full_name'] ?: $user['email'], $rating, $comment]);
            header("Location: " . BASE_URL . "/product.php?handle=" . urlencode($handle) . "#reviews");
            exit;
        }
    }
}

startPage($p['name']);
?>

<div class="section">
  <div class="breadcrumb">
    <a href="<?= BASE_URL ?>/shop.php">← Back to shop</a>
  </div>

  <div class="product-detail-grid">
    <div class="product-detail-img">
      <img src="<?= h($img) ?>" alt="<?= h($p['name']) ?>" />
    </div>
    <div>
      <div class="product-detail-type"><?= h($p['product_type'] ?? '') ?></div>
      <h1 class="product-detail-name"><?= h($p['name']) ?></h1>
      <div class="product-detail-price"><?= formatPrice($p['price']) ?></div>
      <div class="stock-badge <?= $inStock ? 'in' : 'out' ?>"><?= $inStock ? 'In Stock' : 'Out of Stock' ?></div>
      <p class="product-detail-desc"><?= h($p['description'] ?? '') ?></p>

      <form onsubmit="handleAddToCart(event, <?= $p['id'] ?>)" class="qty-add-row">
        <div class="qty-control">
          <button type="button" onclick="changeQty(-1)">−</button>
          <span class="qty-value" id="qty">1</span>
          <button type="button" onclick="changeQty(1)">+</button>
        </div>
        <button type="submit" class="btn btn-green" <?= $inStock ? '' : 'disabled' ?> style="flex:1" id="add-btn">
          🛒 <?= $inStock ? 'Add to Cart' : 'Out of Stock' ?>
        </button>
      </form>
    </div>
  </div>

  <div class="reviews-section" id="reviews">
    <div class="reviews-header">
      <h2>Reviews</h2>
      <?php if (count($reviews) > 0): ?>
      <div class="avg-stars">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <span style="color:<?= $i <= round($avgRating) ? 'var(--yellow)' : 'var(--gray-300)' ?>">★</span>
        <?php endfor; ?>
        <strong><?= number_format($avgRating, 1) ?></strong>
        <span style="color:var(--gray-400);font-size:.85rem">(<?= count($reviews) ?>)</span>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($user): ?>
    <div class="review-form">
      <p><?= $myReview ? 'Update your review' : 'Review ' . h($p['name']) ?></p>
      <?php if ($reviewError): ?>
        <div class="alert alert-red"><?= h($reviewError) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= BASE_URL ?>/product.php?handle=<?= h($handle) ?>#reviews">
        <input type="hidden" name="action" value="review" />
        <div class="stars-input" aria-label="Rating">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="star <?= ($myReview && $i <= $myReview['rating']) ? 'active' : '' ?>" data-val="<?= $i ?>">★</span>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="rating" id="rating-val" value="<?= $myReview['rating'] ?? 0 ?>" />
        <textarea name="comment" placeholder="Share your thoughts…" style="margin-top:.75rem"><?= h($myReview['comment'] ?? '') ?></textarea>
        <button type="submit" class="btn btn-green btn-sm" style="margin-top:.75rem">Submit Review</button>
      </form>
    </div>
    <?php else: ?>
    <div class="review-form" style="background:var(--gray-50)">
      <p style="color:var(--gray-600)"><a href="<?= BASE_URL ?>/login.php" class="link-green">Sign in</a> to leave a review.</p>
    </div>
    <?php endif; ?>

    <div class="space-y">
      <?php if (empty($reviews)): ?>
        <p style="color:var(--gray-500);font-size:.875rem">No reviews yet. Be the first!</p>
      <?php endif; ?>
      <?php foreach ($reviews as $r): ?>
      <div class="review-card">
        <div class="review-card-header">
          <span class="review-author"><?= h($r['author_name'] ?? 'Customer') ?></span>
          <span class="review-date"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
        </div>
        <div class="star-display">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="star <?= $i <= $r['rating'] ? 'on' : '' ?>">★</span>
          <?php endfor; ?>
        </div>
        <?php if ($r['comment']): ?>
          <p class="review-comment"><?= h($r['comment']) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!empty($related)): ?>
  <div style="margin-top:4rem">
    <h2 class="section-title">Related Products</h2>
    <div class="product-grid">
      <?php foreach ($related as $rp):
        $rimgs = json_decode($rp['images'] ?? '[]', true);
        $rimg  = $rimgs[0] ?? '';
      ?>
      <a href="<?= BASE_URL ?>/product.php?handle=<?= h($rp['handle']) ?>" class="product-card">
        <div class="product-card-img">
          <img src="<?= h($rimg) ?>" alt="<?= h($rp['name']) ?>" loading="lazy" />
        </div>
        <div class="product-card-body">
          <span class="product-type"><?= h($rp['product_type'] ?? '') ?></span>
          <h3 class="product-name"><?= h($rp['name']) ?></h3>
          <p class="product-desc"><?= h($rp['description'] ?? '') ?></p>
          <div class="product-footer">
            <span class="product-price"><?= formatPrice($rp['price']) ?></span>
            <button class="add-btn" onclick="event.preventDefault();addToCart(<?= $rp['id'] ?>)" aria-label="Add to cart">+</button>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
let qty = 1;
function changeQty(delta) {
  qty = Math.max(1, qty + delta);
  document.getElementById('qty').textContent = qty;
}
function handleAddToCart(e, id) {
  e.preventDefault();
  const btn = document.getElementById('add-btn');
  addToCart(id, qty);
  btn.textContent = '✓ Added!';
  setTimeout(() => { btn.innerHTML = '🛒 Add to Cart'; }, 2000);
}
</script>

<?php endPage(); ?>

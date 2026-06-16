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

// Track page view for analytics + AI recommendations
trackPageView('product', $p['id']);

$images  = json_decode($p['images'] ?? '[]', true);
$img     = $images[0] ?? '';
$inStock = ($p['inventory_qty'] === null || $p['inventory_qty'] > 0);
$user    = getCurrentUser();

// Wishlist status
$wishlisted = false;
if ($user) {
    $ws = $pdo->prepare("SELECT id FROM wishlists WHERE user_id=? AND product_id=?");
    $ws->execute([$user['id'], $p['id']]);
    $wishlisted = (bool)$ws->fetch();
}

// AI-powered recommendations
$recommended = getRecommendations($p['id'], $p['product_type']);

// Flash deal
$deal      = getActiveFlashDeal($p['id']);
$salePrice = $deal ? applyFlashDeal($p['price'], $deal) : $p['price'];

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
      <div class="product-detail-price">
        <?= formatPrice($salePrice) ?>
        <?php if ($deal): ?>
          <span style="text-decoration:line-through;font-size:.9rem;color:var(--gray-400);margin-left:.4rem"><?= formatPrice($p['price']) ?></span>
          <span style="background:#ef4444;color:#fff;font-size:.72rem;padding:.15rem .45rem;border-radius:.3rem;margin-left:.4rem">⚡ <?= $deal['discount_pct'] ?>% OFF</span>
          <div style="font-size:.78rem;color:#ef4444;margin-top:.25rem">Flash deal ends <?= date('M j, g:ia', strtotime($deal['ends_at'])) ?></div>
        <?php endif ?>
      </div>
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
        <!-- Wishlist button -->
        <?php if ($user): ?>
        <button type="button" id="wish-btn"
          onclick="toggleWishlist(<?= $p['id'] ?>)"
          class="btn btn-outline" style="padding:.75rem 1rem"
          title="<?= $wishlisted ? 'Remove from wishlist' : 'Add to wishlist' ?>">
          <?= $wishlisted ? '❤️' : '🤍' ?>
        </button>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline" style="padding:.75rem 1rem" title="Login to wishlist">🤍</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- ── AI Recommendations ── -->
  <?php if (!empty($recommended)): ?>
  <div style="margin-top:4rem">
    <h2 class="section-title">🤖 Customers Also Viewed</h2>
    <div class="product-grid">
      <?php foreach ($recommended as $rp):
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

  <!-- ── Reviews ── -->
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
function toggleWishlist(id) {
  fetch('<?= BASE_URL ?>/ajax/wishlist_toggle.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'product_id=' + id
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      const btn = document.getElementById('wish-btn');
      btn.textContent = data.wishlisted ? '❤️' : '🤍';
      showToast(data.wishlisted ? 'Added to wishlist!' : 'Removed from wishlist');
    }
  });
}
</script>

<?php endPage(); ?>

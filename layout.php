<?php
require_once __DIR__ . '/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// BASE_URL is defined in db.php (loaded via functions.php)

// Call startPage() at the top of every page, endPage() at the bottom
function startPage(string $title = 'FreshMart'): void {
    $user  = getCurrentUser();
    $count = cartCount();
    $pdo   = getDB();
    $cols  = $pdo->query("SELECT id, title, handle FROM ecom_collections WHERE is_visible=1 ORDER BY title")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($title) ?> – <?= h(getSetting('store_name','FreshMart')) ?></title>
  <meta name="description" content="<?= h(getSetting('meta_description','')) ?>" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css" />
  <?php if(getSetting('google_analytics')): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= h(getSetting('google_analytics')) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= h(getSetting('google_analytics')) ?>');</script>
  <?php endif ?>
</head>
<body>
<header class="site-header">
<?php if(getSetting('announcement_show','1')==='1'): ?>
  <div class="announcement-bar"><?= h(getSetting('announcement_text', 'Free shipping on all orders — Fresh groceries delivered to your door')) ?></div>
<?php endif ?>
  <div class="header-inner">
    <a href="<?= BASE_URL ?>/" class="logo">
      <?php $logoUrl = getSetting('logo_url'); if ($logoUrl): ?>
        <img src="<?= h($logoUrl) ?>" alt="<?= h(getSetting('store_name','FreshMart')) ?>" class="logo-img" style="height:40px;max-width:160px;object-fit:contain" />
      <?php else: ?>
        <span class="logo-icon">🌿</span>
        <span class="logo-text"><?= h(getSetting('store_name','FreshMart')) ?></span>
      <?php endif; ?>
    </a>

    <form action="<?= BASE_URL ?>/shop.php" method="get" class="search-form desktop-only">
      <input type="text" name="q" placeholder="Search products…" value="<?= h($_GET['q'] ?? '') ?>" />
      <button type="submit">🔍</button>
    </form>

    <div class="header-actions">
      <div class="account-wrap">
        <button class="account-btn" onclick="toggleMenu(this)">
          👤 <span class="desktop-only"><?= $user ? h(explode(' ', $user['full_name'])[0] ?: 'Account') : 'Account' ?></span>
        </button>
        <div class="account-dropdown" style="display:none">
          <?php if (!$user): ?>
            <a href="<?= BASE_URL ?>/login.php">Sign In</a>
            <a href="<?= BASE_URL ?>/register.php">Create Account</a>
          <?php else: ?>
            <div class="dropdown-email"><?= h($user['email']) ?></div>
            <?php $pts = getLoyaltyPoints($user['id']); if ($pts > 0): ?>
            <div class="dropdown-points">⭐ <?= $pts ?> points</div>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/orders.php">My Orders</a>
            <a href="<?= BASE_URL ?>/wishlist.php">❤️ Wishlist</a>
            <a href="<?= BASE_URL ?>/referral.php">🎁 Refer & Earn</a>
            <a href="<?= BASE_URL ?>/subscriptions.php">📦 Subscriptions</a>
            <a href="<?= BASE_URL ?>/vendor.php">🏪 Vendor Shop</a>
            <?php if ($user['role'] === 'admin'): ?>
              <a href="<?= BASE_URL ?>/admin.php">⚙ Admin</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/logout.php" class="danger">Sign Out</a>
          <?php endif; ?>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/cart.php" class="cart-btn">
        🛒<?php if ($count > 0): ?><span class="cart-badge"><?= $count ?></span><?php endif; ?>
      </a>
      <button class="mobile-menu-btn" onclick="toggleMobileMenu()" aria-label="Menu">☰</button>
    </div>
  </div>

  <nav class="main-nav desktop-only">
    <a href="<?= BASE_URL ?>/shop.php">All Products</a>
    <?php foreach ($cols as $c): ?>
      <a href="<?= BASE_URL ?>/collection.php?handle=<?= h($c['handle']) ?>"><?= h($c['title']) ?></a>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/flash-deals.php" style="color:#ef4444;font-weight:600">⚡ Flash Deals</a>
    <a href="<?= BASE_URL ?>/subscriptions.php">📦 Subscriptions</a>
  </nav>

  <div class="mobile-nav" id="mobile-nav" style="display:none">
    <form action="<?= BASE_URL ?>/shop.php" method="get" class="search-form">
      <input type="text" name="q" placeholder="Search…" value="<?= h($_GET['q'] ?? '') ?>" />
      <button type="submit">🔍</button>
    </form>
    <a href="<?= BASE_URL ?>/shop.php">All Products</a>
    <?php foreach ($cols as $c): ?>
      <a href="<?= BASE_URL ?>/collection.php?handle=<?= h($c['handle']) ?>"><?= h($c['title']) ?></a>
    <?php endforeach; ?>
  </div>
</header>
<main class="main-content">
<?php
}

function endPage(): void {
?>
</main>
<footer class="site-footer">

  <!-- Top wave divider -->
  <div class="footer-wave" aria-hidden="true">
    <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
      <path d="M0,30 C360,60 1080,0 1440,30 L1440,0 L0,0 Z" fill="#f9fafb"/>
    </svg>
  </div>

  <div class="footer-body">
    <div class="footer-inner">

      <!-- Brand column -->
      <div class="footer-col footer-brand">
        <a href="<?= BASE_URL ?>/" class="footer-logo">
          <?php $logoUrl = getSetting('logo_url'); if ($logoUrl): ?>
            <img src="<?= h($logoUrl) ?>" alt="<?= h(getSetting('store_name','FreshMart')) ?>" style="height:36px;max-width:140px;object-fit:contain" />
          <?php else: ?>
            <span class="footer-logo-icon">🌿</span>
            <span class="footer-logo-text"><?= h(getSetting('store_name','FreshMart')) ?></span>
          <?php endif; ?>
        </a>
        <p class="footer-tagline">Fresh groceries delivered to your door. Quality you can trust, prices you'll love.</p>

        <!-- Trust badges -->
        <div class="footer-badges">
          <div class="footer-badge"><span>🚚</span> Free Shipping</div>
          <div class="footer-badge"><span>✅</span> Quality Guarantee</div>
          <div class="footer-badge"><span>⚡</span> Same-Day Delivery</div>
        </div>
      </div>

      <!-- Shop links -->
      <div class="footer-col">
        <h4 class="footer-heading">Shop</h4>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>/shop.php">All Products</a></li>
          <li><a href="<?= BASE_URL ?>/collection.php?handle=fruits">🍎 Fruits</a></li>
          <li><a href="<?= BASE_URL ?>/collection.php?handle=vegetables">🥦 Vegetables</a></li>
          <li><a href="<?= BASE_URL ?>/collection.php?handle=dairy">🥛 Dairy</a></li>
          <li><a href="<?= BASE_URL ?>/collection.php?handle=beverages">🧃 Beverages</a></li>
          <li><a href="<?= BASE_URL ?>/collection.php?handle=bakery">🍞 Bakery</a></li>
          <li><a href="<?= BASE_URL ?>/collection.php?handle=snacks">🍿 Snacks</a></li>
        </ul>
      </div>

      <!-- Account links -->
      <div class="footer-col">
        <h4 class="footer-heading">Account</h4>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>/login.php">Sign In</a></li>
          <li><a href="<?= BASE_URL ?>/register.php">Create Account</a></li>
          <li><a href="<?= BASE_URL ?>/orders.php">My Orders</a></li>
          <li><a href="<?= BASE_URL ?>/wishlist.php">❤️ Wishlist</a></li>
          <li><a href="<?= BASE_URL ?>/cart.php">View Cart</a></li>
          <li><a href="<?= BASE_URL ?>/admin.php">Admin Dashboard</a></li>
        </ul>

        <h4 class="footer-heading" style="margin-top:1.75rem">Support</h4>
        <ul class="footer-links">
          <li><a href="#">Help Center</a></li>
          <li><a href="#">Returns Policy</a></li>
          <li><a href="#">Privacy Policy</a></li>
        </ul>
      </div>

      <!-- Newsletter -->
      <div class="footer-col footer-newsletter-col">
        <h4 class="footer-heading">Stay Fresh</h4>
        <p class="footer-newsletter-desc">Get weekly deals and seasonal picks delivered to your inbox.</p>
        <div id="newsletter-wrap">
          <form id="newsletter-form" class="footer-nl-form" onsubmit="subscribeNewsletter(event)">
            <div class="footer-nl-field">
              <span class="footer-nl-icon">✉</span>
              <input type="email" id="nl-email" required placeholder="Your email address" />
            </div>
            <div class="footer-nl-field">
              <span class="footer-nl-icon">📱</span>
              <input type="tel" id="nl-phone" placeholder="Phone number (optional)" />
            </div>
            <label class="footer-sms-label">
              <input type="checkbox" id="nl-sms" checked />
              <span>Text me updates. Msg &amp; data rates may apply. Reply STOP to unsubscribe.</span>
            </label>
            <button type="submit" class="footer-nl-btn">Subscribe &rarr;</button>
          </form>
        </div>
      </div>

    </div>
  </div>

  <!-- Bottom bar -->
  <div class="footer-bottom">
    <div class="footer-bottom-inner">
      <span>© <?= date('Y') ?> FreshMart. All rights reserved.</span>
      <div class="footer-bottom-links">
        <a href="#">Terms</a>
        <a href="#">Privacy</a>
        <a href="#">Cookies</a>
      </div>
      <div class="footer-social">
        <a href="#" aria-label="Facebook"  class="social-btn">f</a>
        <a href="#" aria-label="Instagram" class="social-btn">in</a>
        <a href="#" aria-label="Twitter"   class="social-btn">𝕏</a>
      </div>
    </div>
  </div>

</footer>

<script>const BASE_URL = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/assets/app.js"></script>
</body>
</html>
<?php
}

<?php
require_once __DIR__ . '/layout.php';
startPage('Cart');

$cart     = getCart();
$subtotal = cartSubtotal();
?>

<div class="section">
  <h1 class="section-title">Shopping Cart</h1>

  <?php if (empty($cart)): ?>
  <div class="empty-state">
    <div class="empty-icon">🛒</div>
    <h2>Your cart is empty</h2>
    <p>Add some fresh products to get started.</p>
    <a href="<?= BASE_URL ?>/shop.php" class="btn btn-green">Shop Now</a>
  </div>
  <?php else: ?>
  <div class="cart-layout">
    <div class="space-y">
      <?php foreach ($cart as $item): ?>
      <div class="cart-item">
        <img class="cart-item-img" src="<?= h($item['image'] ?? '') ?>" alt="<?= h($item['name']) ?>" />
        <div class="cart-item-info">
          <div class="cart-item-name"><?= h($item['name']) ?></div>
          <div class="cart-item-price"><?= formatPrice($item['price']) ?></div>
        </div>
        <div class="qty-control">
          <button type="button" onclick="updateQty(<?= $item['product_id'] ?>, <?= $item['quantity'] - 1 ?>)">−</button>
          <span class="qty-value"><?= $item['quantity'] ?></span>
          <button type="button" onclick="updateQty(<?= $item['product_id'] ?>, <?= $item['quantity'] + 1 ?>)">+</button>
        </div>
        <span class="cart-item-line"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
        <button class="cart-remove" onclick="removeFromCart(<?= $item['product_id'] ?>)" aria-label="Remove">🗑</button>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="order-summary">
      <h2>Order Summary</h2>
      <div class="summary-row"><span class="label">Subtotal</span><span><?= formatPrice($subtotal) ?></span></div>
      <div class="summary-row"><span class="label">Shipping</span><span class="free">Free</span></div>
      <div class="summary-row"><span class="label">Tax</span><span style="color:var(--gray-400)">Calculated at checkout</span></div>
      <div class="summary-row total"><span>Total</span><span><?= formatPrice($subtotal) ?></span></div>
      <a href="<?= BASE_URL ?>/checkout.php" class="btn btn-green" style="width:100%;margin-top:1.5rem;display:flex">Proceed to Checkout</a>
      <a href="<?= BASE_URL ?>/shop.php" style="display:block;text-align:center;margin-top:.75rem;color:var(--green);font-size:.9rem">Continue Shopping</a>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php endPage(); ?>

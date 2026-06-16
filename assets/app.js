// FreshMart – Client-side JavaScript

// ── Account dropdown ──────────────────────────────────────────
function toggleMenu(btn) {
  const dd = btn.nextElementSibling;
  const isOpen = dd.style.display !== 'none';
  // close all dropdowns first
  document.querySelectorAll('.account-dropdown').forEach(d => d.style.display = 'none');
  dd.style.display = isOpen ? 'none' : 'block';
}
document.addEventListener('click', function(e) {
  if (!e.target.closest('.account-wrap')) {
    document.querySelectorAll('.account-dropdown').forEach(d => d.style.display = 'none');
  }
});

// ── Mobile nav ────────────────────────────────────────────────
function toggleMobileMenu() {
  const nav = document.getElementById('mobile-nav');
  if (!nav) return;
  nav.style.display = nav.style.display === 'none' ? 'flex' : 'none';
}

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, type = 'green') {
  const el = document.createElement('div');
  el.className = `alert alert-${type}`;
  el.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;min-width:220px;box-shadow:0 4px 16px rgba(0,0,0,.15);';
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}

// ── Add to cart (AJAX) ────────────────────────────────────────
function addToCart(productId, qty = 1) {
  fetch('/store-php/ajax/cart_add.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `product_id=${productId}&qty=${qty}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      showToast('Added to cart!');
      // update badge
      document.querySelectorAll('.cart-badge').forEach(b => b.textContent = data.count);
      if (data.count > 0) {
        document.querySelectorAll('.cart-btn').forEach(b => {
          if (!b.querySelector('.cart-badge')) {
            const badge = document.createElement('span');
            badge.className = 'cart-badge';
            badge.textContent = data.count;
            b.appendChild(badge);
          }
        });
      }
    } else {
      showToast(data.error || 'Error', 'red');
    }
  })
  .catch(() => showToast('Network error', 'red'));
}

// ── Cart quantity update (AJAX) ───────────────────────────────
function updateQty(productId, qty) {
  fetch('/store-php/ajax/cart_update.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `product_id=${productId}&qty=${qty}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) location.reload();
  });
}

// ── Cart remove ───────────────────────────────────────────────
function removeFromCart(productId) {
  fetch('/store-php/ajax/cart_remove.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `product_id=${productId}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) location.reload();
  });
}

// ── Admin modal ───────────────────────────────────────────────
function openModal(id) {
  const m = document.getElementById(id);
  if (m) m.style.display = 'flex';
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.style.display = 'none';
}

// ── Admin product form: populate for edit ─────────────────────
function editProduct(data) {
  const form = document.getElementById('product-form');
  if (!form) return;
  form.querySelector('[name=id]').value          = data.id || '';
  form.querySelector('[name=name]').value        = data.name || '';
  form.querySelector('[name=price]').value       = data.price_dollars || '';
  form.querySelector('[name=product_type]').value = data.product_type || 'Fruits';
  form.querySelector('[name=image]').value       = data.image || '';
  form.querySelector('[name=inventory_qty]').value = data.inventory_qty || 0;
  form.querySelector('[name=description]').value = data.description || '';
  form.querySelector('[name=featured]').checked  = data.featured == 1;
  document.getElementById('modal-title').textContent = data.id ? 'Edit Product' : 'Add Product';
  openModal('product-modal');
}
function openNewProduct() {
  editProduct({});
}

// ── Star rating input ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.stars-input').forEach(container => {
    const stars = container.querySelectorAll('.star');
    const input = container.nextElementSibling; // hidden input
    stars.forEach((star, i) => {
      star.addEventListener('mouseenter', () => {
        stars.forEach((s, j) => s.classList.toggle('active', j <= i));
      });
      star.addEventListener('click', () => {
        if (input) input.value = i + 1;
        stars.forEach((s, j) => {
          s.classList.toggle('active', j <= i);
          s.dataset.selected = j <= i ? '1' : '0';
        });
      });
    });
    container.addEventListener('mouseleave', () => {
      const val = parseInt(input ? input.value : '0') || 0;
      stars.forEach((s, j) => s.classList.toggle('active', j < val));
    });
  });
});

// ── Newsletter subscribe ──────────────────────────────────────
function subscribeNewsletter(e) {
  e.preventDefault();
  const email = document.getElementById('nl-email').value;
  const phone = document.getElementById('nl-phone').value;
  const sms   = document.getElementById('nl-sms').checked;
  fetch('/store-php/ajax/newsletter.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}&sms=${sms ? 1 : 0}`
  })
  .then(() => {
    document.getElementById('newsletter-wrap').innerHTML = '<p style="color:#48bb78;font-size:.9rem">Thanks for subscribing!</p>';
  });
}

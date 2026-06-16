<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/paypack.php';

$cart = getCart();
if (empty($cart)) {
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

$user      = getCurrentUser();
$subtotal  = cartSubtotal();
$amountRwf = (int) round(($subtotal / 100) * USD_TO_RWF);

startPage('Checkout');
?>

<div class="section">
  <h1 class="section-title">Checkout</h1>

  <div class="checkout-grid">

    <!-- LEFT: two-step form -->
    <div>

      <!-- Step 1: Delivery details -->
      <div class="card" id="step-delivery">
        <div class="checkout-step-head">
          <span class="checkout-step-num">1</span>
          <h2>Delivery Details</h2>
        </div>
        <div class="form-group" id="delivery-form">
          <input type="text"  id="co-name"     required placeholder="Full Name"
                 value="<?= h($user['full_name'] ?? '') ?>" />
          <input type="email" id="co-email"    required placeholder="Email address"
                 value="<?= h($user['email'] ?? '') ?>" />
          <input type="text"  id="co-address"  required placeholder="Street Address" />
          <div class="form-row-2">
            <input type="text" id="co-city"     required placeholder="City / Town" />
            <input type="text" id="co-district" placeholder="District (optional)" />
          </div>
          <div id="delivery-error" class="alert alert-red" style="display:none"></div>
          <button class="btn btn-navy" onclick="goToPayment()" style="width:100%">
            Continue to Payment →
          </button>
        </div>
      </div>

      <!-- Step 2: Mobile Money payment (hidden until step 1 done) -->
      <div class="card" id="step-payment" style="display:none;margin-top:1rem">
        <div class="checkout-step-head">
          <span class="checkout-step-num">2</span>
          <h2>Mobile Money Payment</h2>
        </div>

        <!-- Payment idle state -->
        <div id="pay-idle">
          <div class="paypack-logos">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/93/New-mtn-logo.jpg/200px-New-mtn-logo.jpg"
                 alt="MTN MoMo" class="momo-logo" />
            <span style="color:var(--gray-400);font-size:.9rem">or</span>
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/4/49/Airtel_logo_%282010%29.svg/200px-Airtel_logo_%282010%29.svg.png"
                 alt="Airtel Money" class="momo-logo" />
          </div>
          <p class="paypack-hint">Enter your MTN MoMo or Airtel Money number. You will receive a USSD prompt to approve the payment.</p>

          <div class="paypack-amount-display">
            <span class="paypack-amount-rwf">RWF <?= number_format($amountRwf) ?></span>
            <span class="paypack-amount-usd"><?= formatPrice($subtotal) ?></span>
          </div>

          <div class="paypack-phone-wrap">
            <span class="paypack-phone-flag">🇷🇼 +250</span>
            <input type="tel" id="co-phone" placeholder="07X XXX XXXX"
                   maxlength="13" pattern="^(\+?25)?(078|079|075|073|072)\d{7}$" />
          </div>

          <div id="payment-error" class="alert alert-red" style="display:none;margin-top:.75rem"></div>

          <button class="btn btn-paypack" id="pay-btn" onclick="initiatePayment()" style="width:100%;margin-top:1rem">
            <span id="pay-btn-text">💳 Pay RWF <?= number_format($amountRwf) ?></span>
          </button>
          <button onclick="backToDelivery()" style="display:block;margin-top:.75rem;font-size:.85rem;color:var(--gray-500);text-align:center;background:none;border:none;cursor:pointer;width:100%">
            ← Edit delivery details
          </button>
        </div>

        <!-- Waiting for USSD confirmation -->
        <div id="pay-waiting" style="display:none;text-align:center;padding:2rem 1rem">
          <div class="paypack-spinner"></div>
          <h3 style="color:var(--navy);margin-top:1.25rem">Check your phone!</h3>
          <p style="color:var(--gray-500);margin-top:.5rem">
            A USSD payment prompt has been sent to<br>
            <strong id="waiting-phone"></strong>
          </p>
          <p style="color:var(--gray-400);font-size:.85rem;margin-top:.75rem">
            Approve the payment on your phone, then wait here…
          </p>
          <div class="paypack-timer" id="poll-timer">Checking in <span id="timer-count">5</span>s</div>
          <button onclick="cancelPayment()" class="btn btn-outline btn-sm" style="margin-top:1.5rem">Cancel</button>
        </div>

        <!-- Payment result states (filled by JS) -->
        <div id="pay-success" style="display:none;text-align:center;padding:2rem 1rem">
          <div style="font-size:4rem">✅</div>
          <h3 style="color:var(--green);margin-top:.75rem">Payment Successful!</h3>
          <p style="color:var(--gray-500)">Redirecting to your order confirmation…</p>
        </div>
        <div id="pay-failed" style="display:none;text-align:center;padding:2rem 1rem">
          <div style="font-size:4rem">❌</div>
          <h3 style="color:var(--red);margin-top:.75rem">Payment Failed</h3>
          <p style="color:var(--gray-500);margin-top:.5rem">The transaction was declined or timed out.</p>
          <button onclick="resetPayment()" class="btn btn-green" style="margin-top:1.25rem">Try Again</button>
        </div>
      </div>
    </div>

    <!-- RIGHT: Order summary -->
    <div>
      <div class="order-summary">
        <h2>Order Summary</h2>
        <div style="max-height:16rem;overflow-y:auto;margin-bottom:1rem" class="space-y">
          <?php foreach ($cart as $i): ?>
          <div class="checkout-item">
            <img src="<?= h($i['image'] ?? '') ?>" alt="<?= h($i['name']) ?>" />
            <div style="flex:1">
              <strong><?= h($i['name']) ?></strong>
              <br><small style="color:var(--gray-400)">Qty: <?= $i['quantity'] ?></small>
            </div>
            <span><?= formatPrice($i['price'] * $i['quantity']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="summary-row"><span class="label">Subtotal</span><span><?= formatPrice($subtotal) ?></span></div>
        <div class="summary-row"><span class="label">Shipping</span><span class="free">Free</span></div>
        <div class="summary-divider"></div>
        <div class="summary-row total"><span>Total (USD)</span><span><?= formatPrice($subtotal) ?></span></div>
        <div class="summary-row total" style="font-size:1.1rem;color:var(--green)">
          <span>Total (RWF)</span><span>RWF <?= number_format($amountRwf) ?></span>
        </div>
        <div class="paypack-secure-badge">
          <span>🔒</span> Secured by Paypack · MTN MoMo &amp; Airtel Money
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL_JS = '<?= BASE_URL ?>';
// Collected delivery data
let deliveryData = {};
let currentRef   = null;
let pollTimer    = null;
let pollCount    = 0;
const MAX_POLLS  = 36; // 36 × 5s = 3 minutes timeout

function goToPayment() {
  const name     = document.getElementById('co-name').value.trim();
  const email    = document.getElementById('co-email').value.trim();
  const address  = document.getElementById('co-address').value.trim();
  const city     = document.getElementById('co-city').value.trim();
  const district = document.getElementById('co-district').value.trim();
  const errEl    = document.getElementById('delivery-error');

  errEl.style.display = 'none';

  if (!name)                              return showDeliveryError('Full name is required.');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showDeliveryError('Valid email is required.');
  if (!address)                           return showDeliveryError('Street address is required.');
  if (!city)                              return showDeliveryError('City is required.');

  deliveryData = { name, email, address, city, district };

  document.getElementById('step-delivery').style.opacity = '.5';
  document.getElementById('step-payment').style.display  = 'block';
  document.getElementById('step-payment').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function showDeliveryError(msg) {
  const el = document.getElementById('delivery-error');
  el.textContent    = msg;
  el.style.display  = 'block';
}

function backToDelivery() {
  document.getElementById('step-delivery').style.opacity = '1';
  document.getElementById('step-payment').style.display  = 'none';
}

function initiatePayment() {
  const phone  = document.getElementById('co-phone').value.trim();
  const errEl  = document.getElementById('payment-error');
  errEl.style.display = 'none';

  const phoneClean = phone.replace(/\s/g, '');
  if (!phoneClean) return showPayError('Phone number is required.');
  const re = /^(\+?25)?(078|079|075|073|072)\d{7}$/;
  if (!re.test(phoneClean)) return showPayError('Enter a valid Rwandan MTN (078/079) or Airtel (073/072/075) number.');

  // Disable button + show loading
  const btn = document.getElementById('pay-btn');
  btn.disabled = true;
  document.getElementById('pay-btn-text').textContent = 'Initiating…';

  const body = new URLSearchParams({
    ...deliveryData,
    phone: phoneClean,
  });

  fetch(BASE_URL_JS + '/ajax/paypack_initiate.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    document.getElementById('pay-btn-text').textContent = '💳 Pay RWF <?= number_format($amountRwf) ?>';

    if (!data.ok) return showPayError(data.error || 'Could not initiate payment.');

    currentRef = data.ref;
    pollCount  = 0;

    // Show waiting screen
    document.getElementById('pay-idle').style.display    = 'none';
    document.getElementById('pay-waiting').style.display = 'block';
    document.getElementById('waiting-phone').textContent = data.phone;

    startPolling();
  })
  .catch(() => {
    btn.disabled = false;
    document.getElementById('pay-btn-text').textContent = '💳 Pay RWF <?= number_format($amountRwf) ?>';
    showPayError('Network error. Please check your connection and try again.');
  });
}

function showPayError(msg) {
  const el = document.getElementById('payment-error');
  el.textContent   = msg;
  el.style.display = 'block';
}

function startPolling() {
  let countdown = 5;
  document.getElementById('timer-count').textContent = countdown;

  pollTimer = setInterval(() => {
    countdown--;
    document.getElementById('timer-count').textContent = countdown;
    if (countdown <= 0) {
      clearInterval(pollTimer);
      checkPayment();
    }
  }, 1000);
}

function checkPayment() {
  if (!currentRef) return;
  pollCount++;

  fetch(BASE_URL_JS + '/ajax/paypack_verify.php?ref=' + encodeURIComponent(currentRef))
  .then(r => r.json())
  .then(data => {
    if (data.status === 'successful') {
      document.getElementById('pay-waiting').style.display = 'none';
      document.getElementById('pay-success').style.display = 'block';
      setTimeout(() => { window.location = BASE_URL_JS + '/order-confirmation.php'; }, 1800);
      return;
    }

    if (data.status === 'failed') {
      document.getElementById('pay-waiting').style.display = 'none';
      document.getElementById('pay-failed').style.display  = 'block';
      return;
    }

    // Still pending
    if (pollCount >= MAX_POLLS) {
      document.getElementById('pay-waiting').style.display = 'none';
      document.getElementById('pay-failed').style.display  = 'block';
      return;
    }

    // Poll again in 5s
    startPolling();
  })
  .catch(() => {
    if (pollCount < MAX_POLLS) startPolling();
  });
}

function cancelPayment() {
  clearInterval(pollTimer);
  currentRef = null;
  document.getElementById('pay-waiting').style.display = 'none';
  document.getElementById('pay-idle').style.display    = 'block';
}

function resetPayment() {
  currentRef = null;
  pollCount  = 0;
  document.getElementById('pay-failed').style.display  = 'none';
  document.getElementById('pay-idle').style.display    = 'block';
  document.getElementById('payment-error').style.display = 'none';
}
</script>

<?php endPage(); ?>

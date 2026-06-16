<?php
require_once __DIR__ . '/layout.php';
$user = getCurrentUser();
if (!$user) { header('Location: ' . BASE_URL . '/login.php'); exit; }

$pdo    = getDB();
$vendor = getVendor($user['id']);

// ── Registration form ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'register' && !$vendor) {
        $shopName = trim($_POST['shop_name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        if ($shopName) {
            $handle = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($shopName)), '-') . '-' . substr((string)time(), -4);
            $pdo->prepare(
                'INSERT IGNORE INTO vendors (user_id, shop_name, shop_handle, description) VALUES (?,?,?,?)'
            )->execute([$user['id'], $shopName, $handle, $desc]);
            securityLog('vendor_register', $user['id'], $shopName);
            flash('success', 'Application submitted! An admin will review it shortly.');
        }
        header('Location: ' . BASE_URL . '/vendor.php'); exit;
    }

    if ($act === 'payout_request' && $vendor && $vendor['status'] === 'approved') {
        $amount = (int)($_POST['amount_rwf'] ?? 0);
        if ($amount >= 1000) {
            $pdo->prepare('INSERT INTO vendor_payouts (vendor_id, amount_rwf) VALUES (?,?)')->execute([$vendor['id'], $amount]);
            flash('success', 'Payout request of RWF ' . number_format($amount) . ' submitted.');
        }
        header('Location: ' . BASE_URL . '/vendor.php'); exit;
    }

    if ($act === 'add_product' && $vendor && $vendor['status'] === 'approved') {
        $name  = trim($_POST['name'] ?? '');
        $price = (int)round((float)($_POST['price'] ?? 0) * 100);
        $type  = $_POST['product_type'] ?? 'Fruits';
        $desc  = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $qty   = (int)($_POST['inventory_qty'] ?? 0);
        if ($name && $price > 0) {
            $handle = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') . '-' . substr((string)time(), -4);
            $images = $image ? json_encode([$image]) : '[]';
            $pdo->prepare(
                "INSERT INTO ecom_products (name, handle, description, price, images, product_type, status, inventory_qty, tags, vendor_id)
                 VALUES (?,?,?,?,?,?,'active',?,?,?)"
            )->execute([$name, $handle, $desc, $price, $images, $type, $qty, '[]', $vendor['id']]);
            flash('success', 'Product "' . $name . '" added to your shop.');
        }
        header('Location: ' . BASE_URL . '/vendor.php'); exit;
    }
}

$vendor = getVendor($user['id']); // refresh
$msg    = flash('success');

startPage('Vendor Dashboard');
?>

<div class="section">
  <h1 class="section-title">🏪 Vendor Dashboard</h1>

  <?php if ($msg): ?><div class="alert" style="background:#f0fdf4;color:#15803d;padding:.8rem 1rem;border-radius:.5rem;margin-bottom:1.5rem">✅ <?= h($msg) ?></div><?php endif; ?>

  <?php if (!$vendor): ?>
  <!-- ── Registration ─────────────────────────────────── -->
  <div class="card" style="max-width:540px">
    <h2 style="margin:0 0 .5rem">Become a Vendor</h2>
    <p style="color:var(--gray-500);margin-bottom:1.5rem;font-size:.9rem">
      Sell your fresh produce on FreshMart. We handle payments and delivery — you focus on quality.
    </p>
    <form method="post" class="adm-form">
      <input type="hidden" name="action" value="register">
      <div class="form-group">
        <label style="font-weight:600;display:block;margin-bottom:.4rem">Shop Name *</label>
        <input type="text" name="shop_name" required placeholder="e.g. Green Valley Farms" class="adm-input"
               style="width:100%;padding:.65rem .9rem;border:1px solid var(--gray-300);border-radius:.5rem">
      </div>
      <div class="form-group" style="margin-top:1rem">
        <label style="font-weight:600;display:block;margin-bottom:.4rem">About your shop</label>
        <textarea name="description" rows="3" placeholder="Describe what you sell…"
                  style="width:100%;padding:.65rem .9rem;border:1px solid var(--gray-300);border-radius:.5rem"></textarea>
      </div>
      <button type="submit" class="btn btn-green" style="margin-top:1.25rem;width:100%">Apply to Become a Vendor →</button>
    </form>
  </div>

  <?php elseif ($vendor['status'] === 'pending'): ?>
  <div class="card" style="max-width:540px;text-align:center;padding:2.5rem">
    <div style="font-size:3rem">⏳</div>
    <h2 style="margin:.75rem 0 .5rem">Application Under Review</h2>
    <p style="color:var(--gray-500)">Your shop <strong><?= h($vendor['shop_name']) ?></strong> is pending admin approval. You'll be notified once approved.</p>
  </div>

  <?php elseif ($vendor['status'] === 'suspended'): ?>
  <div class="alert alert-red">Your vendor account has been suspended. Please contact support.</div>

  <?php else: /* approved */
    // Stats
    $revenue = (int)$pdo->prepare(
        "SELECT COALESCE(SUM(oi.total),0) FROM ecom_order_items oi
         JOIN ecom_products p ON p.id=oi.product_id WHERE p.vendor_id=?"
    )->execute([$vendor['id']]) ? $pdo->query("SELECT COALESCE(SUM(oi.total),0) FROM ecom_order_items oi JOIN ecom_products p ON p.id=oi.product_id WHERE p.vendor_id={$vendor['id']}")->fetchColumn() : 0;

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(oi.total),0) FROM ecom_order_items oi JOIN ecom_products p ON p.id=oi.product_id WHERE p.vendor_id=?"
    );
    $stmt->execute([$vendor['id']]);
    $grossRev = (int)$stmt->fetchColumn();
    $netRev   = (int)round($grossRev * (1 - $vendor['commission'] / 100));

    $prodCount = (int)$pdo->prepare("SELECT COUNT(*) FROM ecom_products WHERE vendor_id=? AND status='active'")->execute([$vendor['id']]) ? $pdo->query("SELECT COUNT(*) FROM ecom_products WHERE vendor_id={$vendor['id']} AND status='active'")->fetchColumn() : 0;

    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM ecom_products WHERE vendor_id=? AND status='active'");
    $stmt2->execute([$vendor['id']]);
    $prodCount = (int)$stmt2->fetchColumn();

    $myProducts = $pdo->prepare("SELECT * FROM ecom_products WHERE vendor_id=? ORDER BY created_at DESC LIMIT 20");
    $myProducts->execute([$vendor['id']]);
    $myProducts = $myProducts->fetchAll();

    $payouts = $pdo->prepare("SELECT * FROM vendor_payouts WHERE vendor_id=? ORDER BY requested_at DESC LIMIT 5");
    $payouts->execute([$vendor['id']]);
    $payouts = $payouts->fetchAll();
  ?>

  <!-- KPI row -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;margin-bottom:2rem">
    <div class="adm-kpi-card"><div class="adm-kpi-icon" style="background:#f0fdf4;color:#16a34a">💰</div><div class="adm-kpi-body"><div class="adm-kpi-label">Gross Revenue</div><div class="adm-kpi-value" style="font-size:1rem"><?= formatPrice($grossRev) ?></div></div></div>
    <div class="adm-kpi-card"><div class="adm-kpi-icon" style="background:#eff6ff;color:#2563eb">🏦</div><div class="adm-kpi-body"><div class="adm-kpi-label">Your Earnings (<?= 100-$vendor['commission'] ?>%)</div><div class="adm-kpi-value" style="font-size:1rem"><?= formatPrice($netRev) ?></div></div></div>
    <div class="adm-kpi-card"><div class="adm-kpi-icon" style="background:#fff7ed;color:#ea580c">📦</div><div class="adm-kpi-body"><div class="adm-kpi-label">Active Products</div><div class="adm-kpi-value"><?= $prodCount ?></div></div></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

    <!-- Add Product -->
    <div class="card">
      <h3 style="margin:0 0 1rem">➕ Add Product</h3>
      <form method="post">
        <input type="hidden" name="action" value="add_product">
        <div class="form-group" style="margin-bottom:.75rem">
          <input type="text" name="name" required placeholder="Product name"
                 style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-300);border-radius:.5rem">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.75rem">
          <input type="number" name="price" required step="0.01" min="0" placeholder="Price (USD)"
                 style="padding:.6rem .8rem;border:1px solid var(--gray-300);border-radius:.5rem">
          <input type="number" name="inventory_qty" min="0" placeholder="Qty" value="10"
                 style="padding:.6rem .8rem;border:1px solid var(--gray-300);border-radius:.5rem">
        </div>
        <select name="product_type" style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-300);border-radius:.5rem;margin-bottom:.75rem">
          <?php foreach(['Fruits','Vegetables','Dairy','Beverages','Bakery','Snacks'] as $t): ?>
            <option><?= $t ?></option>
          <?php endforeach ?>
        </select>
        <input type="url" name="image" placeholder="Image URL (optional)"
               style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-300);border-radius:.5rem;margin-bottom:.75rem">
        <textarea name="description" rows="2" placeholder="Description…"
                  style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-300);border-radius:.5rem;margin-bottom:.75rem"></textarea>
        <button type="submit" class="btn btn-green" style="width:100%">Add Product</button>
      </form>
    </div>

    <!-- Payout Request -->
    <div class="card">
      <h3 style="margin:0 0 .5rem">💳 Request Payout</h3>
      <p style="font-size:.85rem;color:var(--gray-500);margin-bottom:1rem">
        Net earnings available: <strong><?= formatPrice($netRev) ?></strong>
      </p>
      <form method="post">
        <input type="hidden" name="action" value="payout_request">
        <input type="number" name="amount_rwf" min="1000" placeholder="Amount in RWF (min 1,000)"
               style="width:100%;padding:.6rem .8rem;border:1px solid var(--gray-300);border-radius:.5rem;margin-bottom:.75rem">
        <button type="submit" class="btn btn-navy" style="width:100%">Request Payout</button>
      </form>

      <?php if (!empty($payouts)): ?>
      <div style="margin-top:1.25rem">
        <h4 style="font-size:.875rem;margin:0 0 .5rem">Recent Requests</h4>
        <?php foreach ($payouts as $pay): ?>
        <div style="display:flex;justify-content:space-between;font-size:.82rem;padding:.3rem 0;border-bottom:1px solid var(--gray-100)">
          <span>RWF <?= number_format($pay['amount_rwf']) ?></span>
          <span style="color:<?= $pay['status']==='paid'?'#16a34a':($pay['status']==='rejected'?'#dc2626':'#d97706') ?>">
            <?= ucfirst($pay['status']) ?>
          </span>
        </div>
        <?php endforeach ?>
      </div>
      <?php endif ?>
    </div>
  </div>

  <!-- My Products -->
  <?php if (!empty($myProducts)): ?>
  <div class="card" style="margin-top:1.5rem">
    <h3 style="margin:0 0 1rem">My Products</h3>
    <table style="width:100%;border-collapse:collapse;font-size:.875rem">
      <thead><tr style="border-bottom:2px solid var(--gray-200)">
        <th style="text-align:left;padding:.5rem">Product</th>
        <th style="text-align:right;padding:.5rem">Price</th>
        <th style="text-align:right;padding:.5rem">Stock</th>
      </tr></thead>
      <tbody>
      <?php foreach ($myProducts as $mp): ?>
      <tr style="border-bottom:1px solid var(--gray-100)">
        <td style="padding:.5rem"><a href="<?= BASE_URL ?>/product.php?handle=<?= h($mp['handle']) ?>" style="color:var(--green)"><?= h($mp['name']) ?></a></td>
        <td style="text-align:right;padding:.5rem"><?= formatPrice($mp['price']) ?></td>
        <td style="text-align:right;padding:.5rem"><?= $mp['inventory_qty'] ?></td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php endif ?>

  <?php endif ?>
</div>

<?php endPage(); ?>

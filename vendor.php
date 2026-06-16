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
      <form method="post" id="vendor-product-form">
        <input type="hidden" name="action" value="add_product">
        <input type="hidden" name="image"  id="vnd-image-url" value="">
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

        <!-- Image: tab switcher -->
        <div style="margin-bottom:.75rem">
          <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:.4rem">Product Image</label>
          <div style="display:flex;gap:0;border:1px solid var(--gray-300);border-radius:.5rem .5rem 0 0;overflow:hidden;margin-bottom:0">
            <button type="button" id="vnd-tab-upload" onclick="vndSwitchTab('upload')"
              style="flex:1;padding:.45rem;font-size:.8rem;border:none;background:#f0fdf4;color:#15803d;font-weight:600;cursor:pointer">
              ⬆ Upload File
            </button>
            <button type="button" id="vnd-tab-url" onclick="vndSwitchTab('url')"
              style="flex:1;padding:.45rem;font-size:.8rem;border:none;background:#f9fafb;color:#6b7280;cursor:pointer">
              🔗 Image URL
            </button>
          </div>

          <!-- Upload panel -->
          <div id="vnd-panel-upload" style="border:1px solid var(--gray-300);border-top:none;border-radius:0 0 .5rem .5rem;padding:.6rem">
            <div id="vnd-dropzone"
              style="border:2px dashed var(--gray-300);border-radius:.4rem;padding:1.25rem;text-align:center;cursor:pointer;background:#fafafa"
              ondragover="event.preventDefault();this.style.borderColor='#16a34a'"
              ondragleave="this.style.borderColor='var(--gray-300)'"
              ondrop="vndHandleDrop(event)"
              onclick="document.getElementById('vnd-file-input').click()">
              <input type="file" id="vnd-file-input" accept="image/*" style="display:none"
                     onchange="vndUploadFile(this.files[0])">
              <div id="vnd-dropzone-inner">
                <div style="font-size:1.75rem">🖼</div>
                <p style="font-size:.8rem;color:var(--gray-500);margin:.3rem 0 0">Click or drag &amp; drop · JPG, PNG, WebP · max 5 MB</p>
              </div>
            </div>
            <div id="vnd-upload-progress" style="display:none;margin-top:.5rem">
              <div style="background:var(--gray-200);border-radius:9999px;height:.4rem">
                <div id="vnd-upload-fill" style="background:#16a34a;height:.4rem;border-radius:9999px;width:5%;transition:width .2s"></div>
              </div>
              <p id="vnd-upload-status" style="font-size:.78rem;color:var(--gray-500);margin:.25rem 0 0">Uploading…</p>
            </div>
            <p id="vnd-upload-error" style="display:none;font-size:.78rem;color:#dc2626;margin:.25rem 0 0"></p>
          </div>

          <!-- URL panel -->
          <div id="vnd-panel-url" style="display:none;border:1px solid var(--gray-300);border-top:none;border-radius:0 0 .5rem .5rem;padding:.6rem">
            <input type="url" id="vnd-url-input" placeholder="https://example.com/image.jpg"
                   oninput="vndSetImage(this.value)"
                   style="width:100%;padding:.55rem .75rem;border:1px solid var(--gray-300);border-radius:.4rem;font-size:.85rem">
          </div>

          <!-- Preview -->
          <div id="vnd-preview-wrap" style="display:none;margin-top:.5rem;position:relative;display:none">
            <img id="vnd-preview-img" src="" alt="Preview"
                 style="width:100%;max-height:140px;object-fit:cover;border-radius:.4rem;border:1px solid var(--gray-200)">
            <button type="button" onclick="vndClearImage()"
              style="position:absolute;top:.3rem;right:.3rem;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:1.4rem;height:1.4rem;font-size:.75rem;cursor:pointer;line-height:1">✕</button>
          </div>
        </div>

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

<script>
const VND_UPLOAD_URL = '<?= BASE_URL ?>/ajax/upload_image.php';

function vndSwitchTab(tab) {
  const isUpload = tab === 'upload';
  document.getElementById('vnd-panel-upload').style.display = isUpload ? 'block' : 'none';
  document.getElementById('vnd-panel-url').style.display    = isUpload ? 'none'  : 'block';
  document.getElementById('vnd-tab-upload').style.background = isUpload ? '#f0fdf4' : '#f9fafb';
  document.getElementById('vnd-tab-upload').style.color      = isUpload ? '#15803d' : '#6b7280';
  document.getElementById('vnd-tab-upload').style.fontWeight = isUpload ? '600' : '400';
  document.getElementById('vnd-tab-url').style.background    = isUpload ? '#f9fafb' : '#f0fdf4';
  document.getElementById('vnd-tab-url').style.color         = isUpload ? '#6b7280' : '#15803d';
  document.getElementById('vnd-tab-url').style.fontWeight    = isUpload ? '400' : '600';
}

function vndSetImage(url) {
  if (!url) { vndClearImage(); return; }
  document.getElementById('vnd-image-url').value = url;
  const img = document.getElementById('vnd-preview-img');
  img.src = url;
  document.getElementById('vnd-preview-wrap').style.display = 'block';
}

function vndClearImage() {
  document.getElementById('vnd-image-url').value = '';
  document.getElementById('vnd-preview-img').src = '';
  document.getElementById('vnd-preview-wrap').style.display = 'none';
  document.getElementById('vnd-url-input').value = '';
  document.getElementById('vnd-dropzone-inner').innerHTML =
    '<div style="font-size:1.75rem">🖼</div>' +
    '<p style="font-size:.8rem;color:var(--gray-500);margin:.3rem 0 0">Click or drag &amp; drop &middot; JPG, PNG, WebP &middot; max 5 MB</p>';
  document.getElementById('vnd-upload-error').style.display = 'none';
  document.getElementById('vnd-upload-progress').style.display = 'none';
}

function vndHandleDrop(e) {
  e.preventDefault();
  e.currentTarget.style.borderColor = 'var(--gray-300)';
  const file = e.dataTransfer.files[0];
  if (file) vndUploadFile(file);
}

function vndUploadFile(file) {
  if (!file) return;
  const prog  = document.getElementById('vnd-upload-progress');
  const fill  = document.getElementById('vnd-upload-fill');
  const stat  = document.getElementById('vnd-upload-status');
  const errEl = document.getElementById('vnd-upload-error');

  errEl.style.display  = 'none';
  prog.style.display   = 'block';
  fill.style.width     = '5%';
  stat.textContent     = 'Uploading…';

  const fd = new FormData();
  fd.append('image', file);
  const xhr = new XMLHttpRequest();
  xhr.open('POST', VND_UPLOAD_URL);

  xhr.upload.onprogress = e => {
    if (e.lengthComputable) fill.style.width = Math.round((e.loaded / e.total) * 90) + '%';
  };

  xhr.onload = () => {
    fill.style.width = '100%';
    try {
      const res = JSON.parse(xhr.responseText);
      if (res.ok) {
        stat.textContent = '✓ Uploaded!';
        vndSetImage(res.url);
        document.getElementById('vnd-dropzone-inner').innerHTML =
          `<img src="${res.url}" style="max-height:80px;border-radius:.3rem;object-fit:cover"/>` +
          `<p style="font-size:.75rem;color:var(--gray-500);margin:.25rem 0 0">${file.name}</p>`;
        setTimeout(() => { prog.style.display = 'none'; }, 1000);
      } else {
        prog.style.display   = 'none';
        errEl.textContent    = '✕ ' + (res.error || 'Upload failed');
        errEl.style.display  = 'block';
      }
    } catch {
      prog.style.display  = 'none';
      errEl.textContent   = '✕ Invalid server response';
      errEl.style.display = 'block';
    }
  };

  xhr.onerror = () => {
    prog.style.display  = 'none';
    errEl.textContent   = '✕ Network error';
    errEl.style.display = 'block';
  };

  xhr.send(fd);
}
</script>

<?php endPage(); ?>

<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header('Location: <?= BASE_URL ?>/login.php');
    exit;
}

$pdo = getDB();
$tab = $_GET['tab'] ?? 'dashboard';

// ── Handle POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_vendor') {
        $vid = (int)$_POST['vendor_id'];
        $pdo->prepare("UPDATE vendors SET status='approved' WHERE id=?")->execute([$vid]);
        header('Location: ' . BASE_URL . '/admin.php?tab=vendors'); exit;
    }
    if ($action === 'suspend_vendor') {
        $vid = (int)$_POST['vendor_id'];
        $pdo->prepare("UPDATE vendors SET status='suspended' WHERE id=?")->execute([$vid]);
        header('Location: ' . BASE_URL . '/admin.php?tab=vendors'); exit;
    }
    if ($action === 'resolve_payout') {
        $pid    = (int)$_POST['payout_id'];
        $status = in_array($_POST['status'] ?? '', ['paid','rejected']) ? $_POST['status'] : 'paid';
        $pdo->prepare("UPDATE vendor_payouts SET status=?, resolved_at=NOW() WHERE id=?")->execute([$status, $pid]);
        header('Location: ' . BASE_URL . '/admin.php?tab=vendors'); exit;
    }
    if ($action === 'save_flash_deal') {
        $pid  = (int)$_POST['product_id'];
        $disc = min(90, max(1, (int)$_POST['discount_pct']));
        $from = $_POST['starts_at'] ?? date('Y-m-d H:i:s');
        $to   = $_POST['ends_at']   ?? date('Y-m-d H:i:s', strtotime('+24 hours'));
        $pdo->prepare("INSERT INTO flash_deals (product_id,discount_pct,starts_at,ends_at) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE discount_pct=VALUES(discount_pct),starts_at=VALUES(starts_at),ends_at=VALUES(ends_at)")
            ->execute([$pid, $disc, $from, $to]);
        header('Location: ' . BASE_URL . '/admin.php?tab=flash'); exit;
    }
    if ($action === 'delete_flash_deal') {
        $pdo->prepare("DELETE FROM flash_deals WHERE id=?")->execute([(int)$_POST['deal_id']]);
        header('Location: ' . BASE_URL . '/admin.php?tab=flash'); exit;
    }

    if ($action === 'save_product') {
        $id         = (int)($_POST['id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $priceCents = (int)round((float)($_POST['price'] ?? 0) * 100);
        $type       = $_POST['product_type'] ?? 'Fruits';
        $desc       = trim($_POST['description'] ?? '');
        $image      = trim($_POST['image'] ?? '');
        $invQty     = (int)($_POST['inventory_qty'] ?? 0);
        $featured   = isset($_POST['featured']) ? 1 : 0;
        $tags       = $featured ? '["featured"]' : '[]';
        $images     = $image ? json_encode([$image]) : '[]';
        if ($id) {
            $pdo->prepare("UPDATE ecom_products SET name=?,price=?,product_type=?,description=?,images=?,inventory_qty=?,tags=? WHERE id=?")
                ->execute([$name,$priceCents,$type,$desc,$images,$invQty,$tags,$id]);
            $productId = $id;
        } else {
            $handle = trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name)),'-').'-'.substr((string)time(),-4);
            $pdo->prepare("INSERT INTO ecom_products (name,handle,price,product_type,description,images,inventory_qty,status,has_variants,tags) VALUES (?,?,?,?,?,?,?,'active',0,?)")
                ->execute([$name,$handle,$priceCents,$type,$desc,$images,$invQty,$tags]);
            $productId = (int)$pdo->lastInsertId();
        }
        $colRow = $pdo->prepare("SELECT id FROM ecom_collections WHERE title=?");
        $colRow->execute([$type]); $col = $colRow->fetch();
        if ($col) {
            $pdo->prepare("DELETE FROM ecom_product_collections WHERE product_id=?")->execute([$productId]);
            $pdo->prepare("INSERT INTO ecom_product_collections (product_id,collection_id,position) VALUES (?,?,0)")->execute([$productId,$col['id']]);
        }
        // Email all customers about the new product
        if (!$id) {
            $newProduct = $pdo->prepare('SELECT * FROM ecom_products WHERE id=?');
            $newProduct->execute([$productId]);
            $newProduct = $newProduct->fetch();
            if ($newProduct) {
                $allCustomers = $pdo->query("SELECT email, full_name FROM profiles WHERE role='customer'")->fetchAll();
                foreach ($allCustomers as $cust) {
                    sendNewProductEmail($cust['email'], $cust['full_name'] ?: 'Customer', $newProduct);
                }
            }
        }
        header('Location: ' . BASE_URL . '/admin.php?tab=products'); exit;
    }

    if ($action === 'delete_product') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM ecom_product_collections WHERE product_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM ecom_products WHERE id=?")->execute([$id]);
        header('Location: <?= BASE_URL ?>/admin.php?tab=products'); exit;
    }

    if ($action === 'save_collection') {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_visible  = isset($_POST['is_visible']) ? 1 : 0;
        if ($title === '') {
            flash('error', 'Collection name is required.');
            header('Location: <?= BASE_URL ?>/admin.php?tab=collections'); exit;
        }
        if ($id) {
            $pdo->prepare("UPDATE ecom_collections SET title=?, description=?, is_visible=? WHERE id=?")
                ->execute([$title, $description, $is_visible, $id]);
            flash('success', 'Collection "' . $title . '" updated successfully.');
        } else {
            $handle = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
            // Ensure handle uniqueness
            $existing = $pdo->prepare("SELECT COUNT(*) FROM ecom_collections WHERE handle LIKE ?");
            $existing->execute([$handle . '%']);
            $count = (int)$existing->fetchColumn();
            if ($count > 0) $handle .= '-' . ($count + 1);
            $pdo->prepare("INSERT INTO ecom_collections (title, handle, description, is_visible) VALUES (?,?,?,?)")
                ->execute([$title, $handle, $description, $is_visible]);
            flash('success', 'Collection "' . $title . '" created successfully.');
        }
        header('Location: <?= BASE_URL ?>/admin.php?tab=collections'); exit;
    }

    if ($action === 'delete_collection') {
        $id = (int)$_POST['id'];
        // Cascade handled by FK, but let's be explicit
        $pdo->prepare("DELETE FROM ecom_product_collections WHERE collection_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM ecom_collections WHERE id=?")->execute([$id]);
        header('Location: <?= BASE_URL ?>/admin.php?tab=collections'); exit;
    }

    if ($action === 'update_order_status') {
        $orderId = (int)$_POST['order_id'];
        $status  = $_POST['status'] ?? 'paid';
        $allowed = ['pending','paid','processing','shipped','delivered','cancelled'];
        if (in_array($status, $allowed)) {
            $pdo->prepare("UPDATE ecom_orders SET status=? WHERE id=?")->execute([$status, $orderId]);
            // Send tracking update email to customer
            $orderRow = $pdo->prepare(
                "SELECT o.*, c.email, c.name AS cust_name FROM ecom_orders o
                 LEFT JOIN ecom_customers c ON c.id=o.customer_id WHERE o.id=?"
            );
            $orderRow->execute([$orderId]);
            $orderRow = $orderRow->fetch();
            if ($orderRow && !empty($orderRow['email'])) {
                sendOrderStatusEmail($orderRow['email'], $orderRow['cust_name'] ?? 'Customer', $orderId, $status);
            }
        }
        header('Location: ' . BASE_URL . '/admin.php?tab=orders'); exit;
    }

    if ($action === 'save_settings') {
        $allowed_keys = [
            'store_name','store_tagline','store_email','store_phone','store_address',
            'usd_to_rwf_rate',
            'hero_title','hero_subtitle','hero_image_url','hero_btn1_text','hero_btn1_url','hero_btn2_text','hero_btn2_url',
            'announcement_text','announcement_show',
            'shipping_free','shipping_flat_rwf','min_order_rwf','tax_enabled',
            'maintenance_mode','maintenance_msg',
            'facebook_url','instagram_url','twitter_url',
            'footer_about','meta_description','google_analytics',
            'paypack_app_id','paypack_app_secret','paypack_enabled',
        ];
        $allowed_keys[] = 'logo_url';
        foreach ($allowed_keys as $key) {
            if (isset($_POST[$key])) {
                saveSetting($key, trim($_POST[$key]));
            }
        }
        // Checkboxes (unchecked = not in POST)
        foreach (['announcement_show','shipping_free','tax_enabled','maintenance_mode','paypack_enabled'] as $ck) {
            saveSetting($ck, isset($_POST[$ck]) ? '1' : '0');
        }
        $_SESSION['flash']['success'] = 'Settings saved successfully.';
        header('Location: <?= BASE_URL ?>/admin.php?tab=settings&section=' . ($_POST['section'] ?? 'general')); exit;
    }
}

// ── Load data ──────────────────────────────────────────────────
$products  = $pdo->query("SELECT * FROM ecom_products ORDER BY created_at DESC")->fetchAll();
$customers = $pdo->query("SELECT * FROM ecom_customers ORDER BY created_at DESC")->fetchAll();
$revenue   = (int)$pdo->query("SELECT COALESCE(SUM(total),0) FROM ecom_orders WHERE status != 'cancelled'")->fetchColumn();

$orders = $pdo->query(
    "SELECT o.*, c.name AS cust_name, c.email AS cust_email
     FROM ecom_orders o LEFT JOIN ecom_customers c ON c.id=o.customer_id
     ORDER BY o.created_at DESC"
)->fetchAll();
foreach ($orders as &$o) {
    $s = $pdo->prepare("SELECT * FROM ecom_order_items WHERE order_id=?");
    $s->execute([$o['id']]); $o['items'] = $s->fetchAll();
}
unset($o);

// Recent 5 orders for dashboard overview
$recentOrders = array_slice($orders, 0, 5);
// Low stock products
$lowStock = array_filter($products, fn($p) => $p['inventory_qty'] < 5);
// Monthly revenue (this month)
$monthRevenue = (int)$pdo->query(
    "SELECT COALESCE(SUM(total),0) FROM ecom_orders
     WHERE status != 'cancelled' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
)->fetchColumn();
// Orders this month
$monthOrders = (int)$pdo->query(
    "SELECT COUNT(*) FROM ecom_orders WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
)->fetchColumn();

// ── Analytics data ───────────────────────────────────────────
$analytics = [];
if ($tab === 'analytics') {
    $analytics['daily_orders'] = $pdo->query(
        "SELECT DATE(created_at) AS day, COUNT(*) AS orders, SUM(total) AS revenue
         FROM ecom_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         AND status != 'cancelled' GROUP BY DATE(created_at) ORDER BY day ASC"
    )->fetchAll();
    $analytics['top_products'] = $pdo->query(
        "SELECT p.name, SUM(oi.total) AS revenue, SUM(oi.quantity) AS units_sold
         FROM ecom_order_items oi JOIN ecom_products p ON p.id=oi.product_id
         GROUP BY oi.product_id ORDER BY revenue DESC LIMIT 5"
    )->fetchAll();
    $analytics['top_viewed'] = $pdo->query(
        "SELECT p.name, COUNT(*) AS views
         FROM page_views pv JOIN ecom_products p ON p.id=pv.product_id
         WHERE pv.product_id IS NOT NULL
         GROUP BY pv.product_id ORDER BY views DESC LIMIT 5"
    )->fetchAll();
    $analytics['by_status'] = $pdo->query(
        "SELECT status, COUNT(*) AS cnt FROM ecom_orders GROUP BY status"
    )->fetchAll();
    $analytics['by_category'] = $pdo->query(
        "SELECT p.product_type, SUM(oi.total) AS revenue
         FROM ecom_order_items oi JOIN ecom_products p ON p.id=oi.product_id
         GROUP BY p.product_type ORDER BY revenue DESC"
    )->fetchAll();
    $analytics['views_today'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM page_views WHERE DATE(created_at)=CURDATE()"
    )->fetchColumn();
    $analytics['top_wishlisted'] = $pdo->query(
        "SELECT p.name, COUNT(*) AS cnt FROM wishlists w
         JOIN ecom_products p ON p.id=w.product_id
         GROUP BY w.product_id ORDER BY cnt DESC LIMIT 5"
    )->fetchAll();
    // Enhanced: AOV, new customers this month, today's conversion rate
    $analytics['avg_order_value']      = (int)round($revenue / max(1, count($orders)));
    $analytics['new_customers_month']  = (int)$pdo->query(
        "SELECT COUNT(*) FROM profiles WHERE role='customer'
         AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
    )->fetchColumn();
    $ordersToday = (int)$pdo->query("SELECT COUNT(*) FROM ecom_orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $analytics['conversion_rate'] = round(($ordersToday / max(1, $analytics['views_today'])) * 100, 1);
}

$statusColors = [
    'pending'    => ['bg'=>'#fff7ed','color'=>'#c2410c','dot'=>'#f97316'],
    'paid'       => ['bg'=>'#f0fdf4','color'=>'#15803d','dot'=>'#22c55e'],
    'processing' => ['bg'=>'#eff6ff','color'=>'#1d4ed8','dot'=>'#3b82f6'],
    'shipped'    => ['bg'=>'#f5f3ff','color'=>'#6d28d9','dot'=>'#8b5cf6'],
    'delivered'  => ['bg'=>'#ecfdf5','color'=>'#065f46','dot'=>'#10b981'],
    'cancelled'  => ['bg'=>'#fef2f2','color'=>'#991b1b','dot'=>'#ef4444'],
];
function statusBadge(string $status, array $map): string {
    $s = $map[$status] ?? ['bg'=>'#f3f4f6','color'=>'#374151','dot'=>'#9ca3af'];
    return '<span class="adm-badge" style="background:'.$s['bg'].';color:'.$s['color'].'">
              <span class="adm-badge-dot" style="background:'.$s['dot'].'"></span>'.ucfirst($status).'
            </span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin – FreshMart</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/admin.css" />
</head>
<body class="adm-body">

<!-- ── Sidebar ── -->
<aside class="adm-sidebar" id="adm-sidebar">
  <div class="adm-sidebar-logo">
    <a href="<?= BASE_URL ?>/" class="adm-logo-link">
      <span class="adm-logo-icon">🌿</span>
      <span class="adm-logo-text">FreshMart</span>
    </a>
    <button class="adm-sidebar-close" onclick="toggleSidebar()" aria-label="Close">✕</button>
  </div>

  <div class="adm-sidebar-section-label">Main</div>
  <nav class="adm-nav">
    <a href="<?= BASE_URL ?>/admin.php?tab=dashboard" class="adm-nav-item <?= $tab==='dashboard'?'active':'' ?>">
      <span class="adm-nav-icon">🏠</span> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=orders" class="adm-nav-item <?= $tab==='orders'?'active':'' ?>">
      <span class="adm-nav-icon">🛒</span> Orders
      <?php $pendingCount = count(array_filter($orders, fn($o)=>$o['status']==='pending')); ?>
      <?php if($pendingCount): ?><span class="adm-nav-badge"><?= $pendingCount ?></span><?php endif ?>
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=products" class="adm-nav-item <?= $tab==='products'?'active':'' ?>">
      <span class="adm-nav-icon">📦</span> Products
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=customers" class="adm-nav-item <?= $tab==='customers'?'active':'' ?>">
      <span class="adm-nav-icon">👥</span> Customers
    </a>
  </nav>

  <div class="adm-sidebar-section-label">Store</div>
  <nav class="adm-nav">
    <a href="<?= BASE_URL ?>/" class="adm-nav-item" target="_blank">
      <span class="adm-nav-icon">🏪</span> View Store
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=collections" class="adm-nav-item <?= $tab==='collections'?'active':'' ?>">
      <span class="adm-nav-icon">🗂</span> Collections
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=analytics" class="adm-nav-item <?= $tab==='analytics'?'active':'' ?>">
      <span class="adm-nav-icon">📊</span> Analytics
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=vendors" class="adm-nav-item <?= $tab==='vendors'?'active':'' ?>">
      <span class="adm-nav-icon">🏪</span> Vendors
      <?php $pendingVendors = (int)$pdo->query("SELECT COUNT(*) FROM vendors WHERE status='pending'")->fetchColumn(); ?>
      <?php if($pendingVendors): ?><span class="adm-nav-badge"><?= $pendingVendors ?></span><?php endif ?>
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=flash" class="adm-nav-item <?= $tab==='flash'?'active':'' ?>">
      <span class="adm-nav-icon">⚡</span> Flash Deals
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=security" class="adm-nav-item <?= $tab==='security'?'active':'' ?>">
      <span class="adm-nav-icon">🔐</span> Security Log
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=settings" class="adm-nav-item <?= $tab==='settings'?'active':'' ?>">
      <span class="adm-nav-icon">⚙️</span> Settings
    </a>
  </nav>

  <div class="adm-sidebar-footer">
    <div class="adm-user-card">
      <div class="adm-user-avatar"><?= strtoupper(substr($user['full_name'] ?: $user['email'], 0, 1)) ?></div>
      <div class="adm-user-info">
        <div class="adm-user-name"><?= h($user['full_name'] ?: 'Admin') ?></div>
        <div class="adm-user-role">Administrator</div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="adm-logout-btn" title="Sign out">⏻</a>
    </div>
  </div>
</aside>

<!-- ── Overlay (mobile) -->
<div class="adm-overlay" id="adm-overlay" onclick="toggleSidebar()"></div>

<!-- ── Main area ── -->
<div class="adm-main" id="adm-main">

  <!-- Top bar -->
  <header class="adm-topbar">
    <div class="adm-topbar-left">
      <button class="adm-hamburger" onclick="toggleSidebar()" aria-label="Menu">☰</button>
      <div class="adm-breadcrumb">
        <span>Admin</span>
        <span class="adm-breadcrumb-sep">›</span>
        <span class="adm-breadcrumb-current"><?= ucfirst($tab) ?></span>
      </div>
    </div>
    <div class="adm-topbar-right">
      <span class="adm-topbar-date"><?= date('D, M j Y') ?></span>
      <a href="<?= BASE_URL ?>/" class="adm-topbar-store-btn" target="_blank">🏪 View Store</a>
    </div>
  </header>

  <!-- ── Page content ── -->
  <div class="adm-content">

  <?php if($tab==='dashboard'): ?>
  <!-- ═══════════════════ DASHBOARD ═══════════════════ -->
  <div class="adm-page-header">
    <h1 class="adm-page-title">Dashboard</h1>
    <p class="adm-page-sub">Welcome back, <?= h(explode(' ',$user['full_name'])[0] ?: 'Admin') ?> 👋</p>
  </div>

  <!-- KPI cards -->
  <div class="adm-kpi-grid">
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#f0fdf4;color:#16a34a">💰</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">Total Revenue</div>
        <div class="adm-kpi-value">RWF <?= number_format(round($revenue/100*USD_TO_RWF)) ?></div>
        <div class="adm-kpi-sub"><?= formatPrice($revenue) ?></div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#eff6ff;color:#2563eb">🛒</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">Total Orders</div>
        <div class="adm-kpi-value"><?= count($orders) ?></div>
        <div class="adm-kpi-sub"><?= $monthOrders ?> this month</div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#fdf4ff;color:#9333ea">👥</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">Customers</div>
        <div class="adm-kpi-value"><?= count($customers) ?></div>
        <div class="adm-kpi-sub">Registered accounts</div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#fff7ed;color:#ea580c">📦</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">Products</div>
        <div class="adm-kpi-value"><?= count($products) ?></div>
        <div class="adm-kpi-sub"><?= count($lowStock) ?> low stock</div>
      </div>
    </div>
  </div>

  <!-- Recent orders + Low stock -->
  <div class="adm-dash-grid">

    <!-- Recent orders -->
    <div class="adm-panel">
      <div class="adm-panel-head">
        <h2>Recent Orders</h2>
        <a href="<?= BASE_URL ?>/admin.php?tab=orders" class="adm-panel-link">View all →</a>
      </div>
      <?php if(empty($recentOrders)): ?>
        <div class="adm-empty"><span>🛒</span><p>No orders yet</p></div>
      <?php else: ?>
      <table class="adm-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach($recentOrders as $o):
          $sc = $statusColors[$o['status']] ?? $statusColors['paid'];
        ?>
        <tr>
          <td><strong>#<?= strtoupper(str_pad($o['id'],6,'0',STR_PAD_LEFT)) ?></strong></td>
          <td><?= h($o['cust_name']??'Guest') ?></td>
          <td><?= formatPrice($o['total']) ?></td>
          <td><?= statusBadge($o['status'],$statusColors) ?></td>
          <td style="color:var(--adm-muted);font-size:.8rem"><?= date('M j',strtotime($o['created_at'])) ?></td>
        </tr>
        <?php endforeach?>
        </tbody>
      </table>
      <?php endif?>
    </div>

    <!-- Low stock + quick links -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">

      <!-- Low stock alert -->
      <div class="adm-panel">
        <div class="adm-panel-head">
          <h2>⚠ Low Stock</h2>
          <a href="<?= BASE_URL ?>/admin.php?tab=products" class="adm-panel-link">Manage →</a>
        </div>
        <?php if(empty($lowStock)): ?>
          <div class="adm-empty"><span>✅</span><p>All products stocked</p></div>
        <?php else: ?>
        <div class="adm-low-stock-list">
          <?php foreach(array_slice($lowStock,0,5) as $p):
            $imgs=json_decode($p['images']??'[]',true); $img=$imgs[0]??'';
          ?>
          <div class="adm-low-stock-item">
            <img src="<?= h($img) ?>" alt="" class="adm-low-stock-img"/>
            <div class="adm-low-stock-info">
              <div class="adm-low-stock-name"><?= h($p['name']) ?></div>
              <div class="adm-low-stock-qty <?= $p['inventory_qty']==0?'zero':'' ?>">
                <?= $p['inventory_qty']==0 ? 'Out of stock' : $p['inventory_qty'].' left' ?>
              </div>
            </div>
          </div>
          <?php endforeach?>
        </div>
        <?php endif?>
      </div>

      <!-- Quick actions -->
      <div class="adm-panel">
        <div class="adm-panel-head"><h2>Quick Actions</h2></div>
        <div class="adm-quick-actions">
          <button onclick="openNewProduct()" class="adm-quick-btn" style="--qc:#f0fdf4;--qt:#15803d">
            <span>➕</span> Add Product
          </button>
          <a href="<?= BASE_URL ?>/admin.php?tab=orders" class="adm-quick-btn" style="--qc:#eff6ff;--qt:#1d4ed8">
            <span>🛒</span> View Orders
          </a>
          <a href="<?= BASE_URL ?>/admin.php?tab=customers" class="adm-quick-btn" style="--qc:#fdf4ff;--qt:#7e22ce">
            <span>👥</span> Customers
          </a>
          <a href="<?= BASE_URL ?>/" target="_blank" class="adm-quick-btn" style="--qc:#fff7ed;--qt:#c2410c">
            <span>🏪</span> View Store
          </a>
        </div>
      </div>

    </div>
  </div>
  <?php endif?>

  <?php if($tab==='products'): ?>
  <!-- ═══════════════════ PRODUCTS ═══════════════════ -->
  <div class="adm-page-header">
    <div>
      <h1 class="adm-page-title">Products</h1>
      <p class="adm-page-sub"><?= count($products) ?> total products</p>
    </div>
    <button onclick="openNewProduct()" class="adm-primary-btn">+ Add Product</button>
  </div>

  <div class="adm-panel">
    <table class="adm-table">
      <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($products as $p):
        $imgs=json_decode($p['images']??'[]',true); $img=$imgs[0]??'';
        $tags=json_decode($p['tags']??'[]',true);   $low=$p['inventory_qty']<5;
      ?>
      <tr>
        <td>
          <div class="adm-product-cell">
            <img src="<?= h($img) ?>" alt="" class="adm-product-thumb"/>
            <div>
              <div class="adm-product-name"><?= h($p['name']) ?></div>
              <?php if(in_array('featured',$tags)):?>
                <span class="adm-featured-tag">★ Featured</span>
              <?php endif?>
            </div>
          </div>
        </td>
        <td><span class="adm-cat-tag"><?= h($p['product_type']??'—') ?></span></td>
        <td><?= formatPrice($p['price']) ?></td>
        <td>
          <?php if($p['inventory_qty']==0): ?>
            <span class="adm-stock-badge zero">Out of stock</span>
          <?php elseif($low): ?>
            <span class="adm-stock-badge low"><?= $p['inventory_qty'] ?> left</span>
          <?php else: ?>
            <span class="adm-stock-badge ok"><?= $p['inventory_qty'] ?></span>
          <?php endif?>
        </td>
        <td><?= statusBadge($p['status']==='active'?'delivered':'cancelled',$statusColors) ?></td>
        <td>
          <div class="adm-row-actions">
            <button class="adm-icon-btn edit" title="Edit"
              onclick='editProduct(<?= json_encode(["id"=>$p['id'],"name"=>$p['name'],"price_dollars"=>number_format($p['price']/100,2,'.',''),"product_type"=>$p['product_type'],"image"=>$img,"inventory_qty"=>$p['inventory_qty'],"description"=>$p['description']??'',"featured"=>in_array('featured',$tags)?1:0]) ?>)'>✏️</button>
            <form method="post" action="<?= BASE_URL ?>/admin.php?tab=products" style="display:inline" onsubmit="return confirm('Delete this product?')">
              <input type="hidden" name="action" value="delete_product">
              <input type="hidden" name="id"     value="<?= $p['id'] ?>">
              <button type="submit" class="adm-icon-btn del" title="Delete">🗑️</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach?>
      </tbody>
    </table>
  </div>
  <?php endif?>

  <?php if($tab==='orders'): ?>
  <!-- ═══════════════════ ORDERS ═══════════════════ -->
  <div class="adm-page-header">
    <div>
      <h1 class="adm-page-title">Orders</h1>
      <p class="adm-page-sub"><?= count($orders) ?> total · <?= $pendingCount ?> pending</p>
    </div>
  </div>

  <?php if(empty($orders)): ?>
    <div class="adm-panel"><div class="adm-empty"><span>🛒</span><p>No orders yet</p></div></div>
  <?php else: ?>
  <div class="adm-orders-list">
    <?php foreach($orders as $o):
      $addr=json_decode($o['shipping_address']??'{}',true);
      $sc=$statusColors[$o['status']]??$statusColors['paid'];
    ?>
    <div class="adm-order-card">
      <div class="adm-order-top">
        <div class="adm-order-meta">
          <span class="adm-order-id">#<?= strtoupper(str_pad($o['id'],6,'0',STR_PAD_LEFT)) ?></span>
          <?= statusBadge($o['status'],$statusColors) ?>
        </div>
        <div class="adm-order-amount"><?= formatPrice($o['total']) ?></div>
      </div>
      <div class="adm-order-info">
        <span>👤 <?= h($o['cust_name']??'Guest') ?></span>
        <span>✉ <?= h($o['cust_email']??'—') ?></span>
        <span>🕐 <?= date('M j, Y g:ia',strtotime($o['created_at'])) ?></span>
        <?php if(isset($addr['address'])): ?>
          <span>📍 <?= h($addr['address']) ?>, <?= h($addr['city']??'') ?></span>
        <?php endif?>
      </div>
      <div class="adm-order-items">
        <?php foreach($o['items'] as $it): ?>
          <span class="adm-order-item-pill">
            <?= h($it['product_name']) ?> ×<?= $it['quantity'] ?>
            <span><?= formatPrice($it['total']) ?></span>
          </span>
        <?php endforeach?>
      </div>
      <div class="adm-order-actions">
        <form method="post" action="<?= BASE_URL ?>/admin.php?tab=orders" style="display:flex;gap:.5rem;align-items:center">
          <input type="hidden" name="action"   value="update_order_status">
          <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
          <label style="font-size:.8rem;color:var(--adm-muted)">Update status:</label>
          <select name="status" class="adm-select">
            <?php foreach(['pending','paid','processing','shipped','delivered','cancelled'] as $s): ?>
              <option value="<?= $s ?>"<?= $o['status']===$s?' selected':''?>><?= ucfirst($s) ?></option>
            <?php endforeach?>
          </select>
          <button type="submit" class="adm-save-btn">Save</button>
        </form>
      </div>
    </div>
    <?php endforeach?>
  </div>
  <?php endif?>
  <?php endif?>

  <?php if($tab==='customers'): ?>
  <!-- ═══════════════════ CUSTOMERS ═══════════════════ -->
  <div class="adm-page-header">
    <div>
      <h1 class="adm-page-title">Customers</h1>
      <p class="adm-page-sub"><?= count($customers) ?> registered</p>
    </div>
  </div>
  <div class="adm-panel">
    <table class="adm-table">
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th></tr></thead>
      <tbody>
      <?php foreach($customers as $c): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:.65rem">
            <div class="adm-cust-avatar"><?= strtoupper(substr($c['name']??$c['email'],0,1)) ?></div>
            <?= h($c['name']??'—') ?>
          </div>
        </td>
        <td><?= h($c['email']) ?></td>
        <td><?= h($c['phone']??'—') ?></td>
        <td style="color:var(--adm-muted);font-size:.85rem"><?= date('M j, Y',strtotime($c['created_at'])) ?></td>
      </tr>
      <?php endforeach?>
      </tbody>
    </table>
  </div>
  <?php endif?>

  <?php if($tab==='collections'): ?>
  <!-- ═══════════════════ COLLECTIONS ═══════════════════ -->
  <?php $cols = $pdo->query("SELECT c.*, COUNT(pc.product_id) AS product_count FROM ecom_collections c LEFT JOIN ecom_product_collections pc ON pc.collection_id=c.id GROUP BY c.id ORDER BY c.title")->fetchAll(); ?>
  <div class="adm-page-header">
    <div><h1 class="adm-page-title">Collections</h1><p class="adm-page-sub"><?= count($cols) ?> categories</p></div>
    <button onclick="openNewCollection()" class="adm-primary-btn">+ Add Collection</button>
  </div>
  <div class="adm-panel">
    <?php if(empty($cols)): ?>
      <div class="adm-empty"><span>🗂</span><p>No collections yet. Add your first category.</p></div>
    <?php else: ?>
    <table class="adm-table">
      <thead><tr><th>Collection</th><th>Handle</th><th>Products</th><th>Visible</th><th>Actions</th></tr></thead>
      <tbody>
      <?php $catIcons=['Fruits'=>'🍎','Vegetables'=>'🥦','Dairy'=>'🥛','Beverages'=>'🧃','Bakery'=>'🍞','Snacks'=>'🍿']; ?>
      <?php foreach($cols as $c): ?>
      <tr>
        <td>
          <strong><?= ($catIcons[$c['title']]??'🗂').' '.h($c['title']) ?></strong>
          <?php if($c['description']): ?><br><small style="color:var(--adm-muted)"><?= h($c['description']) ?></small><?php endif?>
        </td>
        <td><code style="background:var(--adm-bg);padding:.2rem .5rem;border-radius:.3rem;font-size:.8rem"><?= h($c['handle']) ?></code></td>
        <td>
          <a href="<?= BASE_URL ?>/admin.php?tab=products" style="color:var(--adm-primary);text-decoration:none">
            <?= $c['product_count'] ?> product<?= $c['product_count']!=1?'s':'' ?>
          </a>
        </td>
        <td><?= $c['is_visible'] ? '<span style="color:#16a34a">✓ Visible</span>' : '<span style="color:#dc2626">✗ Hidden</span>' ?></td>
        <td>
          <div class="adm-row-actions">
            <button class="adm-icon-btn edit" title="Edit"
              onclick='editCollection(<?= json_encode(["id"=>$c["id"],"title"=>$c["title"],"description"=>$c["description"]??"","is_visible"=>$c["is_visible"]]) ?>)'>✏️</button>
            <form method="post" action="<?= BASE_URL ?>/admin.php?tab=collections" style="display:inline"
                  onsubmit="return confirm('Delete &quot;<?= h(addslashes($c['title'])) ?>&quot;? Products will be unlinked from this collection.')">
              <input type="hidden" name="action" value="delete_collection">
              <input type="hidden" name="id"     value="<?= $c['id'] ?>">
              <button type="submit" class="adm-icon-btn del" title="Delete">🗑️</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach?>
      </tbody>
    </table>
    <?php endif?>
  </div>
  <?php endif?>

  <?php if($tab==='analytics'): ?>
  <!-- ═════════════════════ ANALYTICS ═════════════════════ -->
  <div class="adm-page-header">
    <div><h1 class="adm-page-title">Analytics Dashboard</h1>
    <p class="adm-page-sub">Real-time store performance insights</p></div>
  </div>

  <!-- KPI row -->
  <div class="adm-kpi-grid" style="margin-bottom:2rem">
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#f0fdf4;color:#16a34a">👁</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">Page Views Today</div>
        <div class="adm-kpi-value"><?= number_format($analytics['views_today']) ?></div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#eff6ff;color:#2563eb">📊</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">Orders (30 days)</div>
        <div class="adm-kpi-value"><?= array_sum(array_column($analytics['daily_orders'],'orders')) ?></div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#fdf4ff;color:#9333ea">❤️</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">Wishlisted Items</div>
        <div class="adm-kpi-value"><?= array_sum(array_column($analytics['top_wishlisted'],'cnt')) ?></div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#fff7ed;color:#ea580c">💰</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">Revenue (30 days)</div>
        <div class="adm-kpi-value" style="font-size:1rem"><?= formatPrice((int)array_sum(array_column($analytics['daily_orders'],'revenue'))) ?></div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#f0fdf4;color:#16a34a">🛍</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">Avg Order Value</div>
        <div class="adm-kpi-value" style="font-size:1rem"><?= formatPrice($analytics['avg_order_value']) ?></div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#eff6ff;color:#2563eb">🆕</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">New Customers</div>
        <div class="adm-kpi-value"><?= $analytics['new_customers_month'] ?></div>
        <div class="adm-kpi-sub">This month</div>
      </div>
    </div>
    <div class="adm-kpi-card">
      <div class="adm-kpi-icon" style="background:#fdf4ff;color:#9333ea">📈</div>
      <div class="adm-kpi-body">
        <div class="adm-kpi-label">Conversion Rate</div>
        <div class="adm-kpi-value"><?= $analytics['conversion_rate'] ?>%</div>
        <div class="adm-kpi-sub">Orders ÷ views today</div>
      </div>
    </div>
  </div>

  <div class="adm-dash-grid">

    <!-- Daily Revenue Chart -->
    <div class="adm-panel">
      <div class="adm-panel-head"><h2>📈 Revenue Last 30 Days</h2></div>
      <canvas id="revenueChart" height="120"></canvas>
    </div>

    <!-- Orders by Status -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">
      <div class="adm-panel">
        <div class="adm-panel-head"><h2>🎯 Orders by Status</h2></div>
        <?php foreach($analytics['by_status'] as $s): ?>
        <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.875rem;border-bottom:1px solid var(--adm-border)">
          <span><?= statusBadge($s['status'],$statusColors) ?></span>
          <strong><?= $s['cnt'] ?></strong>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Top Wishlisted -->
      <div class="adm-panel">
        <div class="adm-panel-head"><h2>❤️ Top Wishlisted</h2></div>
        <?php if(empty($analytics['top_wishlisted'])): ?>
          <p style="font-size:.85rem;color:var(--adm-muted)">No wishlist data yet.</p>
        <?php else: ?>
        <?php foreach($analytics['top_wishlisted'] as $w): ?>
        <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.875rem;border-bottom:1px solid var(--adm-border)">
          <span><?= h($w['name']) ?></span><strong><?= $w['cnt'] ?> ♥</strong>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Top Products + Most Viewed + Category Revenue -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;margin-top:1.5rem">

    <div class="adm-panel">
      <div class="adm-panel-head"><h2>🏆 Top Products by Revenue</h2></div>
      <?php if(empty($analytics['top_products'])): ?>
        <p style="font-size:.85rem;color:var(--adm-muted)">No sales yet.</p>
      <?php else: ?>
      <?php foreach($analytics['top_products'] as $i => $tp): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--adm-border);font-size:.85rem">
        <span><strong style="color:var(--adm-primary)">#<?= $i+1 ?></strong> <?= h($tp['name']) ?><br>
        <small style="color:var(--adm-muted)"><?= $tp['units_sold'] ?> units sold</small></span>
        <span><?= formatPrice((int)$tp['revenue']) ?></span>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="adm-panel">
      <div class="adm-panel-head"><h2>👁 Most Viewed Products</h2></div>
      <?php if(empty($analytics['top_viewed'])): ?>
        <p style="font-size:.85rem;color:var(--adm-muted)">No view data yet.</p>
      <?php else: ?>
      <?php foreach($analytics['top_viewed'] as $i => $tv): ?>
      <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--adm-border);font-size:.85rem">
        <span><strong style="color:var(--adm-primary)">#<?= $i+1 ?></strong> <?= h($tv['name']) ?></span>
        <strong><?= number_format($tv['views']) ?> views</strong>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="adm-panel">
      <div class="adm-panel-head"><h2>📂 Revenue by Category</h2></div>
      <?php if(empty($analytics['by_category'])): ?>
        <p style="font-size:.85rem;color:var(--adm-muted)">No sales yet.</p>
      <?php else: ?>
      <?php
      $catTotal = array_sum(array_column($analytics['by_category'],'revenue')) ?: 1;
      $catIcons2 = ['Fruits'=>'🍎','Vegetables'=>'🥦','Dairy'=>'🥛','Beverages'=>'🧃','Bakery'=>'🍞','Snacks'=>'🏿'];
      foreach($analytics['by_category'] as $cat):
        $pct = round($cat['revenue']/$catTotal*100);
      ?>
      <div style="margin-bottom:.75rem">
        <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:.25rem">
          <span><?= ($catIcons2[$cat['product_type']]??'📦').' '.h($cat['product_type']) ?></span>
          <span><?= $pct ?>%</span>
        </div>
        <div style="background:var(--adm-border);border-radius:9999px;height:.5rem">
          <div style="background:var(--adm-primary);width:<?= $pct ?>%;height:.5rem;border-radius:9999px"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
  <script>
  (function(){
    const labels = <?= json_encode(array_column($analytics['daily_orders'],'day')) ?>;
    const revenue = <?= json_encode(array_map(fn($r)=>round($r['revenue']/100,2), $analytics['daily_orders'])) ?>;
    const orders  = <?= json_encode(array_column($analytics['daily_orders'],'orders')) ?>;
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Revenue (USD)',
          data: revenue,
          backgroundColor: 'rgba(72,187,120,.6)',
          borderColor: '#48bb78',
          borderWidth: 1,
          yAxisID: 'y'
        },{
          label: 'Orders',
          data: orders,
          type: 'line',
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59,130,246,.1)',
          tension: .4,
          yAxisID: 'y1'
        }]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
          y:  { position: 'left',  title: { display:true, text:'Revenue ($)' } },
          y1: { position: 'right', title: { display:true, text:'Orders' }, grid: { drawOnChartArea:false } }
        }
      }
    });
  })();
  </script>
  <?php endif; ?>

  <?php if($tab==='vendors'): ?>
  <!-- ═════════════════════ VENDORS ═════════════════════ -->
  <?php
    $vendors = $pdo->query(
        "SELECT v.*, p.email, p.full_name,
                (SELECT COUNT(*) FROM ecom_products ep WHERE ep.vendor_id=v.id) AS product_count
         FROM vendors v JOIN profiles p ON p.id=v.user_id ORDER BY v.created_at DESC"
    )->fetchAll();
    $payoutRequests = $pdo->query(
        "SELECT vp.*, v.shop_name FROM vendor_payouts vp JOIN vendors v ON v.id=vp.vendor_id
         WHERE vp.status='pending' ORDER BY vp.requested_at DESC"
    )->fetchAll();
  ?>
  <div class="adm-page-header">
    <div><h1 class="adm-page-title">Vendors</h1><p class="adm-page-sub"><?= count($vendors) ?> vendors</p></div>
  </div>

  <?php if (!empty($payoutRequests)): ?>
  <div class="adm-panel" style="margin-bottom:1.5rem">
    <div class="adm-panel-head"><h2>💳 Pending Payout Requests (<?= count($payoutRequests) ?>)</h2></div>
    <table class="adm-table">
      <thead><tr><th>Vendor</th><th>Amount</th><th>Requested</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($payoutRequests as $pay): ?>
      <tr>
        <td><?= h($pay['shop_name']) ?></td>
        <td>RWF <?= number_format($pay['amount_rwf']) ?></td>
        <td style="font-size:.82rem;color:var(--adm-muted)"><?= date('M j, Y', strtotime($pay['requested_at'])) ?></td>
        <td>
          <div style="display:flex;gap:.5rem">
            <form method="post" style="display:inline">
              <input type="hidden" name="action"    value="resolve_payout">
              <input type="hidden" name="payout_id" value="<?= $pay['id'] ?>">
              <input type="hidden" name="status"    value="paid">
              <button class="adm-save-btn" style="background:#16a34a">Mark Paid</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="action"    value="resolve_payout">
              <input type="hidden" name="payout_id" value="<?= $pay['id'] ?>">
              <input type="hidden" name="status"    value="rejected">
              <button class="adm-icon-btn del">Reject</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php endif ?>

  <div class="adm-panel">
    <?php if (empty($vendors)): ?>
      <div class="adm-empty"><span>🏪</span><p>No vendor applications yet.</p></div>
    <?php else: ?>
    <table class="adm-table">
      <thead><tr><th>Shop</th><th>Owner</th><th>Products</th><th>Commission</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($vendors as $v): ?>
      <tr>
        <td><strong><?= h($v['shop_name']) ?></strong></td>
        <td><?= h($v['full_name']) ?><br><small style="color:var(--adm-muted)"><?= h($v['email']) ?></small></td>
        <td><?= $v['product_count'] ?></td>
        <td><?= $v['commission'] ?>%</td>
        <td><?= statusBadge($v['status']==='approved'?'delivered':($v['status']==='pending'?'pending':'cancelled'),$statusColors) ?></td>
        <td>
          <div class="adm-row-actions">
          <?php if ($v['status'] !== 'approved'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="action"    value="approve_vendor">
              <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
              <button class="adm-save-btn">Approve</button>
            </form>
          <?php endif ?>
          <?php if ($v['status'] !== 'suspended'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="action"    value="suspend_vendor">
              <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
              <button class="adm-icon-btn del">Suspend</button>
            </form>
          <?php endif ?>
          </div>
        </td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>
  <?php endif ?>

  <?php if($tab==='flash'): ?>
  <!-- ═════════════════════ FLASH DEALS ═════════════════════ -->
  <?php
    $allDeals = $pdo->query(
        "SELECT fd.*, p.name AS product_name FROM flash_deals fd
         JOIN ecom_products p ON p.id=fd.product_id ORDER BY fd.ends_at DESC"
    )->fetchAll();
    $allProds = $pdo->query("SELECT id, name FROM ecom_products WHERE status='active' ORDER BY name")->fetchAll();
  ?>
  <div class="adm-page-header">
    <div><h1 class="adm-page-title">⚡ Flash Deals</h1><p class="adm-page-sub"><?= count($allDeals) ?> deals</p></div>
  </div>

  <!-- Add deal form -->
  <div class="adm-panel" style="margin-bottom:1.5rem">
    <div class="adm-panel-head"><h2>Create Flash Deal</h2></div>
    <form method="post" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:.75rem;align-items:end">
      <input type="hidden" name="action" value="save_flash_deal">
      <div>
        <label style="font-size:.82rem;display:block;margin-bottom:.25rem">Product</label>
        <select name="product_id" class="adm-select" style="width:100%">
          <?php foreach ($allProds as $ap): ?>
            <option value="<?= $ap['id'] ?>"><?= h($ap['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label style="font-size:.82rem;display:block;margin-bottom:.25rem">Discount %</label>
        <input type="number" name="discount_pct" min="1" max="90" value="20" class="adm-select" style="width:100%">
      </div>
      <div>
        <label style="font-size:.82rem;display:block;margin-bottom:.25rem">Starts</label>
        <input type="datetime-local" name="starts_at" value="<?= date('Y-m-d\TH:i') ?>" class="adm-select" style="width:100%">
      </div>
      <div>
        <label style="font-size:.82rem;display:block;margin-bottom:.25rem">Ends</label>
        <input type="datetime-local" name="ends_at" value="<?= date('Y-m-d\TH:i', strtotime('+24 hours')) ?>" class="adm-select" style="width:100%">
      </div>
      <button type="submit" class="adm-primary-btn">⚡ Create</button>
    </form>
  </div>

  <div class="adm-panel">
    <?php if (empty($allDeals)): ?>
      <div class="adm-empty"><span>⚡</span><p>No flash deals yet.</p></div>
    <?php else: ?>
    <table class="adm-table">
      <thead><tr><th>Product</th><th>Discount</th><th>Starts</th><th>Ends</th><th>Status</th><th>Del</th></tr></thead>
      <tbody>
      <?php foreach ($allDeals as $d):
        $now   = time();
        $start = strtotime($d['starts_at']);
        $end   = strtotime($d['ends_at']);
        $status = $now < $start ? 'processing' : ($now <= $end ? 'paid' : 'cancelled');
        $label  = $now < $start ? 'Upcoming' : ($now <= $end ? 'Live' : 'Expired');
      ?>
      <tr>
        <td><strong><?= h($d['product_name']) ?></strong></td>
        <td style="color:#ef4444;font-weight:700"><?= $d['discount_pct'] ?>% OFF</td>
        <td style="font-size:.82rem"><?= date('M j g:ia', strtotime($d['starts_at'])) ?></td>
        <td style="font-size:.82rem"><?= date('M j g:ia', strtotime($d['ends_at'])) ?></td>
        <td><?= statusBadge($status, $statusColors) ?></td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete deal?')">
            <input type="hidden" name="action"  value="delete_flash_deal">
            <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
            <button class="adm-icon-btn del">🗑️</button>
          </form>
        </td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>
  <?php endif ?>

  <?php if($tab==='security'): ?>
  <!-- ═════════════════════ SECURITY LOG ═════════════════════ -->
  <?php $secLogs = $pdo->query(
      "SELECT sl.*, p.email FROM security_log sl LEFT JOIN profiles p ON p.id=sl.user_id
       ORDER BY sl.created_at DESC LIMIT 100"
  )->fetchAll(); ?>
  <div class="adm-page-header">
    <div><h1 class="adm-page-title">🔐 Security Log</h1><p class="adm-page-sub">Last 100 events</p></div>
  </div>
  <div class="adm-panel">
    <?php if (empty($secLogs)): ?>
      <div class="adm-empty"><span>🔐</span><p>No security events logged yet.</p></div>
    <?php else: ?>
    <table class="adm-table">
      <thead><tr><th>Time</th><th>Action</th><th>User</th><th>Detail</th></tr></thead>
      <tbody>
      <?php foreach ($secLogs as $log): ?>
      <tr>
        <td style="font-size:.8rem;color:var(--adm-muted);white-space:nowrap"><?= date('M j H:i', strtotime($log['created_at'])) ?></td>
        <td><code style="font-size:.8rem;background:var(--adm-bg);padding:.15rem .4rem;border-radius:.3rem"><?= h($log['action']) ?></code></td>
        <td style="font-size:.85rem"><?= $log['email'] ? h($log['email']) : '<span style="color:var(--adm-muted)">Guest</span>' ?></td>
        <td style="font-size:.82rem;color:var(--adm-muted)"><?= h($log['detail']) ?></td>
      </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>
  <?php endif ?>

  <?php if($tab==='settings'):
    $section = $_GET['section'] ?? 'general';
    // Load all settings fresh
    $settingsRows = $pdo->query("SELECT setting_key, setting_val FROM shop_settings")->fetchAll();
    $cfg = array_column($settingsRows, 'setting_val', 'setting_key');
    $flashMsg = flash('success');
  ?>
  <!-- ═══════════════════ SETTINGS ═══════════════════ -->
  <div class="adm-page-header">
    <div>
      <h1 class="adm-page-title">Shop Settings</h1>
      <p class="adm-page-sub">Manage your store configuration</p>
    </div>
  </div>

  <?php if($flashMsg): ?>
    <div class="adm-alert adm-alert-success">✅ <?= h($flashMsg) ?></div>
  <?php endif ?>

  <div class="adm-settings-layout">

    <!-- Settings sidebar nav -->
    <div class="adm-settings-nav">
      <?php
      $sections = [
        'general'      => ['icon'=>'🏪','label'=>'General & Logo'],
        'hero'         => ['icon'=>'🖼','label'=>'Homepage Hero'],
        'announcement' => ['icon'=>'📢','label'=>'Announcement Bar'],
        'shipping'     => ['icon'=>'🚚','label'=>'Shipping & Tax'],
        'payment'      => ['icon'=>'💳','label'=>'Payment (Paypack)'],
        'social'       => ['icon'=>'🌐','label'=>'Social & SEO'],
        'maintenance'  => ['icon'=>'🔧','label'=>'Maintenance'],
      ];
      foreach($sections as $key => $s): ?>
        <a href="<?= BASE_URL ?>/admin.php?tab=settings&section=<?= $key ?>"
           class="adm-settings-nav-item <?= $section===$key?'active':'' ?>">
          <span><?= $s['icon'] ?></span> <?= $s['label'] ?>
        </a>
      <?php endforeach ?>
    </div>

    <!-- Settings form panel -->
    <div class="adm-settings-content">
      <form method="post" action="<?= BASE_URL ?>/admin.php?tab=settings&section=<?= h($section) ?>" class="adm-settings-form">
        <input type="hidden" name="action"  value="save_settings">
        <input type="hidden" name="section" value="<?= h($section) ?>">

        <?php if($section==='general'): ?>
        <div class="adm-settings-section-title">🏪 General Information</div>
        <div class="adm-settings-grid">
          <div class="adm-form-group">
            <label>Store Name</label>
            <input type="text" name="store_name" value="<?= h($cfg['store_name']??'FreshMart') ?>" placeholder="FreshMart" required>
          </div>
          <div class="adm-form-group">
            <label>Store Tagline</label>
            <input type="text" name="store_tagline" value="<?= h($cfg['store_tagline']??'') ?>" placeholder="Fresh groceries delivered...">
          </div>
          <div class="adm-form-group">
            <label>Contact Email</label>
            <input type="email" name="store_email" value="<?= h($cfg['store_email']??'') ?>" placeholder="hello@yourstore.com">
          </div>
          <div class="adm-form-group">
            <label>Contact Phone</label>
            <input type="text" name="store_phone" value="<?= h($cfg['store_phone']??'') ?>" placeholder="+250 780 000 000">
          </div>
          <div class="adm-form-group adm-col-span-2">
            <label>Store Address</label>
            <input type="text" name="store_address" value="<?= h($cfg['store_address']??'') ?>" placeholder="Kigali, Rwanda">
          </div>
          <div class="adm-form-group">
            <label>USD &rarr; RWF Exchange Rate</label>
            <input type="number" name="usd_to_rwf_rate" value="<?= h($cfg['usd_to_rwf_rate']??'1400') ?>" min="1" step="1" placeholder="1400">
            <small class="adm-field-hint">Used to display prices in RWF across the store</small>
          </div>
        </div>

        <!-- Logo upload -->
        <div class="adm-settings-section-title" style="margin-top:1.5rem">🖼 Store Logo</div>
        <div class="adm-settings-grid">
          <div class="adm-form-group adm-col-span-2">
            <label>Logo Image</label>
            <?php $currentLogo = $cfg['logo_url'] ?? ''; ?>
            <div id="logo-preview-wrap" style="display:<?= $currentLogo ? 'flex' : 'none' ?>;align-items:center;gap:1rem;margin-bottom:.75rem">
              <img id="logo-preview" src="<?= h($currentLogo) ?>" alt="Logo"
                   style="height:52px;max-width:200px;object-fit:contain;border:1px solid var(--adm-border);border-radius:.5rem;padding:.25rem .5rem;background:#fff" />
              <button type="button" onclick="removeLogo()" class="adm-icon-btn del">&times; Remove</button>
            </div>
            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
              <label style="cursor:pointer;background:var(--adm-bg);border:1px solid var(--adm-border);padding:.5rem 1rem;border-radius:.5rem;font-size:.875rem;font-weight:500">
                &uarr; Upload Logo
                <input type="file" id="logo-file-input" accept="image/*" style="display:none" onchange="uploadLogo(this.files[0])">
              </label>
              <span style="color:var(--adm-muted);font-size:.8rem">or paste URL:</span>
              <input type="url" id="logo-url-input" placeholder="https://example.com/logo.png"
                     value="<?= h($currentLogo) ?>" oninput="setLogoFromUrl(this.value)"
                     style="flex:1;min-width:180px;padding:.45rem .75rem;border:1px solid var(--adm-border);border-radius:.5rem;font-size:.875rem" />
            </div>
            <input type="hidden" name="logo_url" id="logo-url-final" value="<?= h($currentLogo) ?>">
            <div id="logo-upload-status" style="font-size:.8rem;margin-top:.4rem;color:var(--adm-muted)"></div>
            <small class="adm-field-hint">PNG/SVG with transparent background recommended. Leave empty to use the default &ldquo;🌿 FreshMart&rdquo; text logo.</small>
          </div>
        </div>

        <?php elseif($section==='hero'): ?>
        <div class="adm-settings-section-title">🖼 Homepage Hero Banner</div>
        <div class="adm-settings-grid">
          <div class="adm-form-group adm-col-span-2">
            <label>Hero Title</label>
            <input type="text" name="hero_title" value="<?= h($cfg['hero_title']??'') ?>" placeholder="Fresh Groceries, Delivered Fast">
          </div>
          <div class="adm-form-group adm-col-span-2">
            <label>Hero Subtitle</label>
            <textarea name="hero_subtitle" rows="2" placeholder="Describe your store..."><?= h($cfg['hero_subtitle']??'') ?></textarea>
          </div>
          <div class="adm-form-group adm-col-span-2">
            <label>Hero Background Image URL</label>
            <input type="url" name="hero_image_url" id="hero-img-input"
                   value="<?= h($cfg['hero_image_url']??'') ?>"
                   placeholder="https://..." oninput="previewHeroImg(this.value)">
            <div class="adm-hero-preview" id="hero-img-preview"
                 style="<?= !empty($cfg['hero_image_url']) ? 'background-image:url('.h($cfg['hero_image_url']).')' : '' ?>">
              <?php if(empty($cfg['hero_image_url'])): ?><span>No image set</span><?php endif?>
            </div>
          </div>
          <div class="adm-form-group">
            <label>Button 1 Text</label>
            <input type="text" name="hero_btn1_text" value="<?= h($cfg['hero_btn1_text']??'Shop Now') ?>">
          </div>
          <div class="adm-form-group">
            <label>Button 1 URL</label>
            <input type="text" name="hero_btn1_url" value="<?= h($cfg['hero_btn1_url']??'/shop.php') ?>">
          </div>
          <div class="adm-form-group">
            <label>Button 2 Text</label>
            <input type="text" name="hero_btn2_text" value="<?= h($cfg['hero_btn2_text']??'Browse Fruits') ?>">
          </div>
          <div class="adm-form-group">
            <label>Button 2 URL</label>
            <input type="text" name="hero_btn2_url" value="<?= h($cfg['hero_btn2_url']??'') ?>">
          </div>
        </div>

        <?php elseif($section==='announcement'): ?>
        <div class="adm-settings-section-title">📢 Announcement Bar</div>
        <div class="adm-settings-grid">
          <div class="adm-form-group adm-col-span-2">
            <label class="adm-toggle-label">
              <span>Show announcement bar</span>
              <label class="adm-toggle">
                <input type="checkbox" name="announcement_show" <?= ($cfg['announcement_show']??'1')==='1'?'checked':'' ?>>
                <span class="adm-toggle-slider"></span>
              </label>
            </label>
          </div>
          <div class="adm-form-group adm-col-span-2">
            <label>Announcement Message</label>
            <input type="text" name="announcement_text"
                   value="<?= h($cfg['announcement_text']??'Free shipping on all orders') ?>"
                   placeholder="Free shipping on all orders — Fresh groceries delivered to your door">
            <small class="adm-field-hint">Displayed in the navy bar at the top of every page</small>
          </div>
        </div>

        <?php elseif($section==='shipping'): ?>
        <div class="adm-settings-section-title">🚚 Shipping</div>
        <div class="adm-settings-grid">
          <div class="adm-form-group adm-col-span-2">
            <label class="adm-toggle-label">
              <span>Free shipping on all orders</span>
              <label class="adm-toggle">
                <input type="checkbox" name="shipping_free" <?= ($cfg['shipping_free']??'1')==='1'?'checked':'' ?>>
                <span class="adm-toggle-slider"></span>
              </label>
            </label>
          </div>
          <div class="adm-form-group">
            <label>Flat shipping rate (RWF)</label>
            <input type="number" name="shipping_flat_rwf" value="<?= h($cfg['shipping_flat_rwf']??'0') ?>" min="0" placeholder="0">
            <small class="adm-field-hint">Used when free shipping is disabled</small>
          </div>
          <div class="adm-form-group">
            <label>Minimum order amount (RWF)</label>
            <input type="number" name="min_order_rwf" value="<?= h($cfg['min_order_rwf']??'100') ?>" min="0" placeholder="100">
          </div>
        </div>
        <div class="adm-settings-section-title" style="margin-top:1.5rem">🧾 Tax</div>
        <div class="adm-settings-grid">
          <div class="adm-form-group adm-col-span-2">
            <label class="adm-toggle-label">
              <span>Enable tax calculation</span>
              <label class="adm-toggle">
                <input type="checkbox" name="tax_enabled" <?= ($cfg['tax_enabled']??'1')==='1'?'checked':'' ?>>
                <span class="adm-toggle-slider"></span>
              </label>
            </label>
            <small class="adm-field-hint">Tax rates are calculated based on delivery state/region</small>
          </div>
        </div>

        <?php elseif($section==='payment'): ?>
        <div class="adm-settings-section-title">💳 Paypack Mobile Money</div>
        <div class="adm-settings-grid">
          <div class="adm-form-group adm-col-span-2">
            <label class="adm-toggle-label">
              <span>Enable Paypack payments</span>
              <label class="adm-toggle">
                <input type="checkbox" name="paypack_enabled" <?= ($cfg['paypack_enabled']??'1')==='1'?'checked':'' ?>>
                <span class="adm-toggle-slider"></span>
              </label>
            </label>
          </div>
          <div class="adm-form-group adm-col-span-2">
            <label>Application ID</label>
            <input type="text" name="paypack_app_id"
                   value="<?= h($cfg['paypack_app_id']??'') ?>"
                   placeholder="e.g. e1121f46-68eb-11f1-...">
          </div>
          <div class="adm-form-group adm-col-span-2">
            <label>Application Secret</label>
            <div style="position:relative">
              <input type="password" name="paypack_app_secret" id="paypack-secret"
                     value="<?= h($cfg['paypack_app_secret']??'') ?>"
                     placeholder="Application secret key">
              <button type="button" onclick="toggleSecret()" class="adm-show-secret-btn" id="secret-toggle">👁 Show</button>
            </div>
            <small class="adm-field-hint">Keep this secret. Never share it publicly.</small>
          </div>
          <div class="adm-form-group adm-col-span-2">
            <div class="adm-info-box">
              <strong>ℹ Paypack supports:</strong> MTN Mobile Money (078/079) · Airtel Money (072/073/075)<br>
              Minimum transaction: <strong>100 RWF</strong>
            </div>
          </div>
        </div>

        <?php elseif($section==='social'): ?>
        <div class="adm-settings-section-title">🌐 Social Media</div>
        <div class="adm-settings-grid">
          <div class="adm-form-group">
            <label>Facebook URL</label>
            <input type="url" name="facebook_url" value="<?= h($cfg['facebook_url']??'') ?>" placeholder="https://facebook.com/yourpage">
          </div>
          <div class="adm-form-group">
            <label>Instagram URL</label>
            <input type="url" name="instagram_url" value="<?= h($cfg['instagram_url']??'') ?>" placeholder="https://instagram.com/yourpage">
          </div>
          <div class="adm-form-group">
            <label>Twitter / X URL</label>
            <input type="url" name="twitter_url" value="<?= h($cfg['twitter_url']??'') ?>" placeholder="https://twitter.com/yourhandle">
          </div>
        </div>
        <div class="adm-settings-section-title" style="margin-top:1.5rem">🔍 SEO</div>
        <div class="adm-settings-grid">
          <div class="adm-form-group adm-col-span-2">
            <label>Meta Description</label>
            <textarea name="meta_description" rows="2" placeholder="Short description for search engines..."><?= h($cfg['meta_description']??'') ?></textarea>
            <small class="adm-field-hint">Recommended: 120–160 characters</small>
          </div>
          <div class="adm-form-group adm-col-span-2">
            <label>Google Analytics ID</label>
            <input type="text" name="google_analytics" value="<?= h($cfg['google_analytics']??'') ?>" placeholder="G-XXXXXXXXXX or UA-XXXXXXXX-X">
          </div>
          <div class="adm-form-group adm-col-span-2">
            <label>Footer About Text</label>
            <textarea name="footer_about" rows="2" placeholder="Short description shown in the footer..."><?= h($cfg['footer_about']??'') ?></textarea>
          </div>
        </div>

        <?php elseif($section==='maintenance'): ?>
        <div class="adm-settings-section-title">🔧 Maintenance Mode</div>
        <div class="adm-settings-grid">
          <div class="adm-form-group adm-col-span-2">
            <label class="adm-toggle-label">
              <span>Enable maintenance mode</span>
              <label class="adm-toggle">
                <input type="checkbox" name="maintenance_mode" <?= ($cfg['maintenance_mode']??'0')==='1'?'checked':'' ?>>
                <span class="adm-toggle-slider"></span>
              </label>
            </label>
            <?php if(($cfg['maintenance_mode']??'0')==='1'): ?>
            <div class="adm-alert adm-alert-warning" style="margin-top:.75rem">
              ⚠️ Maintenance mode is <strong>ON</strong> — the store is hidden from visitors.
            </div>
            <?php endif?>
          </div>
          <div class="adm-form-group adm-col-span-2">
            <label>Maintenance Message</label>
            <textarea name="maintenance_msg" rows="3" placeholder="We are currently undergoing maintenance..."><?= h($cfg['maintenance_msg']??'') ?></textarea>
          </div>
        </div>
        <?php endif ?>

        <div class="adm-settings-actions">
          <button type="submit" class="adm-primary-btn">💾 Save Settings</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif?>

  </div><!-- /adm-content -->
</div><!-- /adm-main -->

<!-- ── Product modal ── -->
<div class="adm-modal-overlay" id="product-modal" style="display:none" onclick="closeModal('product-modal')">
  <div class="adm-modal" onclick="event.stopPropagation()">
    <div class="adm-modal-head">
      <h2 id="modal-title">Add Product</h2>
      <button class="adm-modal-close" onclick="closeModal('product-modal')">✕</button>
    </div>
    <form id="product-form" method="post" action="<?= BASE_URL ?>/admin.php?tab=products" class="adm-form">
      <input type="hidden" name="action" value="save_product">
      <input type="hidden" name="id"     value="">

      <div class="adm-form-group">
        <label>Product Name *</label>
        <input type="text" name="name" required placeholder="e.g. Organic Bananas">
      </div>
      <div class="adm-form-row">
        <div class="adm-form-group">
          <label>Price (USD) *</label>
          <div class="adm-price-wrap">
            <span class="adm-price-prefix">$</span>
            <input type="number" name="price" required step="0.01" min="0"
                   placeholder="0.00" id="price-input" oninput="updateRwfPreview(this.value)">
          </div>
          <small id="rwf-preview" class="adm-rwf-hint"></small>
        </div>
        <div class="adm-form-group">
          <label>Category</label>
          <select name="product_type">
            <?php foreach(['Fruits','Vegetables','Beverages','Dairy','Bakery','Snacks'] as $t): ?>
              <option><?= $t ?></option>
            <?php endforeach?>
          </select>
        </div>
      </div>
      <div class="adm-form-group">
        <label>Product Image</label>

        <!-- Tab switcher -->
        <div class="adm-img-tabs">
          <button type="button" class="adm-img-tab active" onclick="switchImgTab('upload', this)">⬆ Upload File</button>
          <button type="button" class="adm-img-tab"        onclick="switchImgTab('url', this)">🔗 Image URL</button>
        </div>

        <!-- Upload panel -->
        <div id="img-tab-upload" class="adm-img-panel">
          <div class="adm-dropzone" id="adm-dropzone"
               ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)"
               ondrop="handleDrop(event)" onclick="document.getElementById('img-file-input').click()">
            <input type="file" id="img-file-input" accept="image/*"
                   style="display:none" onchange="handleFileSelect(this.files[0])">
            <div class="adm-dropzone-inner" id="adm-dropzone-inner">
              <span class="adm-dropzone-icon">🖼</span>
              <p class="adm-dropzone-text">Click or drag &amp; drop an image here</p>
              <p class="adm-dropzone-hint">JPG, PNG, WebP · max 5 MB</p>
            </div>
          </div>
          <div id="upload-progress" style="display:none">
            <div class="adm-upload-bar"><div class="adm-upload-fill" id="upload-fill"></div></div>
            <p class="adm-upload-status" id="upload-status">Uploading…</p>
          </div>
          <div id="upload-error" class="adm-upload-error" style="display:none"></div>
        </div>

        <!-- URL panel -->
        <div id="img-tab-url" class="adm-img-panel" style="display:none">
          <input type="url" id="img-url-input" placeholder="https://example.com/image.jpg"
                 oninput="setImageFromUrl(this.value)">
        </div>

        <!-- Shared hidden field + live preview -->
        <input type="hidden" name="image" id="image-final-url" value="">
        <div class="adm-img-preview-wrap" id="adm-img-preview-wrap" style="display:none">
          <img id="img-preview" src="" alt="Preview" />
          <button type="button" class="adm-img-remove" onclick="clearImage()" title="Remove image">✕</button>
        </div>
      </div>
      <div class="adm-form-row">
        <div class="adm-form-group">
          <label>Inventory Qty</label>
          <input type="number" name="inventory_qty" placeholder="0" value="50" min="0">
        </div>
        <div class="adm-form-group" style="justify-content:flex-end;padding-top:1.5rem">
          <label class="adm-checkbox-label">
            <input type="checkbox" name="featured"> <span>Mark as featured</span>
          </label>
        </div>
      </div>
      <div class="adm-form-group">
        <label>Description</label>
        <textarea name="description" placeholder="Describe this product…" rows="3"></textarea>
      </div>
      <button type="submit" class="adm-primary-btn" style="width:100%;border-radius:.6rem">Save Product</button>
    </form>
  </div>
</div>

<!-- ── Collection modal ── -->
<div class="adm-modal-overlay" id="collection-modal" style="display:none" onclick="closeModal('collection-modal')">
  <div class="adm-modal" onclick="event.stopPropagation()">
    <div class="adm-modal-head">
      <h2 id="col-modal-title">Add Collection</h2>
      <button class="adm-modal-close" onclick="closeModal('collection-modal')">✕</button>
    </div>
    <form id="collection-form" method="post" action="<?= BASE_URL ?>/admin.php?tab=collections" class="adm-form">
      <input type="hidden" name="action" value="save_collection">
      <input type="hidden" name="id"     value="">

      <div class="adm-form-group">
        <label>Collection Name *</label>
        <input type="text" name="title" required placeholder="e.g. Organic Produce"
               oninput="previewHandle(this.value)">
        <small class="adm-field-hint">
          URL handle: <code id="handle-preview" style="font-size:.8rem"></code>
        </small>
      </div>
      <div class="adm-form-group">
        <label>Description</label>
        <textarea name="description" rows="3" placeholder="Short description shown on the collection page…"></textarea>
      </div>
      <div class="adm-form-group">
        <label class="adm-toggle-label">
          <span>Visible in store</span>
          <label class="adm-toggle">
            <input type="checkbox" name="is_visible" checked>
            <span class="adm-toggle-slider"></span>
          </label>
        </label>
        <small class="adm-field-hint">Hidden collections won't appear in navigation or search</small>
      </div>

      <button type="submit" class="adm-primary-btn" style="width:100%;border-radius:.6rem">Save Collection</button>
    </form>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/app.js"></script>
<script>
const USD_TO_RWF = <?= USD_TO_RWF ?>;

function toggleSidebar() {
  document.getElementById('adm-sidebar').classList.toggle('open');
  document.getElementById('adm-overlay').classList.toggle('show');
}

function openModal(id)  { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openNewProduct() {
  document.getElementById('product-form').reset();
  document.getElementById('modal-title').textContent = 'Add Product';
  document.getElementById('product-form').querySelector('[name=id]').value = '';
  clearImage();
  document.getElementById('rwf-preview').textContent = '';
  openModal('product-modal');
}

function editProduct(data) {
  const f = document.getElementById('product-form');
  f.querySelector('[name=id]').value           = data.id || '';
  f.querySelector('[name=name]').value         = data.name || '';
  f.querySelector('[name=price]').value        = data.price_dollars || '';
  f.querySelector('[name=product_type]').value = data.product_type || 'Fruits';
  f.querySelector('[name=inventory_qty]').value= data.inventory_qty || 0;
  f.querySelector('[name=description]').value  = data.description || '';
  f.querySelector('[name=featured]').checked   = data.featured == 1;
  document.getElementById('modal-title').textContent = 'Edit Product';
  updateRwfPreview(data.price_dollars);
  // Pre-fill image
  if (data.image) {
    setFinalImage(data.image);
    // Show in URL tab if it's a remote URL
    if (data.image.startsWith('http')) {
      switchImgTab('url', document.querySelectorAll('.adm-img-tab')[1]);
      document.getElementById('img-url-input').value = data.image;
    } else {
      switchImgTab('upload', document.querySelectorAll('.adm-img-tab')[0]);
    }
  } else {
    clearImage();
  }
  openModal('product-modal');
}

// ── Image tab switcher ────────────────────────────────────────
function switchImgTab(tab, btn) {
  document.querySelectorAll('.adm-img-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('img-tab-upload').style.display = tab === 'upload' ? 'block' : 'none';
  document.getElementById('img-tab-url').style.display    = tab === 'url'    ? 'block' : 'none';
}

// ── Set final image (shared by both tabs) ─────────────────────
function setFinalImage(url) {
  if (!url) { clearImage(); return; }
  document.getElementById('image-final-url').value = url;
  const preview = document.getElementById('img-preview');
  preview.src = url;
  document.getElementById('adm-img-preview-wrap').style.display = 'block';
}

function clearImage() {
  document.getElementById('image-final-url').value = '';
  document.getElementById('img-preview').src = '';
  document.getElementById('adm-img-preview-wrap').style.display = 'none';
  document.getElementById('img-url-input').value = '';
  resetDropzone();
  document.getElementById('upload-error').style.display = 'none';
  document.getElementById('upload-progress').style.display = 'none';
}

// ── URL tab ───────────────────────────────────────────────────
function setImageFromUrl(url) {
  if (url && url.startsWith('http')) setFinalImage(url);
  else clearImage();
}

// ── Drag & drop / file select ─────────────────────────────────
function handleDragOver(e) {
  e.preventDefault();
  document.getElementById('adm-dropzone').classList.add('drag-over');
}
function handleDragLeave(e) {
  document.getElementById('adm-dropzone').classList.remove('drag-over');
}
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('adm-dropzone').classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) uploadFile(file);
}
function handleFileSelect(file) {
  if (file) uploadFile(file);
}

function resetDropzone() {
  const dz = document.getElementById('adm-dropzone');
  dz.classList.remove('drag-over', 'uploaded');
  document.getElementById('adm-dropzone-inner').innerHTML = `
    <span class="adm-dropzone-icon">🖼</span>
    <p class="adm-dropzone-text">Click or drag &amp; drop an image here</p>
    <p class="adm-dropzone-hint">JPG, PNG, WebP · max 5 MB</p>
  `;
}

// ── AJAX upload ───────────────────────────────────────────────
function uploadFile(file) {
  const errEl  = document.getElementById('upload-error');
  const progEl = document.getElementById('upload-progress');
  const fillEl = document.getElementById('upload-fill');
  const statEl = document.getElementById('upload-status');

  errEl.style.display  = 'none';
  progEl.style.display = 'block';
  fillEl.style.width   = '5%';
  statEl.textContent   = 'Uploading…';

  const fd = new FormData();
  fd.append('image', file);

  const xhr = new XMLHttpRequest();
  xhr.open('POST', '<?= BASE_URL ?>/ajax/upload_image.php');

  xhr.upload.onprogress = (e) => {
    if (e.lengthComputable) {
      const pct = Math.round((e.loaded / e.total) * 90);
      fillEl.style.width = pct + '%';
    }
  };

  xhr.onload = () => {
    fillEl.style.width = '100%';
    try {
      const res = JSON.parse(xhr.responseText);
      if (res.ok) {
        statEl.textContent = '✓ Uploaded!';
        setFinalImage(res.url);
        // Show thumbnail inside dropzone
        document.getElementById('adm-dropzone').classList.add('uploaded');
        document.getElementById('adm-dropzone-inner').innerHTML = `
          <img src="${res.url}" style="max-height:90px;border-radius:.4rem;object-fit:cover"/>
          <p class="adm-dropzone-hint" style="margin-top:.4rem">${file.name}</p>
        `;
        setTimeout(() => { progEl.style.display = 'none'; }, 1000);
      } else {
        progEl.style.display = 'none';
        errEl.textContent    = '✕ ' + (res.error || 'Upload failed');
        errEl.style.display  = 'block';
      }
    } catch {
      progEl.style.display = 'none';
      errEl.textContent    = '✕ Invalid server response';
      errEl.style.display  = 'block';
    }
  };

  xhr.onerror = () => {
    progEl.style.display = 'none';
    errEl.textContent    = '✕ Network error during upload';
    errEl.style.display  = 'block';
  };

  xhr.send(fd);
}

function updateRwfPreview(val) {
  const usd = parseFloat(val) || 0;
  const rwf = Math.round(usd * USD_TO_RWF);
  const el = document.getElementById('rwf-preview');
  if (el) el.textContent = rwf > 0 ? '≈ RWF ' + rwf.toLocaleString() : '';
}

function previewHeroImg(url) {
  const el = document.getElementById('hero-img-preview');
  if (!el) return;
  if (url && url.startsWith('http')) {
    el.style.backgroundImage = 'url(' + url + ')';
    const span = el.querySelector('span');
    if (span) span.style.display = 'none';
  }
}

// ── Logo upload ──────────────────────────────────────────────
function uploadLogo(file) {
  if (!file) return;
  const stat = document.getElementById('logo-upload-status');
  stat.textContent = 'Uploading…';
  const fd = new FormData();
  fd.append('image', file);
  fetch('<?= BASE_URL ?>/ajax/upload_image.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        setLogo(res.url);
        stat.textContent = '✓ Uploaded!';
        setTimeout(() => stat.textContent = '', 2000);
      } else {
        stat.textContent = '✕ ' + (res.error || 'Upload failed');
      }
    })
    .catch(() => stat.textContent = '✕ Network error');
}

function setLogoFromUrl(url) {
  if (url && url.startsWith('http')) setLogo(url);
  else if (!url) { setLogo(''); }
}

function setLogo(url) {
  document.getElementById('logo-url-final').value = url;
  document.getElementById('logo-url-input').value = url;
  const wrap = document.getElementById('logo-preview-wrap');
  const img  = document.getElementById('logo-preview');
  if (url) { img.src = url; wrap.style.display = 'flex'; }
  else     { img.src = ''; wrap.style.display = 'none'; }
}

function removeLogo() {
  setLogo('');
  document.getElementById('logo-file-input').value = '';
}

function toggleSecret() {
  const input = document.getElementById('paypack-secret');
  const btn   = document.getElementById('secret-toggle');
  if (input.type === 'password') { input.type = 'text';     btn.textContent = '🙈 Hide'; }
  else                           { input.type = 'password'; btn.textContent = '👁 Show'; }
}

// ── Collection modal ──────────────────────────────────────────
function slugify(str) {
  return str.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

function previewHandle(val) {
  const el = document.getElementById('handle-preview');
  if (el) el.textContent = slugify(val) || '—';
}

function openNewCollection() {
  const f = document.getElementById('collection-form');
  f.reset();
  f.querySelector('[name=id]').value = '';
  f.querySelector('[name=is_visible]').checked = true;
  document.getElementById('handle-preview').textContent = '—';
  document.getElementById('col-modal-title').textContent = 'Add Collection';
  openModal('collection-modal');
}

function editCollection(data) {
  const f = document.getElementById('collection-form');
  f.querySelector('[name=id]').value          = data.id || '';
  f.querySelector('[name=title]').value       = data.title || '';
  f.querySelector('[name=description]').value = data.description || '';
  f.querySelector('[name=is_visible]').checked = data.is_visible == 1;
  document.getElementById('handle-preview').textContent = slugify(data.title || '');
  document.getElementById('col-modal-title').textContent = 'Edit Collection';
  openModal('collection-modal');
}
</script>
</body>
</html>

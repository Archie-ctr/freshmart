<?php
require_once __DIR__ . '/layout.php';
startPage('About Us');
?>

<!-- Hero -->
<div style="background:linear-gradient(135deg,var(--navy) 60%,#2d5a8e);padding:4rem 1rem;text-align:center;color:#fff">
  <div style="max-width:680px;margin:0 auto">
    <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(72,187,120,.2);border:1px solid rgba(72,187,120,.4);border-radius:9999px;padding:.35rem 1rem;font-size:.875rem;margin-bottom:1.25rem">
      🌿 Our Story
    </div>
    <h1 style="font-size:clamp(2rem,5vw,3rem);font-weight:800;line-height:1.2;margin-bottom:1rem">
      Fresh Groceries, Delivered with Care
    </h1>
    <p style="color:rgba(255,255,255,.75);font-size:1.1rem;line-height:1.7;max-width:520px;margin:0 auto">
      FreshMart was born out of a simple idea — every family in Rwanda deserves
      access to fresh, quality groceries without leaving home.
    </p>
  </div>
</div>

<!-- Mission & Vision -->
<div class="section">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem">

    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:1.25rem;padding:2rem">
      <div style="width:3rem;height:3rem;background:rgba(72,187,120,.1);border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1rem">🎯</div>
      <h2 style="font-size:1.4rem;font-weight:700;color:var(--navy);margin-bottom:.75rem">Our Mission</h2>
      <p style="color:var(--gray-600);line-height:1.75">
        To make fresh, affordable groceries accessible to every household in Rwanda
        through a simple, fast, and locally relevant online shopping experience —
        powered by Mobile Money payments that everyone can use.
      </p>
    </div>

    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:1.25rem;padding:2rem">
      <div style="width:3rem;height:3rem;background:rgba(26,54,93,.08);border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1rem">🔭</div>
      <h2 style="font-size:1.4rem;font-weight:700;color:var(--navy);margin-bottom:.75rem">Our Vision</h2>
      <p style="color:var(--gray-600);line-height:1.75">
        To become East Africa's most trusted online grocery platform — connecting
        local farmers and small vendors directly with customers, reducing food waste,
        and building a stronger local food economy.
      </p>
    </div>

  </div>
</div>

<!-- Why FreshMart -->
<div style="background:#fff;border-top:1px solid var(--gray-200);border-bottom:1px solid var(--gray-200);padding:3rem 1rem">
  <div style="max-width:1200px;margin:0 auto">
    <h2 style="font-size:1.75rem;font-weight:700;color:var(--navy);text-align:center;margin-bottom:2.5rem">Why Choose FreshMart?</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.5rem">

      <?php
      $features = [
        ['🚚','Free Delivery','Free shipping on every order — no minimum, no hidden fees.'],
        ['💳','Mobile Money','Pay with MTN MoMo or Airtel Money. No card needed.'],
        ['🌿','Farm Fresh','Products sourced fresh daily from trusted local suppliers.'],
        ['✅','Quality Guarantee','Not satisfied? We will make it right, every time.'],
        ['💱','Dual Currency','Prices shown in both USD and RWF so you always know the cost.'],
        ['⚡','Same-Day Delivery','Order before 2 PM and receive your groceries today.'],
      ];
      foreach ($features as [$icon, $title, $desc]):
      ?>
      <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:1rem;padding:1.5rem;text-align:center">
        <div style="font-size:2rem;margin-bottom:.75rem"><?= $icon ?></div>
        <h3 style="font-weight:600;color:var(--navy);margin-bottom:.5rem"><?= $title ?></h3>
        <p style="font-size:.875rem;color:var(--gray-500);line-height:1.6"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>

    </div>
  </div>
</div>

<!-- Stats -->
<div class="section" style="text-align:center">
  <h2 style="font-size:1.75rem;font-weight:700;color:var(--navy);margin-bottom:2.5rem">FreshMart by the Numbers</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1.5rem">
    <?php
    $stats = [
      ['6','Product Categories'],
      ['14+','Fresh Products'],
      ['3','Subscription Boxes'],
      ['2','Mobile Money Networks'],
    ];
    foreach ($stats as [$num, $label]):
    ?>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:1.25rem;padding:2rem 1rem">
      <div style="font-size:2.5rem;font-weight:800;color:var(--green);line-height:1"><?= $num ?></div>
      <div style="font-size:.9rem;color:var(--gray-500);margin-top:.5rem"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Categories -->
<div style="background:#fff;border-top:1px solid var(--gray-200);padding:3rem 1rem">
  <div style="max-width:1200px;margin:0 auto;text-align:center">
    <h2 style="font-size:1.75rem;font-weight:700;color:var(--navy);margin-bottom:.5rem">What We Offer</h2>
    <p style="color:var(--gray-500);margin-bottom:2rem">Six fresh categories, hundreds of products</p>
    <div style="display:flex;flex-wrap:wrap;gap:1rem;justify-content:center">
      <?php
      $cats = ['🍎 Fruits','🥦 Vegetables','🥛 Dairy','🧃 Beverages','🍞 Bakery','🍿 Snacks'];
      foreach ($cats as $cat):
      ?>
      <span style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:9999px;padding:.5rem 1.25rem;font-size:.9rem;font-weight:500;color:var(--navy)"><?= $cat ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- CTA -->
<div class="section" style="text-align:center">
  <div style="background:linear-gradient(135deg,rgba(72,187,120,.08),rgba(26,54,93,.04));border:1px solid rgba(72,187,120,.2);border-radius:1.5rem;padding:3rem 2rem;max-width:600px;margin:0 auto">
    <h2 style="font-size:1.75rem;font-weight:700;color:var(--navy);margin-bottom:.75rem">Ready to Shop Fresh?</h2>
    <p style="color:var(--gray-500);margin-bottom:2rem">Join thousands of happy customers and get fresh groceries delivered to your door.</p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
      <a href="<?= BASE_URL ?>/shop.php" class="btn btn-green">Browse Products</a>
      <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline">Contact Us</a>
    </div>
  </div>
</div>

<?php endPage(); ?>

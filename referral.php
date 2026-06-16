<?php
require_once __DIR__ . '/layout.php';
$user = getCurrentUser();
if (!$user) { header('Location: ' . BASE_URL . '/login.php'); exit; }

$pdo     = getDB();
$code    = getReferralCode($user['id']);
$refUrl  = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
           . BASE_URL . '/register.php?ref=' . $code;

$totalReferrals = (int)$pdo->prepare('SELECT COUNT(*) FROM referrals WHERE referrer_id=?')
                     ->execute([$user['id']]) ? 0 : 0;
$stmt = $pdo->prepare('SELECT COUNT(*) FROM referrals WHERE referrer_id=?');
$stmt->execute([$user['id']]);
$totalReferrals = (int)$stmt->fetchColumn();

$stmt2 = $pdo->prepare('SELECT COUNT(*) FROM referrals WHERE referrer_id=? AND rewarded=1');
$stmt2->execute([$user['id']]);
$rewarded = (int)$stmt2->fetchColumn();

$pts    = getLoyaltyPoints($user['id']);
$reward = (int)(getSetting('referral_reward_points', '100') ?: 100);

startPage('Refer a Friend');
?>

<div class="section" style="max-width:640px;margin:0 auto">
  <h1 class="section-title">🎁 Refer a Friend</h1>
  <p style="color:var(--gray-500);margin-bottom:2rem">
    Invite friends to FreshMart and earn <strong><?= $reward ?> loyalty points</strong> for each friend who signs up!
  </p>

  <!-- Referral link box -->
  <div class="card" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:none;margin-bottom:1.5rem">
    <h3 style="margin:0 0 .75rem">Your Unique Referral Link</h3>
    <div style="display:flex;gap:.5rem">
      <input type="text" id="ref-link" value="<?= h($refUrl) ?>" readonly
             style="flex:1;padding:.65rem .9rem;border:1px solid var(--gray-300);border-radius:.5rem;font-size:.85rem;background:#fff">
      <button onclick="copyRef()" class="btn btn-green" id="copy-btn">📋 Copy</button>
    </div>
    <p style="font-size:.8rem;color:var(--gray-500);margin-top:.5rem">Share this link via WhatsApp, email, or social media.</p>
  </div>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1.5rem">
    <div class="card" style="text-align:center;padding:1.25rem">
      <div style="font-size:1.75rem;font-weight:700;color:var(--green)"><?= $totalReferrals ?></div>
      <div style="font-size:.8rem;color:var(--gray-500)">Friends Invited</div>
    </div>
    <div class="card" style="text-align:center;padding:1.25rem">
      <div style="font-size:1.75rem;font-weight:700;color:var(--green)"><?= $rewarded ?></div>
      <div style="font-size:.8rem;color:var(--gray-500)">Rewards Earned</div>
    </div>
    <div class="card" style="text-align:center;padding:1.25rem">
      <div style="font-size:1.75rem;font-weight:700;color:var(--green)"><?= $pts ?></div>
      <div style="font-size:.8rem;color:var(--gray-500)">Total Points</div>
    </div>
  </div>

  <div class="card">
    <h3 style="margin:0 0 1rem">How it works</h3>
    <div style="display:flex;flex-direction:column;gap:.75rem;font-size:.9rem">
      <div style="display:flex;align-items:center;gap:.75rem"><span style="font-size:1.4rem">🔗</span><span>Share your link with friends and family</span></div>
      <div style="display:flex;align-items:center;gap:.75rem"><span style="font-size:1.4rem">👤</span><span>Your friend registers using your link</span></div>
      <div style="display:flex;align-items:center;gap:.75rem"><span style="font-size:1.4rem">⭐</span><span>You earn <strong><?= $reward ?> points</strong> instantly</span></div>
      <div style="display:flex;align-items:center;gap:.75rem"><span style="font-size:1.4rem">💰</span><span>Points can be redeemed for discounts at checkout</span></div>
    </div>
  </div>
</div>

<script>
function copyRef() {
  const el = document.getElementById('ref-link');
  el.select(); document.execCommand('copy');
  const btn = document.getElementById('copy-btn');
  btn.textContent = '✅ Copied!';
  setTimeout(() => { btn.textContent = '📋 Copy'; }, 2000);
}
</script>

<?php endPage(); ?>

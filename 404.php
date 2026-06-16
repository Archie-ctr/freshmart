<?php
require_once __DIR__ . '/layout.php';
http_response_code(404);
startPage('Page Not Found');
?>
<div class="notfound-wrap">
  <div class="notfound-card">
    <h1>404</h1>
    <p>Page not found</p>
    <a href="<?= BASE_URL ?>/" class="link-green" style="display:inline-block;margin-top:1rem">Return to Home</a>
  </div>
</div>
<?php endPage(); ?>

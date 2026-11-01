<?php
/**
 * SMM APEX PLUS — homepage (v2).
 *
 * Market copy per COPY-v2.md. The "Starting from" line is rendered
 * ONLY when the live catalogue loads: it is the cheapest retail
 * price across the customer-visible (merged) list, computed
 * server-side. When services are not loaded, the line is omitted
 * entirely — never filled with a guess.
 */
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/api.php';
require __DIR__ . '/includes/service-overrides.php';
require __DIR__ . '/includes/auth.php';
$config = get_effective_config();
$page_title = 'Home';
$meta_description = 'SMM APEX PLUS — order social media growth services in PKR, pay with EasyPaisa, JazzCash or UPaisa, and track every order from your dashboard.';
$active = 'home';
$cheapest = null;
if (trim((string) ($config['provider']['api_key'] ?? '')) !== '' && trim((string) ($config['provider']['api_url'] ?? '')) !== '') {
    $svc = provider_services();
    if ($svc['ok']) {
        $overrides = load_service_overrides(db_connect());
        $merged = apply_service_overrides($svc['services'], $overrides);
        foreach ($merged as $row) {
            $retail = retail_price($row['rate'] ?? $row['price'] ?? null, category_markup_percent($config, (string) ($row['category'] ?? '')), empty($row['_manual']));
            // Zero-rate rows are unpriced placeholders, not free
            // offers: they must never become the advertised
            // "starting from" price. Skip them; if every row is
            // zero-rate, the line is omitted entirely.
            if ($retail !== null && $retail > 0 && ($cheapest === null || $retail < $cheapest)) {
                $cheapest = $retail;
            }
        }
    }
}
$other_url = trim((string) ($config['other_services_url'] ?? $config['main_website_url'] ?? ''));
$other_label = trim((string) ($config['other_services_label'] ?? 'Go to Other Services'));
if ($other_label === '') $other_label = 'Go to Other Services';
$other_subtitle = trim((string) ($config['other_services_subtitle'] ?? ''));
$other_domain = $other_url !== '' ? (string) parse_url($other_url, PHP_URL_HOST) : '';
require __DIR__ . '/includes/header.php';
?>
<?php if (!is_logged_in()): ?>
<section class="login-card-section" aria-label="Log in">
  <div class="card login-card">
    <h2>Welcome Back</h2>
    <p class="login-subtitle">Sign in to your professional dashboard</p>
    <form method="post" action="login.php" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <label for="home_username">Username</label>
      <input type="text" id="home_username" name="username" placeholder="Username" required>
      <label for="home_password">Password</label>
      <input type="password" id="home_password" name="password" placeholder="Password" required>
      <p><button class="btn btn-blue btn-login" type="submit">Log In</button></p>
    </form>
    <p class="login-alt">Don&rsquo;t have an account? <a href="signup.php">Sign up now</a></p>
  </div>
</section>
<?php else: ?>
<section class="login-card-section" aria-label="Your account">
  <div class="card login-card">
    <h2>Welcome Back</h2>
    <p class="login-subtitle">You&rsquo;re signed in — your orders and balance are one tap away.</p>
    <p><a class="btn btn-blue" href="new-order.php">Place a New Order</a></p>
    <p><a class="btn btn-outline" href="dashboard.php">Open Your Dashboard</a></p>
  </div>
</section>
<?php endif; ?>

<?php if ($other_url !== ''): ?>
<section class="other-card" id="other-services" aria-label="Other services">
  <p class="eyebrow">Looking for something else?</p>
  <h3><?php echo htmlspecialchars($other_label); ?></h3>
  <?php if ($other_subtitle !== ''): ?><p class="other-subtitle"><?php echo htmlspecialchars($other_subtitle); ?></p><?php endif; ?>
  <p>Explore more digital services on our other website — all in one place.</p>
  <p><a class="btn btn-blue" href="<?php echo htmlspecialchars($other_url); ?>">Visit <?php echo htmlspecialchars($other_domain !== '' ? $other_domain : $other_label); ?> →</a></p>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

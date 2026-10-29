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
            $retail = retail_price($row['rate'] ?? $row['price'] ?? null, category_markup_percent($config, (string) ($row['category'] ?? '')));
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
<section class="hero hero-v2 home-hero">
  <p class="eyebrow">SMM APEX PLUS — Social Media Services</p>
  <h1>Grow on Instagram, TikTok and YouTube — with upfront PKR prices</h1>
  <p class="hero-lead">Order followers, likes, views and more — with upfront prices, easy ordering and live order tracking. Pick a service, pay in PKR, and watch your order move from your dashboard.</p>
  <div class="hero-actions">
    <a class="btn btn-blue" href="services.php">Browse Services</a>
    <a class="btn btn-outline" href="signup.php">Create Your Free Account</a>
  </div>
  <p class="platform-label">Browse by platform:</p>
  <nav class="platform-chips" aria-label="Platforms">
    <a class="chip chip-instagram" href="services.php?q=Instagram">Instagram</a>
    <a class="chip chip-tiktok" href="services.php?q=TikTok">TikTok</a>
    <a class="chip chip-youtube" href="services.php?q=YouTube">YouTube</a>
    <a class="chip chip-facebook" href="services.php?q=Facebook">Facebook</a>
    <a class="chip chip-telegram" href="services.php?q=Telegram">Telegram</a>
    <a class="chip chip-x" href="services.php?q=X">X</a>
  </nav>
  <p class="small">Find your platform in seconds — search the live catalogue below.</p>
</section>
<hr class="home-rule">

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

<h2>Everything You Need, In One Place</h2>
<div class="feature-list">
  <div class="feature-row">
    <h3>Grow Faster</h3>
    <p>Pick a premium service, paste your link, set your quantity — and place your order in under a minute.</p>
    <p><a href="services.php">Browse Services →</a></p>
  </div>
  <div class="feature-row">
    <h3>Add Funds in Seconds</h3>
    <p>Top up easily with EasyPaisa, JazzCash or UPaisa — the payment methods Pakistan trusts every day.</p>
    <p><a href="add-funds.php">Add Funds Now →</a></p>
  </div>
  <div class="feature-row">
    <h3>Track Every Order</h3>
    <p>See your balance, your orders and your tickets from one simple dashboard — your Tracking ID shows you exactly where every order stands.</p>
    <p><a href="dashboard.php">Open Your Dashboard →</a></p>
  </div>
  <div class="feature-row">
    <h3>Real Support, Real People</h3>
    <p>Questions about an order or a payment? Message us on WhatsApp <?php echo htmlspecialchars($config['support_whatsapp']); ?> or email <?php echo htmlspecialchars($config['support_email']); ?> — a real person will help you with your order.</p>
    <p><a href="tickets.php">Ask Us Anything →</a></p>
  </div>
</div>

<h2>How It Works — As Easy As 1, 2, 3</h2>
<ol class="steps">
  <li><span class="step-num">01</span><strong>Create Your Account</strong> Sign up free and log in — it takes less than a minute.</li>
  <li><span class="step-num">02</span><strong>Add Funds</strong> Top up your balance in PKR with EasyPaisa, JazzCash or UPaisa.</li>
  <li><span class="step-num">03</span><strong>Place Your Order</strong> Choose a service, paste your link, set your quantity — your order is sent for processing right away.</li>
</ol>

<h2>Premium Services, Upfront Prices</h2>
<p>Explore the full live catalogue on the <a href="services.php">Services page</a> — every service shows its real price, minimum and maximum quantity, and delivery time where available. What you see is what you pay. No hidden charges, no surprises.</p>
<?php if ($cheapest !== null): ?>
<p class="starting-from">Starting from <?php echo htmlspecialchars(number_format($cheapest, 2) . ' ' . $config['currency']); ?> per 1,000 — see the live catalogue for every service.</p>
<?php endif; ?>
<p><a class="btn btn-blue" href="services.php">Browse Services</a></p>

<?php if ($other_url !== ''): ?>
<section class="other-card" id="other-services" aria-label="Other services">
  <p class="eyebrow">Looking for something else?</p>
  <h3><?php echo htmlspecialchars($other_label); ?></h3>
  <?php if ($other_subtitle !== ''): ?><p class="other-subtitle"><?php echo htmlspecialchars($other_subtitle); ?></p><?php endif; ?>
  <p>Explore more digital services on our other website — all in one place.</p>
  <p><a class="btn btn-blue" href="<?php echo htmlspecialchars($other_url); ?>">Visit <?php echo htmlspecialchars($other_domain !== '' ? $other_domain : $other_label); ?> →</a></p>
</section>
<?php endif; ?>

<h2>Questions? We've Got Answers</h2>
<div class="faq">
  <details>
    <summary>How do I place an order?</summary>
    <p>Create your free account, add funds, pick a service on the Services page, paste your link, set your quantity and place your order. Your order is sent for processing right away, and you can track it from your dashboard.</p>
  </details>
  <details>
    <summary>How do I add funds?</summary>
    <p>Open Add Funds, send your payment to one of the numbers shown using EasyPaisa, JazzCash or UPaisa, then submit your transaction ID exactly as shown by your payment app. Your balance is updated as soon as support approves your payment.</p>
  </details>
  <details>
    <summary>How do refunds work?</summary>
    <p>If an order cannot be completed, it stays Pending. Open a ticket with your order number and ask for a refund — approved refunds are credited exactly once to your balance.</p>
  </details>
  <details>
    <summary>When will my order start?</summary>
    <p>Orders are sent for processing the moment you submit them. Delivery time is shown for each service on the Services page, wherever it is available. Where you see a dash, no delivery time is published for that service — and we never guess one.</p>
  </details>
  <details>
    <summary>What payment methods do you accept?</summary>
    <p>EasyPaisa, JazzCash and UPaisa, in PKR. The payment numbers and any extra instructions are shown on the Add Funds page.</p>
  </details>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

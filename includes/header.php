<?php
// Shared header (v2). Expects $config and $page_title to be set.
if (!isset($config)) {
    $config = require __DIR__ . '/../config.php';
}
require_once __DIR__ . '/settings.php';
$site_theme = current_theme($config);
$other_url = trim((string) ($config['other_services_url'] ?? $config['main_website_url'] ?? ''));
$other_label = trim((string) ($config['other_services_label'] ?? 'Go to Other Services'));
if ($other_label === '') $other_label = 'Go to Other Services';
$other_subtitle = trim((string) ($config['other_services_subtitle'] ?? ''));
if (!isset($page_title)) {
    $page_title = 'SMM APEX PLUS';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$active = $active ?? '';
function nav_class($name, $active) {
    return $name === $active ? ' class="active"' : '';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($site_theme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title . ' — SMM APEX PLUS'); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($meta_description ?? 'SMM APEX PLUS — social media services panel. Services are supplied by SMM APEX PLUS.'); ?>">
<link rel="icon" type="image/webp" href="assets/img/logo.webp">
<link rel="stylesheet" href="assets/css/style-v2.css?v=20261029h">
<script>/* Visitor theme (header switcher): saved on this device only, never
  changes the admin's site default for other visitors. Applied here, before
  first paint, so the page does not flash the default theme first. */
try { var __t = localStorage.getItem('apex_theme'); if (__t) { document.documentElement.setAttribute('data-theme', __t); } } catch (e) {}</script>
</head>
<body>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="index.php">
      <img src="assets/img/logo.webp" alt="SMM APEX PLUS logo" width="48" height="48">
      <span class="brand-text">SMM <span>APEX</span> PLUS</span>
    </a>
<?php $moon_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
$sun_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>'; ?>
    <button class="theme-toggle" id="theme_toggle" type="button" aria-label="Switch between light and dark theme"><?php echo $site_theme === 'daylight' ? $moon_svg : $sun_svg; ?></button>
    <button class="nav-toggle" type="button" aria-label="Toggle navigation"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
    <nav class="main-nav">
      <a href="index.php"<?php echo nav_class('home', $active); ?>>Home</a>
      <a href="services.php"<?php echo nav_class('services', $active); ?>>Services</a>
      <a href="add-funds.php"<?php echo nav_class('funds', $active); ?>>Add Funds</a>
      <?php if (!empty($_SESSION['user_id'])): ?>
      <a href="dashboard.php"<?php echo nav_class('dashboard', $active); ?>>Dashboard</a>
      <a href="new-order.php"<?php echo nav_class('new-order', $active); ?>>New Order</a>
      <a href="orders.php"<?php echo nav_class('orders', $active); ?>>My Orders</a>
      <a href="tickets.php"<?php echo nav_class('tickets', $active); ?>>Support Tickets</a>
      <a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>)</a>
      <?php else: ?>
      <a class="nav-login" href="login.php"<?php echo nav_class('login', $active); ?>>Login</a>
      <a class="nav-signup" href="signup.php"<?php echo nav_class('signup', $active); ?>>Sign Up</a>
      <?php endif; ?>
      <?php if (!empty($_SESSION['admin_logged_in'])): ?>
      <a href="admin-overview.php"<?php echo nav_class('admin', $active); ?>>Admin Overview</a>
      <a href="admin.php"<?php echo nav_class('admin', $active); ?>>Admin Settings</a>
      <a href="admin-services.php"<?php echo nav_class('admin', $active); ?>>Manage Services</a>
      <?php endif; ?>
      <?php if ($other_url !== ''): ?><span class="other-compact"><a href="<?php echo htmlspecialchars($other_url); ?>"><strong><?php echo htmlspecialchars($other_label); ?></strong><?php if ($other_subtitle !== ''): ?><span><?php echo htmlspecialchars($other_subtitle); ?></span><?php endif; ?></a></span><?php endif; ?>
    </nav>
  </div>
</header>
<script>/* Theme toggle, inline in the page itself (2026-10-29 fix): the
   external JS file can sit cached on a phone for days, which is why
   the button did nothing after deploy. This handler runs with the
   fresh page every time, so a tap always works. One tap: white <->
   dark; the visitor's last dark theme is remembered on their device
   only, never the admin's default for others. */
(function () {
  var btn = document.getElementById('theme_toggle');
  if (!btn) return;
  function cur() { return document.documentElement.getAttribute('data-theme') || 'daylight'; }
  function sync() {
    if (cur() === 'daylight') { btn.innerHTML = '<?php echo $moon_svg; ?>'; btn.setAttribute('aria-label', 'Switch to dark theme'); }
    else { btn.innerHTML = '<?php echo $sun_svg; ?>'; btn.setAttribute('aria-label', 'Switch to light theme'); }
  }
  sync();
  btn.addEventListener('click', function () {
    var c = cur(), next;
    if (c === 'daylight') {
      try { next = localStorage.getItem('apex_last_dark') || 'midnight-navy'; }
      catch (e) { next = 'midnight-navy'; }
    } else {
      try { localStorage.setItem('apex_last_dark', c); } catch (e) {}
      next = 'daylight';
    }
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('apex_theme', next); } catch (e) {}
    sync();
  });
})();</script>
<main class="wrap">
<?php if (!empty($config['announcement'])): ?>
  <p class="notice"><?php echo htmlspecialchars($config['announcement']); ?></p>
<?php endif; ?>

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
</head>
<body>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="index.php">
      <img src="assets/img/logo.webp" alt="SMM APEX PLUS logo" width="48" height="48">
      <span class="brand-text">SMM <span>APEX</span> PLUS</span>
    </a>
    <button class="nav-toggle" type="button" aria-label="Toggle navigation"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
    <nav class="main-nav">
      <?php if (!empty($_SESSION['user_id'])): ?>
      <a href="dashboard.php"<?php echo nav_class('dashboard', $active); ?>>Dashboard</a>
      <a href="services.php"<?php echo nav_class('services', $active); ?>>Services</a>
      <a href="add-funds.php"<?php echo nav_class('funds', $active); ?>>Add Funds</a>
      <a href="new-order.php"<?php echo nav_class('new-order', $active); ?>>New Order</a>
      <a href="orders.php"<?php echo nav_class('orders', $active); ?>>My Orders</a>
      <a href="tickets.php"<?php echo nav_class('tickets', $active); ?>>Ticket Support</a>
      <a href="reviews.php"<?php echo nav_class('reviews', $active); ?>>Reviews</a>
      <a href="referral.php"<?php echo nav_class('referral', $active); ?>>Referral</a>
      <a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>)</a>
      <?php else: ?>
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
<main class="wrap">
<?php if (!empty($config['announcement'])): ?>
  <p class="notice"><?php echo htmlspecialchars($config['announcement']); ?></p>
<?php endif; ?>

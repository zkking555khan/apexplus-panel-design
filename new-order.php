<?php
/**
 * SMM APEX PLUS — place an order (v2).
 *
 * Logged-in customers only.
 *
 * - When the admin has connected the live catalogue, the Services
 *   dropdown shows ONLY the customer-visible merged list (live rows
 *   minus hidden rows, plus admin manual rows). Submitting a live
 *   order sends it at once; the real order ID is stored and the
 *   status becomes 'Sent'. On any failure the order stays 'Pending'
 *   and can be retried from Admin Orders.
 * - Manual orders (admin hand-fulfilled services) are NEVER sent
 *   anywhere automatically: the live API call is skipped entirely,
 *   the order stays 'Pending' with fulfilment='manual' until the
 *   admin marks it completed by hand.
 * - When the catalogue is not connected, the customer types a
 *   service name manually and the order is stored as 'Pending' only
 *   — no delivery time and no order ID are promised or invented.
 * - When the catalogue fetch itself fails, ordering is paused with
 *   an error state (v2 §8 default 1): manual rows are NOT shown
 *   alone as if the catalogue were fine.
 * - Balance IS deducted here: charge = quantity/1000 x retail price
 *   (rate + admin percentage). Insufficient balance refuses the order.
 *   Order IDs are never invented.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/api.php';
require __DIR__ . '/includes/service-overrides.php';
require_login();
$user = current_user();
$config = get_effective_config();
$page_title = 'Place Your Order';
$meta_description = 'Place a new SMM APEX PLUS order — pick a platform, category and service, paste your link, set your quantity, and see the exact charge before you order.';
$active = 'new-order';
$message = '';
$show_add_funds_link = false;
$db_ready = db_available();
$maintenance = maintenance_active($config);

$merged_services = null;
$api_failed = false;
$balance = null;
if (trim((string) ($config['provider']['api_key'] ?? '')) !== '' && trim((string) ($config['provider']['api_url'] ?? '')) !== '') {
    $svc = provider_services();
    if ($svc['ok']) {
        $overrides = load_service_overrides(db_connect());
        $merged_services = apply_service_overrides($svc['services'], $overrides);
    } else {
        $api_failed = true;
    }
}
if ($db_ready) {
    $bc = db_connect();
    if ($bc !== null) {
        $bst = $bc->prepare('SELECT balance FROM users WHERE id = ? LIMIT 1');
        $bst->bind_param('i', $user['id']);
        $bst->execute();
        $brow = $bst->get_result()->fetch_assoc();
        $bst->close(); $bc->close();
        if ($brow) { $balance = (float) $brow['balance']; }
    }
}

// Pre-selected service from the Services page (?service=<id>), validated against the merged list.
$preselected_id = trim($_GET['service'] ?? '');
$selected_row = null;
if ($merged_services !== null && $preselected_id !== '') {
    foreach ($merged_services as $row) {
        if ((string) ($row['service'] ?? $row['id'] ?? '') === $preselected_id) {
            $selected_row = $row;
            break;
        }
    }
    if ($selected_row === null) {
        $preselected_id = '';
    }
}
$posted_service_id = trim($_POST['service_id'] ?? '');
if ($merged_services !== null && $posted_service_id !== '' && $selected_row === null) {
    foreach ($merged_services as $row) {
        if ((string) ($row['service'] ?? $row['id'] ?? '') === $posted_service_id) {
            $selected_row = $row;
            $preselected_id = $posted_service_id;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$db_ready) {
        $message = 'Database not configured yet. The admin must finish the database setup first.';
    } elseif ($maintenance) {
        $message = trim((string) ($config['maintenance_message'] ?? '')) !== '' ? trim((string) $config['maintenance_message']) : 'Ordering is paused for maintenance right now — please check back shortly.';
    } elseif ($api_failed) {
        $message = 'Our catalogue could not be loaded just now. Please reload the page — your balance and orders are safe.';
    } elseif (!csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'That didn\'t go through — please reload the page and try again.';
    } else {
        $service_id = null;
        $service_name = '';
        $provider_rate = null;
        $service_category = '';
        $fulfilment = 'live';
        if ($merged_services !== null) {
            $service_id = trim($_POST['service_id'] ?? '');
            foreach ($merged_services as $row) {
                $row_id = (string) ($row['service'] ?? $row['id'] ?? '');
                if ($row_id !== '' && $row_id === $service_id) {
                    $service_name = (string) ($row['name'] ?? $row['service_name'] ?? $service_id);
                    $provider_rate = $row['rate'] ?? $row['price'] ?? null;
                    $service_category = (string) ($row['category'] ?? '');
                    $selected_row = $row;
                    $preselected_id = $service_id;
                    break;
                }
            }
            if ($service_name === '') {
                $message = 'Please choose a service first — then your price and total will appear instantly.';
            } elseif (is_manual_service_id($service_id)) {
                $fulfilment = 'manual';
            }
        } else {
            $service_name = trim($_POST['service_name'] ?? '');
            if ($service_name === '' || strlen($service_name) > 255) {
                $message = 'Please type the service name exactly as shown on the Services page.';
            }
        }
        $link = trim($_POST['link'] ?? '');
        $quantity_raw = trim($_POST['quantity'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($message === '' && (!filter_var($link, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $link) || strlen($link) > 1000)) {
            $message = 'That link doesn\'t look right — please paste the full link starting with https://';
        } elseif ($message === '' && (!ctype_digit($quantity_raw) || (int) $quantity_raw < 1 || (int) $quantity_raw > 1000000)) {
            $message = 'Quantity must be a whole number — and within this service\'s minimum and maximum, shown above.';
        } elseif ($message === '' && $selected_row !== null && ((int) $quantity_raw < (int) ($selected_row['min'] ?? 1) || (int) $quantity_raw > (int) ($selected_row['max'] ?? 1000000))) {
            $message = 'Quantity must be a whole number — and within this service\'s minimum and maximum, shown above.';
        } elseif ($message === '' && strlen($notes) > 500) {
            $message = 'Please shorten your notes to 500 characters or less.';
        } elseif ($message === '') {
            $quantity = (int) $quantity_raw;
            $charge = order_charge($provider_rate, $quantity, category_markup_percent($config, $service_category));
            if ($charge === null) { $charge = 0.00; }
            if ($balance === null) {
                $message = 'We couldn\'t reach our system just now — please try again in a moment. Nothing was charged.';
            } elseif ($charge > 0 && $balance < $charge) {
                $message = 'Not enough balance yet — this order costs ' . number_format($charge, 2) . ' ' . $config['currency'] . ', and your balance is ' . number_format($balance, 2) . ' ' . $config['currency'] . '. Add funds and you\'re good to go.';
                $show_add_funds_link = true;
            } else {
            $conn = db_connect();
            if ($conn === null) {
                $message = 'We couldn\'t reach our system just now — please try again in a moment. Nothing was charged.';
            } else {
                $status = 'Pending';
                $deducted = ($charge > 0) ? 1 : 0;
                if ($fulfilment === 'manual') {
                    $stmt = $conn->prepare('INSERT INTO orders (user_id, service_name, service_id, link, quantity, status, notes, charge, charge_deducted, fulfilment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('isssissdis', $user['id'], $service_name, $service_id, $link, $quantity, $status, $notes, $charge, $deducted, $fulfilment);
                } else {
                    $stmt = $conn->prepare('INSERT INTO orders (user_id, service_name, service_id, link, quantity, status, notes, charge, charge_deducted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('isssissdi', $user['id'], $service_name, $service_id, $link, $quantity, $status, $notes, $charge, $deducted);
                }
                if (!$stmt->execute()) {
                    $message = 'Order could not be saved. Please try again later.';
                    $stmt->close();
                    $conn->close();
                } else {
                    $order_id = $stmt->insert_id;
                    $stmt->close();
                    if ($deducted) {
                        $du = $conn->prepare('UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?');
                        $du->bind_param('did', $charge, $user['id'], $charge);
                        $du->execute();
                        if ($du->affected_rows !== 1) {
                            $du->close();
                            $del = $conn->prepare('DELETE FROM orders WHERE id = ? AND user_id = ?');
                            $del->bind_param('ii', $order_id, $user['id']);
                            $del->execute(); $del->close();
                            $message = 'Order could not be saved (balance check failed). Please try again.';
                            $conn->close();
                            $order_id = 0;
                        } else {
                            $du->close();
                            log_balance_event($conn, (int) $user['id'], -$charge, 'order_charge', 'order', (int) $order_id, 'Order #' . (int) $order_id . ' placed');
                        }
                    }
                    // Manual orders are NEVER sent anywhere automatically.
                    if ($order_id > 0 && $fulfilment !== 'manual' && $service_id !== null && $service_id !== '') {
                        $sent = provider_place_order($service_id, $link, $quantity);
                        if ($sent['ok']) {
                            $new_status = 'Sent';
                            $upd = $conn->prepare("UPDATE orders SET status = ?, provider_order_id = ? WHERE id = ? AND user_id = ?");
                            $upd->bind_param('ssii', $new_status, $sent['provider_order_id'], $order_id, $user['id']);
                            $upd->execute();
                            $upd->close();
                        }
                    }
                    $conn->close();
                    if ($order_id > 0) { header('Location: orders.php?created=' . (int) $order_id); exit; }
                }
            }
        }
    }
}
}

$form_disabled = !$db_ready || $maintenance || $api_failed;
$bar_rate = 0; $bar_min = 1; $bar_max = 1000000; $bar_qty = '';
if ($selected_row !== null) {
    $bar_rate = retail_price($selected_row['rate'] ?? $selected_row['price'] ?? null, category_markup_percent($config, (string) ($selected_row['category'] ?? ''))) ?? 0;
    $bar_min = (int) ($selected_row['min'] ?? 1);
    $bar_max = (int) ($selected_row['max'] ?? 1000000);
    $bar_qty = trim($_POST['quantity'] ?? '');
    if ($bar_qty === '') { $bar_qty = (string) $bar_min; }
}
$bar_total = ($bar_qty !== '' && ctype_digit((string) $bar_qty)) ? round($bar_rate / 1000 * (int) $bar_qty, 2) : 0;

// Platform -> Category -> Service cascade (2026-10-29, his order).
// Platform is derived from the category name itself (e.g. a category
// containing "Instagram" belongs to Instagram); anything unmatched is
// "Other". No new data is invented: categories and services come only
// from the merged live list above.
$order_platforms = [];
$order_cats = [];
$order_cat_platform = [];
if ($merged_services !== null) {
    foreach ($merged_services as $row) {
        $cat = (string) ($row['category'] ?? '');
        if ($cat === '') continue;
        if (!in_array($cat, $order_cats, true)) $order_cats[] = $cat;
        $plat = 'Other';
        foreach (['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Telegram', 'WhatsApp'] as $p) {
            if (stripos($cat, $p) !== false) { $plat = $p; break; }
        }
        if ($plat === 'Other' && preg_match('/(^|[^A-Za-z])X([^A-Za-z]|$)/', $cat)) $plat = 'X';
        $order_cat_platform[$cat] = $plat;
        if (!in_array($plat, $order_platforms, true)) $order_platforms[] = $plat;
    }
    sort($order_cats);
}
$filter_platform = trim($_GET['platform'] ?? '');
if ($filter_platform !== '' && !in_array($filter_platform, $order_platforms, true)) $filter_platform = '';
$filter_category = trim($_GET['category'] ?? '');
if ($filter_category !== '' && !in_array($filter_category, $order_cats, true)) $filter_category = '';
if ($filter_platform !== '' && $filter_category !== '' && ($order_cat_platform[$filter_category] ?? '') !== $filter_platform) $filter_category = '';
if ($selected_row !== null) {
    $sel_cat = (string) ($selected_row['category'] ?? '');
    if ($sel_cat !== '' && $filter_category === '') $filter_category = $sel_cat;
    if ($sel_cat !== '' && $filter_platform === '' && isset($order_cat_platform[$sel_cat])) $filter_platform = $order_cat_platform[$sel_cat];
}
$order_services = $merged_services;
if ($merged_services !== null) {
    $order_services = [];
    foreach ($merged_services as $row) {
        $cat = (string) ($row['category'] ?? '');
        if ($filter_category !== '' && $cat !== $filter_category) continue;
        if ($filter_category === '' && $filter_platform !== '' && ($order_cat_platform[$cat] ?? 'Other') !== $filter_platform) continue;
        $order_services[] = $row;
    }
}
$order_cats_shown = [];
foreach ($order_cats as $c) {
    if ($filter_platform === '' || ($order_cat_platform[$c] ?? 'Other') === $filter_platform) $order_cats_shown[] = $c;
}

require __DIR__ . '/includes/header.php';
?>
<h1>Place Your Order</h1>
<?php if ($balance !== null): ?><div class="card balance-card balance-card-compact"><div><p class="balance-label">Your Balance</p><p class="balance-amount"><?php echo htmlspecialchars(number_format($balance, 2) . ' ' . $config['currency']); ?></p></div><div><a class="btn btn-blue" href="add-funds.php">Add Funds Now</a></div></div><p class="small">The charge shown below is deducted only when you place the order. If an order cannot be completed, it stays Pending and you can ask for a refund.</p><?php endif; ?>
<?php if (!$db_ready): ?><p class="notice">Ordering is opening soon — please check back shortly.</p><?php endif; ?>
<?php if ($maintenance): ?><p class="notice notice-warning"><?php echo htmlspecialchars(trim((string) ($config['maintenance_message'] ?? '')) !== '' ? trim((string) $config['maintenance_message']) : 'Ordering is paused for maintenance right now — please check back shortly.'); ?></p><?php endif; ?>
<?php if ($api_failed): ?>
<p class="notice">Our catalogue could not be loaded just now. Please reload the page — your balance and orders are safe.</p>
<?php elseif ($merged_services !== null): ?>
<p class="notice">You're all set — pick a service below and your order is sent for processing right away. If it cannot be completed, it stays Pending and we will retry it for you.</p>
<?php else: ?>
<p class="notice">Your order is saved as Pending and will be processed as soon as our live catalogue is ready. No delivery time is promised until then.</p>
<?php endif; ?>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php if ($show_add_funds_link): ?><p><a class="btn btn-blue" href="add-funds.php">Add Funds Now</a></p><?php endif; ?><?php endif; ?>
<?php if ($selected_row !== null): ?>
<div class="card">
  <h3><?php echo htmlspecialchars((string) ($selected_row['name'] ?? $selected_row['service'] ?? '')); ?></h3>
  <p>Category: <?php echo htmlspecialchars((string) ($selected_row['category'] ?? '—')); ?><br>
  Price: <?php echo htmlspecialchars(number_format($bar_rate, 2) . ' ' . $config['currency']); ?> per 1,000<br>
  Minimum: <?php echo (int) $bar_min; ?> &nbsp;|&nbsp; Maximum: <?php echo (int) $bar_max; ?><br>
  Delivery time: <?php $sdt = service_delivery_time($selected_row); echo htmlspecialchars($sdt !== '' ? $sdt : '—'); ?></p>
</div>
<?php endif; ?>
<?php if ($merged_services !== null): ?>
<nav class="platform-chips" aria-label="Platforms">
  <a class="chip<?php echo $filter_platform === '' ? ' active' : ''; ?>" href="new-order.php">All</a>
  <?php foreach ($order_platforms as $p): $pc = strtolower($p); $pclass = in_array($pc, ['instagram', 'tiktok', 'youtube', 'facebook', 'telegram', 'x'], true) ? ' chip-' . $pc : ''; ?>
  <a class="chip<?php echo $pclass; ?><?php echo $filter_platform === $p ? ' active' : ''; ?>" href="new-order.php?platform=<?php echo urlencode($p); ?>"><?php echo htmlspecialchars($p); ?></a>
  <?php endforeach; ?>
</nav>
<form method="get" action="new-order.php" class="filter-form">
  <?php if ($filter_platform !== ''): ?><input type="hidden" name="platform" value="<?php echo htmlspecialchars($filter_platform); ?>"><?php endif; ?>
  <label>Category <select id="order_category" name="category"><option value="">— Choose a category —</option><?php foreach ($order_cats_shown as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"<?php echo $c === $filter_category ? ' selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select></label>
  <button class="btn btn-blue" type="submit">Show Services</button>
</form>
<p class="small">Pick a platform above, then its category here — the service list below will show only that category's services.</p>
<?php if (empty($order_services)): ?><p class="notice">No services in this selection right now. Try another platform or category.</p><?php endif; ?>
<?php endif; ?>
<form method="post" action="new-order.php<?php echo $preselected_id !== '' ? '?service=' . urlencode($preselected_id) : ''; ?>" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
  <?php if ($merged_services !== null): ?>
  <label for="service_search">Search services</label>
  <input type="text" id="service_search" placeholder="Search services…" <?php echo $form_disabled ? 'disabled' : ''; ?>>
  <label for="service_id">Choose Your Service</label>
  <select id="service_id" name="service_id" required <?php echo $form_disabled ? 'disabled' : ''; ?>>
    <option value="">— Choose a service —</option>
    <?php foreach ($order_services as $row): $row_id = (string) ($row['service'] ?? $row['id'] ?? ''); $row_name = (string) ($row['name'] ?? $row['service_name'] ?? $row_id); $retail = retail_price($row['rate'] ?? $row['price'] ?? null, category_markup_percent($config, (string) ($row['category'] ?? ''))); $row_rate = $retail === null ? '' : ' — ' . number_format($retail, 2) . ' ' . $config['currency'] . '/1000'; $row_dt = service_delivery_time($row); $row_time = $row_dt !== '' ? ' — Delivery: ' . $row_dt : ''; ?>
    <option value="<?php echo htmlspecialchars($row_id); ?>" data-category="<?php echo htmlspecialchars((string) ($row['category'] ?? '')); ?>" data-rate="<?php echo htmlspecialchars((string) ($retail ?? 0)); ?>" data-min="<?php echo (int) ($row['min'] ?? 1); ?>" data-max="<?php echo (int) ($row['max'] ?? 1000000); ?>"<?php echo $row_id === $preselected_id ? ' selected' : ''; ?>><?php echo htmlspecialchars($row_name . $row_rate . $row_time); ?></option>
    <?php endforeach; ?>
  </select>
  <?php else: ?>
  <label for="service_name">Service name</label>
  <input type="text" id="service_name" name="service_name" required maxlength="255" value="<?php echo htmlspecialchars(trim($_POST['service_name'] ?? '')); ?>" <?php echo $form_disabled ? 'disabled' : ''; ?>>
  <?php endif; ?>
  <label for="link">Your Link</label>
  <p class="small">Paste the full link to your post, profile or video.</p>
  <input type="url" id="link" name="link" required maxlength="1000" placeholder="https://…" value="<?php echo htmlspecialchars(trim($_POST['link'] ?? '')); ?>" <?php echo $form_disabled ? 'disabled' : ''; ?>>
  <label for="quantity">Quantity</label>
  <div class="qty-row">
    <button type="button" class="qty-btn" data-qty-step="-1" <?php echo $form_disabled ? 'disabled' : ''; ?>>−</button>
    <input type="number" id="quantity" name="quantity" required min="1" max="1000000" value="<?php echo htmlspecialchars($bar_qty); ?>" <?php echo $form_disabled ? 'disabled' : ''; ?>>
    <button type="button" class="qty-btn" data-qty-step="1" <?php echo $form_disabled ? 'disabled' : ''; ?>>+</button>
  </div>
  <p>
    <button type="button" class="qty-pill" data-qty-pill="min" <?php echo $form_disabled ? 'disabled' : ''; ?>>Min</button>
    <button type="button" class="qty-pill" data-qty-pill="100" <?php echo $form_disabled ? 'disabled' : ''; ?>>100</button>
    <button type="button" class="qty-pill" data-qty-pill="500" <?php echo $form_disabled ? 'disabled' : ''; ?>>500</button>
    <button type="button" class="qty-pill" data-qty-pill="1000" <?php echo $form_disabled ? 'disabled' : ''; ?>>1,000</button>
    <button type="button" class="qty-pill" data-qty-pill="max" <?php echo $form_disabled ? 'disabled' : ''; ?>>Max</button>
  </p>
  <label for="notes">Notes (optional)</label>
  <p class="small">Anything we should know about this order?</p>
  <input type="text" id="notes" name="notes" maxlength="500" value="<?php echo htmlspecialchars(trim($_POST['notes'] ?? '')); ?>" <?php echo $form_disabled ? 'disabled' : ''; ?>>
  <div class="charge-bar" id="charge_bar" data-rate="<?php echo htmlspecialchars((string) $bar_rate); ?>" data-balance="<?php echo htmlspecialchars((string) ($balance ?? 0)); ?>" data-min="<?php echo (int) $bar_min; ?>" data-max="<?php echo (int) $bar_max; ?>">
    <div>Balance: <strong id="cb_balance"><?php echo htmlspecialchars(number_format((float) ($balance ?? 0), 2)); ?></strong> <?php echo htmlspecialchars($config['currency']); ?></div>
    <div>Service price: <strong id="cb_price"><?php echo htmlspecialchars(number_format($bar_rate, 2)); ?></strong> <?php echo htmlspecialchars($config['currency']); ?> / 1,000</div>
    <div>Quantity: <strong id="cb_qty"><?php echo htmlspecialchars($bar_qty === '' ? '—' : $bar_qty); ?></strong></div>
    <div class="charge-total">Total charge: <span id="cb_total"><?php echo htmlspecialchars(number_format($bar_total, 2)); ?></span> <?php echo htmlspecialchars($config['currency']); ?></div>
  </div>
  <p><button class="btn btn-blue" type="submit" <?php echo $form_disabled ? 'disabled' : ''; ?>>Place Your Order</button></p>
</form>
<p class="small">Delivery time for each service? See it on the <a href="services.php">Services page</a>, wherever it is available.</p>
<p><a href="orders.php">View my orders</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>

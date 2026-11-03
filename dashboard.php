<?php
/**
 * SMM APEX PLUS — customer dashboard (v2).
 * Logged-in customers only. Shows the five dashboard statistics,
 * Quick Actions and Announcements — all filtered to the session
 * user, never another customer's data. Recent orders live on My Orders.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_login();
$user = current_user();
$config = get_effective_config();
$page_title = 'Dashboard';
$meta_description = 'Your SMM APEX PLUS dashboard — your balance, your orders, your fund requests, and your support tickets at a glance.';
$active = 'dashboard';
$db_ready = db_available();
$balance = 0;
$total_orders = 0;
$pending_orders = 0;
$open_tickets = 0;
$pending_funds = 0;
$completed_orders = 0;
$total_spent = 0;
if ($db_ready) {
    $conn = db_connect();
    if ($conn !== null) {
        $stmt = $conn->prepare('SELECT balance FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $balance = (float) $row['balance'];
        }
        $stmt = $conn->prepare('SELECT COUNT(*) c, SUM(status = ?) p, SUM(status = ?) comp, SUM(CASE WHEN charge_deducted = 1 THEN charge ELSE 0 END) spent FROM orders WHERE user_id = ?');
        $pend = 'Pending';
        $comp = 'Completed';
        $stmt->bind_param('ssi', $pend, $comp, $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $total_orders = (int) $row['c'];
            $pending_orders = (int) $row['p'];
            $completed_orders = (int) ($row['comp'] ?? 0);
            $total_spent = (float) ($row['spent'] ?? 0);
        }
        $stmt = $conn->prepare('SELECT COUNT(*) c FROM tickets WHERE user_id = ? AND status = ?');
        $open = 'open';
        $stmt->bind_param('is', $user['id'], $open);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $open_tickets = (int) $row['c'];
        }
        $stmt = $conn->prepare('SELECT COUNT(*) c FROM fund_requests WHERE user_id = ? AND status = ?');
        $stmt->bind_param('is', $user['id'], $pend);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $pending_funds = (int) $row['c'];
        }
        $conn->close();
    } else {
        $db_ready = false;
    }
}
require __DIR__ . '/includes/header.php';
?>
<h1>Your Dashboard</h1>
<p>Welcome back, <?php echo htmlspecialchars($user['username']); ?> — here's everything at a glance.</p>
<?php if (!$db_ready): ?>
<?php else: ?>
<?php if ($pending_funds > 0 || $pending_orders > 0 || $open_tickets > 0): ?>
<p class="notice notice-warning">Needs your attention: <?php $parts = []; if ($pending_funds > 0) $parts[] = '<a href="add-funds.php">' . (int) $pending_funds . ' fund request(s) waiting</a>'; if ($pending_orders > 0) $parts[] = '<a href="orders.php">' . (int) $pending_orders . ' order(s) in progress</a>'; if ($open_tickets > 0) $parts[] = '<a href="tickets.php">' . (int) $open_tickets . ' open ticket(s)</a>'; echo implode(' · ', $parts); ?></p>
<?php endif; ?>
<div class="grid">
  <div class="card"><h3>Available Balance</h3><p class="stat-value"><?php echo htmlspecialchars(number_format($balance, 2) . ' ' . $config['currency']); ?></p></div>
  <div class="card"><h3>Total Orders</h3><p class="stat-value"><?php echo (int) $total_orders; ?></p></div>
  <div class="card"><h3>Completed Orders</h3><p class="stat-value"><?php echo (int) $completed_orders; ?></p></div>
  <div class="card"><h3>Pending Orders</h3><p class="stat-value"><?php echo (int) $pending_orders; ?></p></div>
  <div class="card"><h3>Total Spent</h3><p class="stat-value"><?php echo htmlspecialchars(number_format($total_spent, 2) . ' ' . $config['currency']); ?></p></div>
</div>
<h2>Quick Actions</h2>
<p><a class="btn btn-blue" href="add-funds.php">Add Funds</a> <a class="btn btn-blue" href="new-order.php">New Order</a></p>
<h2>Announcements</h2>
<?php if (trim((string) ($config['announcement'] ?? '')) !== ''): ?>
<div class="card"><p><?php echo htmlspecialchars((string) $config['announcement']); ?></p></div>
<?php else: ?>
<p>No announcements yet.</p>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * SMM APEX PLUS — customer dashboard (v2).
 * Logged-in customers only. Shows balance, order counts, recent
 * orders, open tickets and pending fund requests — all filtered to
 * the session user, never another customer's data.
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
$recent = [];
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
        $stmt = $conn->prepare('SELECT COUNT(*) c, SUM(status = ?) p FROM orders WHERE user_id = ?');
        $pend = 'Pending';
        $stmt->bind_param('si', $pend, $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $total_orders = (int) $row['c'];
            $pending_orders = (int) $row['p'];
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
        $stmt = $conn->prepare('SELECT id, service_name, quantity, status, charge, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 5');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recent[] = $row;
        }
        $stmt->close();
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
<p class="notice">Dashboard is opening soon — please check back shortly.</p>
<?php else: ?>
<?php if ($pending_funds > 0 || $pending_orders > 0 || $open_tickets > 0): ?>
<p class="notice notice-warning">Needs your attention: <?php $parts = []; if ($pending_funds > 0) $parts[] = '<a href="add-funds.php">' . (int) $pending_funds . ' fund request(s) waiting</a>'; if ($pending_orders > 0) $parts[] = '<a href="orders.php">' . (int) $pending_orders . ' order(s) in progress</a>'; if ($open_tickets > 0) $parts[] = '<a href="tickets.php">' . (int) $open_tickets . ' open ticket(s)</a>'; echo implode(' · ', $parts); ?></p>
<?php endif; ?>
<div class="grid">
  <div class="card balance-card"><p class="balance-label">Your Balance</p><p class="balance-amount"><?php echo htmlspecialchars(number_format($balance, 2) . ' ' . $config['currency']); ?></p><p class="balance-note">Top up anytime with EasyPaisa, JazzCash or UPaisa.</p><p><a class="btn btn-blue" href="add-funds.php">Add Funds Now</a></p></div>
  <div class="card card-accent-teal"><h3>Your Orders</h3><p class="stat-value"><?php echo (int) $total_orders; ?> total · <?php echo (int) $pending_orders; ?> still in progress</p><p><a href="orders.php">View My Orders</a> &nbsp;|&nbsp; <a href="new-order.php">Place a New Order</a></p></div>
  <div class="card card-accent-violet"><h3>Support &amp; Funds</h3><p class="stat-value"><?php echo (int) $open_tickets; ?> open tickets · <?php echo (int) $pending_funds; ?> fund requests waiting</p><p><a href="tickets.php">Open a Ticket</a> &nbsp;|&nbsp; <a href="add-funds.php">Add Funds</a></p></div>
</div>
<h2>Your Recent Orders <span class="small"><a href="orders.php">View all →</a></span></h2>
<?php if (empty($recent)): ?>
<p>You haven't placed an order yet — your first one is just a minute away.</p>
<p><a class="btn btn-blue" href="new-order.php">Place Your First Order</a></p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Service</th><th>Qty</th><th>Charge</th><th>Status</th><th>Placed</th></tr></thead>
  <tbody>
    <?php foreach ($recent as $row): ?>
    <tr>
      <td><a href="orders.php?id=<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></a></td>
      <td><?php echo htmlspecialchars($row['service_name']); ?></td>
      <td><?php echo (int) $row['quantity']; ?></td>
      <td><?php echo htmlspecialchars(number_format((float) ($row['charge'] ?? 0), 2)); ?></td>
      <td><?php echo status_pill($row['status']); ?></td>
      <td><?php echo htmlspecialchars($row['created_at']); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

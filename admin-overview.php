<?php
/**
 * SMM APEX PLUS — Admin Overview (v2).
 *
 * Admin only (require_admin). Every number below is a server-rendered
 * local-database query — no number is hardcoded, no total is
 * invented. "Today's revenue" means the sum of charges on
 * non-refunded orders today; it is labelled as such, never as
 * profit (fulfilment cost is not in this database). The manual
 * card is rendered only when the fulfilment column exists (after
 * the Service Manager migration); before that it is omitted.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_admin();
ensure_session();
$config = get_effective_config();
$page_title = 'Admin Overview';
$active = 'admin';
$db_ready = db_available();
$orders_today = 0;
$revenue_today = 0.0;
$pending_funds = 0;
$open_tickets = 0;
$stuck_orders = 0;
$total_users = 0;
$manual_pending = null;
$today_orders = [];
if ($db_ready) {
    $conn = db_connect();
    if ($conn !== null) {
        $r = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(charge), 0) s FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'Refunded'");
        if ($r && ($row = $r->fetch_assoc())) {
            $orders_today = (int) $row['c'];
            $revenue_today = (float) $row['s'];
        }
        $r = $conn->query("SELECT COUNT(*) c FROM fund_requests WHERE status = 'Pending'");
        if ($r && ($row = $r->fetch_assoc())) {
            $pending_funds = (int) $row['c'];
        }
        $r = $conn->query("SELECT COUNT(*) c FROM tickets WHERE status = 'open'");
        if ($r && ($row = $r->fetch_assoc())) {
            $open_tickets = (int) $row['c'];
        }
        $r = $conn->query("SELECT COUNT(*) c FROM orders WHERE status = 'Pending' AND created_at < NOW() - INTERVAL 24 HOUR");
        if ($r && ($row = $r->fetch_assoc())) {
            $stuck_orders = (int) $row['c'];
        }
        $r = $conn->query('SELECT COUNT(*) c FROM users');
        if ($r && ($row = $r->fetch_assoc())) {
            $total_users = (int) $row['c'];
        }
        $r = $conn->query("SELECT COUNT(*) c FROM orders WHERE fulfilment = 'manual' AND status = 'Pending'");
        if ($r && ($row = $r->fetch_assoc())) {
            $manual_pending = (int) $row['c'];
        }
        $result = $conn->query('SELECT o.id, o.user_id, u.username, o.service_name, o.quantity, o.charge, o.status, o.created_at FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE DATE(o.created_at) = CURDATE() ORDER BY o.created_at DESC, o.id DESC LIMIT 10');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $today_orders[] = $row;
            }
        }
        $conn->close();
    } else {
        $db_ready = false;
    }
}
require __DIR__ . '/includes/header.php';
?>
<h1>Admin Overview</h1>
<p class="notice">Today at a glance — all figures come from this panel's own database. Nothing here is visible to customers.</p>
<?php if (!$db_ready): ?>
<p class="notice">Database not configured yet.</p>
<?php else: ?>
<div class="grid admin-overview">
  <div class="card card-accent-teal">
    <h3>Orders Today</h3>
    <p class="stat-value"><?php echo (int) $orders_today; ?></p>
    <p>Today's revenue (non-refunded orders): <?php echo htmlspecialchars(number_format($revenue_today, 2) . ' ' . $config['currency']); ?></p>
    <p><a href="admin-orders.php?filter=today">View today's orders →</a></p>
  </div>
  <div class="card card-accent-amber">
    <h3>Pending Funds</h3>
    <p class="stat-value"><?php echo (int) $pending_funds; ?></p>
    <p>Fund requests waiting for approval.</p>
    <p><a href="admin-funds.php">Review fund requests →</a></p>
  </div>
  <div class="card card-accent-violet">
    <h3>Open Tickets</h3>
    <p class="stat-value"><?php echo (int) $open_tickets; ?></p>
    <p>Support conversations waiting for a reply.</p>
    <p><a href="admin-tickets.php">Answer tickets →</a></p>
  </div>
  <div class="card" style="border-top: 3px solid var(--danger);">
    <h3>Stuck Orders</h3>
    <p class="stat-value"><?php echo (int) $stuck_orders; ?></p>
    <p>Pending for more than 24 hours — these need a look first.</p>
    <p><a href="admin-orders.php?filter=stuck">View stuck orders →</a></p>
  </div>
  <div class="card card-accent-emerald">
    <h3>Total Users</h3>
    <p class="stat-value"><?php echo (int) $total_users; ?></p>
    <p><a href="admin-users.php">Manage users →</a></p>
  </div>
  <?php if ($manual_pending !== null): ?>
  <div class="card card-accent-amber">
    <h3>Manual Orders Waiting</h3>
    <p class="stat-value"><?php echo (int) $manual_pending; ?></p>
    <p>Hand-fulfilled orders waiting for you to mark them completed.</p>
    <p><a href="admin-orders.php?filter=manual">View manual orders →</a></p>
  </div>
  <?php endif; ?>
</div>

<h2>Today's Orders</h2>
<p class="small">The newest orders first. Full detail, Send, Refund and status refresh live on Admin Orders.</p>
<?php if (empty($today_orders)): ?>
<p>No orders today yet.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>User</th><th>Service</th><th>Qty</th><th>Charge</th><th>Status</th><th>Placed</th></tr></thead>
  <tbody>
    <?php foreach ($today_orders as $row): ?>
    <tr>
      <td>#<?php echo (int) $row['id']; ?></td>
      <td><?php echo htmlspecialchars((string) ($row['username'] ?? ('user ' . (int) $row['user_id']))); ?></td>
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
<p><a href="admin.php">Back to Admin Settings</a> | <a href="admin-services.php">Manage Services</a> | <a href="admin-audit.php">Balance Audit Log</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>

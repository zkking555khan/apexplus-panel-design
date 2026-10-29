<?php
/**
 * SMM APEX PLUS — order tracking (v2).
 * Logged-in customers only, and each customer sees ONLY their own
 * orders (every query filters by the session user_id, and the detail
 * view re-checks ownership). Status shown here is the stored status:
 * 'Pending' until the order is sent — it is never upgraded
 * by this page on its own.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_login();
$user = current_user();
$config = get_effective_config();
$page_title = 'My Orders';
$meta_description = 'Track your SMM APEX PLUS orders — status, quantity, charge, and Tracking ID for every order you placed.';
$active = 'orders';
$db_ready = db_available();
$detail = null;
$orders = [];
$created_id = isset($_GET['created']) ? (int) $_GET['created'] : 0;
$detail_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($db_ready) {
    $conn = db_connect();
    if ($conn === null) {
        $db_ready = false;
    } elseif ($detail_id > 0) {
        $stmt = $conn->prepare('SELECT id, service_name, link, quantity, status, provider_order_id, notes, charge, created_at FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $detail_id, $user['id']);
        $stmt->execute();
        $detail = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        $conn->close();
    } else {
        $stmt = $conn->prepare('SELECT id, service_name, link, quantity, status, provider_order_id, charge, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
        $conn->close();
    }
}

require __DIR__ . '/includes/header.php';
?>
<h1>Your Orders</h1>
<?php if ($created_id > 0): ?><p class="notice notice-success">Order #<?php echo (int) $created_id; ?> placed successfully! Track it below — its Tracking ID appears once it is sent for processing.</p><?php endif; ?>
<?php if (isset($_GET['welcome'])): ?><p class="notice">Welcome, <?php echo htmlspecialchars($user['username']); ?>! Your account is ready — pick a service and grow faster.</p><?php endif; ?>
<?php if (!$db_ready): ?>
  <p class="notice">Orders are opening soon — please check back shortly.</p>
<?php elseif ($detail_id > 0): ?>
  <?php if ($detail === null): ?>
    <p class="notice">We couldn't find that order — it may belong to a different account.</p>
  <?php else: ?>
    <h2>Order #<?php echo (int) $detail['id']; ?></h2>
    <p>Service: <?php echo htmlspecialchars($detail['service_name']); ?><br>
    Link: <?php echo htmlspecialchars($detail['link']); ?><br>
    Quantity: <?php echo (int) $detail['quantity']; ?><br>
    Status: <?php echo status_pill($detail['status']); ?><br>
    Charge: <?php echo htmlspecialchars(number_format((float) ($detail['charge'] ?? 0), 2)); ?><br>
    Tracking ID: <?php echo $detail['provider_order_id'] !== null && $detail['provider_order_id'] !== '' ? htmlspecialchars($detail['provider_order_id']) : '— (waiting to be sent)'; ?><br>
    Notes: <?php echo htmlspecialchars((string) ($detail['notes'] ?? '')); ?><br>
    Placed: <?php echo htmlspecialchars($detail['created_at']); ?></p>
    <?php if ($detail['status'] !== 'Refunded' && $detail['status'] !== 'Completed'): ?>
    <p><a class="btn btn-outline" href="tickets.php?<?php echo htmlspecialchars(http_build_query(['subject' => 'Refund request for order #' . (int) $detail['id'], 'message' => 'Please refund order #' . (int) $detail['id'] . ' (' . $detail['service_name'] . ').'])); ?>">Request a Refund</a></p>
    <p class="small">Refunds are approved by support and credited exactly once to your balance.</p>
    <?php endif; ?>
  <?php endif; ?>
  <p><a href="orders.php">Back to all orders</a></p>
<?php elseif (empty($orders)): ?>
  <p>No orders yet — your first order is just a minute away.</p>
  <p><a class="btn btn-blue" href="new-order.php">Place Your First Order</a></p>
<?php else: ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>ID</th><th>Service</th><th>Quantity</th><th>Charge</th><th>Status</th><th>Placed</th></tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $row): ?>
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
<p><a class="btn btn-blue" href="new-order.php">Place a New Order</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>

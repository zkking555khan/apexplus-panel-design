<?php
/**
 * SMM APEX PLUS — Admin Orders (v2).
 *
 * Admin only (require_admin). Lists every customer's orders.
 * - "Send to provider": for a Pending LIVE order that has a
 *   service ID, sends it via the live API. On success the real
 *   order ID is stored and status becomes 'Sent'. On failure the
 *   order stays Pending with the error shown. Never shown for
 *   manual orders — manual orders are never sent anywhere.
 * - "Refresh status": for a sent order, asks for its current
 *   status and stores ONLY a status actually returned. Hidden for
 *   manual orders (they have no live status to refresh).
 * - "Mark Completed": for a Pending MANUAL order only. Changes the
 *   status to 'Completed' and nothing else — never touches the
 *   balance, never calls the live API.
 * - "Refund": exactly once, via the charge_deducted guard. Refunds
 *   write a balance audit row.
 * - Filters: ?filter=today (today's orders), ?filter=stuck
 *   (Pending over 24h), ?filter=manual (hand-fulfilled orders),
 *   ?filter=pending, plus ?q= search over id, username, service
 *   name and link. Export respects the current filter.
 * No status or order ID is ever invented here.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/api.php';
require __DIR__ . '/includes/service-overrides.php';
require_admin();
ensure_session();
$config = get_effective_config();
$page_title = 'Admin Orders';
$active = 'admin';
$message = '';
$db_ready = db_available();
$orders = [];
$filter = trim($_GET['filter'] ?? '');
$search_q = trim($_GET['q'] ?? '');
if (!in_array($filter, ['today', 'stuck', 'manual', 'pending', ''], true)) {
    $filter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_ready) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'Security check failed. Please reload the page and try again.';
    } else {
        $order_id = (int) ($_POST['order_id'] ?? 0);
        $action = trim($_POST['action'] ?? '');
        $conn = db_connect();
        if ($conn === null) {
            $message = 'Database could not be reached. Please try again later.';
        } elseif ($order_id <= 0) {
            $message = 'Invalid order.';
            $conn->close();
        } else {
            $stmt = $conn->prepare('SELECT id, user_id, service_id, link, quantity, status, provider_order_id, charge, charge_deducted, fulfilment FROM orders WHERE id = ? LIMIT 1');
            if ($stmt === false) {
                // Pre-migration table (no fulfilment column): re-read without it.
                $stmt = $conn->prepare('SELECT id, user_id, service_id, link, quantity, status, provider_order_id, charge, charge_deducted FROM orders WHERE id = ? LIMIT 1');
            }
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            if ($order === null) {
                $message = 'Order not found.';
            } else {
                $is_manual = (($order['fulfilment'] ?? 'live') === 'manual') || is_manual_service_id($order['service_id'] ?? '');
                if ($action === 'send') {
                    if ($is_manual) {
                        $message = 'Manual orders are fulfilled by hand and are never sent automatically.';
                    } elseif ($order['status'] !== 'Pending') {
                        $message = 'Only Pending orders can be sent.';
                    } elseif (empty($order['service_id'])) {
                        $message = 'This order has no service ID (it was placed before the catalogue was connected), so it cannot be sent automatically.';
                    } else {
                        $sent = provider_place_order($order['service_id'], $order['link'], (int) $order['quantity']);
                        if ($sent['ok']) {
                            $new_status = 'Sent';
                            $upd = $conn->prepare('UPDATE orders SET status = ?, provider_order_id = ? WHERE id = ?');
                            $upd->bind_param('ssi', $new_status, $sent['provider_order_id'], $order_id);
                            $upd->execute();
                            $upd->close();
                            $message = 'Order #' . $order_id . ' sent (order ID ' . $sent['provider_order_id'] . ').';
                        } else {
                            $message = 'Send failed: ' . $sent['error'] . ' The order stays Pending.';
                        }
                    }
                } elseif ($action === 'complete_manual') {
                    if (!$is_manual) {
                        $message = 'Only manual orders can be marked completed by hand.';
                    } elseif ($order['status'] !== 'Pending') {
                        $message = 'Only Pending manual orders can be marked completed.';
                    } else {
                        $upd = $conn->prepare("UPDATE orders SET status = 'Completed' WHERE id = ? AND status = 'Pending'");
                        $upd->bind_param('i', $order_id);
                        $upd->execute();
                        $changed = $upd->affected_rows;
                        $upd->close();
                        $message = $changed === 1 ? 'Order #' . $order_id . ' marked Completed. No balance was changed.' : 'Order #' . $order_id . ' could not be marked completed.';
                    }
                } elseif ($action === 'refund') {
                    if ((int) ($order['charge_deducted'] ?? 0) !== 1 || (float) ($order['charge'] ?? 0) <= 0) {
                        $message = 'This order has no deducted charge to refund.';
                    } elseif ($order['status'] === 'Refunded') {
                        $message = 'This order was already refunded.';
                    } else {
                        $charge = (float) $order['charge'];
                        $uid = (int) $order['user_id'];
                        $conn->begin_transaction();
                        $upd = $conn->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
                        $upd->bind_param('di', $charge, $uid);
                        $ok1 = $upd->execute(); $upd->close();
                        $upd2 = $conn->prepare("UPDATE orders SET status = 'Refunded', charge_deducted = 0 WHERE id = ? AND charge_deducted = 1");
                        $upd2->bind_param('i', $order_id);
                        $ok2 = $upd2->execute() && $upd2->affected_rows === 1; $upd2->close();
                        if ($ok1 && $ok2) {
                            log_balance_event($conn, $uid, $charge, 'order_refund', 'order', $order_id, 'Order #' . $order_id . ' refunded');
                            $conn->commit(); $message = 'Order #' . $order_id . ' refunded: ' . number_format($charge, 2) . ' credited back.';
                        } else { $conn->rollback(); $message = 'Refund failed. Please try again.'; }
                    }
                } elseif ($action === 'refresh') {
                    if ($is_manual) {
                        $message = 'Manual orders have no live status to refresh.';
                    } elseif (empty($order['provider_order_id'])) {
                        $message = 'This order has not been sent yet, so there is no status to refresh.';
                    } else {
                        $res = provider_order_status($order['provider_order_id']);
                        if ($res['ok']) {
                            $upd = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
                            $upd->bind_param('si', $res['status'], $order_id);
                            $upd->execute();
                            $upd->close();
                            $message = 'Order #' . $order_id . ' status updated to: ' . $res['status'];
                        } else {
                            $message = 'Status refresh failed: ' . $res['error'];
                        }
                    }
                } else {
                    $message = 'Unknown action.';
                }
            }
            $conn->close();
        }
    }
}

if ($db_ready) {
    $conn = db_connect();
    if ($conn !== null) {
        $where = [];
        $params = [];
        $types = '';
        if ($filter === 'today') {
            $where[] = 'DATE(o.created_at) = CURDATE()';
        } elseif ($filter === 'stuck') {
            $where[] = "o.status = 'Pending' AND o.created_at < NOW() - INTERVAL 24 HOUR";
        } elseif ($filter === 'manual') {
            $where[] = "o.fulfilment = 'manual'";
        } elseif ($filter === 'pending') {
            $where[] = "o.status = 'Pending'";
        }
        if ($search_q !== '') {
            $where[] = '(CAST(o.id AS CHAR) LIKE ? OR u.username LIKE ? OR o.service_name LIKE ? OR o.link LIKE ?)';
            $like = '%' . $search_q . '%';
            $params = [$like, $like, $like, $like];
            $types = 'ssss';
        }
        $sql = 'SELECT o.id, o.user_id, u.username, o.service_name, o.service_id, o.link, o.quantity, o.status, o.provider_order_id, o.charge, o.created_at FROM orders o LEFT JOIN users u ON u.id = o.user_id';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT 200';
        $stmt = $conn->prepare($sql);
        if ($stmt !== false) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $orders[] = $row;
                }
            }
            $stmt->close();
        }
        $conn->close();
    }
}

// CSV export: exactly the filtered rows, same columns as the table.
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $db_ready) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="apex-orders.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User', 'Service', 'Service ID', 'Link', 'Quantity', 'Charge', 'Status', 'Tracking ID', 'Placed']);
    foreach ($orders as $row) {
        fputcsv($out, [(int) $row['id'], (string) ($row['username'] ?? ('user ' . (int) $row['user_id'])), (string) $row['service_name'], (string) ($row['service_id'] ?? ''), (string) $row['link'], (int) $row['quantity'], number_format((float) ($row['charge'] ?? 0), 2), (string) $row['status'], (string) ($row['provider_order_id'] ?? ''), (string) $row['created_at']]);
    }
    fclose($out);
    exit;
}

require __DIR__ . '/includes/header.php';
?>
<h1>Admin Orders</h1>
<p class="notice">Signed in as admin. <a href="admin-overview.php">Admin Overview</a> | <a href="admin.php">Back to Admin Settings</a> | <a href="admin-funds.php">Manage Fund Requests</a></p>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="get" action="admin-orders.php" class="filter-form">
  <label>Search <input type="search" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Order ID, username, service, link"></label>
  <label>Filter <select name="filter"><option value="">All orders</option><option value="today"<?php echo $filter === 'today' ? ' selected' : ''; ?>>Today's orders</option><option value="pending"<?php echo $filter === 'pending' ? ' selected' : ''; ?>>Pending</option><option value="stuck"<?php echo $filter === 'stuck' ? ' selected' : ''; ?>>Stuck (Pending &gt; 24h)</option><option value="manual"<?php echo $filter === 'manual' ? ' selected' : ''; ?>>Manual orders</option></select></label>
  <button class="btn btn-blue" type="submit">Filter</button>
  <?php if (!empty($orders)): ?><a class="btn btn-outline" href="admin-orders.php?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>">Export filtered list (CSV)</a><?php endif; ?>
</form>
<?php if (!$db_ready): ?>
<p class="notice">Database not configured yet.</p>
<?php elseif (empty($orders)): ?>
<p>No orders match this filter.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>User</th><th>Service</th><th>Qty</th><th>Charge</th><th>Status</th><th>Tracking ID</th><th>Placed</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($orders as $row): $row_manual = is_manual_service_id($row['service_id'] ?? ''); ?>
    <tr>
      <td>#<?php echo (int) $row['id']; ?></td>
      <td><?php echo htmlspecialchars((string) ($row['username'] ?? ('user ' . (int) $row['user_id']))); ?></td>
      <td><?php echo htmlspecialchars($row['service_name']); ?><?php if ($row_manual): ?> <span class="pill pill-pending">Manual</span><?php endif; ?></td>
      <td><?php echo (int) $row['quantity']; ?></td>
      <td><?php echo htmlspecialchars(number_format((float) ($row['charge'] ?? 0), 2)); ?></td>
      <td><?php echo status_pill($row['status']); ?></td>
      <td><?php echo $row['provider_order_id'] ? htmlspecialchars($row['provider_order_id']) : '—'; ?></td>
      <td><?php echo htmlspecialchars($row['created_at']); ?></td>
      <td>
        <form method="post" action="admin-orders.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="order_id" value="<?php echo (int) $row['id']; ?>">
          <?php if ($row['status'] === 'Pending' && !$row_manual): ?><button type="submit" name="action" value="send">Send to provider</button><?php endif; ?>
          <?php if ($row['status'] === 'Pending' && $row_manual): ?><button type="submit" name="action" value="complete_manual">Mark Completed</button><?php endif; ?>
          <?php if ($row['status'] !== 'Refunded'): ?><button type="submit" name="action" value="refund">Refund</button><?php endif; ?>
          <?php if (!empty($row['provider_order_id']) && !$row_manual): ?><button type="submit" name="action" value="refresh">Refresh status</button><?php endif; ?>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

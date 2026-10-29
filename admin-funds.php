<?php
/**
 * SMM APEX PLUS — Admin Fund Requests (phase 3).
 *
 * Admin only (require_admin).
 * - Approve: credits the request's amount to that customer's
 *   balance EXACTLY ONCE. Both UPDATEs only match status='Pending',
 *   so approving the same request twice cannot credit twice, and
 *   an already-approved request is not credited again.
 * - Reject: marks the request Rejected and changes no balance.
 * Amounts are taken from the stored request only — never typed in
 * by hand here, so no amount can be invented at approval time.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_admin();
ensure_session();
$config = get_effective_config();
$page_title = 'Admin Fund Requests';
$active = 'admin';
$message = '';
$db_ready = db_available();
$requests = [];
$search_q = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_ready) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'Security check failed. Please reload the page and try again.';
    } else {
        $request_id = (int) ($_POST['request_id'] ?? 0);
        $action = trim($_POST['action'] ?? '');
        $conn = db_connect();
        if ($conn === null) {
            $message = 'Database could not be reached. Please try again later.';
        } elseif ($request_id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
            $message = 'Invalid request or action.';
            $conn->close();
        } else {
            $conn->begin_transaction();
            $stmt = $conn->prepare("SELECT user_id, amount FROM fund_requests WHERE id = ? AND status = 'Pending' LIMIT 1 FOR UPDATE");
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $req = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            if ($req === null) {
                $conn->rollback();
                $message = 'Request not found, or it is no longer Pending (it may already have been decided). No balance was changed.';
            } elseif ($action === 'approve') {
                $upd_user = $conn->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
                $upd_user->bind_param('di', $req['amount'], $req['user_id']);
                $upd_user->execute();
                $upd_user->close();
                $new_status = 'Approved';
                $upd_req = $conn->prepare("UPDATE fund_requests SET status = ? WHERE id = ? AND status = 'Pending'");
                $upd_req->bind_param('si', $new_status, $request_id);
                $upd_req->execute();
                $changed = $upd_req->affected_rows;
                $upd_req->close();
                if ($changed === 1) {
                    log_balance_event($conn, (int) $req['user_id'], (float) $req['amount'], 'fund_approve', 'fund_request', $request_id, 'Fund request #' . $request_id . ' approved');
                    $conn->commit();
                    $message = 'Request #' . $request_id . ' approved and Rs ' . $req['amount'] . ' credited to user ' . (int) $req['user_id'] . '.';
                } else {
                    $conn->rollback();
                    $message = 'Request was already decided. No balance was changed.';
                }
            } else {
                $new_status = 'Rejected';
                $upd_req = $conn->prepare("UPDATE fund_requests SET status = ? WHERE id = ? AND status = 'Pending'");
                $upd_req->bind_param('si', $new_status, $request_id);
                $upd_req->execute();
                $changed = $upd_req->affected_rows;
                $upd_req->close();
                if ($changed === 1) {
                    $conn->commit();
                    $message = 'Request #' . $request_id . ' rejected. No balance was changed.';
                } else {
                    $conn->rollback();
                    $message = 'Request was already decided. No balance was changed.';
                }
            }
            $conn->close();
        }
    }
}

if ($db_ready) {
    $conn = db_connect();
    if ($conn !== null) {
        if ($search_q !== '') {
            $like = '%' . $search_q . '%';
            $stmt = $conn->prepare('SELECT f.id, f.user_id, u.username, f.method, f.amount, f.trx_id, f.sender_number, f.status, f.created_at FROM fund_requests f LEFT JOIN users u ON u.id = f.user_id WHERE CAST(f.id AS CHAR) LIKE ? OR u.username LIKE ? OR f.trx_id LIKE ? OR f.sender_number LIKE ? ORDER BY f.created_at DESC, f.id DESC LIMIT 200');
            if ($stmt !== false) {
                $stmt->bind_param('ssss', $like, $like, $like, $like);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $requests[] = $row;
                    }
                }
                $stmt->close();
            }
        } else {
            $result = $conn->query('SELECT f.id, f.user_id, u.username, f.method, f.amount, f.trx_id, f.sender_number, f.status, f.created_at FROM fund_requests f LEFT JOIN users u ON u.id = f.user_id ORDER BY f.created_at DESC, f.id DESC LIMIT 200');
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $requests[] = $row;
                }
            }
        }
        $conn->close();
    }
}
// Duplicate transaction IDs, checked across ALL requests (warning
// only — the exactly-once approval guard is unchanged; a repeated
// TRX ID is flagged, never auto-blocked).
$trx_counts = [];
if ($db_ready) {
    $conn2 = db_connect();
    if ($conn2 !== null) {
        $dup = $conn2->query('SELECT trx_id, COUNT(*) c FROM fund_requests GROUP BY trx_id HAVING c > 1');
        if ($dup) {
            while ($d = $dup->fetch_assoc()) {
                $trx_counts[(string) $d['trx_id']] = (int) $d['c'];
            }
        }
        $conn2->close();
    }
}

require __DIR__ . '/includes/header.php';
?>
<h1>Admin Fund Requests</h1>
<p class="notice">Signed in as admin. <a href="admin-overview.php">Admin Overview</a> | <a href="admin.php">Back to Admin Settings</a> | <a href="admin-orders.php">Manage Orders</a></p>
<p>Approve only after you have actually received the payment. Approval credits the exact stored amount once — it cannot be double-credited.</p>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="get" action="admin-funds.php" class="filter-form">
  <label>Search <input type="search" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Request ID, username, TRX ID, sender"></label>
  <button class="btn btn-blue" type="submit">Filter</button>
</form>
<?php if (!$db_ready): ?>
<p class="notice">Database not configured yet.</p>
<?php elseif (empty($requests)): ?>
<p>No fund requests match this filter.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>User</th><th>Method</th><th>Amount</th><th>Transaction ID</th><th>Sender</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($requests as $row): ?>
    <tr>
      <td>#<?php echo (int) $row['id']; ?></td>
      <td><?php echo htmlspecialchars((string) ($row['username'] ?? ('user ' . (int) $row['user_id']))); ?></td>
      <td><?php echo htmlspecialchars($row['method']); ?></td>
      <td><?php echo htmlspecialchars($row['amount']); ?></td>
      <td><?php echo htmlspecialchars($row['trx_id']); ?><?php $tc = trim((string) ($row['trx_id'] ?? '')); if ($tc !== '' && ($trx_counts[$tc] ?? 0) > 1): ?> <span class="pill pill-refunded">Duplicate TRX ID — check before approving</span><?php endif; ?></td>
      <td><?php echo htmlspecialchars((string) ($row['sender_number'] ?? '')); ?></td>
      <td><?php echo status_pill($row['status']); ?></td>
      <td><?php echo htmlspecialchars($row['created_at']); ?></td>
      <td>
        <?php if ($row['status'] === 'Pending'): ?>
        <form method="post" action="admin-funds.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="request_id" value="<?php echo (int) $row['id']; ?>">
          <button type="submit" name="action" value="approve">Approve + credit</button>
          <button type="submit" name="action" value="reject">Reject</button>
        </form>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

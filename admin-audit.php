<?php
/**
 * SMM APEX PLUS — Balance Audit Log (v2).
 *
 * Admin only (require_admin). One row per money event: fund
 * approvals, order charges, order refunds and manual admin
 * adjustments, each with the reason recorded at the time. The
 * running balance is computed at display from the users table;
 * this log is the explanation, not the balance itself. When the
 * audit table does not exist yet (before the v2 migration), the
 * page says so instead of failing.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_admin();
ensure_session();
$config = get_effective_config();
$page_title = 'Balance Audit Log';
$active = 'admin';
$db_ready = db_available();
$events = [];
$table_missing = false;
$user_filter = trim($_GET['user'] ?? '');
if ($db_ready) {
    $conn = db_connect();
    if ($conn !== null) {
        if ($user_filter !== '' && ctype_digit($user_filter)) {
            $stmt = $conn->prepare('SELECT a.id, a.user_id, u.username, a.delta, a.kind, a.ref_type, a.ref_id, a.reason, a.created_at FROM balance_audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.user_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT 200');
            if ($stmt !== false) {
                $uid = (int) $user_filter;
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $events[] = $row;
                    }
                }
                $stmt->close();
            } else {
                $table_missing = true;
            }
        } else {
            $result = $conn->query('SELECT a.id, a.user_id, u.username, a.delta, a.kind, a.ref_type, a.ref_id, a.reason, a.created_at FROM balance_audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC, a.id DESC LIMIT 200');
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $events[] = $row;
                }
            } else {
                $table_missing = true;
            }
        }
        $conn->close();
    } else {
        $db_ready = false;
    }
}
require __DIR__ . '/includes/header.php';
?>
<h1>Balance Audit Log</h1>
<p class="notice">Signed in as admin. <a href="admin-overview.php">Admin Overview</a> | <a href="admin.php">Back to Admin Settings</a></p>
<p>Every balance change, with its reason: fund approvals, order charges, order refunds and manual adjustments. Balances themselves live in the users table; this log explains them.</p>
<form method="get" action="admin-audit.php" class="filter-form">
  <label>Filter by user ID <input type="search" name="user" value="<?php echo htmlspecialchars($user_filter); ?>" placeholder="User ID (blank = all)"></label>
  <button class="btn btn-blue" type="submit">Filter</button>
</form>
<?php if (!$db_ready): ?>
<p class="notice">Database not configured yet.</p>
<?php elseif ($table_missing): ?>
<p class="notice">No audit rows yet — the audit table is created by the v2 migration. Money actions still work; they are simply not logged until the table exists.</p>
<?php elseif (empty($events)): ?>
<p>No audit events match this filter.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>User</th><th>Change</th><th>Kind</th><th>Reference</th><th>Reason</th><th>When</th></tr></thead>
  <tbody>
    <?php foreach ($events as $row): ?>
    <tr>
      <td>#<?php echo (int) $row['id']; ?></td>
      <td><?php echo htmlspecialchars((string) ($row['username'] ?? ('user ' . (int) $row['user_id']))); ?></td>
      <td><?php echo htmlspecialchars(number_format((float) $row['delta'], 2)); ?></td>
      <td><?php echo htmlspecialchars($row['kind']); ?></td>
      <td><?php echo htmlspecialchars($row['ref_type'] . ($row['ref_id'] !== null ? ' #' . (int) $row['ref_id'] : '')); ?></td>
      <td><?php echo htmlspecialchars((string) ($row['reason'] ?? '')); ?></td>
      <td><?php echo htmlspecialchars($row['created_at']); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

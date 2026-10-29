<?php
/**
 * SMM APEX PLUS — Full Panel: admin users.
 * Admin only. Lists customers with balances, adjusts a balance by
 * a signed amount (never below zero), and suspends / reactivates
 * accounts. Suspended accounts cannot log in (see login.php check).
 * Balance changes here are manual admin actions — they are not
 * payment verifications, so approve a fund request only after
 * actually receiving the payment, as on the funds page.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_admin();
ensure_session();
$config = get_effective_config();
$page_title = 'Admin Users';
$active = 'admin';
$message = '';
$db_ready = db_available();
$users = [];
$search_q = trim($_GET['q'] ?? '');
if ($db_ready) {
    $conn = db_connect();
    if ($conn !== null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['csrf_token'] ?? '')) {
                $message = 'Security check failed. Please reload the page and try again.';
            } else {
                $uid = (int) ($_POST['user_id'] ?? 0);
                $action = trim($_POST['action'] ?? '');
                if ($uid > 0 && $action === 'adjust') {
                    $amount_raw = trim($_POST['amount'] ?? '');
                    $reason = substr(trim($_POST['reason'] ?? ''), 0, 200);
                    if (!is_numeric($amount_raw)) {
                        $message = 'Amount must be a number (positive to add, negative to deduct).';
                    } elseif ($reason === '') {
                        $message = 'A reason is required for every balance adjustment (it is written to the audit log).';
                    } else {
                        $amount = (float) $amount_raw;
                        $stmt = $conn->prepare('UPDATE users SET balance = balance + ? WHERE id = ? AND balance + ? >= 0');
                        $stmt->bind_param('did', $amount, $uid, $amount);
                        $stmt->execute();
                        if ($stmt->affected_rows === 1) {
                            $stmt->close();
                            log_balance_event($conn, $uid, $amount, 'admin_adjust', 'user', $uid, $reason);
                            $message = 'Balance adjusted by ' . number_format($amount, 2) . ' ' . $config['currency'] . ' for user #' . $uid . '.';
                        } else {
                            $message = 'Balance adjustment failed (would go below zero, or user not found).';
                            $stmt->close();
                        }
                    }
                } elseif ($uid > 0 && ($action === 'suspend' || $action === 'activate')) {
                    $new_status = $action === 'suspend' ? 'suspended' : 'active';
                    $stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
                    $stmt->bind_param('si', $new_status, $uid);
                    $stmt->execute();
                    $stmt->close();
                    $message = 'User #' . $uid . ' is now ' . $new_status . '.';
                }
            }
        }
        if ($search_q !== '') {
            $like = '%' . $search_q . '%';
            $stmt = $conn->prepare('SELECT id, username, email, balance, status, created_at FROM users WHERE CAST(id AS CHAR) LIKE ? OR username LIKE ? OR email LIKE ? ORDER BY created_at DESC, id DESC LIMIT 200');
            if ($stmt !== false) {
                $stmt->bind_param('sss', $like, $like, $like);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $users[] = $row;
                    }
                }
                $stmt->close();
            }
        } else {
            $result = $conn->query('SELECT id, username, email, balance, status, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 200');
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
            }
        }
        $conn->close();
    } else {
        $db_ready = false;
    }
}
require __DIR__ . '/includes/header.php';
?>
<h1>Admin Users</h1>
<p class="notice">Signed in as admin. <a href="admin-overview.php">Admin Overview</a> | <a href="admin.php">Back to Admin Settings</a></p>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="get" action="admin-users.php" class="filter-form">
  <label>Search <input type="search" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="User ID, username, email"></label>
  <button class="btn btn-blue" type="submit">Filter</button>
</form>
<?php if (!$db_ready): ?>
<p class="notice">Database not configured yet.</p>
<?php elseif (empty($users)): ?>
<p>No users match this filter.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Balance</th><th>Status</th><th>Joined</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($users as $row): ?>
    <tr>
      <td>#<?php echo (int) $row['id']; ?></td>
      <td><?php echo htmlspecialchars($row['username']); ?></td>
      <td><?php echo htmlspecialchars($row['email']); ?></td>
      <td><?php echo htmlspecialchars(number_format((float) $row['balance'], 2)); ?></td>
      <td><?php echo status_pill($row['status'] ?? 'active'); ?></td>
      <td><?php echo htmlspecialchars($row['created_at']); ?></td>
      <td>
        <form method="post" action="admin-users.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
          <input type="number" name="amount" step="0.01" placeholder="+/- amount" style="width:130px;padding:6px;border-radius:8px;border:1px solid var(--border);background:var(--input-bg);color:var(--text);">
          <input type="text" name="reason" placeholder="Reason (required)" maxlength="200" style="width:170px;padding:6px;border-radius:8px;border:1px solid var(--border);background:var(--input-bg);color:var(--text);">
          <button type="submit" name="action" value="adjust">Adjust</button>
          <button type="submit" name="action" value="<?php echo ($row['status'] ?? 'active') === 'suspended' ? 'activate' : 'suspend'; ?>"><?php echo ($row['status'] ?? 'active') === 'suspended' ? 'Activate' : 'Suspend'; ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

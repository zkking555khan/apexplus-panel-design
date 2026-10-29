<?php
/**
 * SMM APEX PLUS — Full Panel: admin tickets.
 * Admin only. Lists every customer's tickets, replies as admin,
 * and closes / reopens tickets. Replies are stored as author_type
 * 'admin' so customers can tell them apart from their own messages.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_admin();
ensure_session();
$config = get_effective_config();
$page_title = 'Admin Tickets';
$active = 'admin';
$message = '';
$db_ready = db_available();
$tickets = [];
$search_q = trim($_GET['q'] ?? '');
$view_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$view = null;
$view_messages = [];
if ($db_ready) {
    $conn = db_connect();
    if ($conn !== null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['csrf_token'] ?? '')) {
                $message = 'Security check failed. Please reload the page and try again.';
            } else {
                $tid = (int) ($_POST['ticket_id'] ?? 0);
                $action = trim($_POST['action'] ?? '');
                if ($tid > 0 && $action === 'reply') {
                    $reply = trim($_POST['message'] ?? '');
                    if ($reply !== '' && strlen($reply) <= 2000) {
                        $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, author_type, message) VALUES (?, 'admin', ?)");
                        $stmt->bind_param('is', $tid, $reply);
                        $stmt->execute();
                        $stmt->close();
                        $upd = $conn->prepare('UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                        $upd->bind_param('i', $tid);
                        $upd->execute();
                        $upd->close();
                        $message = 'Reply sent.';
                        $view_id = $tid;
                    } else {
                        $message = 'Reply (up to 2000 characters) is required.';
                    }
                } elseif ($tid > 0 && ($action === 'close' || $action === 'reopen')) {
                    $new_status = $action === 'close' ? 'closed' : 'open';
                    $stmt = $conn->prepare('UPDATE tickets SET status = ? WHERE id = ?');
                    $stmt->bind_param('si', $new_status, $tid);
                    $stmt->execute();
                    $stmt->close();
                    $message = 'Ticket #' . $tid . ' is now ' . $new_status . '.';
                    $view_id = $tid;
                }
            }
        }
        if ($search_q !== '') {
            $like = '%' . $search_q . '%';
            $stmt = $conn->prepare('SELECT t.id, t.user_id, u.username, t.subject, t.status, t.created_at, t.updated_at FROM tickets t LEFT JOIN users u ON u.id = t.user_id WHERE CAST(t.id AS CHAR) LIKE ? OR u.username LIKE ? OR t.subject LIKE ? ORDER BY t.updated_at DESC, t.id DESC LIMIT 200');
            if ($stmt !== false) {
                $stmt->bind_param('sss', $like, $like, $like);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $tickets[] = $row;
                    }
                }
                $stmt->close();
            }
        } else {
            $result = $conn->query('SELECT t.id, t.user_id, u.username, t.subject, t.status, t.created_at, t.updated_at FROM tickets t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.updated_at DESC, t.id DESC LIMIT 200');
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $tickets[] = $row;
                }
            }
        }
        if ($view_id > 0) {
            $stmt = $conn->prepare('SELECT id, user_id, subject, status FROM tickets WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $view_id);
            $stmt->execute();
            $view = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            if ($view !== null) {
                $stmt = $conn->prepare('SELECT author_type, message, created_at FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC, id ASC');
                $stmt->bind_param('i', $view_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $view_messages[] = $row;
                }
                $stmt->close();
            }
        }
        $conn->close();
    } else {
        $db_ready = false;
    }
}
require __DIR__ . '/includes/header.php';
?>
<h1>Admin Tickets</h1>
<p class="notice">Signed in as admin. <a href="admin-overview.php">Admin Overview</a> | <a href="admin.php">Back to Admin Settings</a></p>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if ($view === null): ?>
<form method="get" action="admin-tickets.php" class="filter-form">
  <label>Search <input type="search" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Ticket ID, username, subject"></label>
  <button class="btn btn-blue" type="submit">Filter</button>
</form>
<?php endif; ?>
<?php if (!$db_ready): ?>
<p class="notice">Database not configured yet.</p>
<?php elseif ($view !== null): ?>
<h2>#<?php echo (int) $view['id']; ?> — <?php echo htmlspecialchars($view['subject']); ?> <?php echo status_pill($view['status']); ?></h2>
<?php foreach ($view_messages as $m): ?>
<div class="card">
  <p class="small"><?php echo $m['author_type'] === 'admin' ? 'Admin' : 'Customer'; ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($m['created_at']); ?></p>
  <p><?php echo nl2br(htmlspecialchars($m['message'])); ?></p>
</div>
<?php endforeach; ?>
<form method="post" action="admin-tickets.php?id=<?php echo (int) $view['id']; ?>">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
  <input type="hidden" name="ticket_id" value="<?php echo (int) $view['id']; ?>">
  <label for="message">Reply as admin</label>
  <textarea id="message" name="message" maxlength="2000" rows="4"></textarea>
  <?php if (!empty($config['canned_replies'])): ?>
  <p class="small">Canned replies (tap to insert — you still send the reply yourself):</p>
  <p><?php foreach ($config['canned_replies'] as $cr): ?><button type="button" class="btn btn-outline" data-canned-reply="<?php echo htmlspecialchars($cr); ?>"><?php echo htmlspecialchars(strlen($cr) > 40 ? substr($cr, 0, 37) . '…' : $cr); ?></button> <?php endforeach; ?></p>
  <?php endif; ?>
  <p><button class="btn btn-blue" type="submit" name="action" value="reply">Send Reply</button>
  <button class="btn btn-outline" type="submit" name="action" value="<?php echo $view['status'] === 'open' ? 'close' : 'reopen'; ?>"><?php echo $view['status'] === 'open' ? 'Close Ticket' : 'Reopen Ticket'; ?></button></p>
</form>
<p><a href="admin-tickets.php">Back to all tickets</a></p>
<?php elseif (empty($tickets)): ?>
<p>No tickets match this filter.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>User</th><th>Subject</th><th>Status</th><th>Updated</th></tr></thead>
  <tbody>
    <?php foreach ($tickets as $row): ?>
    <tr>
      <td><a href="admin-tickets.php?id=<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></a></td>
      <td><?php echo htmlspecialchars((string) ($row['username'] ?? ('user ' . (int) $row['user_id']))); ?></td>
      <td><?php echo htmlspecialchars($row['subject']); ?></td>
      <td><?php echo status_pill($row['status']); ?></td>
      <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * SMM APEX PLUS — Full Panel: single ticket (customer side).
 * Ownership is re-checked on every load and every reply: a customer
 * can only open tickets that belong to their own user_id.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_login();
$user = current_user();
$config = get_effective_config();
$page_title = 'Ticket';
$active = 'tickets';
$message = '';
$db_ready = db_available();
$ticket_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$ticket = null;
$messages = [];
if ($db_ready && $ticket_id > 0) {
    $conn = db_connect();
    if ($conn !== null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_check($_POST['csrf_token'] ?? '')) {
                $message = 'That didn\'t go through — please reload the page and try again.';
            } else {
                $reply = trim($_POST['message'] ?? '');
                if ($reply === '' || strlen($reply) > 2000) {
                    $message = 'Reply (up to 2000 characters) is required.';
                } else {
                    $stmt = $conn->prepare('SELECT id FROM tickets WHERE id = ? AND user_id = ? LIMIT 1');
                    $stmt->bind_param('ii', $ticket_id, $user['id']);
                    $stmt->execute();
                    $owns = $stmt->get_result()->fetch_assoc() !== null;
                    $stmt->close();
                    if (!$owns) {
                        $message = 'Ticket not found, or it does not belong to your account.';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, author_type, message) VALUES (?, 'user', ?)");
                        $stmt->bind_param('is', $ticket_id, $reply);
                        if ($stmt->execute()) {
                            $message = 'Reply sent.';
                        } else {
                            $message = 'Reply could not be saved. Please try again later.';
                        }
                        $stmt->close();
                        $upd = $conn->prepare('UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                        $upd->bind_param('i', $ticket_id);
                        $upd->execute();
                        $upd->close();
                    }
                }
            }
        }
        $stmt = $conn->prepare('SELECT id, subject, status, created_at, updated_at FROM tickets WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $ticket_id, $user['id']);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if ($ticket !== null) {
            $stmt = $conn->prepare('SELECT author_type, message, created_at FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC, id ASC');
            $stmt->bind_param('i', $ticket_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
            $stmt->close();
        }
        $conn->close();
    } else {
        $db_ready = false;
    }
}
require __DIR__ . '/includes/header.php';
?>
<h1>Ticket</h1>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (!$db_ready): ?>
<p class="notice">Tickets are not available yet — the admin has not finished the database setup.</p>
<?php elseif ($ticket === null): ?>
<p class="notice">Ticket not found, or it does not belong to your account.</p>
<?php else: ?>
<h2>#<?php echo (int) $ticket['id']; ?> — <?php echo htmlspecialchars($ticket['subject']); ?> <?php echo status_pill($ticket['status']); ?></h2>
<?php foreach ($messages as $m): ?>
<div class="card">
  <p class="small"><?php echo $m['author_type'] === 'admin' ? 'Admin' : 'You'; ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($m['created_at']); ?></p>
  <p><?php echo nl2br(htmlspecialchars($m['message'])); ?></p>
</div>
<?php endforeach; ?>
<?php if ($ticket['status'] === 'open'): ?>
<form method="post" action="ticket-view.php?id=<?php echo (int) $ticket['id']; ?>">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
  <label for="message">Reply</label>
  <textarea id="message" name="message" required maxlength="2000" rows="4"></textarea>
  <p><button class="btn btn-blue" type="submit">Send Reply</button></p>
</form>
<?php else: ?>
<p class="notice">This ticket is closed. Open a new ticket if you still need help.</p>
<?php endif; ?>
<?php endif; ?>
<p><a href="tickets.php">Back to tickets</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * SMM APEX PLUS — Full Panel: support tickets (customer side).
 * Logged-in customers only, and each customer sees ONLY their own
 * tickets (every query filters by the session user_id).
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_login();
$user = current_user();
$config = get_effective_config();
$page_title = 'Support Tickets';
$meta_description = 'Open and follow your SMM APEX PLUS support tickets for orders, payments, and account questions.';
$active = 'tickets';
$message = '';
$db_ready = db_available();
$tickets = [];
// Pre-filled ticket (e.g. the "Request a Refund" button on an order).
// Values are capped and escaped on output, like every other input.
$prefill_subject = substr(trim($_GET['subject'] ?? ''), 0, 200);
$prefill_message = substr(trim($_GET['message'] ?? ''), 0, 2000);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_ready) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'That didn\'t go through — please reload the page and try again.';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['message'] ?? '');
        if ($subject === '' || strlen($subject) > 200 || $body === '' || strlen($body) > 2000) {
            $message = 'Subject (up to 200 characters) and message (up to 2000 characters) are both required.';
        } else {
            $conn = db_connect();
            if ($conn === null) {
                $message = 'Database could not be reached. Please try again later.';
            } else {
                $conn->begin_transaction();
                $stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, status) VALUES (?, ?, 'open')");
                $stmt->bind_param('is', $user['id'], $subject);
                $ok = $stmt->execute();
                $ticket_id = $ok ? $stmt->insert_id : 0;
                $stmt->close();
                if ($ok) {
                    $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, author_type, message) VALUES (?, 'user', ?)");
                    $stmt->bind_param('is', $ticket_id, $body);
                    $ok = $stmt->execute();
                    $stmt->close();
                }
                if ($ok) {
                    $conn->commit();
                    $message = 'Ticket #' . (int) $ticket_id . ' opened. The admin will reply here.';
                } else {
                    $conn->rollback();
                    $message = 'Ticket could not be saved. Please try again later.';
                }
                $conn->close();
            }
        }
    }
}
if ($db_ready) {
    $conn = db_connect();
    if ($conn !== null) {
        $stmt = $conn->prepare('SELECT id, subject, status, created_at, updated_at FROM tickets WHERE user_id = ? ORDER BY updated_at DESC, id DESC');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }
        $stmt->close();
        $conn->close();
    } else {
        $db_ready = false;
    }
}
require __DIR__ . '/includes/header.php';
?>
<h1>Support — We're Here To Help</h1>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (!$db_ready): ?>
<p class="notice">Support tickets are opening soon — please check back shortly. You can still reach us on WhatsApp or email below.</p>
<?php else: ?>
<h2>Ask Us Anything</h2>
<form method="post" action="tickets.php">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
  <label for="subject">What's it about?</label>
  <p class="small">For example: "My order #123"</p>
  <input type="text" id="subject" name="subject" required maxlength="200" value="<?php echo htmlspecialchars($prefill_subject); ?>">
  <label for="message">Tell us more</label>
  <textarea id="message" name="message" required maxlength="2000" rows="5"><?php echo htmlspecialchars($prefill_message); ?></textarea>
  <p><button class="btn btn-blue" type="submit">Send My Message</button></p>
</form>
<h2>Your Tickets</h2>
<?php if (empty($tickets)): ?>
<p>No tickets yet — if anything ever needs a hand, this is the place.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Subject</th><th>Status</th><th>Updated</th></tr></thead>
  <tbody>
    <?php foreach ($tickets as $row): ?>
    <tr>
      <td><a href="ticket-view.php?id=<?php echo (int) $row['id']; ?>">#<?php echo (int) $row['id']; ?></a></td>
      <td><?php echo htmlspecialchars($row['subject']); ?></td>
      <td><?php echo status_pill($row['status']); ?></td>
      <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
<p>Need a faster answer? Message us on WhatsApp: <?php echo htmlspecialchars($config['support_whatsapp']); ?> or email: <?php echo htmlspecialchars($config['support_email']); ?></p>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * SMM APEX PLUS — Add Funds (v2).
 *
 * Shows the admin-set payment numbers, and — for signed-in customers
 * with a configured database — accepts a fund request (method,
 * amount, transaction ID, sender number). A request is stored as
 * 'Pending' only; no balance changes until an admin approves it in
 * admin-funds.php, where approval credits the amount exactly once.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
$config = get_effective_config();
$page_title = 'Add Funds';
$meta_description = 'Add funds to your SMM APEX PLUS balance in PKR with EasyPaisa, JazzCash or UPaisa by submitting your transaction ID.';
$active = 'funds';
$user = current_user();
$message = '';
$db_ready = db_available();
$maintenance = maintenance_active($config);
$my_requests = [];

function payment_number($config, $method) {
    $n = trim($config['payments'][$method] ?? '');
    return $n === '' ? 'Coming soon — please check back shortly.' : $n;
}

function payment_number_is_set($config, $method) {
    return trim($config['payments'][$method] ?? '') !== '';
}

function payment_description($config, $method) {
    return trim($config['payment_descriptions'][$method] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user !== null) {
    if (!$db_ready) {
        $message = 'Fund requests are opening soon — please check back shortly.';
    } elseif ($maintenance) {
        $message = trim((string) ($config['maintenance_message'] ?? '')) !== '' ? trim((string) $config['maintenance_message']) : 'Fund requests are paused for maintenance right now — please check back shortly.';
    } elseif (!csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'That didn\'t go through — please reload the page and try again.';
    } else {
        $method = trim($_POST['method'] ?? '');
        $amount_raw = trim($_POST['amount'] ?? '');
        $trx_id = trim($_POST['trx_id'] ?? '');
        $sender_number = trim($_POST['sender_number'] ?? '');
        if (!in_array($method, ['easypaisa', 'jazzcash', 'upaisa'], true)) {
            $message = 'Please choose a payment method.';
        } elseif (!is_numeric($amount_raw) || (float) $amount_raw <= 0 || (float) $amount_raw > 1000000) {
            $message = 'Please enter an amount greater than zero.';
        } elseif ($trx_id === '' || strlen($trx_id) > 100) {
            $message = 'Please paste your transaction ID — you can find it in your payment app.';
        } elseif (strlen($sender_number) > 50) {
            $message = 'Please shorten the sender number to 50 characters or less.';
        } else {
            $conn = db_connect();
            if ($conn === null) {
                $message = 'We couldn\'t reach our system just now — please try again in a moment. Nothing was charged.';
            } else {
                $amount = number_format((float) $amount_raw, 2, '.', '');
                $status = 'Pending';
                $stmt = $conn->prepare('INSERT INTO fund_requests (user_id, method, amount, trx_id, sender_number, status) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('isdsss', $user['id'], $method, $amount, $trx_id, $sender_number, $status);
                if ($stmt->execute()) {
                    $message = 'Payment submitted! It is now Pending — your balance will update as soon as support approves it.';
                } else {
                    $message = 'Fund request could not be saved. Please try again later.';
                }
                $stmt->close();
                $conn->close();
            }
        }
    }
}

if ($user !== null && $db_ready) {
    $conn = db_connect();
    if ($conn !== null) {
        $stmt = $conn->prepare('SELECT id, method, amount, trx_id, status, created_at FROM fund_requests WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 20');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $my_requests[] = $row;
        }
        $stmt->close();
        $conn->close();
    }
}

$any_number_blank = false;
foreach (['easypaisa', 'jazzcash', 'upaisa'] as $m) {
    if (!payment_number_is_set($config, $m)) { $any_number_blank = true; break; }
}
$form_disabled = !$db_ready || $maintenance;

require __DIR__ . '/includes/header.php';
?>
<h1>Add Funds — Top Up in PKR</h1>
<?php if ($any_number_blank): ?>
<p>Send your payment to one of the numbers below, then submit your transaction ID here. Numbers marked 'Coming soon' are not ready yet — please do not send money to them.</p>
<?php else: ?>
<p>Send your payment to any number below, then submit your transaction ID — it's that easy. Your balance is updated as soon as support approves your payment.</p>
<?php endif; ?>

<div class="grid pay-grid">
  <div class="card pay-card pay-card-easypaisa">
    <h3>EasyPaisa</h3>
    <p class="small">Pakistan's most-loved wallet</p>
    <p>Send payment to: <span class="pay-number"><?php echo htmlspecialchars(payment_number($config, 'easypaisa')); ?></span></p>
    <?php if (payment_number_is_set($config, 'easypaisa')): ?><p><button type="button" class="btn btn-outline" data-copy-number="<?php echo htmlspecialchars(trim($config['payments']['easypaisa'])); ?>">Copy Number</button></p><?php endif; ?>
    <p class="pay-desc"><?php $pd = payment_description($config, 'easypaisa'); echo htmlspecialchars($pd !== '' ? $pd : 'Account holder details will appear here soon.'); ?></p>
  </div>
  <div class="card pay-card pay-card-jazzcash">
    <h3>JazzCash</h3>
    <p class="small">Fast, easy mobile payments</p>
    <p>Send payment to: <span class="pay-number"><?php echo htmlspecialchars(payment_number($config, 'jazzcash')); ?></span></p>
    <?php if (payment_number_is_set($config, 'jazzcash')): ?><p><button type="button" class="btn btn-outline" data-copy-number="<?php echo htmlspecialchars(trim($config['payments']['jazzcash'])); ?>">Copy Number</button></p><?php endif; ?>
    <p class="pay-desc"><?php $pd = payment_description($config, 'jazzcash'); echo htmlspecialchars($pd !== '' ? $pd : 'Account holder details will appear here soon.'); ?></p>
  </div>
  <div class="card pay-card pay-card-upaisa">
    <h3>UPaisa</h3>
    <p class="small">Simple payments, made easy</p>
    <p>Send payment to: <span class="pay-number"><?php echo htmlspecialchars(payment_number($config, 'upaisa')); ?></span></p>
    <?php if (payment_number_is_set($config, 'upaisa')): ?><p><button type="button" class="btn btn-outline" data-copy-number="<?php echo htmlspecialchars(trim($config['payments']['upaisa'])); ?>">Copy Number</button></p><?php endif; ?>
    <p class="pay-desc"><?php $pd = payment_description($config, 'upaisa'); echo htmlspecialchars($pd !== '' ? $pd : 'Account holder details will appear here soon.'); ?></p>
  </div>
</div>

<h2>Submit Your Payment — You're Almost Done</h2>
<p>Send the payment first, then paste the transaction ID exactly as shown by your payment app. Your balance changes only after approval.</p>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if ($user === null): ?>
<p class="notice">Please <a href="login.php">log in</a> to submit your payment.</p>
<?php elseif (!$db_ready): ?>
<p class="notice">Fund requests are opening soon — please check back shortly.</p>
<?php elseif ($maintenance): ?>
<p class="notice notice-warning"><?php echo htmlspecialchars(trim((string) ($config['maintenance_message'] ?? '')) !== '' ? trim((string) $config['maintenance_message']) : 'Fund requests are paused for maintenance right now — please check back shortly.'); ?></p>
<?php else: ?>
<form method="post" action="add-funds.php" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
  <label for="method">How did you pay?</label>
  <select id="method" name="method" required <?php echo $form_disabled ? 'disabled' : ''; ?>>
    <option value="easypaisa">EasyPaisa</option>
    <option value="jazzcash">JazzCash</option>
    <option value="upaisa">UPaisa</option>
  </select>
  <label for="amount">Amount you sent (<?php echo htmlspecialchars($config['currency']); ?>)</label>
  <input type="number" id="amount" name="amount" required min="1" step="0.01" <?php echo $form_disabled ? 'disabled' : ''; ?>>
  <label for="trx_id">Transaction ID (TRX ID)</label>
  <p class="small">Copy it exactly from your payment app — this is how we find your payment.</p>
  <input type="text" id="trx_id" name="trx_id" required maxlength="100" <?php echo $form_disabled ? 'disabled' : ''; ?>>
  <label for="sender_number">Your payment number (optional)</label>
  <p class="small">The number you paid from — it helps us match your payment faster.</p>
  <input type="text" id="sender_number" name="sender_number" maxlength="50" <?php echo $form_disabled ? 'disabled' : ''; ?>>
  <p><button class="btn btn-blue" type="submit" <?php echo $form_disabled ? 'disabled' : ''; ?>>Submit My Payment</button></p>
</form>
<?php endif; ?>

<?php if (!empty($my_requests)): ?>
<h2>Your Payment Requests</h2>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Method</th><th>Amount</th><th>Transaction ID</th><th>Status</th><th>Submitted</th></tr></thead>
  <tbody>
    <?php foreach ($my_requests as $row): ?>
    <tr>
      <td>#<?php echo (int) $row['id']; ?></td>
      <td><?php echo htmlspecialchars($row['method']); ?></td>
      <td><?php echo htmlspecialchars($row['amount']); ?></td>
      <td><?php echo htmlspecialchars($row['trx_id']); ?></td>
      <td><?php echo status_pill($row['status']); ?></td>
      <td><?php echo htmlspecialchars($row['created_at']); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<h2>Support</h2>
<p>WhatsApp: <?php echo htmlspecialchars($config['support_whatsapp']); ?><br>
Email: <?php echo htmlspecialchars($config['support_email']); ?></p>
<p><a class="btn btn-blue" href="<?php echo htmlspecialchars($config['other_services_url'] ?? $config['main_website_url']); ?>"><?php echo htmlspecialchars($config['other_services_label'] ?? 'Go to Other Services'); ?></a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>

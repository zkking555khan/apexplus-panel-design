<?php
/**
 * SMM APEX PLUS — real signup (phase 2).
 * Creates a user row in the database with password_hash() only.
 * If the database is not configured yet, the form is shown disabled
 * with a clear setup message — it never pretends to create an account.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
ensure_session();
if (is_logged_in()) {
    header('Location: orders.php');
    exit;
}
$config = get_effective_config();
$page_title = 'Sign Up';
$meta_description = 'Create your free SMM APEX PLUS account in under a minute and start ordering social media services in PKR.';
$active = 'signup';
$message = '';
$db_ready = db_available();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$db_ready) {
        $message = 'Signups are opening soon — please check back shortly. No account is created from this form until then.';
    } elseif (!csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'That didn\'t go through — please reload the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $message = 'Username must be 3–30 characters: letters, digits or underscore only.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters.';
        } else {
            $conn = db_connect();
            if ($conn === null) {
                $message = 'Database could not be reached. Please try again later.';
            } else {
                $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
                $stmt->bind_param('ss', $username, $email);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($exists) {
                    $message = 'That username or email is already registered. Try logging in instead.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
                    $stmt->bind_param('sss', $username, $email, $hash);
                    if ($stmt->execute()) {
                        $user_id = $stmt->insert_id;
                        $stmt->close();
                        $conn->close();
                        unset($password, $hash);
                        login_user($user_id, $username);
                        header('Location: orders.php?welcome=1');
                        exit;
                    }
                    $stmt->close();
                    $message = 'Account could not be created. Please try again later.';
                }
                $conn->close();
            }
            unset($password);
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<h1>Create Your Free Account</h1>
<p>Join SMM APEX PLUS — premium services, upfront prices and live order tracking.</p>
<?php if (!$db_ready): ?><p class="notice">Signups are opening soon — please check back shortly. No account is created from this form until then.</p><?php endif; ?>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="post" action="signup.php" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
  <label for="username">Username</label>
  <input type="text" id="username" name="username" required <?php echo $db_ready ? '' : 'disabled'; ?>>
  <label for="email">Email</label>
  <input type="email" id="email" name="email" required <?php echo $db_ready ? '' : 'disabled'; ?>>
  <label for="password">Choose a password (at least 8 characters)</label>
  <div class="qty-row">
    <input type="password" id="password" name="password" required minlength="8" <?php echo $db_ready ? '' : 'disabled'; ?>>
    <button type="button" class="btn btn-outline" data-toggle-password="password">Show</button>
  </div>
  <p><button class="btn btn-blue" type="submit" <?php echo $db_ready ? '' : 'disabled'; ?>>Create My Free Account</button></p>
</form>
<p>Already with us? <a href="login.php">Log in</a>.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>

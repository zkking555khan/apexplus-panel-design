<?php
/**
 * SMM APEX PLUS — real login (phase 2).
 * Checks username + password_verify() against the database row only.
 * The error message is deliberately generic so it does not reveal
 * whether a username exists.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
ensure_session();
if (is_logged_in()) {
    // Login ke baad direct order page (2026-10-29, his order):
    // dashboard beech me nahi aayega.
    header('Location: new-order.php');
    exit;
}
$config = get_effective_config();
$page_title = 'Login';
$meta_description = 'Log in to your SMM APEX PLUS account to order services, check your balance, and track your orders.';
$active = 'login';
$message = '';
$db_ready = db_available();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$db_ready) {
        $message = 'Login is opening soon — please check back shortly.';
    } elseif (!csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'That didn\'t go through — please reload the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $message = 'Please enter your username and password.';
        } else {
            $conn = db_connect();
            if ($conn === null) {
                $message = 'Database could not be reached. Please try again later.';
            } else {
                $user = find_user_by_username($conn, $username);
                $conn->close();
                if ($user !== null && password_verify($password, (string) $user['password_hash'])) {
                    if (($user['status'] ?? 'active') === 'suspended') {
                        $message = 'This account is suspended. Contact support.';
                    } else {
                        unset($password);
                        login_user((int) $user['id'], (string) $user['username']);
                        // Direct new-order page, dashboard beech me nahi.
                        header('Location: new-order.php');
                        exit;
                    }
                }
                unset($password);
                $message = 'Invalid username or password.';
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<h1>Welcome Back</h1>
<p>Log in to order premium services, track every order and manage your balance — all in one place.</p>
<?php if (!$db_ready): ?><p class="notice">Login is opening soon — please check back shortly.</p><?php endif; ?>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="post" action="login.php" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
  <label for="username">Username</label>
  <input type="text" id="username" name="username" required <?php echo $db_ready ? '' : 'disabled'; ?>>
  <label for="password">Password</label>
  <div class="qty-row">
    <input type="password" id="password" name="password" required <?php echo $db_ready ? '' : 'disabled'; ?>>
    <button type="button" class="btn btn-outline" data-toggle-password="password">Show</button>
  </div>
  <p><button class="btn btn-blue btn-login" type="submit" <?php echo $db_ready ? '' : 'disabled'; ?>>Log In</button></p>
</form>
<p>New here? <a href="signup.php">Create your free account — it takes less than a minute</a>.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>

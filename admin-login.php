<?php
/**
 * SMM APEX PLUS — admin login (phase 2).
 * Checks the password against the hash in data/admin.json only.
 * No default password exists; if no admin account exists yet, this
 * page sends the visitor to the one-time setup page instead.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
ensure_session();
if (is_admin()) {
    header('Location: admin.php');
    exit;
}
if (!admin_exists()) {
    header('Location: admin-setup.php');
    exit;
}
$config = get_effective_config();
$page_title = 'Admin Login';
$active = 'admin';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'Security check failed. Please reload the page and try again.';
    } else {
        $hash = load_admin_hash();
        $password = (string) ($_POST['password'] ?? '');
        if ($hash !== null && password_verify($password, $hash)) {
            unset($password);
            admin_login();
            header('Location: admin.php');
            exit;
        }
        unset($password);
        $message = 'Invalid admin password.';
    }
}

require __DIR__ . '/includes/header.php';
?>
<h1>Admin Login</h1>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="post" action="admin-login.php" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
  <label for="password">Admin password</label>
  <div class="qty-row">
    <input type="password" id="password" name="password" required autocomplete="current-password">
    <button type="button" class="btn btn-outline" data-toggle-password="password">Show</button>
  </div>
  <p><button class="btn btn-blue" type="submit">Log In as Admin</button></p>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>

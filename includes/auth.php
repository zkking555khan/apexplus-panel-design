<?php
/**
 * SMM APEX PLUS — session, CSRF, user auth and admin auth (phase 2).
 *
 * User accounts live in the MySQL database (schema.sql) and passwords
 * are checked with password_verify() against password_hash() values
 * only — never plain text, never a reversible encoding.
 *
 * The admin account is SEPARATE from user accounts. Its password hash
 * lives only in data/admin.json (blocked from web access by
 * data/.htaccess, chmod 0600). There is no default password and no
 * password in code: the admin password is created once through
 * admin-setup.php, which refuses to run again once admin.json exists
 * and tries to delete itself after a successful setup.
 */
require_once __DIR__ . '/db.php';

function ensure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $secure,
        ]);
        session_start();
    }
}

function csrf_token() {
    ensure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check($token) {
    ensure_session();
    return isset($_SESSION['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], (string) $token);
}

/* ---------- customer users ---------- */

function current_user() {
    ensure_session();
    if (empty($_SESSION['user_id']) || empty($_SESSION['username'])) {
        return null;
    }
    return ['id' => (int) $_SESSION['user_id'], 'username' => (string) $_SESSION['username']];
}

function is_logged_in() {
    return current_user() !== null;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function login_user($user_id, $username) {
    ensure_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user_id;
    $_SESSION['username'] = (string) $username;
}

function logout_user() {
    ensure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function find_user_by_username($conn, $username) {
    $stmt = $conn->prepare('SELECT id, username, email, password_hash, status FROM users WHERE username = ? LIMIT 1');
    if ($stmt === false) {
        return null;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/* ---------- admin ---------- */

function admin_file_path() {
    return __DIR__ . '/../data/admin.json';
}

function admin_exists() {
    return is_file(admin_file_path());
}

function load_admin_hash() {
    if (!admin_exists()) {
        return null;
    }
    $raw = file_get_contents(admin_file_path());
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) && isset($data['password_hash']) ? (string) $data['password_hash'] : null;
}

function save_admin_hash($hash) {
    $file = admin_file_path();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ok = file_put_contents($file, json_encode(['password_hash' => $hash, 'created_at' => date('c')], JSON_PRETTY_PRINT), LOCK_EX) !== false;
    if ($ok) {
        @chmod($file, 0600);
    }
    return $ok;
}

function is_admin() {
    ensure_session();
    return !empty($_SESSION['admin_logged_in']);
}

function admin_login() {
    ensure_session();
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
}

function admin_logout() {
    ensure_session();
    unset($_SESSION['admin_logged_in']);
}

/**
 * If no admin account exists yet, send the admin to the one-time
 * setup page. If an account exists but the visitor is not signed
 * in as admin, send them to the admin login page.
 */
function require_admin() {
    if (!admin_exists()) {
        header('Location: admin-setup.php');
        exit;
    }
    if (!is_admin()) {
        header('Location: admin-login.php');
        exit;
    }
}

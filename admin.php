<?php
/**
 * SMM APEX PLUS — Admin Settings (phase 2).
 *
 * PROTECTED: require_admin() runs before any output. Visitors who
 * are not signed in as admin are sent to admin-login.php (or to
 * admin-setup.php when no admin account exists yet). Without that
 * login, nobody can change the API key or the payment numbers.
 *
 * What this page does:
 *  - Lets the customer paste his OWN ZeeXFame API key (from his own
 *    ZeeXFame account). The key is empty by default and is never
 *    hardcoded anywhere in the site.
 *  - Lets the admin set and change the EasyPaisa / JazzCash / UPaisa
 *    payment numbers shown on the Add Funds page. Numbers are empty
 *    by default and are never hardcoded.
 *
 * What it does NOT do:
 *  - It never displays the full API key again after saving (only the
 *    last 4 characters). Leaving the key field blank keeps the saved key.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_admin();
ensure_session();
$config = get_effective_config();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'Security check failed. Please reload the page and try again.';
    } else {
        $saved = load_saved_settings();
        $payments = $saved['payments'] ?? [];
        $descriptions = $saved['payment_descriptions'] ?? [];
        $valid = true;
        // API URL is locked in config.php and is never taken from POST, so the provider cannot be switched from here.
        foreach (['easypaisa', 'jazzcash', 'upaisa'] as $method) {
            $value = trim($_POST[$method] ?? '');
            if (!valid_payment_number($value)) {
                $message = 'Payment numbers may only contain digits, spaces, + or -, and must be 7–20 characters. Nothing was saved.';
                $valid = false;
                break;
            }
            $payments[$method] = $value;
        }
        if ($valid) {
            // Locked: api_url is not saved from POST.
            $theme_post = trim($_POST['theme'] ?? '');
            if (isset(site_themes()[$theme_post])) { $saved['theme'] = $theme_post; }
            $markup_post = trim($_POST['markup_percent'] ?? '');
            if ($markup_post !== '' && is_numeric($markup_post) && (float) $markup_post >= 0 && (float) $markup_post <= 1000) { $saved['markup_percent'] = (float) $markup_post; }
            $os_url = trim($_POST['other_services_url'] ?? '');
            if ($os_url === '' || (filter_var($os_url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $os_url))) { $saved['other_services_url'] = $os_url; }
            $saved['other_services_label'] = substr(trim($_POST['other_services_label'] ?? ''), 0, 60);
            $saved['other_services_subtitle'] = substr(trim($_POST['other_services_subtitle'] ?? ''), 0, 120);
            $saved['announcement'] = substr(trim($_POST['announcement'] ?? ''), 0, 500);
            // Scheduled announcement window: stored only when parseable, else blank (= always shown).
            foreach (['announcement_start', 'announcement_end'] as $win_key) {
                $win_val = trim($_POST[$win_key] ?? '');
                $saved[$win_key] = ($win_val !== '' && strtotime($win_val) !== false) ? substr($win_val, 0, 32) : '';
            }
            // Category markup: one "Category | percent" per line, percent 0–1000.
            $cat_markup = [];
            foreach (preg_split('/\r\n|\r|\n/', (string) ($_POST['category_markup'] ?? '')) as $line) {
                $parts = explode('|', $line, 2);
                if (count($parts) !== 2) continue;
                $cat = trim($parts[0]);
                $pct = trim($parts[1]);
                if ($cat !== '' && is_numeric($pct) && (float) $pct >= 0 && (float) $pct <= 1000) {
                    $cat_markup[$cat] = (float) $pct;
                }
            }
            $saved['category_markup'] = $cat_markup;
            $saved['maintenance_mode'] = !empty($_POST['maintenance_mode']);
            $saved['maintenance_message'] = substr(trim($_POST['maintenance_message'] ?? ''), 0, 500);
            // Canned ticket replies: up to 5, insert-only templates.
            $canned = [];
            for ($i = 0; $i < 5; $i++) {
                $r = trim($_POST['canned_' . $i] ?? '');
                if ($r !== '') {
                    $canned[] = substr($r, 0, 500);
                }
            }
            $saved['canned_replies'] = $canned;
            if (!empty($_POST['clear_api_key'])) {
                $saved['api_key'] = '';
            } elseif (trim($_POST['api_key'] ?? '') !== '') {
                $saved['api_key'] = trim($_POST['api_key']);
            }
            $saved['payments'] = $payments;
            foreach (['easypaisa', 'jazzcash', 'upaisa'] as $method) {
                $descriptions[$method] = substr(trim($_POST[$method . '_desc'] ?? ''), 0, 300);
            }
            $saved['payment_descriptions'] = $descriptions;
            if (save_saved_settings($saved)) {
                $config = get_effective_config();
                $message = 'Settings saved. The API key (if entered) is stored on the server only and is not shown in full anywhere.';
            } else {
                $message = 'Settings could not be saved. Check that the data/ folder is writable on the server.';
            }
        }
    }
}

$page_title = 'Admin Settings';
$active = 'admin';
require __DIR__ . '/includes/header.php';
?>
<h1>Admin Settings</h1>
<p class="notice">You are signed in as admin. <a href="admin-logout.php">Log out</a> when you are done.</p>
<p><a class="btn btn-blue" href="admin-overview.php">Admin Overview</a> <a class="btn btn-blue" href="admin-orders.php">Manage Orders</a> <a class="btn btn-blue" href="admin-funds.php">Manage Fund Requests</a> <a class="btn btn-blue" href="admin-users.php">Manage Users</a> <a class="btn btn-blue" href="admin-tickets.php">Manage Tickets</a> <a class="btn btn-blue" href="admin-services.php">Manage Services</a> <a class="btn btn-blue" href="admin-audit.php">Balance Audit Log</a></p>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>

<form method="post" action="admin.php" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

  <h2>ZeeXFame API connection</h2>
  <p>Enter the API key from <strong>your own ZeeXFame account</strong>. The API URL is already locked to the provider (the client uses the standard key + action convention). The provider stays ZeeXFame only — no other provider can be added. Current key: <?php echo htmlspecialchars(mask_api_key($config['provider']['api_key'])); ?></p>
  <p>API URL (locked): <strong>https://zeexfame.store/api/v2</strong> — this link is pre-locked and cannot be changed from here. Only the API key field below is left empty for you to fill.</p>
  <label for="api_key">API key (leave blank to keep the current key)</label>
  <input type="password" id="api_key" name="api_key" placeholder="Paste your ZeeXFame API key here" autocomplete="off">
  <p><label><input type="checkbox" name="clear_api_key" value="1"> Clear the saved API key</label></p>

  <h2>Theme, pricing &amp; Other Services button</h2>
  <p>Theme changes the whole site at once for every visitor. Markup % is added to every service rate to make the retail price. The Other Services button replaces the old Premium APK button.</p>
  <label for="theme">Site theme (admin can change this any time)</label>
  <select id="theme" name="theme"><?php foreach (site_themes() as $key => $label): ?><option value="<?php echo htmlspecialchars($key); ?>"<?php echo current_theme($config) === $key ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select>
  <label for="markup_percent">Markup % on service rates (e.g. 20 = provider rate + 20%)</label>
  <input type="number" id="markup_percent" name="markup_percent" min="0" max="1000" step="0.01" value="<?php echo htmlspecialchars((string) ($config['markup_percent'] ?? 0)); ?>">
  <label for="other_services_label">Other Services button text</label>
  <input type="text" id="other_services_label" name="other_services_label" maxlength="60" value="<?php echo htmlspecialchars($config['other_services_label'] ?? 'Go to Other Services'); ?>">
  <label for="other_services_url">Other Services button link (set by admin)</label>
  <input type="url" id="other_services_url" name="other_services_url" value="<?php echo htmlspecialchars($config['other_services_url'] ?? ''); ?>" placeholder="https://…">
  <label for="other_services_subtitle">Other Services card subtitle</label>
  <p class="small">Shown under the button so visitors know what that website offers. Keep it short — for example, the other website's own tagline. Blank = the subtitle line is omitted.</p>
  <input type="text" id="other_services_subtitle" name="other_services_subtitle" maxlength="120" value="<?php echo htmlspecialchars($config['other_services_subtitle'] ?? 'All in One Digital Services'); ?>">
  <label for="announcement">Announcement (shown on every page, blank = none)</label>
  <input type="text" id="announcement" name="announcement" maxlength="500" value="<?php echo htmlspecialchars($config['announcement'] ?? ''); ?>">
  <label for="announcement_start">Announcement shown from (optional)</label>
  <input type="datetime-local" id="announcement_start" name="announcement_start" value="<?php echo htmlspecialchars($config['announcement_start'] ?? ''); ?>">
  <label for="announcement_end">Announcement shown until (optional)</label>
  <input type="datetime-local" id="announcement_end" name="announcement_end" value="<?php echo htmlspecialchars($config['announcement_end'] ?? ''); ?>">
  <p class="small">Outside this window the announcement is hidden. Leave both blank to always show it.</p>

  <h2>Category markup (optional)</h2>
  <p>One line per category: <code>Category name | percent</code> — for example, <code>Instagram Followers | 30</code>. A category listed here uses its own markup instead of the global markup above. Lines that do not match are ignored; nothing is saved for them.</p>
  <label for="category_markup">Category markup lines</label>
  <textarea id="category_markup" name="category_markup" rows="5"><?php $cm_lines = []; foreach (($config['category_markup'] ?? []) as $cc => $pp) { $cm_lines[] = $cc . ' | ' . $pp; } echo htmlspecialchars(implode("\n", $cm_lines)); ?></textarea>

  <h2>Maintenance mode</h2>
  <p>When on, customers see a notice and the order and fund forms are disabled. The services catalogue stays browsable, and admins bypass maintenance. There is no URL-parameter bypass.</p>
  <p><label><input type="checkbox" name="maintenance_mode" value="1"<?php echo !empty($config['maintenance_mode']) ? ' checked' : ''; ?>> Maintenance mode on</label></p>
  <label for="maintenance_message">Maintenance message (shown to customers)</label>
  <input type="text" id="maintenance_message" name="maintenance_message" maxlength="500" value="<?php echo htmlspecialchars($config['maintenance_message'] ?? ''); ?>" placeholder="Ordering is paused for maintenance right now — please check back shortly.">

  <h2>Canned ticket replies (up to 5)</h2>
  <p>Templates the admin can insert into a ticket reply with one tap. Insert only — the reply is still sent by hand.</p>
  <?php $canned_existing = $config['canned_replies'] ?? []; for ($ci = 0; $ci < 5; $ci++): ?>
  <label for="canned_<?php echo $ci; ?>">Canned reply <?php echo $ci + 1; ?></label>
  <textarea id="canned_<?php echo $ci; ?>" name="canned_<?php echo $ci; ?>" maxlength="500" rows="2"><?php echo htmlspecialchars($canned_existing[$ci] ?? ''); ?></textarea>
  <?php endfor; ?>

  <h2>Payment numbers</h2>
  <p>These numbers are shown on the Add Funds page. Leave a field blank if that method is not used yet — it will show "Coming soon — please check back shortly." to customers.</p>
  <label for="easypaisa">EasyPaisa number</label>
  <input type="text" id="easypaisa" name="easypaisa" value="<?php echo htmlspecialchars($config['payments']['easypaisa']); ?>">
  <label for="easypaisa_desc">EasyPaisa description (shown on Add Funds)</label>
  <textarea id="easypaisa_desc" name="easypaisa_desc" maxlength="300" rows="2"><?php echo htmlspecialchars($config['payment_descriptions']['easypaisa'] ?? ''); ?></textarea>
  <label for="jazzcash">JazzCash number</label>
  <input type="text" id="jazzcash" name="jazzcash" value="<?php echo htmlspecialchars($config['payments']['jazzcash']); ?>">
  <label for="jazzcash_desc">JazzCash description (shown on Add Funds)</label>
  <textarea id="jazzcash_desc" name="jazzcash_desc" maxlength="300" rows="2"><?php echo htmlspecialchars($config['payment_descriptions']['jazzcash'] ?? ''); ?></textarea>
  <label for="upaisa">UPaisa number</label>
  <input type="text" id="upaisa" name="upaisa" value="<?php echo htmlspecialchars($config['payments']['upaisa']); ?>">
  <label for="upaisa_desc">UPaisa description (shown on Add Funds)</label>
  <textarea id="upaisa_desc" name="upaisa_desc" maxlength="300" rows="2"><?php echo htmlspecialchars($config['payment_descriptions']['upaisa'] ?? ''); ?></textarea>

  <p><button class="btn btn-blue" type="submit">Save Settings</button></p>
</form>

<h2>Support contacts (from config)</h2>
<p>WhatsApp: <?php echo htmlspecialchars($config['support_whatsapp']); ?><br>
Email: <?php echo htmlspecialchars($config['support_email']); ?><br>
Currency: <?php echo htmlspecialchars($config['currency']); ?><br>
Provider: <?php echo htmlspecialchars($config['provider']['name']); ?> only</p>
<?php require __DIR__ . '/includes/footer.php'; ?>

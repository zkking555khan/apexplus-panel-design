<?php
/**
 * SMM APEX PLUS — admin settings storage (v2).
 *
 * The API key and the payment numbers are NEVER hardcoded.
 * They are entered by the admin on admin.php and stored only
 * in data/settings.json on the server. That file is blocked from web
 * access by data/.htaccess, and the full API key is never displayed
 * again after it is saved (only its last 4 characters are shown).
 *
 * Access control: admin.php and every admin-*.php page are protected
 * by the admin login in includes/auth.php. Visitors who are not
 * signed in as admin are sent to admin-login.php (or to
 * admin-setup.php when no admin account exists yet). Never remove
 * that guard.
 *
 * v2 additions (all admin-only settings, never customer copy):
 *  - other_services_subtitle: subtitle under the Other Services
 *    button/card. Default 'All in One Digital Services'; a blank
 *    saved value means the subtitle line is omitted, never rendered
 *    empty.
 *  - category_markup: per-category markup % overriding the global
 *    markup_percent for that category only. Values are validated
 *    0–1000 on save; anything else is ignored here.
 *  - maintenance_mode + maintenance_message: when on, customers see
 *    a notice and the order / fund forms are disabled. Admins
 *    (admin session) bypass it. There is never a URL-parameter
 *    bypass. The services catalogue stays browsable.
 *  - announcement_start / announcement_end: optional scheduling
 *    window for the announcement. Outside the window the
 *    announcement is hidden. Empty = always shown.
 *  - canned_replies: up to 5 admin ticket-reply templates, insert
 *    only — the admin still sends the reply by hand.
 */

function settings_file_path() {
    return __DIR__ . '/../data/settings.json';
}

function load_saved_settings() {
    $file = settings_file_path();
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function save_saved_settings($data) {
    $dir = dirname(settings_file_path());
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $ok = file_put_contents(settings_file_path(), $json, LOCK_EX) !== false;
    if ($ok) {
        @chmod(settings_file_path(), 0600);
    }
    return $ok;
}

function get_effective_config() {
    $config = require __DIR__ . '/../config.php';
    $saved = load_saved_settings();
    if (isset($saved['api_key']) && is_string($saved['api_key'])) {
        $config['provider']['api_key'] = $saved['api_key'];
    }
    // api_url is locked in config.php; a saved api_url is never applied, so the provider cannot be switched.
    if (isset($saved['theme']) && is_string($saved['theme'])) {
        $config['theme'] = $saved['theme'];
    }
    if (isset($saved['markup_percent']) && is_numeric($saved['markup_percent'])) {
        $config['markup_percent'] = (float) $saved['markup_percent'];
    }
    if (isset($saved['other_services_url']) && is_string($saved['other_services_url'])) {
        $config['other_services_url'] = $saved['other_services_url'];
    }
    if (isset($saved['other_services_label']) && is_string($saved['other_services_label'])) {
        $config['other_services_label'] = $saved['other_services_label'];
    }
    if (isset($saved['other_services_subtitle']) && is_string($saved['other_services_subtitle'])) {
        $config['other_services_subtitle'] = $saved['other_services_subtitle'];
    } else {
        $config['other_services_subtitle'] = 'All in One Digital Services';
    }
    if (isset($saved['announcement']) && is_string($saved['announcement'])) {
        $config['announcement'] = $saved['announcement'];
    }
    // Scheduled announcements: outside the window, hide the announcement.
    if (!empty($config['announcement'])) {
        $now = time();
        if (isset($saved['announcement_start']) && is_string($saved['announcement_start']) && trim($saved['announcement_start']) !== '') {
            $start = strtotime($saved['announcement_start']);
            if ($start !== false && $now < $start) {
                $config['announcement'] = '';
            }
        }
        if (!empty($config['announcement']) && isset($saved['announcement_end']) && is_string($saved['announcement_end']) && trim($saved['announcement_end']) !== '') {
            $end = strtotime($saved['announcement_end']);
            if ($end !== false && $now > $end) {
                $config['announcement'] = '';
            }
        }
    }
    $config['announcement_start'] = (isset($saved['announcement_start']) && is_string($saved['announcement_start'])) ? $saved['announcement_start'] : '';
    $config['announcement_end'] = (isset($saved['announcement_end']) && is_string($saved['announcement_end'])) ? $saved['announcement_end'] : '';
    if (isset($saved['category_markup']) && is_array($saved['category_markup'])) {
        $clean = [];
        foreach ($saved['category_markup'] as $cat => $pct) {
            $cat = trim((string) $cat);
            if ($cat !== '' && is_numeric($pct) && (float) $pct >= 0 && (float) $pct <= 1000) {
                $clean[$cat] = (float) $pct;
            }
        }
        $config['category_markup'] = $clean;
    } else {
        $config['category_markup'] = [];
    }
    $config['maintenance_mode'] = !empty($saved['maintenance_mode']);
    $config['maintenance_message'] = (isset($saved['maintenance_message']) && is_string($saved['maintenance_message'])) ? $saved['maintenance_message'] : '';
    if (isset($saved['canned_replies']) && is_array($saved['canned_replies'])) {
        $replies = [];
        foreach ($saved['canned_replies'] as $r) {
            $r = trim((string) $r);
            if ($r !== '') {
                $replies[] = substr($r, 0, 500);
            }
            if (count($replies) >= 5) {
                break;
            }
        }
        $config['canned_replies'] = $replies;
    } else {
        $config['canned_replies'] = [];
    }
    if (isset($saved['payments']) && is_array($saved['payments'])) {
        foreach (['easypaisa', 'jazzcash', 'upaisa'] as $method) {
            if (isset($saved['payments'][$method]) && is_string($saved['payments'][$method])) {
                $config['payments'][$method] = $saved['payments'][$method];
            }
        }
    }
    if (isset($saved['payment_descriptions']) && is_array($saved['payment_descriptions'])) {
        foreach (['easypaisa', 'jazzcash', 'upaisa'] as $method) {
            if (isset($saved['payment_descriptions'][$method]) && is_string($saved['payment_descriptions'][$method])) {
                $config['payment_descriptions'][$method] = $saved['payment_descriptions'][$method];
            }
        }
    }
    return $config;
}

function mask_api_key($key) {
    $key = (string) $key;
    if ($key === '') {
        return 'Not set';
    }
    if (strlen($key) <= 4) {
        return '••••';
    }
    return '••••' . substr($key, -4);
}

function valid_payment_number($number) {
    $number = trim((string) $number);
    if ($number === '') {
        return true; // empty means "not set yet"
    }
    return preg_match('/^[0-9+\-\s]{7,20}$/', $number) === 1;
}

function site_themes() {
    return [
        'daylight' => 'Daylight (white + soft animated background)',
        'midnight-navy' => 'Midnight Navy (dark navy + royal blue + silver)',
        'emerald-night' => 'Emerald Night (dark + emerald green)',
        'royal-purple' => 'Royal Purple (dark + purple)',
        'ocean-teal' => 'Ocean Teal (dark + teal)',
    ];
}
function current_theme($config) {
    $themes = site_themes();
    $t = (string) ($config['theme'] ?? 'daylight');
    return isset($themes[$t]) ? $t : 'daylight';
}

/**
 * Effective markup % for one category: the category override when
 * set, otherwise the global markup. Admin-only data — never render
 * the result on a customer page.
 */
function category_markup_percent($config, $category) {
    $category = trim((string) ($category ?? ''));
    if ($category !== '' && isset($config['category_markup']) && is_array($config['category_markup']) && isset($config['category_markup'][$category]) && is_numeric($config['category_markup'][$category])) {
        return (float) $config['category_markup'][$category];
    }
    return is_numeric($config['markup_percent'] ?? null) ? (float) $config['markup_percent'] : 0.0;
}

/* Pricing maths, unchanged from v1. Callers resolve the category
   markup first with category_markup_percent() and pass it here. */
function retail_price($provider_rate, $markup_percent) {
    if (!is_numeric($provider_rate)) return null;
    $rate = (float) $provider_rate;
    $markup = is_numeric($markup_percent) ? (float) $markup_percent : 0.0;
    if ($markup < 0) $markup = 0;
    return round($rate * (1 + $markup / 100), 2);
}
function order_charge($provider_rate, $quantity, $markup_percent) {
    $retail = retail_price($provider_rate, $markup_percent);
    if ($retail === null) return null;
    return round($retail / 1000 * (int) $quantity, 2);
}

/**
 * Maintenance mode, active for customers only. Admins (admin
 * session) bypass it. There is deliberately no URL-parameter bypass.
 * Reads the session directly so this helper works whether or not
 * includes/auth.php has been loaded.
 */
function maintenance_active($config) {
    if (empty($config['maintenance_mode'])) {
        return false;
    }
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin_logged_in'])) {
        return false;
    }
    return true;
}

/**
 * Status pill HTML. The status text is always escaped; unknown
 * statuses get a plain pill. Exact status names are never renamed.
 */
function status_pill($status) {
    $s = (string) $status;
    $map = [
        'pending' => 'pill-pending',
        'sent' => 'pill-sent',
        'in progress' => 'pill-sent',
        'processing' => 'pill-sent',
        'completed' => 'pill-completed',
        'partial' => 'pill-pending',
        'cancelled' => 'pill-cancelled',
        'canceled' => 'pill-cancelled',
        'refunded' => 'pill-refunded',
        'open' => 'pill-pending',
        'closed' => 'pill-completed',
        'approved' => 'pill-completed',
        'rejected' => 'pill-refunded',
        'active' => 'pill-completed',
        'suspended' => 'pill-refunded',
        'live' => 'pill-completed',
        'hidden' => 'pill-refunded',
        'manual' => 'pill-pending',
    ];
    $cls = $map[strtolower(trim($s))] ?? '';
    return '<span class="pill' . ($cls !== '' ? ' ' . $cls : '') . '">' . htmlspecialchars($s) . '</span>';
}

/**
 * Delivery time from live API fields only; '' where none supplied.
 * Never guessed, never defaulted.
 */
function service_delivery_time($row) {
    if (!is_array($row)) {
        return '';
    }
    foreach (['average_time', 'avg_time', 'delivery_time', 'start_time', 'estimated_time'] as $k) {
        if (isset($row[$k]) && trim((string) $row[$k]) !== '') {
            return trim((string) $row[$k]);
        }
    }
    return '';
}

/**
 * Balance audit log (v2 table balance_audit_log). One row per money
 * event. Failures are ignored silently: a missing table (before the
 * migration) must never break an order, refund, approval or
 * adjustment — the money action itself still commits.
 */
function log_balance_event($conn, $user_id, $delta, $kind, $ref_type, $ref_id, $reason) {
    if ($conn === null) {
        return false;
    }
    $stmt = $conn->prepare('INSERT INTO balance_audit_log (user_id, delta, kind, ref_type, ref_id, reason) VALUES (?, ?, ?, ?, ?, ?)');
    if ($stmt === false) {
        return false;
    }
    $delta_f = (float) $delta;
    $ref_id_i = $ref_id === null ? null : (int) $ref_id;
    $stmt->bind_param('idssis', $user_id, $delta_f, $kind, $ref_type, $ref_id_i, $reason);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

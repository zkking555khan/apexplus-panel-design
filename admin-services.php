<?php
/**
 * SMM APEX PLUS — Admin Service Manager (v2).
 *
 * Admin only (require_admin). The live list comes from the single
 * locked connection in Admin Settings — it cannot be switched to
 * another source from here, and no add-source form exists by design.
 * This page only:
 *  - hides / unhides live services (hiding never cancels, refunds
 *    or changes existing orders; a hidden service's Pending orders
 *    can still be sent, refreshed and refunded from Admin Orders);
 *  - adds, edits and deletes MANUAL services, which are fulfilled
 *    by hand and never sent anywhere automatically;
 *  - deletes orphaned overrides (a hidden service that is no longer
 *    in the live list) and invalid overrides (a manual row carrying
 *    a live service ID, which the merge layer always skips).
 * Manual delete is blocked while any non-refunded order references
 * the service.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/api.php';
require __DIR__ . '/includes/service-overrides.php';
require_admin();
ensure_session();
$config = get_effective_config();
$page_title = 'Manage Services';
$active = 'admin';
$message = '';
$db_ready = db_available();

$live = null;
$api_error = null;
if (trim((string) ($config['provider']['api_key'] ?? '')) !== '' && trim((string) ($config['provider']['api_url'] ?? '')) !== '') {
    $svc = provider_services();
    if ($svc['ok']) {
        $live = $svc['services'];
    } else {
        $api_error = $svc['error'];
    }
} else {
    $api_error = 'API key not set yet';
}

$overrides = [];
$conn_for_overrides = $db_ready ? db_connect() : null;
if ($conn_for_overrides !== null) {
    $overrides = load_service_overrides($conn_for_overrides);
    $conn_for_overrides->close();
}

function override_upsert($conn, $service_id, $fields) {
    $cols = ['service_id'];
    $placeholders = ['?'];
    $types = 's';
    $values = [$service_id];
    foreach ($fields as $col => $val) {
        $cols[] = $col;
        $placeholders[] = '?';
        if (is_int($val)) { $types .= 'i'; }
        elseif (is_float($val) || (is_string($val) && is_numeric($val) && strpos((string) $val, '.') !== false)) { $types .= 'd'; }
        else { $types .= 's'; }
        $values[] = $val;
    }
    $updates = [];
    foreach ($fields as $col => $val) {
        $updates[] = $col . ' = VALUES(' . $col . ')';
    }
    $sql = 'INSERT INTO service_overrides (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_ready) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'Security check failed. Please reload the page and try again.';
    } else {
        $action = trim($_POST['action'] ?? '');
        $conn = db_connect();
        if ($conn === null) {
            $message = 'Database could not be reached. Please try again later.';
        } else {
            if ($action === 'add_manual' || $action === 'update_manual') {
                $m_name = substr(trim($_POST['m_name'] ?? ''), 0, 255);
                $m_category = substr(trim($_POST['m_category'] ?? ''), 0, 100);
                $m_rate = trim($_POST['m_rate'] ?? '');
                $m_min = trim($_POST['m_min'] ?? '');
                $m_max = trim($_POST['m_max'] ?? '');
                $m_sort = trim($_POST['m_sort'] ?? '0');
                if ($m_name === '') {
                    $message = 'Service name is required.';
                } elseif ($m_category === '') {
                    $message = 'Category is required.';
                } elseif (!is_numeric($m_rate) || (float) $m_rate <= 0) {
                    $message = 'Rate per 1,000 must be greater than 0.';
                } elseif (!ctype_digit($m_min) || (int) $m_min < 1) {
                    $message = 'Minimum quantity must be at least 1.';
                } elseif (!ctype_digit($m_max) || (int) $m_max < (int) $m_min || (int) $m_max > 1000000) {
                    $message = 'Maximum quantity must be at least the minimum and at most 1,000,000.';
                } elseif ($m_sort !== '' && !preg_match('/^-?\d+$/', $m_sort)) {
                    $message = 'Sort order must be a whole number.';
                } else {
                    if ($action === 'add_manual') {
                        $new_id = '';
                        for ($try = 0; $try < 5; $try++) {
                            $candidate = new_manual_service_id();
                            $chk = $conn->prepare('SELECT service_id FROM service_overrides WHERE service_id = ? LIMIT 1');
                            $chk->bind_param('s', $candidate);
                            $chk->execute();
                            $exists = $chk->get_result()->fetch_assoc() !== null;
                            $chk->close();
                            if (!$exists) { $new_id = $candidate; break; }
                        }
                        if ($new_id === '') {
                            $message = 'Could not generate a service ID. Please try again.';
                        } else {
                            $ok = override_upsert($conn, $new_id, ['is_hidden' => 0, 'is_manual' => 1, 'display_name' => $m_name, 'manual_rate' => number_format((float) $m_rate, 4, '.', ''), 'manual_min' => (int) $m_min, 'manual_max' => (int) $m_max, 'manual_category' => $m_category, 'sort_order' => (int) $m_sort]);
                            $message = $ok ? 'Manual service added: ' . $m_name . ' (' . $new_id . '). It is fulfilled by hand.' : 'Manual service could not be saved.';
                        }
                    } else {
                        $edit_id = trim($_POST['service_id'] ?? '');
                        $existing = $overrides[$edit_id] ?? null;
                        if ($existing === null || (int) ($existing['is_manual'] ?? 0) !== 1) {
                            $message = 'Only manual services can be edited.';
                        } else {
                            $ok = override_upsert($conn, $edit_id, ['is_hidden' => (int) ($existing['is_hidden'] ?? 0), 'is_manual' => 1, 'display_name' => $m_name, 'manual_rate' => number_format((float) $m_rate, 4, '.', ''), 'manual_min' => (int) $m_min, 'manual_max' => (int) $m_max, 'manual_category' => $m_category, 'sort_order' => (int) $m_sort]);
                            $message = $ok ? 'Manual service updated.' : 'Manual service could not be updated.';
                        }
                    }
                }
            } elseif ($action === 'hide' || $action === 'unhide') {
                $sid = trim($_POST['service_id'] ?? '');
                $in_live = false;
                if (is_array($live)) {
                    foreach ($live as $lr) {
                        if ((string) ($lr['service'] ?? $lr['id'] ?? '') === $sid) { $in_live = true; break; }
                    }
                }
                $existing = $overrides[$sid] ?? null;
                if ($action === 'hide' && !$in_live && $existing === null) {
                    $message = 'Only a live service can be hidden.';
                } elseif ($action === 'unhide' && $existing === null) {
                    $message = 'Nothing to unhide for this service.';
                } else {
                    $fields = ['is_hidden' => $action === 'hide' ? 1 : 0];
                    if ($existing !== null) {
                        $fields['is_manual'] = (int) ($existing['is_manual'] ?? 0);
                        if ((int) ($existing['is_manual'] ?? 0) === 1) {
                            $fields['display_name'] = $existing['display_name'];
                            $fields['manual_rate'] = $existing['manual_rate'];
                            $fields['manual_min'] = $existing['manual_min'];
                            $fields['manual_max'] = $existing['manual_max'];
                            $fields['manual_category'] = $existing['manual_category'];
                            $fields['sort_order'] = (int) ($existing['sort_order'] ?? 0);
                        }
                    } else {
                        $fields['is_manual'] = 0;
                    }
                    $ok = override_upsert($conn, $sid, $fields);
                    $message = $ok ? ($action === 'hide' ? 'Service hidden from customers. Existing orders are untouched.' : 'Service is visible to customers again.') : 'Change could not be saved.';
                }
            } elseif ($action === 'delete_manual' || $action === 'delete_override') {
                $sid = trim($_POST['service_id'] ?? '');
                $existing = $overrides[$sid] ?? null;
                if ($existing === null) {
                    $message = 'Override not found.';
                } elseif ($action === 'delete_manual' && (int) ($existing['is_manual'] ?? 0) !== 1) {
                    $message = 'Only manual services can be deleted this way.';
                } else {
                    if ($action === 'delete_manual') {
                        $chk = $conn->prepare("SELECT COUNT(*) c FROM orders WHERE service_id = ? AND status != 'Refunded'");
                        $chk->bind_param('s', $sid);
                        $chk->execute();
                        $crow = $chk->get_result()->fetch_assoc();
                        $chk->close();
                        if ((int) ($crow['c'] ?? 0) > 0) {
                            $message = 'Cannot delete: ' . (int) $crow['c'] . ' non-refunded order(s) still reference this service. Refund or complete them first.';
                            $conn->close();
                            $conn = null;
                        }
                    }
                    if ($conn !== null) {
                        $del = $conn->prepare('DELETE FROM service_overrides WHERE service_id = ?');
                        $del->bind_param('s', $sid);
                        $del->execute();
                        $deleted = $del->affected_rows;
                        $del->close();
                        $message = $deleted === 1 ? 'Override deleted.' : 'Override could not be deleted.';
                    }
                }
            } else {
                $message = 'Unknown action.';
            }
            if ($conn !== null) {
                $conn->close();
            }
            // Reload overrides after any change.
            $rc = $db_ready ? db_connect() : null;
            if ($rc !== null) {
                $overrides = load_service_overrides($rc);
                $rc->close();
            }
        }
    }
}

// Build display rows.
$live_ids = [];
if (is_array($live)) {
    foreach ($live as $lr) {
        $live_ids[(string) ($lr['service'] ?? $lr['id'] ?? '')] = true;
    }
}
$display = [];
if (is_array($live)) {
    foreach ($live as $lr) {
        $id = (string) ($lr['service'] ?? $lr['id'] ?? '');
        if ($id === '') continue;
        $ov = $overrides[$id] ?? null;
        $hidden = $ov !== null && (int) ($ov['is_hidden'] ?? 0) === 1;
        $display[] = [
            'id' => $id,
            'name' => (string) ($lr['name'] ?? $lr['service'] ?? $id),
            'category' => (string) ($lr['category'] ?? '—'),
            'retail' => retail_price($lr['rate'] ?? $lr['price'] ?? null, category_markup_percent($config, (string) ($lr['category'] ?? ''))),
            'min' => $lr['min'] ?? null,
            'max' => $lr['max'] ?? null,
            'status' => $hidden ? 'Hidden' : 'Live',
            'kind' => $hidden ? 'hidden' : 'live',
            'ov' => $ov,
        ];
    }
}
foreach ($overrides as $oid => $ov) {
    $is_manual = (int) ($ov['is_manual'] ?? 0) === 1;
    $in_live = isset($live_ids[$oid]);
    if ($is_manual && $in_live) {
        $display[] = ['id' => (string) $oid, 'name' => (string) ($ov['display_name'] ?? $oid), 'category' => (string) ($ov['manual_category'] ?? '—'), 'retail' => null, 'min' => null, 'max' => null, 'status' => 'Invalid (same ID as a live service)', 'kind' => 'invalid', 'ov' => $ov];
    } elseif ($is_manual) {
        $hidden = (int) ($ov['is_hidden'] ?? 0) === 1;
        $display[] = ['id' => (string) $oid, 'name' => (string) ($ov['display_name'] ?? $oid), 'category' => (string) ($ov['manual_category'] ?? '—'), 'retail' => retail_price($ov['manual_rate'] ?? null, category_markup_percent($config, (string) ($ov['manual_category'] ?? ''))), 'min' => $ov['manual_min'], 'max' => $ov['manual_max'], 'status' => $hidden ? 'Manual (hidden)' : 'Manual', 'kind' => $hidden ? 'hidden' : 'manual', 'ov' => $ov];
    } elseif (!$in_live) {
        $display[] = ['id' => (string) $oid, 'name' => 'No longer in the live list', 'category' => '—', 'retail' => null, 'min' => null, 'max' => null, 'status' => 'Orphaned hidden override', 'kind' => 'orphan', 'ov' => $ov];
    }
}
// Hidden rows at the bottom, greyed.
usort($display, function ($a, $b) {
    $ha = ($a['kind'] === 'hidden' || $a['kind'] === 'orphan') ? 1 : 0;
    $hb = ($b['kind'] === 'hidden' || $b['kind'] === 'orphan') ? 1 : 0;
    if ($ha !== $hb) return $ha <=> $hb;
    return strcmp($a['name'], $b['name']);
});
$count_live = 0; $count_hidden = 0; $count_manual = 0;
foreach ($display as $d) {
    if ($d['kind'] === 'live') $count_live++;
    elseif ($d['kind'] === 'hidden') $count_hidden++;
    elseif ($d['kind'] === 'manual') $count_manual++;
}
$filter_q = trim($_GET['q'] ?? '');
$filter_kind = trim($_GET['filter'] ?? '');
if (!in_array($filter_kind, ['live', 'hidden', 'manual', ''], true)) $filter_kind = '';
$filtered = [];
foreach ($display as $d) {
    if ($filter_kind === 'live' && $d['kind'] !== 'live') continue;
    if ($filter_kind === 'hidden' && $d['kind'] !== 'hidden') continue;
    if ($filter_kind === 'manual' && $d['kind'] !== 'manual') continue;
    if ($filter_q !== '' && stripos($d['name'], $filter_q) === false && stripos($d['category'], $filter_q) === false && stripos($d['id'], $filter_q) === false) continue;
    $filtered[] = $d;
}
$edit_id = trim($_GET['edit'] ?? '');
$edit_row = ($edit_id !== '' && isset($overrides[$edit_id]) && (int) ($overrides[$edit_id]['is_manual'] ?? 0) === 1) ? $overrides[$edit_id] : null;

require __DIR__ . '/includes/header.php';
?>
<h1>Manage Services</h1>
<p class="notice">Live services come from the locked connection in Admin Settings — the connection cannot be switched to another source from here. Hidden services are not shown to customers. Manual services are fulfilled by hand by you — they are never sent anywhere automatically.</p>
<p class="notice">Live: <?php echo (int) $count_live; ?> &nbsp;|&nbsp; Hidden: <?php echo (int) $count_hidden; ?> &nbsp;|&nbsp; Manual: <?php echo (int) $count_manual; ?></p>
<?php if ($message !== ''): ?><p class="notice"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (!$db_ready): ?>
<p class="notice">Database not configured yet.</p>
<?php else: ?>
<?php if ($api_error !== null): ?><p class="notice notice-warning">Live catalogue could not be loaded (<?php echo htmlspecialchars($api_error); ?>). Manual services below can still be managed; live rows will appear once the catalogue loads.</p><?php endif; ?>
<form method="get" action="admin-services.php" class="filter-form">
  <label>Search <input type="search" name="q" value="<?php echo htmlspecialchars($filter_q); ?>" placeholder="Service name, category, ID"></label>
  <label>Show <select name="filter"><option value="">All</option><option value="live"<?php echo $filter_kind === 'live' ? ' selected' : ''; ?>>Live</option><option value="hidden"<?php echo $filter_kind === 'hidden' ? ' selected' : ''; ?>>Hidden</option><option value="manual"<?php echo $filter_kind === 'manual' ? ' selected' : ''; ?>>Manual</option></select></label>
  <button class="btn btn-blue" type="submit">Filter</button>
</form>

<h2><?php echo $edit_row !== null ? 'Edit manual service' : 'Add a manual service'; ?></h2>
<form method="post" action="admin-services.php" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
  <?php if ($edit_row !== null): ?><input type="hidden" name="service_id" value="<?php echo htmlspecialchars($edit_id); ?>"><?php endif; ?>
  <label for="m_name">Service name (required)</label>
  <input type="text" id="m_name" name="m_name" required maxlength="255" value="<?php echo htmlspecialchars($edit_row['display_name'] ?? ''); ?>">
  <label for="m_category">Category (required)</label>
  <input type="text" id="m_category" name="m_category" required maxlength="100" value="<?php echo htmlspecialchars($edit_row['manual_category'] ?? ''); ?>">
  <label for="m_rate">Rate per 1,000 before markup (required, greater than 0)</label>
  <p class="small">Customers see this rate plus your markup — the retail price is computed the same way as for live services.</p>
  <input type="number" id="m_rate" name="m_rate" required min="0.0001" step="0.0001" value="<?php echo htmlspecialchars($edit_row['manual_rate'] ?? ''); ?>">
  <label for="m_min">Minimum quantity (required)</label>
  <input type="number" id="m_min" name="m_min" required min="1" value="<?php echo htmlspecialchars($edit_row['manual_min'] ?? ''); ?>">
  <label for="m_max">Maximum quantity (required, at least the minimum)</label>
  <input type="number" id="m_max" name="m_max" required min="1" max="1000000" value="<?php echo htmlspecialchars($edit_row['manual_max'] ?? ''); ?>">
  <label for="m_sort">Sort order (optional, default 0)</label>
  <input type="number" id="m_sort" name="m_sort" value="<?php echo htmlspecialchars($edit_row['sort_order'] ?? '0'); ?>">
  <p><button class="btn btn-blue" type="submit" name="action" value="<?php echo $edit_row !== null ? 'update_manual' : 'add_manual'; ?>"><?php echo $edit_row !== null ? 'Save Manual Service' : 'Add manual service'; ?></button><?php if ($edit_row !== null): ?> <a class="btn btn-outline" href="admin-services.php">Cancel edit</a><?php endif; ?></p>
</form>

<h2>Services</h2>
<?php if (empty($filtered)): ?>
<p>No services match this filter.</p>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>Service</th><th>Category</th><th>Price / 1,000 (<?php echo htmlspecialchars($config['currency']); ?>)</th><th>Min–Max</th><th>Status</th><th>Action</th></tr></thead>
  <tbody>
    <?php foreach ($filtered as $d): $grey = ($d['kind'] === 'hidden' || $d['kind'] === 'orphan') ? ' style="opacity:.6"' : ''; ?>
    <tr<?php echo $grey; ?>>
      <td><?php echo htmlspecialchars($d['name']); ?><br><span class="small"><?php echo htmlspecialchars($d['id']); ?></span></td>
      <td><?php echo htmlspecialchars($d['category']); ?></td>
      <td><?php echo $d['retail'] === null ? '—' : htmlspecialchars(number_format($d['retail'], 2)); ?></td>
      <td><?php echo $d['min'] === null ? '—' : (int) $d['min'] . ' – ' . (int) $d['max']; ?></td>
      <td><?php echo status_pill($d['status']); ?></td>
      <td>
        <form method="post" action="admin-services.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="service_id" value="<?php echo htmlspecialchars($d['id']); ?>">
          <?php if ($d['kind'] === 'live'): ?><button type="submit" name="action" value="hide">Hide</button><?php endif; ?>
          <?php if ($d['kind'] === 'hidden'): ?><button type="submit" name="action" value="unhide">Unhide</button><?php endif; ?>
          <?php if ($d['kind'] === 'manual'): ?><a class="btn btn-outline" href="admin-services.php?edit=<?php echo urlencode($d['id']); ?>">Edit</a> <button type="submit" name="action" value="delete_manual">Delete</button><?php endif; ?>
          <?php if ($d['kind'] === 'invalid' || $d['kind'] === 'orphan'): ?><button type="submit" name="action" value="delete_override">Delete</button><?php endif; ?>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
<p><a href="admin.php">Back to Admin Settings</a> | <a href="admin-orders.php">Manage Orders</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>

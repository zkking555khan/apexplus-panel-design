<?php
/**
 * SMM APEX PLUS — v2 Service Manager merge logic.
 *
 * Pure merge logic: given the live list (already fetched, exactly as
 * v1 fetches it) and the override rows (from service_overrides),
 * return the list customers and New Order should see.
 *
 * Rules enforced here (see SERVICE-MANAGEMENT.md for the full design):
 *  - Hidden live rows are removed. Existing orders are untouched —
 *    this function only shapes lists, never orders.
 *  - Manual rows are appended in the same shape as live rows, with
 *    service IDs that can never be numeric live IDs ('manual-…').
 *  - Live rates are never changed by this layer. Manual rates come
 *    only from manual rows.
 *  - No customer-facing wording appears in any string this file
 *    outputs. Function and variable names are code, not copy.
 *
 * Safety: when the service_overrides table does not exist yet
 * (pre-migration), load_service_overrides() returns [] and the
 * live list passes through unchanged.
 */

require_once __DIR__ . '/settings.php';

/** Load override rows. $conn is a mysqli connection (v1's db_connect()). */
function load_service_overrides($conn) {
    $rows = [];
    if ($conn === null) {
        return $rows;
    }
    $res = $conn->query('SELECT service_id, is_hidden, is_manual, display_name, manual_rate, manual_min, manual_max, manual_category, sort_order FROM service_overrides ORDER BY sort_order ASC, service_id ASC');
    if ($res === false) {
        return $rows; // No table yet (pre-migration) = no overrides. Safe default.
    }
    while ($r = $res->fetch_assoc()) {
        $rows[(string) $r['service_id']] = $r;
    }
    $res->free();
    return $rows;
}

/** True only for manual IDs created by the admin form (never numeric). */
function is_manual_service_id($id) {
    $id = (string) $id;
    return str_starts_with($id, 'manual-') && !is_numeric($id);
}

/** Generate a manual ID. Caller must check uniqueness before insert. */
function new_manual_service_id() {
    return 'manual-' . bin2hex(random_bytes(4)); // 8 hex chars, e.g. manual-9f3ac71d
}

/**
 * Merge live list + overrides into the customer-visible list.
 * $live_services: rows with keys service|id, name, category, rate|price, min, max.
 * $overrides: keyed by service_id, as returned by load_service_overrides().
 * Returns rows in the same shape, hidden rows removed, manual rows appended.
 */
function apply_service_overrides($live_services, $overrides) {
    $out = [];
    if (is_array($live_services)) {
        foreach ($live_services as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['service'] ?? $row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $ov = $overrides[$id] ?? null;
            if ($ov !== null && (int) ($ov['is_hidden'] ?? 0) === 1) {
                continue; // Hidden: not listed anywhere customer-facing.
            }
            if ($ov !== null && (int) ($ov['is_manual'] ?? 0) === 1) {
                continue; // A manual row with a live ID is invalid data; skip it, never show it.
            }
            $out[] = $row;
        }
    }
    $manual = [];
    foreach ($overrides as $id => $ov) {
        if ((int) ($ov['is_manual'] ?? 0) !== 1) {
            continue;
        }
        if ((int) ($ov['is_hidden'] ?? 0) === 1) {
            continue;
        }
        if (empty($ov['display_name']) || $ov['manual_rate'] === null || $ov['manual_min'] === null || $ov['manual_max'] === null) {
            continue; // Incomplete manual row: never list it.
        }
        $manual[] = [
            'service'  => (string) $id,
            'name'     => (string) $ov['display_name'],
            'category' => (string) ($ov['manual_category'] ?? 'Custom'),
            'rate'     => (string) $ov['manual_rate'],
            'min'      => (int) $ov['manual_min'],
            'max'      => (int) $ov['manual_max'],
            '_manual'  => true, // Internal flag only; never rendered.
        ];
    }
    usort($manual, function ($a, $b) use ($overrides) {
        $sa = (int) ($overrides[$a['service']]['sort_order'] ?? 0);
        $sb = (int) ($overrides[$b['service']]['sort_order'] ?? 0);
        if ($sa !== $sb) {
            return $sa <=> $sb;
        }
        return strcmp($a['name'], $b['name']);
    });
    return array_merge($out, $manual);
}

/**
 * Order-time guard (used in new-order.php BEFORE any live call):
 *   $fulfilment = is_manual_service_id($service_id) ? 'manual' : 'live';
 *   if ($fulfilment === 'manual') { skip the live order call; order stays Pending. }
 *   else { existing live order call, unchanged. }
 * Admin Orders guard: show "Mark Completed" only when fulfilment === 'manual'
 * and status === 'Pending'; hide "Refresh status" for manual orders; never
 * show the send action for manual orders.
 */

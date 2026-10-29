<?php
/**
 * SMM APEX PLUS — ZeeXFame API client (phase 2, connection only).
 *
 * The ONLY credentials used here are the ones the customer enters
 * himself on admin.php, from his own ZeeXFame account. The key is
 * empty by default and is never hardcoded, never logged, and never
 * shown in full. No other provider may be added.
 *
 * IMPORTANT — confirm before launch: the ZeeXFame API URL and the
 * exact action/parameter names must be confirmed against ZeeXFame's
 * own API documentation. This client uses the common SMM-panel API
 * convention (POST key + action) and ONLY displays data the API
 * actually returns. If the URL is empty, the key is empty, the
 * request fails, or the response shape is unexpected, the caller
 * gets an error and shows no services and no prices — it never
 * falls back to invented rows, prices or service IDs.
 * No external API call is made from this scaffold copy itself;
 * calls happen only at runtime on the live server, once a key
 * (and a confirmed API URL) have been entered by the customer.
 */
require_once __DIR__ . '/settings.php';

function provider_api_request($action, $params = []) {
    $config = get_effective_config();
    $api_key = trim((string) ($config['provider']['api_key'] ?? ''));
    $api_url = trim((string) ($config['provider']['api_url'] ?? ''));
    if ($api_key === '') {
        return ['ok' => false, 'error' => 'API key not set yet'];
    }
    if ($api_url === '') {
        return ['ok' => false, 'error' => 'API URL not configured yet'];
    }
    $fields = array_merge(['key' => $api_key, 'action' => $action], $params);
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $http !== 200) {
        return ['ok' => false, 'error' => 'Could not reach the provider. Please try again later.'];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Unexpected provider response.'];
    }
    return ['ok' => true, 'data' => $data];
}

/**
 * Sends one order to the provider (phase 3, standard SMM-panel
 * convention: POST key + action=add + service + link + quantity).
 * Returns ['ok' => true, 'provider_order_id' => '...'] only when the
 * provider actually returned an order ID, otherwise
 * ['ok' => false, 'error' => '...']. Accepted ID shapes: a top-level
 * 'order' or 'order_id' value, or a bare numeric JSON response.
 * The caller must keep the local order Pending on any failure —
 * a provider ID is never invented.
 */
function provider_place_order($service_id, $link, $quantity) {
    $result = provider_api_request('add', [
        'service' => $service_id,
        'link' => $link,
        'quantity' => $quantity,
    ]);
    if (!$result['ok']) {
        return $result;
    }
    $data = $result['data'];
    $order_id = null;
    if (is_array($data)) {
        if (isset($data['order'])) {
            $order_id = $data['order'];
        } elseif (isset($data['order_id'])) {
            $order_id = $data['order_id'];
        }
    } elseif (is_numeric($data)) {
        $order_id = $data;
    }
    if ($order_id === null || $order_id === '') {
        return ['ok' => false, 'error' => 'Provider did not return an order ID. The local order stays Pending.'];
    }
    return ['ok' => true, 'provider_order_id' => (string) $order_id];
}

/**
 * Asks the provider for one order's current status (action=status).
 * Returns ['ok' => true, 'status' => '...'] only when the provider
 * returned a status string; otherwise an error. Callers store only
 * a status the provider actually returned.
 */
function provider_order_status($provider_order_id) {
    $result = provider_api_request('status', ['order' => $provider_order_id]);
    if (!$result['ok']) {
        return $result;
    }
    $data = $result['data'];
    if (is_array($data) && isset($data['status']) && is_string($data['status']) && $data['status'] !== '') {
        return ['ok' => true, 'status' => $data['status']];
    }
    return ['ok' => false, 'error' => 'Provider did not return a status for this order.'];
}

/**
 * Returns ['ok' => true, 'services' => [...]] with ONLY the rows the
 * API returned, or ['ok' => false, 'error' => ...] with no rows at all.
 * Each returned row is passed through unchanged (then escaped by the
 * caller) — no price, ID, name or availability is invented here.
 */
function provider_services() {
    $result = provider_api_request('services');
    if (!$result['ok']) {
        return $result;
    }
    $data = $result['data'];
    if (isset($data['services']) && is_array($data['services'])) {
        $list = $data['services'];
    } elseif (array_keys($data) === range(0, count($data) - 1)) {
        $list = $data;
    } else {
        return ['ok' => false, 'error' => 'Unexpected provider response.'];
    }
    $services = [];
    foreach ($list as $row) {
        if (!is_array($row)) {
            continue;
        }
        $services[] = $row;
    }
    return ['ok' => true, 'services' => $services];
}

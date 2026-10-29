<?php
/**
 * SMM APEX PLUS — Services (v2).
 *
 * Live data ONLY, merged with the admin's Service Manager overrides
 * (hidden rows removed, manual rows appended). Rules, enforced here:
 *  - No API key saved -> a clear "getting ready" state, NO service
 *    table at all. Placeholder rows are never shown as services.
 *  - API request fails -> an error state, NO service table at all,
 *    NO fallback rows, and manual rows are NOT shown alone (v2 §8
 *    default 1: the v1 error state covers everything).
 *  - API succeeds -> ONLY the rows the API actually returned (plus
 *    admin manual rows) are shown, escaped, with a dash wherever a
 *    field is missing. No price, service ID, name or availability
 *    is invented here.
 *  - Mobile shows cards, desktop shows a table — both render from
 *    the same filtered list, so they can never disagree.
 */
require __DIR__ . '/includes/auth.php';
// Services sirf login ke baad dikhenge (2026-10-29, his order — jaise
// ZeeXFame me hai). Logged-out visitor yahan aaye to login page par
// jayega, aur login ke baad direct new-order page khulega.
require_login();
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/api.php';
require __DIR__ . '/includes/service-overrides.php';
$config = get_effective_config();
$page_title = 'Services';
$meta_description = 'Browse the live SMM APEX PLUS service catalogue — real prices, minimum and maximum quantities, and delivery times where available.';
$active = 'services';
$api_key = trim((string) ($config['provider']['api_key'] ?? ''));
$api_url = trim((string) ($config['provider']['api_url'] ?? ''));
$services = null;
$error = null;
$error_kind = '';
if ($api_key === '') {
    $error = 'Our live catalogue is getting ready — please check back shortly. No prices are shown until they are real.';
    $error_kind = 'not_connected';
} elseif ($api_url === '') {
    $error = 'Our live catalogue is getting ready — please check back shortly. No prices are shown until they are real.';
    $error_kind = 'not_connected';
} else {
    $result = provider_services();
    if (!$result['ok']) {
        $error = 'Our catalogue could not be loaded just now. Please reload the page — your balance and orders are safe.';
        $error_kind = 'api_error';
    } else {
        $overrides = load_service_overrides(db_connect());
        $services = apply_service_overrides($result['services'], $overrides);
    }
}
require __DIR__ . '/includes/header.php';
?>
<p class="eyebrow">Premium Services — Upfront Prices</p>
<h1>Our Services</h1>
<p>Every service below is fulfilled by SMM APEX PLUS, with its real price shown upfront. Pick the one that fits your goal.</p>

<?php
$filter_cat = trim($_GET['category'] ?? '');
$filter_q = trim($_GET['q'] ?? '');
$filter_sort = trim($_GET['sort'] ?? '');
$cats = [];
if (is_array($services)) { foreach ($services as $r) { $c = (string) ($r['category'] ?? ''); if ($c !== '' && !in_array($c, $cats, true)) $cats[] = $c; } sort($cats); }
$shown = [];
if (is_array($services)) { foreach ($services as $r) {
  $c = (string) ($r['category'] ?? ''); $n = (string) ($r['name'] ?? $r['service'] ?? '');
  if ($filter_cat !== '' && $c !== $filter_cat) continue;
  if ($filter_q !== '' && stripos($n, $filter_q) === false && stripos($c, $filter_q) === false) continue;
  $shown[] = $r;
} }
// Server-side sort: price low→high, price high→low, name A–Z. Default keeps catalogue order.
if ($filter_sort === 'price_asc' || $filter_sort === 'price_desc' || $filter_sort === 'name_asc') {
  usort($shown, function ($a, $b) use ($config, $filter_sort) {
    if ($filter_sort === 'name_asc') {
      return strcmp((string) ($a['name'] ?? $a['service'] ?? ''), (string) ($b['name'] ?? $b['service'] ?? ''));
    }
    $pa = retail_price($a['rate'] ?? $a['price'] ?? null, category_markup_percent($config, (string) ($a['category'] ?? '')));
    $pb = retail_price($b['rate'] ?? $b['price'] ?? null, category_markup_percent($config, (string) ($b['category'] ?? '')));
    $pa = $pa === null ? PHP_FLOAT_MAX : $pa;
    $pb = $pb === null ? PHP_FLOAT_MAX : $pb;
    return $filter_sort === 'price_asc' ? ($pa <=> $pb) : ($pb <=> $pa);
  });
}
// Pagination: 50 per page, only when the filtered list is over 100 rows.
$total_shown = count($shown);
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = 50;
$paged = $shown;
$total_pages = 1;
if ($total_shown > 100) {
  $total_pages = (int) ceil($total_shown / $per_page);
  if ($page > $total_pages) $page = $total_pages;
  $paged = array_slice($shown, ($page - 1) * $per_page, $per_page);
}
function service_extra_details($row) {
  // Structured attributes only. Free-text upstream description blobs
  // (desc/description/details/note/notes/info/text) are NOT rendered:
  // they carry upstream marketing and disclaimer wording that is not
  // this panel's copy, and must never appear as panel wording.
  $skip = ['service' => 1, 'id' => 1, 'name' => 1, 'service_name' => 1, 'category' => 1, 'rate' => 1, 'price' => 1, 'min' => 1, 'max' => 1, 'average_time' => 1, 'avg_time' => 1, 'delivery_time' => 1, 'start_time' => 1, 'estimated_time' => 1, '_manual' => 1, 'desc' => 1, 'description' => 1, 'details' => 1, 'note' => 1, 'notes' => 1, 'info' => 1, 'text' => 1];
  $out = [];
  if (!is_array($row)) return $out;
  foreach ($row as $k => $v) {
    if (isset($skip[$k])) continue;
    if (is_array($v) || is_object($v)) continue;
    $v = trim((string) $v);
    if ($v === '') continue;
    $out[] = $k . ': ' . $v;
  }
  return $out;
}
?>
<nav class="platform-chips" aria-label="Platforms">
  <a class="chip<?php echo $filter_q === '' ? ' active' : ''; ?>" href="services.php">All</a>
  <a class="chip chip-instagram<?php echo $filter_q === 'Instagram' ? ' active' : ''; ?>" href="services.php?q=Instagram">Instagram</a>
  <a class="chip chip-tiktok<?php echo $filter_q === 'TikTok' ? ' active' : ''; ?>" href="services.php?q=TikTok">TikTok</a>
  <a class="chip chip-youtube<?php echo $filter_q === 'YouTube' ? ' active' : ''; ?>" href="services.php?q=YouTube">YouTube</a>
  <a class="chip chip-facebook<?php echo $filter_q === 'Facebook' ? ' active' : ''; ?>" href="services.php?q=Facebook">Facebook</a>
  <a class="chip chip-telegram<?php echo $filter_q === 'Telegram' ? ' active' : ''; ?>" href="services.php?q=Telegram">Telegram</a>
  <a class="chip chip-x<?php echo $filter_q === 'X' ? ' active' : ''; ?>" href="services.php?q=X">X</a>
</nav>
<form method="get" action="services.php" class="filter-form" id="service_filter_form">
  <label>Category <select name="category"><option value="">All categories</option><?php foreach ($cats as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"<?php echo $c === $filter_cat ? ' selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select></label>
  <label>Search <input type="text" id="filter_q" name="q" value="<?php echo htmlspecialchars($filter_q); ?>" placeholder="Search services — try &quot;followers&quot; or &quot;views&quot;"></label>
  <label>Sort <select name="sort"><option value="">Catalogue order</option><option value="price_asc"<?php echo $filter_sort === 'price_asc' ? ' selected' : ''; ?>>Price: low to high</option><option value="price_desc"<?php echo $filter_sort === 'price_desc' ? ' selected' : ''; ?>>Price: high to low</option><option value="name_asc"<?php echo $filter_sort === 'name_asc' ? ' selected' : ''; ?>>Name: A to Z</option></select></label>
  <button class="btn btn-blue" type="submit">Find Services</button>
</form>
<p class="small">Prices shown are per 1,000 units — the price you see is the price you pay.</p>
<?php if ($error !== null): ?>
  <p class="notice"><?php echo htmlspecialchars($error); ?></p>
  <?php if ($error_kind === 'not_connected'): ?><p>No prices are shown until they are real.</p><?php endif; ?>
<?php elseif (empty($shown)): ?>
  <p class="notice">No services match your search right now. Try a different word — or clear your filters to see everything.</p>
  <p><a class="btn btn-outline" href="services.php">Clear Filters</a></p>
<?php elseif (empty($services)): ?>
  <p class="notice">No services are available right now. Please try again later.</p>
<?php else: ?>
  <?php if ($total_shown > 100): ?><p class="small">Showing <?php echo (int) (($page - 1) * $per_page + 1); ?>–<?php echo (int) min($total_shown, $page * $per_page); ?> of <?php echo (int) $total_shown; ?> services.</p><?php endif; ?>
  <div class="service-cards">
    <?php foreach ($paged as $row): $raw_rate = $row['rate'] ?? $row['price'] ?? null; $retail = retail_price($raw_rate, category_markup_percent($config, (string) ($row['category'] ?? ''))); $row_id = (string) ($row['service'] ?? $row['id'] ?? ''); $dt = service_delivery_time($row); $extras = service_extra_details($row); ?>
    <div class="service-card">
      <h3><?php echo htmlspecialchars((string) ($row['name'] ?? $row['service'] ?? '—')); ?></h3>
      <p class="service-price"><?php echo $retail === null ? '—' : htmlspecialchars(number_format($retail, 2) . ' ' . $config['currency']); ?> <span class="small">/ 1,000</span></p>
      <div class="service-meta">
        <span class="tag"><?php echo htmlspecialchars((string) ($row['category'] ?? '—')); ?></span>
        <span>Min <?php echo htmlspecialchars(isset($row['min']) ? (string) $row['min'] : '—'); ?></span>
        <span>Max <?php echo htmlspecialchars(isset($row['max']) ? (string) $row['max'] : '—'); ?></span>
        <span>Delivery: <?php echo htmlspecialchars($dt !== '' ? $dt : '—'); ?></span>
      </div>
      <details>
        <summary>Details</summary>
        <?php if (empty($extras)): ?><p class="small">No extra details supplied for this service.</p>
        <?php else: ?><?php foreach ($extras as $e): ?><p class="small"><?php echo htmlspecialchars($e); ?></p><?php endforeach; ?><?php endif; ?>
      </details>
      <?php if ($row_id !== ''): ?><p><a class="btn btn-blue" href="new-order.php?service=<?php echo urlencode($row_id); ?>">Order This Service</a></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap service-table">
  <table>
    <thead>
      <tr><th>Service</th><th>Category</th><th>Price (<?php echo htmlspecialchars($config['currency']); ?>) / 1,000</th><th>Min</th><th>Max</th><th>Delivery Time</th><th>Details</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($paged as $row): $raw_rate = $row['rate'] ?? $row['price'] ?? null; $retail = retail_price($raw_rate, category_markup_percent($config, (string) ($row['category'] ?? ''))); $row_id = (string) ($row['service'] ?? $row['id'] ?? ''); $dt = service_delivery_time($row); $extras = service_extra_details($row); ?>
      <tr>
        <td><?php echo htmlspecialchars((string) ($row['name'] ?? $row['service'] ?? '—')); ?></td>
        <td><?php echo htmlspecialchars((string) ($row['category'] ?? '—')); ?></td>
        <td><?php echo $retail === null ? '—' : htmlspecialchars(number_format($retail, 2)); ?></td>
        <td><?php echo htmlspecialchars(isset($row['min']) ? (string) $row['min'] : '—'); ?></td>
        <td><?php echo htmlspecialchars(isset($row['max']) ? (string) $row['max'] : '—'); ?></td>
        <td><?php echo htmlspecialchars($dt !== '' ? $dt : '—'); ?></td>
        <td><?php if (empty($extras)): ?>No extra details supplied for this service.<?php else: ?><?php foreach ($extras as $e): ?><?php echo htmlspecialchars($e); ?><br><?php endforeach; ?><?php endif; ?></td>
        <td><?php if ($row_id !== ''): ?><a class="btn btn-blue" href="new-order.php?service=<?php echo urlencode($row_id); ?>">Order This Service</a><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($total_pages > 1): ?>
  <p>
    <?php if ($page > 1): ?><a class="btn btn-outline" href="services.php?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>">← Previous</a><?php endif; ?>
    <span class="small">Page <?php echo (int) $page; ?> of <?php echo (int) $total_pages; ?></span>
    <?php if ($page < $total_pages): ?><a class="btn btn-outline" href="services.php?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>">Next →</a><?php endif; ?>
  </p>
  <?php endif; ?>
  <p class="notice">Prices shown are final — what you see is what you pay. If a detail is missing, it shows as a dash, never a guess.</p>
<?php endif; ?>

<h2>Delivery Time — What To Expect</h2>
<p>Delivery time is shown for each service above, wherever it is available. Where you see a dash, no delivery time is published for that service — and we will never guess one. Delivery speed varies by service and order size, and every order is sent for processing the moment you submit it.</p>
<p><a class="btn btn-blue" href="new-order.php">Place an Order</a>
<a class="btn btn-blue" href="<?php echo htmlspecialchars($config['other_services_url'] ?? $config['main_website_url']); ?>"><?php echo htmlspecialchars($config['other_services_label'] ?? 'Go to Other Services'); ?></a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>

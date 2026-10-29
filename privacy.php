<?php
/**
 * SMM APEX PLUS — Privacy Policy.
 * Describes only what this panel actually does: accounts, orders,
 * fund requests and support tickets. No company registration, legal
 * address or compliance certification is claimed here, because none
 * has been supplied for publication.
 */
require __DIR__ . '/includes/settings.php';
$config = get_effective_config();
$page_title = 'Privacy Policy';
$meta_description = 'SMM APEX PLUS Privacy Policy — what account, order, payment and support information we collect, how we use it, and how to contact us.';
$active = '';
require __DIR__ . '/includes/header.php';
$wa = htmlspecialchars((string) ($config['support_whatsapp'] ?? ''));
$em = htmlspecialchars((string) ($config['support_email'] ?? ''));
?>
<h1>Privacy Policy</h1>
<p class="small">Last updated: 29 October 2026</p>
<p>SMM APEX PLUS is an online social media services panel. This policy explains what information we collect when you use the panel, how we use it, and how you can reach us about it. It applies to this website only.</p>

<h2>Information we collect</h2>
<ul>
  <li><strong>Account information</strong> — the details you provide when you create an account and sign in, such as your username and contact details. Your password is stored only as a one-way hash, never in readable form.</li>
  <li><strong>Order information</strong> — the services you order, the links and quantities you submit, order statuses, and the charges deducted from your balance.</li>
  <li><strong>Payment information</strong> — the payment method you select and the transaction ID you submit on the Add Funds page. You pay through your own EasyPaisa, JazzCash or UPaisa app; we do not collect your payment-app password or PIN.</li>
  <li><strong>Support information</strong> — the messages you send through support tickets, so we can answer them and follow up on your orders and payments.</li>
  <li><strong>Browser preferences</strong> — your theme choice is stored in your own browser (local storage) so the site looks the same when you return. It is not sent to us as personal data.</li>
</ul>

<h2>How we use your information</h2>
<p>We use the information above only to run the panel: to create and protect your account, to process and track your orders, to review and approve your fund requests, to answer your support tickets, and to keep the service working. We do not sell your personal information, and we do not use it for advertising to third parties.</p>

<h2>Third parties</h2>
<p>Orders are delivered through our service delivery partners, and payments are made through the payment apps named above. Those services have their own policies for the information you give them directly. We do not name any upstream provider on customer-facing pages, and this policy does not change that.</p>

<h2>Cookies</h2>
<p>The panel uses a session cookie to keep you signed in while you use it, and to protect forms against forged submissions. The session cookie is required for sign-in and ordering. No advertising cookies are set by this panel.</p>

<h2>Data security and retention</h2>
<p>Access to account and order data is limited to operating the panel and to support requests you make. Your account, order and fund-request records are kept while your account is active so your history and balances remain available to you. If you want your account reviewed or closed, contact support using the details below.</p>

<h2>Your choices</h2>
<p>You can update your account details by contacting support, and you can choose not to place an order or submit a fund request at any time. Because orders are processed once submitted, an order that is already in progress cannot be undone from your side — if an order cannot be completed, it stays Pending and you can ask for a refund through a support ticket.</p>

<h2>Contact us</h2>
<p>For any question about this policy, or about your own data on this panel, contact us on WhatsApp <?php echo $wa; ?> or by email <?php echo $em; ?>, and include your username so we can find your account.</p>
<p><a href="terms.php">Terms and Conditions</a> · <a href="index.php">Back to Home</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>

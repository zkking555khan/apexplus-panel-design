<?php
/**
 * SMM APEX PLUS — Terms and Conditions.
 * Describes only how this panel actually works: accounts, orders,
 * payments and refunds as implemented. No company registration,
 * legal address or compliance certification is claimed here,
 * because none has been supplied for publication.
 */
require __DIR__ . '/includes/settings.php';
$config = get_effective_config();
$page_title = 'Terms and Conditions';
$meta_description = 'SMM APEX PLUS Terms and Conditions — how accounts, orders, payments in PKR, and refunds work on this panel.';
$active = '';
require __DIR__ . '/includes/header.php';
$wa = htmlspecialchars((string) ($config['support_whatsapp'] ?? ''));
$em = htmlspecialchars((string) ($config['support_email'] ?? ''));
?>
<h1>Terms and Conditions</h1>
<p class="small">Last updated: 29 October 2026</p>
<p>By creating an account or placing an order on SMM APEX PLUS, you agree to these terms. They describe how this panel works. If you do not agree, please do not use the panel.</p>

<h2>1. The service</h2>
<p>SMM APEX PLUS is an online panel where you can order social media services, add funds in PKR, and track your orders from your dashboard. Every service shows its real price, minimum and maximum quantity, and delivery time where one is available. Where a delivery time is shown as a dash, no delivery time is published for that service, and none is promised.</p>

<h2>2. Your account</h2>
<p>You are responsible for keeping your sign-in details safe and for everything done from your account. If you believe someone else has used your account, contact support as soon as possible. We may suspend an account that is misused or that breaks these terms.</p>

<h2>3. Orders</h2>
<p>When you place an order, the exact charge shown is deducted from your balance. You must submit a correct link and a quantity within the service's minimum and maximum. Orders are sent for processing when submitted, and you can track each one with its Tracking ID on the My Orders page. If an order cannot be completed, it stays Pending — it is not silently marked as completed.</p>

<h2>4. Payments</h2>
<p>Payments are made in PKR through EasyPaisa, JazzCash or UPaisa. To add funds, send your payment to a number shown on the Add Funds page, then submit the transaction ID exactly as shown by your payment app. Your balance is updated only after support approves the payment. Submitting a transaction ID that does not match a real payment will not be approved, and a transaction ID that has already been credited will not be credited a second time.</p>

<h2>5. Prices</h2>
<p>Prices shown are final — what you see is what you pay for that order. Prices may change over time as the live catalogue changes; the price that applies to your order is the one shown when you place it.</p>

<h2>6. Refunds</h2>
<p>If an order cannot be completed, it stays Pending. Open a support ticket with your order number and ask for a refund, and approved refunds are credited exactly once to your balance. Payments that have already been approved and spent on completed orders are not refunded through this process.</p>

<h2>7. Fair use</h2>
<p>Do not use the panel to access another customer's account, to submit false payment claims, or to interfere with the panel's operation. We may refuse an order or suspend an account for misuse, and we will tell you through your account or support ticket when we do.</p>

<h2>8. Availability</h2>
<p>We aim to keep the panel available, but maintenance, catalogue changes, or problems outside our control may interrupt it from time to time. Your balance and order history are not changed by an interruption.</p>

<h2>9. Changes to these terms</h2>
<p>We may update these terms when the panel changes. The version on this page is the current one, and continued use of the panel after an update means you accept the updated terms.</p>

<h2>10. Contact us</h2>
<p>For questions about these terms, an order, or a payment, contact us on WhatsApp <?php echo $wa; ?> or by email <?php echo $em; ?>, and include your order number or Tracking ID where relevant.</p>
<p><a href="privacy.php">Privacy Policy</a> · <a href="index.php">Back to Home</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>

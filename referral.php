<?php
/**
 * SMM APEX PLUS — Referral (design preview).
 *
 * Design-only page: the referral layout is shown here. Referral
 * link/code generation, commission calculation and referral
 * history from the database are backend work for the panel owner,
 * so this page shows no invented referral code, earnings or
 * history. The commission rate is set from the Admin Panel.
 */
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/settings.php';
require_login();
$config = get_effective_config();
$page_title = 'Referral';
$meta_description = 'Invite other members to SMM APEX PLUS and earn referral commission on their successful deposits.';
$active = 'referral';
require __DIR__ . '/includes/header.php';
?>
<h1>Referral</h1>
<p>Invite other members to SMM APEX PLUS and earn referral commission on their successful deposits.</p>
<p class="notice">Referral is opening soon — this page is a design preview. Your referral link and earnings will appear here after the backend is set up by the panel owner.</p>
<div class="card">
  <h2>Your Referral Link</h2>
  <p>Your referral link will appear here after backend setup.</p>
  <p class="small">Referral commission is set from the Admin Panel and can be changed there whenever needed.</p>
</div>
<div class="grid">
  <div class="card"><h3>Total Referrals</h3><p class="stat-value">—</p><p class="small">No referrals yet.</p></div>
  <div class="card"><h3>Referral Earnings</h3><p class="stat-value">—</p><p class="small">No referral earnings yet.</p></div>
</div>
<h2>Referral History</h2>
<p>No referral history yet — referrals and their commissions will appear here.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>

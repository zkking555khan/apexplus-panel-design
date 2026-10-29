</main>
<footer class="site-footer">
  <div class="wrap footer-inner">
    <div>
      <strong>SMM APEX PLUS</strong><br>
      Premium social media services, delivered by SMM APEX PLUS.
    </div>
    <div>
      Questions? WhatsApp <?php echo htmlspecialchars($config['support_whatsapp']); ?> · Email <?php echo htmlspecialchars($config['support_email']); ?> — real support, real people.
    </div>
    <div>
      <?php $fu = trim((string) ($config['other_services_url'] ?? $config['main_website_url'] ?? '')); $fl = trim((string) ($config['other_services_label'] ?? 'Go to Other Services')); $fs = trim((string) ($config['other_services_subtitle'] ?? '')); if ($fu !== ''): ?><span class="other-compact"><a href="<?php echo htmlspecialchars($fu); ?>"><strong><?php echo htmlspecialchars($fl === '' ? 'Go to Other Services' : $fl); ?></strong><?php if ($fs !== ''): ?><span><?php echo htmlspecialchars($fs); ?></span><?php endif; ?></a></span><?php endif; ?>
    </div>
  </div>
  <p class="wrap small">Prices shown are final — what you see is what you pay.<?php $pay_blank = false; foreach (['easypaisa', 'jazzcash', 'upaisa'] as $pm) { if (trim((string) ($config['payments'][$pm] ?? '')) === '') { $pay_blank = true; break; } } if ($pay_blank): ?> Some payment details are still being set by the team.<?php endif; ?></p>
  <p class="wrap small"><a href="privacy.php">Privacy Policy</a> · <a href="terms.php">Terms and Conditions</a></p>
</footer>
<script src="assets/js/main.js"></script>
<script src="assets/js/main-v2.js?v=20261029b"></script>
</body>
</html>

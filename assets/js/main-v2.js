// SMM APEX PLUS v2 — small progressive enhancement only.
// No accounts, payments or API calls are made from this file.
// Every feature below also works without JavaScript: filters submit
// as a form, details expand natively, and the charge bar's values are
// rendered server-side. JS only makes the same page smoother.
document.addEventListener('DOMContentLoaded', function () {
  // NOTE: the theme toggle handler lives INLINE in header.php, not
  // here — an external JS file can stay cached on a phone for days
  // and a second handler here would toggle twice per tap (no change).
  // This file keeps only the non-critical enhancements below.
  // Reveal entrances: content is visible without JS; JS only staggers it.
  var revealables = document.querySelectorAll('.reveal');
  revealables.forEach(function (el) {
    el.style.animationDelay = el.style.getPropertyValue('--reveal-delay') || '0ms';
  });
  // Hero accent sweep: one pass, then hold.
  var accent = document.querySelector('.hero-accent');
  if (accent) {
    setTimeout(function () { accent.classList.add('swept'); }, 100);
  }

  // Copy-number buttons (Add Funds): swap label to "Copied!" briefly.
  document.querySelectorAll('[data-copy-number]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var num = btn.getAttribute('data-copy-number');
      var done = function () {
        var old = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = old; }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(num).then(done, done);
      } else {
        var tmp = document.createElement('textarea');
        tmp.value = num;
        document.body.appendChild(tmp);
        tmp.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(tmp);
        done();
      }
    });
  });

  // Password visibility toggles (login / signup / admin login).
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-toggle-password'));
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Hide';
      } else {
        input.type = 'password';
        btn.textContent = 'Show';
      }
    });
  });

  // New Order: platform -> category -> service cascade, live in the page.
  // Search text AND the category dropdown both filter the service list;
  // the server-side GET filter is the no-JS fallback for the same flow.
  var svcSearch = document.getElementById('service_search');
  var svcSelect = document.getElementById('service_id');
  var orderCat = document.getElementById('order_category');
  function applyOrderFilters() {
    if (!svcSelect) return;
    var q = svcSearch ? svcSearch.value.toLowerCase() : '';
    var cat = orderCat ? orderCat.value : '';
    Array.prototype.forEach.call(svcSelect.options, function (opt) {
      if (opt.value === '') return;
      var okText = q === '' || opt.text.toLowerCase().indexOf(q) !== -1;
      var okCat = cat === '' || opt.getAttribute('data-category') === cat;
      opt.style.display = (okText && okCat) ? '' : 'none';
    });
    if (svcSelect.value) {
      var cur = svcSelect.options[svcSelect.selectedIndex];
      if (cur && cur.style.display === 'none') svcSelect.value = '';
    }
  }
  if (svcSearch && svcSelect) svcSearch.addEventListener('input', applyOrderFilters);
  if (orderCat && svcSelect) orderCat.addEventListener('change', applyOrderFilters);

  // New Order: quantity steppers + quick pills + sticky charge bar.
  var qty = document.getElementById('quantity');
  var chargeBar = document.getElementById('charge_bar');
  function fmt(n) { return Number(n).toFixed(2); }
  function recalc() {
    if (!qty || !chargeBar) return;
    var rate = parseFloat(chargeBar.getAttribute('data-rate') || '0');
    var bal = parseFloat(chargeBar.getAttribute('data-balance') || '0');
    var q = parseInt(qty.value, 10);
    if (isNaN(q) || q < 0) q = 0;
    var total = rate / 1000 * q;
    var qtyEl = document.getElementById('cb_qty');
    var totalEl = document.getElementById('cb_total');
    var balEl = document.getElementById('cb_balance');
    if (qtyEl) qtyEl.textContent = q;
    if (totalEl) totalEl.textContent = fmt(total);
    if (balEl) balEl.textContent = fmt(bal);
  }
  function setQtyFromSelect() {
    if (!qty || !svcSelect) return;
    var opt = svcSelect.options[svcSelect.selectedIndex];
    if (!opt || !opt.value) return;
    var min = parseInt(opt.getAttribute('data-min') || '1', 10);
    var max = parseInt(opt.getAttribute('data-max') || '1000000', 10);
    var rate = parseFloat(opt.getAttribute('data-rate') || '0');
    if (chargeBar) {
      chargeBar.setAttribute('data-rate', rate);
      chargeBar.setAttribute('data-min', min);
      chargeBar.setAttribute('data-max', max);
    }
    var cur = parseInt(qty.value, 10);
    if (isNaN(cur) || cur < min) qty.value = min;
    if (cur > max) qty.value = max;
    qty.min = min;
    qty.max = max;
    var priceEl = document.getElementById('cb_price');
    if (priceEl) priceEl.textContent = fmt(rate);
    recalc();
  }
  if (svcSelect) {
    svcSelect.addEventListener('change', setQtyFromSelect);
    if (svcSelect.value) setQtyFromSelect();
  }
  if (qty) qty.addEventListener('input', recalc);
  document.querySelectorAll('[data-qty-step]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!qty) return;
      var step = parseInt(btn.getAttribute('data-qty-step'), 10);
      var cur = parseInt(qty.value, 10);
      if (isNaN(cur)) cur = 0;
      var next = cur + step;
      var min = parseInt(qty.min || '1', 10);
      var max = parseInt(qty.max || '1000000', 10);
      if (next < min) next = min;
      if (next > max) next = max;
      qty.value = next;
      recalc();
    });
  });
  document.querySelectorAll('[data-qty-pill]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!qty) return;
      var kind = btn.getAttribute('data-qty-pill');
      var min = parseInt(qty.min || '1', 10);
      var max = parseInt(qty.max || '1000000', 10);
      var val = kind === 'min' ? min : (kind === 'max' ? max : parseInt(kind, 10));
      if (val < min) val = min;
      if (val > max) val = max;
      qty.value = val;
      recalc();
    });
  });
  recalc();

  // Services: chip filter — sets the search box and submits the form.
  document.querySelectorAll('[data-service-chip]').forEach(function (chip) {
    chip.addEventListener('click', function (e) {
      e.preventDefault();
      var form = document.getElementById('service_filter_form');
      var input = document.getElementById('filter_q');
      if (form && input) {
        input.value = chip.getAttribute('data-service-chip');
        form.submit();
      } else {
        window.location.href = chip.getAttribute('href');
      }
    });
  });

  // Admin tickets: canned-reply insert (insert only; admin still sends).
  document.querySelectorAll('[data-canned-reply]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var area = document.getElementById('message');
      if (!area) return;
      var text = btn.getAttribute('data-canned-reply');
      area.value = area.value ? area.value + '\n' + text : text;
      area.focus();
    });
  });
});

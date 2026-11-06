(function () {
  'use strict';

  var root = document.querySelector('[data-cfg]');
  if (!root) return;

  var side = 'exterior';
  var fin = { exterior: '', interior: '' };
  var bar = root.querySelector('[data-cfg-bar]');
  var panel = root.querySelector('[data-cfg-details-panel]');
  var toggle = root.querySelector('[data-cfg-toggle]');
  var widthEl = root.querySelector('[data-cfg-width]');
  var heightEl = root.querySelector('[data-cfg-height]');

  function all(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
  function pressed(btn) { return btn.getAttribute('aria-pressed') === 'true'; }
  function setPressed(btn, on) { btn.setAttribute('aria-pressed', on ? 'true' : 'false'); }
  function label(group, value) {
    if (!value) return '';
    var b = root.querySelector('.cfg-opt[data-group="' + group + '"][data-value="' + value + '"]');
    return b ? b.getAttribute('data-label') : '';
  }
  function single(group) {
    var b = root.querySelector('.cfg-opt[data-group="' + group + '"][aria-pressed="true"]');
    return b ? b.getAttribute('data-label') : '';
  }

  function renderFinish() {
    all('.cfg-opt[data-group="finish"]').forEach(function (b) {
      setPressed(b, fin[side] === b.getAttribute('data-value'));
    });
    all('[data-cfg-side]').forEach(function (b) {
      setPressed(b, b.getAttribute('data-cfg-side') === side);
    });
  }

  function collect() {
    var perf = all('.cfg-opt[data-group="perf"][aria-pressed="true"]').map(function (b) { return b.getAttribute('data-label'); });
    var w = widthEl ? widthEl.value : '';
    var h = heightEl ? heightEl.value : '';
    return {
      perf: perf.join(', '),
      dims: (w || h) ? (w || '—') + ' × ' + (h || '—') + ' mm' : '',
      thickness: single('thickness'),
      frame: single('frame'),
      config: single('config'),
      finext: label('finish', fin.exterior),
      finint: label('finish', fin.interior)
    };
  }

  var LINES = [
    ['perf', 'Performanțe', '#step-1'], ['dims', 'Dimensiuni', '#step-2'], ['thickness', 'Grosime', '#step-3'],
    ['frame', 'Cadru', '#step-4'], ['config', 'Configurație', '#step-5'],
    ['finext', 'Finisaj exterior', '#step-6'], ['finint', 'Finisaj interior', '#step-6']
  ];

  function sync() {
    var s = collect();
    var recap = [];
    var details = [];
    LINES.forEach(function (l) {
      if (s[l[0]]) { recap.push(s[l[0]]); details.push(l[1] + ': ' + s[l[0]]); }
    });
    var hasAny = recap.length > 0;
    var done = [!!s.perf, !!(widthEl && widthEl.value && heightEl && heightEl.value), !!s.thickness, !!s.frame, !!s.config, !!(s.finext || s.finint)]
      .filter(Boolean).length;
    var recapText = recap.join(' · ');

    if (bar) bar.hidden = !hasAny;
    if (!hasAny) closeDetails();

    all('[data-cfg-row]').forEach(function (row) {
      var v = s[row.getAttribute('data-cfg-row')];
      row.classList.toggle('is-on', !!v);
      setText(row.querySelector('[data-v]'), v || 'Neselectat');
      setText(row.querySelector('[data-a]'), v ? 'Editează' : 'Alege');
    });
    var steps = root.querySelector('[data-cfg-steps]');
    if (steps) setText(steps, done + ' din 6 pași');
    var prog = root.querySelector('[data-cfg-progress]');
    if (prog) prog.style.width = Math.round(done / 6 * 100) + '%';
    var barRecap = root.querySelector('[data-cfg-bar-recap]');
    if (barRecap) setText(barRecap, recapText);

    var fe = root.querySelector('[data-cfg-fin-ext]');
    var fi = root.querySelector('[data-cfg-fin-int]');
    if (fe) setText(fe, s.finext || '—');
    if (fi) setText(fi, s.finint || '—');

    // Fields inside the AJAX-updated form partial: look them up each time
    var formRecap = root.querySelector('[data-cfg-form-recap]');
    if (formRecap) {
      formRecap.hidden = !hasAny;
      var t = formRecap.querySelector('[data-cfg-form-recap-text]');
      if (t) setText(t, recapText);
    }
    all('[data-cfg-f-dims]').forEach(function (i) { i.value = s.dims; });
    all('[data-cfg-f-config]').forEach(function (i) { i.value = s.config; });
    all('[data-cfg-f-details]').forEach(function (i) {
      i.value = hasAny ? details.join('\n') : '';
    });
  }

  function setText(el, v) { if (el.textContent !== v) el.textContent = v; }

  function closeDetails() {
    if (!panel || !toggle) return;
    panel.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  }

  // Option selection
  root.addEventListener('click', function (e) {
    var t = e.target;
    if (!t.closest) return;

    var opt = t.closest('.cfg-opt');
    if (opt && root.contains(opt)) {
      var group = opt.getAttribute('data-group');
      var val = opt.getAttribute('data-value');
      var on = pressed(opt);
      if (group === 'perf') {
        setPressed(opt, !on);
      } else if (group === 'finish') {
        fin[side] = on ? '' : val;
        renderFinish();
      } else {
        all('.cfg-opt[data-group="' + group + '"]').forEach(function (b) { setPressed(b, false); });
        setPressed(opt, !on);
      }
      sync();
      return;
    }

    var sideBtn = t.closest('[data-cfg-side]');
    if (sideBtn) { side = sideBtn.getAttribute('data-cfg-side'); renderFinish(); return; }

    if (t.closest('[data-cfg-toggle]')) {
      var open = panel.hidden;
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }
    if (t.closest('[data-cfg-close]')) { closeDetails(); return; }

    var reset = t.closest('[data-cfg-reset]');
    if (reset) {
      var box = reset.closest('[data-cfg-formbox]');
      var sent = box && box.querySelector('[data-cfg-sent]');
      var form = box && box.querySelector('[data-cfg-form]');
      if (sent) sent.hidden = true;
      if (form) form.hidden = false;
      sync();
      return;
    }

    // Tooltips: tap to open on touch
    var tipBtn = t.closest('.cfg-tip__btn');
    all('.cfg-tip.is-open').forEach(function (el) {
      if (!tipBtn || el !== tipBtn.parentNode) {
        el.classList.remove('is-open');
        el.querySelector('.cfg-tip__btn').setAttribute('aria-expanded', 'false');
      }
    });
    if (tipBtn) {
      var wrap = tipBtn.parentNode;
      var o = !wrap.classList.contains('is-open');
      wrap.classList.toggle('is-open', o);
      tipBtn.setAttribute('aria-expanded', o ? 'true' : 'false');
    }
  });

  document.addEventListener('click', function (e) {
    if (root.contains(e.target)) return;
    all('.cfg-tip.is-open').forEach(function (el) {
      el.classList.remove('is-open');
      el.querySelector('.cfg-tip__btn').setAttribute('aria-expanded', 'false');
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    all('.cfg-tip.is-open').forEach(function (el) {
      el.classList.remove('is-open');
      el.querySelector('.cfg-tip__btn').setAttribute('aria-expanded', 'false');
    });
    closeDetails();
  });

  // Dimensions: digits only
  [widthEl, heightEl].forEach(function (el) {
    if (!el) return;
    el.addEventListener('input', function () {
      var clean = el.value.replace(/[^0-9]/g, '');
      if (clean !== el.value) el.value = clean;
      sync();
    });
  });

  // The form partial is re-rendered after submit: re-fill its hidden fields
  // from the page state and close the details panel when the sent state shows.
  var box = root.querySelector('.cfg-contact__card');
  if (box && window.MutationObserver) {
    new MutationObserver(function () {
      sync();
      if (box.querySelector('[data-cfg-sent]')) closeDetails();
    }).observe(box, { childList: true, subtree: true });
  }

  // Safety net: make sure the latest state is in the form right before it is sent
  document.addEventListener('ajax:before-send', sync, true);
  root.addEventListener('submit', sync, true);

  renderFinish();
  sync();
})();

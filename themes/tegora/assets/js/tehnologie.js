// Tehnologie pages: construction explorer (tabs, highlighted cross-section, layer accordion, pager).
// The server renders every panel; this script shows one at a time and keeps ARIA state in sync.
(function () {
    'use strict';

    var root = document.querySelector('[data-tech-explorer]');
    if (!root) return;

    var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
    var panels = tabs.map(function (tab) { return document.getElementById(tab.getAttribute('aria-controls')); });
    var zones = Array.prototype.slice.call(root.querySelectorAll('.tech-zone'));
    var parts = Array.prototype.slice.call(root.querySelectorAll('.tech-part'));
    var legend = root.querySelector('[data-legend]');
    var detailName = root.querySelector('[data-detail-name]');
    var toDetail = root.querySelector('[data-to-detail]');
    var prevBtn = root.querySelector('[data-prev]');
    var nextBtn = root.querySelector('[data-next]');
    var prevName = root.querySelector('[data-prev-name]');
    var nextName = root.querySelector('[data-next-name]');
    var header = document.querySelector('.site-header');
    var reduce = window.matchMedia('(prefers-reduced-motion:reduce)');

    var mode = 0;   // index of the active tab
    var part = 0;   // 0 = none, 1..4 = open layer

    function smoothTo(el, onlyIfAbove) {
        if (!el || !el.scrollIntoView) return;
        if (onlyIfAbove && el.getBoundingClientRect().top > 0) return;
        el.scrollIntoView({ behavior: reduce.matches ? 'auto' : 'smooth', block: 'start' });
    }

    function lower(text) { return text.toLocaleLowerCase('ro'); }

    function render() {
        var active = tabs[mode];
        tabs.forEach(function (tab, i) {
            var on = i === mode;
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            tab.tabIndex = on ? 0 : -1;
            panels[i].hidden = !on;
        });

        zones.forEach(function (zone) {
            var on = part ? Number(zone.dataset.part) === part : zone.dataset.mode === active.dataset.mode;
            zone.classList.toggle('is-on', on);
        });

        parts.forEach(function (btn) {
            var open = Number(btn.dataset.part) === part;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.getElementById(btn.getAttribute('aria-controls')).hidden = !open;
        });

        var openDesc = part ? document.getElementById('part-' + part) : null;
        legend.textContent = openDesc ? openDesc.dataset.hl : active.dataset.highlight;
        detailName.textContent = lower(active.dataset.name);

        var prev = tabs[mode - 1];
        var next = tabs[mode + 1];
        prevBtn.hidden = !prev;
        nextBtn.hidden = !next;
        if (prev) prevName.textContent = prev.dataset.name;
        if (next) nextName.textContent = next.dataset.name;
    }

    // scroll: 'pick' scrolls back to the module only if its top is above the viewport,
    // 'go' always scrolls to it, null never scrolls (keyboard navigation).
    function setMode(index, scroll) {
        mode = index;
        part = 0;
        render();
        if (scroll === 'pick') smoothTo(root, true);
        else if (scroll === 'go') smoothTo(root, false);
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { setMode(i, 'pick'); });
        tab.addEventListener('keydown', function (e) {
            var target = -1;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') target = (i + 1) % tabs.length;
            else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') target = (i - 1 + tabs.length) % tabs.length;
            else if (e.key === 'Home') target = 0;
            else if (e.key === 'End') target = tabs.length - 1;
            if (target < 0) return;
            e.preventDefault();
            setMode(target, null);
            tabs[target].focus();
        });
    });

    parts.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var n = Number(btn.dataset.part);
            part = part === n ? 0 : n;
            render();
        });
    });

    toDetail.addEventListener('click', function () { smoothTo(document.getElementById('detaliu'), false); });
    prevBtn.addEventListener('click', function () { if (mode > 0) setMode(mode - 1, 'go'); });
    nextBtn.addEventListener('click', function () { if (mode < tabs.length - 1) setMode(mode + 1, 'go'); });

    // The tab bar sticks to the top; once the site header pins itself, sit just below it.
    var ticking = false;
    function placeTabs() {
        ticking = false;
        var y = window.scrollY || document.documentElement.scrollTop || 0;
        var top = y > 480 && header ? header.offsetHeight - 1 : 0;
        root.style.setProperty('--tech-tabs-top', top + 'px');
    }
    window.addEventListener('scroll', function () {
        if (!ticking) { ticking = true; window.requestAnimationFrame(placeTabs); }
    }, { passive: true });
    window.addEventListener('resize', placeTabs);

    root.classList.add('is-ready');
    render();
    placeTabs();
})();

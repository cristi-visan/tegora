// Header behaviour shared by every page: scroll-away header, dropdowns, mobile menu.
(function () {
    'use strict';

    var header = document.querySelector('.site-header');
    if (!header) return;

    var SCROLL_AWAY = 90;  // header scrolls off with the page up to here
    var PIN_AFTER = 480;   // past this, it comes back pinned
    var canPin = header.dataset.sticky !== 'off';
    var toggles = header.querySelectorAll('[data-dd-toggle]');
    var navToggle = header.querySelector('.nav-toggle');
    var nav = header.querySelector('.site-nav');
    var isMobile = function () { return window.matchMedia('(max-width:1180px)').matches; };

    function closeDropdowns(except) {
        toggles.forEach(function (btn) {
            if (btn === except) return;
            btn.setAttribute('aria-expanded', 'false');
            var panel = document.getElementById(btn.getAttribute('aria-controls'));
            if (panel) panel.hidden = true;
        });
    }

    function closeMobileNav() {
        if (!nav || !navToggle) return;
        nav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
    }

    function onScroll() {
        var y = window.scrollY || document.documentElement.scrollTop || 0;
        if (nav && nav.classList.contains('is-open')) return;

        header.classList.remove('is-hidden', 'is-pinned');
        if (y <= SCROLL_AWAY) {
            header.style.transform = y > 0 ? 'translateY(-' + Math.round(y) + 'px)' : '';
            return;
        }
        header.style.transform = '';
        closeDropdowns();
        header.classList.add(canPin && y > PIN_AFTER ? 'is-pinned' : 'is-hidden');
    }

    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var open = btn.getAttribute('aria-expanded') === 'true';
            closeDropdowns(btn);
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            var panel = document.getElementById(btn.getAttribute('aria-controls'));
            if (panel) panel.hidden = open;
        });
    });

    if (navToggle && nav) {
        navToggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                header.classList.remove('is-hidden');
                header.style.transform = '';
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (header.contains(e.target)) return;
        if (!isMobile()) closeDropdowns();
        closeMobileNav();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        closeDropdowns();
        closeMobileNav();
    });

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
})();

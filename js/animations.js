/**
 * Thread & Press Hub — motion layer (storefront).
 *
 * Scroll reveal: cards and section headings fade + rise into place as they
 * scroll into view, staggered when several arrive together.
 *
 * Safe by design:
 *   - Only elements that start BELOW the fold are hidden, so nothing the
 *     visitor can already see ever flickers.
 *   - The hidden state is a class this script adds; if the script never runs,
 *     everything is simply visible.
 *   - Elements that already animate (the Shop grid's CSS entrance, the home
 *     page's own .reveal) are left alone.
 *   - The reveal class is removed once the animation ends, so hover effects
 *     (card lift, image zoom) work exactly as before.
 *   - Skipped entirely for "reduce motion" users and on admin pages.
 *
 * Styles live in css/style.css under "Motion".
 * Page-to-page transitions are pure CSS (@view-transition) in the same place.
 */
(function () {
    'use strict';

    try {
        if (document.body.classList.contains('admin-page')) return;
        if (!('IntersectionObserver' in window)) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var TARGETS = [
            '.product-card', '.category-card', '.feature-item', '.home-surface',
            '.discount-card', '.pd-related-card', '.pd-rv-item', '.pd-rv-summary',
            '.custom-order-card', '.order-card', '.tracking-card', '.summary-card',
            '.section-heading', '.shop-sidebar'
        ].join(',');
        var STAGGER_MS = 70;
        var MAX_STAGGER_STEPS = 6;

        var viewportBottom = window.innerHeight;
        var candidates = Array.prototype.filter.call(document.querySelectorAll(TARGETS), function (el) {
            if (el.closest('.reveal')) return false;                        // home page's own reveal
            if (el.parentElement && el.parentElement.closest(TARGETS)) return false; // outermost target only
            if (getComputedStyle(el).animationName !== 'none') return false;  // already animated by CSS
            if (el.getClientRects().length === 0) return false;               // hidden (display:none)
            return el.getBoundingClientRect().top > viewportBottom;           // below the fold only
        });
        if (!candidates.length) return;

        candidates.forEach(function (el) { el.classList.add('tp-reveal-pending'); });

        function finish(e) {
            if (e.target !== this) return; // ignore animations bubbling up from children
            this.classList.remove('tp-reveal-in');
            this.style.animationDelay = '';
            this.removeEventListener('animationend', finish);
        }

        var io = new IntersectionObserver(function (entries) {
            var step = 0;
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                io.unobserve(el);
                el.style.animationDelay = Math.min(step, MAX_STAGGER_STEPS) * STAGGER_MS + 'ms';
                step++;
                el.addEventListener('animationend', finish);
                el.classList.remove('tp-reveal-pending');
                el.classList.add('tp-reveal-in');
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

        candidates.forEach(function (el) { io.observe(el); });
    } catch (e) {
        // Motion is decoration: on any error, make sure nothing stays hidden.
        var stuck = document.querySelectorAll('.tp-reveal-pending');
        for (var i = 0; i < stuck.length; i++) stuck[i].classList.remove('tp-reveal-pending');
    }
})();

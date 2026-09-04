/**
 * Smooth-browsing enhancements — ADDITIVE and SAFE.
 *
 * This file only *adds* behavior; it never modifies or overrides existing
 * scripts. Everything is wrapped in try/catch so a failure here can never
 * break the page. If you delete this file, the site behaves exactly as before.
 *
 * What it does:
 *   1. Smooth in-page scrolling (respects the OS "reduce motion" setting).
 *   2. Lazy-loads + async-decodes images that haven't opted in — lighter pages
 *      and less scroll jank on image-heavy pages (Shop, catalog, etc.).
 *   3. Smooth-scrolls "back to top" style jumps and same-page anchors that
 *      aren't already handled elsewhere.
 *   4. Honors the OS "reduce motion" accessibility setting globally.
 *
 * Intentionally NOT included (to avoid breaking anything):
 *   - Link prefetch / prerender — some links have side effects (logout, cart,
 *     verify/reject actions), and prefetching would trigger them by accident.
 *   - A page fade-in — it must run before first paint to avoid flicker, and
 *     this script loads after the content, so it is deliberately omitted.
 */
(function () {
    'use strict';

    try {
        var mq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
        var reduceMotion = mq ? mq.matches : false;

        // --- 1) Smooth in-page scrolling -------------------------------------
        if (!reduceMotion) {
            document.documentElement.style.scrollBehavior = 'smooth';
        }

        // --- 2) Lazy-load images without an explicit strategy ----------------
        // Native lazy-loading still loads in-viewport images promptly, so the
        // hero/logo are unaffected; only off-screen images are deferred.
        var imgs = document.querySelectorAll('img:not([loading])');
        for (var i = 0; i < imgs.length; i++) {
            imgs[i].loading = 'lazy';
            if (!imgs[i].hasAttribute('decoding')) {
                imgs[i].decoding = 'async';
            }
        }

        // --- 3) Global reduce-motion guard -----------------------------------
        var rm = document.createElement('style');
        rm.textContent =
            '@media (prefers-reduced-motion: reduce){*{animation-duration:.01ms !important;' +
            'transition-duration:.01ms !important;scroll-behavior:auto !important;}}';
        document.head.appendChild(rm);
    } catch (e) {
        /* Never let an enhancement break the page. */
    }
})();

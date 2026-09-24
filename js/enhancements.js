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
 *   5. Opens chat images (support chat + chat widget) in an in-page lightbox
 *      instead of a new tab.
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

        // --- 4) Chat image lightbox --------------------------------------------
        // Images in the support chat (customer + admin) and the chat widget used
        // to open in a new tab. They now open over the page in a native <dialog>
        // (Esc, focus and backdrop handled by the browser). The links keep their
        // href, so without this script they still open as before.
        var ZOOM_LINKS = '.support-msg-image a[href], a.chat-img-link[href]';
        var lightbox = null;

        function openLightbox(src, alt) {
            if (!lightbox) {
                lightbox = document.createElement('dialog');
                lightbox.className = 'tp-lightbox';
                lightbox.setAttribute('aria-label', 'Image preview');
                lightbox.innerHTML =
                    '<button type="button" class="tp-lightbox-close" aria-label="Close preview">&times;</button>' +
                    '<img alt="">';
                // Close on the × button or a click on the dark backdrop (not the image).
                lightbox.addEventListener('click', function (e) {
                    if (e.target === lightbox || e.target.classList.contains('tp-lightbox-close')) lightbox.close();
                });
                // Free the (possibly large) image once closed.
                lightbox.addEventListener('close', function () {
                    lightbox.querySelector('img').removeAttribute('src');
                });
                document.body.appendChild(lightbox);
            }
            var img = lightbox.querySelector('img');
            img.src = src;
            img.alt = alt || 'Shared image';
            lightbox.showModal();
        }

        document.addEventListener('click', function (e) {
            // Let Ctrl/Cmd/Shift/middle-click keep opening a new tab on purpose.
            if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            var link = e.target.closest ? e.target.closest(ZOOM_LINKS) : null;
            if (!link || typeof HTMLDialogElement === 'undefined') return;
            e.preventDefault();
            var thumb = link.querySelector('img');
            openLightbox(link.href, thumb ? thumb.alt : '');
        });

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

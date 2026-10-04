/**
 * App shell for phones and tablets (css/app-shell.css): bottom sheets, the
 * tab bar's cart badge, the header that hides while scrolling down, the
 * product page's sticky buy bar, and the installable app (manifest.json,
 * sw.js). Loaded on storefront pages only (includes/footer/footer.php).
 */
(function () {
    'use strict';

    var mobile = window.matchMedia('(max-width: 991.98px)');

    // ---------- Bottom sheets ----------
    // <div class="app-sheet" id="…" hidden> with .app-sheet-panel inside.
    // Open: [data-sheet-open="id"] or AppSheet.open(id). Close: backdrop,
    // [data-sheet-close], Escape, swipe down, or AppSheet.close(id).
    var openSheet = null;
    var lastFocus = null;

    function open(id) {
        var sheet = document.getElementById(id);
        if (!sheet || sheet === openSheet) return;
        if (openSheet) close();
        lastFocus = document.activeElement;
        sheet.hidden = false;
        document.documentElement.classList.add('app-sheet-open');
        // Next frame, so the slide-up transition runs from the hidden state.
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { sheet.classList.add('is-open'); });
        });
        openSheet = sheet;
        var panel = sheet.querySelector('.app-sheet-panel');
        if (panel) {
            panel.setAttribute('tabindex', '-1');
            panel.scrollTop = 0;
            panel.focus({ preventScroll: true });
        }
    }

    function close() {
        var sheet = openSheet;
        if (!sheet) return;
        openSheet = null;
        sheet.classList.remove('is-open');
        document.documentElement.classList.remove('app-sheet-open');
        var panel = sheet.querySelector('.app-sheet-panel');
        if (panel) panel.style.transform = '';
        var done = false;
        var finish = function () {
            if (done || sheet.classList.contains('is-open')) return;
            done = true;
            sheet.hidden = true;
        };
        if (panel) panel.addEventListener('transitionend', finish, { once: true });
        setTimeout(finish, 350); // reduced motion: no transitionend
        if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
    }

    window.AppSheet = { open: open, close: close };

    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-sheet-open]');
        if (opener) { e.preventDefault(); open(opener.getAttribute('data-sheet-open')); return; }
        if (e.target.closest('[data-sheet-close]')) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openSheet) close();
    });

    // Swipe the handle (or the top of the panel) down to dismiss.
    document.addEventListener('touchstart', function (e) {
        if (!openSheet) return;
        var panel = openSheet.querySelector('.app-sheet-panel');
        if (!panel || !panel.contains(e.target) || panel.scrollTop > 0) return;
        var startY = e.touches[0].clientY, dy = 0;
        function move(ev) {
            dy = Math.max(0, ev.touches[0].clientY - startY);
            if (dy > 0) panel.style.transform = 'translateY(' + dy + 'px)';
        }
        function end() {
            document.removeEventListener('touchmove', move);
            document.removeEventListener('touchend', end);
            if (dy > 90) { close(); } else { panel.style.transform = ''; }
        }
        document.addEventListener('touchmove', move, { passive: true });
        document.addEventListener('touchend', end);
    }, { passive: true });

    // ---------- Cart badge on the tab bar ----------
    // The cart lives in localStorage('cart') as [{quantity, …}]. Pages that
    // change it update #navCartCount; mirror that, and read storage directly
    // so every page shows the count (other tabs included).
    var badge = document.getElementById('appCartBadge');
    function cartCount() {
        try {
            var cart = JSON.parse(localStorage.getItem('cart')) || [];
            return cart.reduce(function (n, i) { return n + (parseInt(i.quantity, 10) || 0); }, 0);
        } catch (e) { return 0; }
    }
    var navCount = document.getElementById('navCartCount');
    var lastCount = null;
    // Restart the pop on every increase (remove, reflow, add). The class change
    // re-runs syncBadge via the observer below, but the count is unchanged by
    // then, so it never bumps twice.
    function bump(el) {
        if (!el) return;
        el.classList.remove('tp-bump');
        void el.offsetWidth;
        el.classList.add('tp-bump');
    }
    function syncBadge() {
        var n = cartCount();
        var label = n > 99 ? '99+' : String(n);
        if (badge) {
            badge.textContent = label;
            badge.hidden = n <= 0;
        }
        // Desktop navbar badge too, so it shows on every page (home, try-on,
        // design studio…), not only the pages that add to the cart. Only
        // write on change: the observer below would otherwise loop on it.
        if (navCount) {
            var display = n > 0 ? 'inline-block' : 'none';
            if (navCount.textContent !== label) navCount.textContent = label;
            if (navCount.style.display !== display) navCount.style.display = display;
        }
        // Not on page load (lastCount null), only when something was added.
        if (lastCount !== null && n > lastCount) {
            bump(badge);
            bump(navCount);
        }
        lastCount = n;
    }
    syncBadge();
    window.addEventListener('storage', function (e) { if (e.key === 'cart' || e.key === null) syncBadge(); });
    window.addEventListener('pageshow', syncBadge); // back/forward cache
    if (navCount) {
        new MutationObserver(syncBadge).observe(navCount, { attributes: true, childList: true, characterData: true, subtree: true });
    }

    // ---------- Header hides while scrolling down (phones/tablets) ----------
    var nav = document.querySelector('.tp-nav');
    if (nav) {
        var lastY = window.scrollY, queued = false;
        window.addEventListener('scroll', function () {
            if (queued) return;
            queued = true;
            requestAnimationFrame(function () {
                queued = false;
                var y = window.scrollY;
                if (!mobile.matches || y < 120 || document.querySelector('#searchOverlay.active')) {
                    nav.classList.remove('app-nav-hidden');
                } else if (y > lastY + 6) {
                    nav.classList.add('app-nav-hidden');
                } else if (y < lastY - 6) {
                    nav.classList.remove('app-nav-hidden');
                }
                lastY = y;
            });
        }, { passive: true });
    }

    // ---------- Product page: sticky Add to Cart / Buy Now ----------
    // Mirrors product.php's own buttons (clicks are forwarded, so stock,
    // colour and size checks stay in one place) and shows only while those
    // buttons are off-screen.
    var realAdd = document.getElementById('pdAddBtn');
    var realActions = document.querySelector('.pd-actions');
    if (realAdd && realActions && 'IntersectionObserver' in window) {
        var realBuy = document.getElementById('pdBuyBtn');
        var priceEl = document.querySelector('.pd-price');
        var bar = document.createElement('div');
        bar.className = 'app-buybar';
        bar.setAttribute('aria-hidden', 'true');

        var price = document.createElement('div');
        price.className = 'app-buybar-price';
        price.textContent = priceEl ? priceEl.textContent.trim() : '';
        bar.appendChild(price);

        var add = document.createElement('button');
        add.type = 'button';
        add.className = 'app-btn app-btn-primary';
        add.textContent = realAdd.textContent.trim();
        add.disabled = realAdd.disabled;
        add.tabIndex = -1;
        add.addEventListener('click', function () { realAdd.click(); });
        bar.appendChild(add);

        if (realBuy) {
            var buy = document.createElement('button');
            buy.type = 'button';
            buy.className = 'app-btn app-btn-accent';
            buy.textContent = realBuy.textContent.trim();
            buy.tabIndex = -1;
            buy.addEventListener('click', function () { realBuy.click(); });
            bar.appendChild(buy);
        }
        document.body.appendChild(bar);

        // product.php may relabel or disable its buttons (stock); follow it.
        new MutationObserver(function () {
            add.textContent = realAdd.textContent.trim();
            add.disabled = realAdd.disabled;
        }).observe(realAdd, { attributes: true, childList: true, characterData: true, subtree: true });

        new IntersectionObserver(function (entries) {
            var show = !entries[0].isIntersecting;
            bar.classList.toggle('is-visible', show);
            document.documentElement.classList.toggle('app-buybar-on', show && mobile.matches);
        }).observe(realActions);
    }

    // ---------- Installable app ----------
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        document.documentElement.classList.add('is-standalone');
    }
    // Service workers need HTTPS (localhost is exempt).
    if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js').catch(function (err) {
                console.warn('Service worker not registered:', err);
            });
        });
    }
    // Chrome/Edge/Android offer an install prompt; surface it in the Account sheet.
    var installBtn = document.getElementById('appInstallBtn');
    var deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        if (installBtn) installBtn.hidden = false;
    });
    if (installBtn) {
        installBtn.addEventListener('click', function () {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            deferredPrompt.userChoice.finally(function () {
                deferredPrompt = null;
                installBtn.hidden = true;
            });
        });
    }
    window.addEventListener('appinstalled', function () {
        if (installBtn) installBtn.hidden = true;
    });
})();

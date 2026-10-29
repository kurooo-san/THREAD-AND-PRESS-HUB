// Live updates without reloading the page. Loaded on every page by the footer;
// it does nothing unless the page opts in.
//
// The server keeps doing all the rendering: parts of a page that can change
// are wrapped in data-live="name", and refreshLiveRegions() re-fetches the
// current URL and swaps just those parts in.
//
//  - data-live-url="order-status.php?..." on a page: polls a small version
//    stamp and refreshes the live parts when the order changes (customer side).
//  - <form data-ajax>: submitted with fetch. The PHP handler sees
//    X-Requested-With: fetch and answers {ok, message} (ajaxFormReply()),
//    then the live parts are refreshed (admin side).
//  - data-live-poll on an admin page: re-fetches every few seconds so new
//    orders, payments, messages... show up on their own. A region the admin is
//    typing in or has a dropdown open in is left alone until they are done.
(function () {
    // Server HTML each region was last rendered from. Compared against the
    // fresh copy (not the live DOM, which scripts may have touched) so an
    // unchanged region is never swapped.
    const served = new WeakMap();
    document.querySelectorAll('[data-live]').forEach(function (el) { served.set(el, el.outerHTML); });

    // True while swapping el would throw away something the admin is doing.
    function inUse(el) {
        const a = document.activeElement;
        if (a && el.contains(a) && a.matches('input, select, textarea, [contenteditable]')) return true;
        if (el.querySelector('[aria-expanded="true"]')) return true;   // open dropdown / collapse
        const sel = window.getSelection && window.getSelection();
        if (sel && !sel.isCollapsed && el.contains(sel.anchorNode)) return true;   // copying text
        return Array.prototype.some.call(el.querySelectorAll('input, select, textarea'), function (f) {
            if (f.type === 'checkbox' || f.type === 'radio') return f.checked !== f.defaultChecked;
            if (f.tagName === 'SELECT') return Array.prototype.some.call(f.options, function (o) { return o.selected !== o.defaultSelected; });
            return f.value !== f.defaultValue;
        });
    }

    async function refreshLiveRegions(polling) {
        const r = await fetch(location.href, { cache: 'no-store', credentials: 'same-origin' });
        // A redirect means the session ended (login page); leave the page alone.
        if (!r.ok || r.redirected) throw new Error('page unavailable');
        const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
        // Same page title = same page (a filtered list may legitimately come
        // back with no rows at all, so the regions themselves can't tell us).
        if (doc.title !== document.title) throw new Error('not the same page');
        let changed = false;
        document.querySelectorAll('[data-live]').forEach(function (el) {
            const fresh = doc.querySelector('[data-live="' + el.dataset.live + '"]');
            const html = fresh ? fresh.outerHTML : null;
            if (html === served.get(el)) return;
            if (polling && (inUse(el) || document.querySelector('.modal.show'))) return;
            changed = true;
            // Gone from the fresh page = no longer belongs here, e.g. an order
            // whose new status no longer matches the list's status filter.
            if (!fresh) { el.remove(); return; }
            const node = document.importNode(fresh, true);
            served.set(node, html);
            el.replaceWith(node);
        });
        // Lets page scripts (charts) re-read data that lives inside a region.
        if (changed) document.dispatchEvent(new Event('live:updated'));
    }

    function toast(message, type) {
        if (typeof showToast !== 'function') return;
        // showToast() writes HTML; messages here are plain text.
        const safe = document.createElement('div');
        safe.textContent = message;
        showToast(safe.innerHTML, type);
    }

    // ---- Customer: poll for order changes -------------------------------
    const root = document.querySelector('[data-live-url]');
    if (root) {
        let version = null;   // first answer is the baseline, not a change
        let busy = false;
        const tick = async function () {
            if (busy || document.hidden) return;
            busy = true;
            try {
                const r = await fetch(root.dataset.liveUrl, { cache: 'no-store', credentials: 'same-origin' });
                if (!r.ok) return;
                const v = (await r.json()).v;
                if (version === null) { version = v; return; }
                if (!v || v === version) return;
                await refreshLiveRegions();
                version = v;
                const label = document.querySelector('[data-live-label]');
                toast('Order updated' + (label ? ': ' + label.textContent.trim() : ''), 'success');
            } catch (e) {
                // Offline or logged out: keep the page as is and try next tick.
            } finally {
                busy = false;
            }
        };
        tick();
        setInterval(tick, 8000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
    }

    // ---- Admin: lists that fill in on their own ---------------------------
    if (document.querySelector('[data-live-poll]')) {
        let polling = false;
        const poll = async function () {
            if (polling || document.hidden) return;
            polling = true;
            try { await refreshLiveRegions(true); }
            catch (e) { /* offline or logged out: try next tick */ }
            finally { polling = false; }
        };
        setInterval(poll, 8000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    }

    // ---- Admin: forms that save without a reload --------------------------
    // Delegated, so forms inside freshly swapped regions keep working.
    document.addEventListener('submit', async function (e) {
        const form = e.target;
        // defaultPrevented: an onsubmit="return confirm(...)" the admin said no to.
        if (e.defaultPrevented || !form.matches || !form.matches('form[data-ajax]')) return;
        e.preventDefault();
        const buttons = form.querySelectorAll('button, select');
        // Read the fields first: a disabled field is left out of FormData.
        const data = new FormData(form);
        // A clicked <button name value> is part of a normal submit too.
        if (e.submitter && e.submitter.name) data.append(e.submitter.name, e.submitter.value);
        buttons.forEach(function (b) { b.disabled = true; });
        try {
            const r = await fetch(form.getAttribute('action') || location.href, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'fetch' },
            });
            // Anything but JSON (a 403, or a redirect to the login page) means
            // the session or form token ran out.
            const res = r.ok && (r.headers.get('content-type') || '').includes('json')
                ? await r.json()
                : { ok: false, message: 'Your session expired. Refresh the page and try again.' };
            toast(res.message || (res.ok ? 'Saved.' : 'Could not save.'), res.ok ? 'success' : 'error');
            if (!res.ok) { form.reset(); return; }   // put a changed dropdown back to what is really saved
            try {
                await refreshLiveRegions();
            } catch (refreshErr) {
                toast((res.message || 'Saved.') + ' Refresh the page to see the latest.', 'success');
            }
        } catch (err) {
            toast('Could not reach the server. Check your connection and try again.', 'error');
            form.reset();
        } finally {
            buttons.forEach(function (b) { b.disabled = false; });
        }
    });
})();

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
(function () {
    async function refreshLiveRegions() {
        const html = await (await fetch(location.href, { cache: 'no-store', credentials: 'same-origin' })).text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        document.querySelectorAll('[data-live]').forEach(function (el) {
            const fresh = doc.querySelector('[data-live="' + el.dataset.live + '"]');
            if (fresh) el.replaceWith(document.importNode(fresh, true));
        });
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
            if (res.ok) await refreshLiveRegions();
            else form.reset();   // put a changed dropdown back to what is really saved
        } catch (err) {
            toast('Could not reach the server. Check your connection and try again.', 'error');
            form.reset();
        } finally {
            buttons.forEach(function (b) { b.disabled = false; });
        }
    });
})();

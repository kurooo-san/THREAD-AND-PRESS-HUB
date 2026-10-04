/**
 * Admin AI assistant (loaded by includes/admin-sidebar.php on every admin page).
 *  - AI Insights panel: read-only questions about the store's live data.
 *  - Support Chat "Suggest reply": drafts a reply into the reply box; the admin
 *    reviews and sends it with the normal Send button.
 * Both call admin/ai-assistant-ajax.php.
 */
(function () {
    'use strict';

    var panel = document.getElementById('adAiPanel');
    if (!panel) return;

    var endpoint = panel.getAttribute('data-endpoint');
    var csrf = panel.getAttribute('data-csrf');
    var STORE_KEY = 'tph_admin_ai_chat';

    function call(payload) {
        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.json().catch(function () { return { success: false, error: 'Unexpected server response (HTTP ' + r.status + ').' }; });
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Escape first, then a tiny markdown subset (bold, italics, bullets).
    function format(text) {
        return escapeHtml(text)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/^\s*[*-]\s+/gm, '• ')
            .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>')
            .replace(/\n/g, '<br>');
    }

    // ------------------------------------------------------------------
    // AI Insights panel
    // ------------------------------------------------------------------
    var toggle = document.getElementById('adAiToggle');
    var log = document.getElementById('adAiLog');
    var form = document.getElementById('adAiForm');
    var input = document.getElementById('adAiInput');
    var send = document.getElementById('adAiSend');
    var chips = document.getElementById('adAiChips');
    var busy = false;
    var history = [];

    // The conversation survives moving between admin pages (this tab only).
    try { history = JSON.parse(sessionStorage.getItem(STORE_KEY) || '[]'); } catch (e) { history = []; }
    if (!Array.isArray(history)) history = [];

    function save() {
        try { sessionStorage.setItem(STORE_KEY, JSON.stringify(history.slice(-20))); } catch (e) {}
    }

    function bubble(role, html) {
        var el = document.createElement('div');
        el.className = 'ad-ai-msg ad-ai-' + role;
        el.innerHTML = html;
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
        return el;
    }

    function render() {
        log.innerHTML = '';
        bubble('bot', 'Hi! I read your <strong>live store data</strong> — sales, orders, custom orders, stock, support and reviews. Ask me anything, or tap a topic below. <span class="ad-ai-muted">I can only read, never change, your data.</span>');
        history.forEach(function (t) {
            bubble(t.role === 'user' ? 'user' : 'bot', t.role === 'user' ? escapeHtml(t.text) : format(t.text));
        });
        chips.hidden = history.length > 0;
    }

    function open() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.classList.add('is-open');
        log.scrollTop = log.scrollHeight;
        input.focus();
    }

    function close() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.classList.remove('is-open');
        toggle.focus();
    }

    function ask(q) {
        q = (q || '').trim();
        if (!q || busy) return;
        busy = true;
        send.disabled = true;
        chips.hidden = true;
        bubble('user', escapeHtml(q));
        input.value = '';
        input.style.height = '';
        var wait = bubble('bot', '<span class="ad-ai-typing"><i></i><i></i><i></i></span> Reading your store data…');

        var prior = history.slice(-10);
        call({ action: 'insights', message: q, history: prior })
            .then(function (d) {
                if (d && d.success) {
                    wait.innerHTML = format(d.message);
                    history.push({ role: 'user', text: q }, { role: 'model', text: d.message });
                    save();
                } else {
                    wait.classList.add('ad-ai-error');
                    wait.textContent = (d && d.error) || 'Something went wrong. Please try again.';
                }
            })
            .catch(function () {
                wait.classList.add('ad-ai-error');
                wait.textContent = 'Network error. Please check your connection and try again.';
            })
            .finally(function () {
                busy = false;
                send.disabled = false;
                log.scrollTop = log.scrollHeight;
                input.focus();
            });
    }

    toggle.addEventListener('click', function () { panel.hidden ? open() : close(); });
    document.getElementById('adAiClose').addEventListener('click', close);
    document.getElementById('adAiClear').addEventListener('click', function () {
        if (busy) return;
        history = [];
        save();
        render();
        input.focus();
    });
    panel.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
    chips.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-q]');
        if (b) ask(b.getAttribute('data-q'));
    });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        ask(input.value);
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            ask(input.value);
        }
    });
    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 110) + 'px';
    });

    render();

    // ------------------------------------------------------------------
    // Support Chat: Suggest reply
    // ------------------------------------------------------------------
    var suggestBtn = document.getElementById('aiSuggestBtn');
    var replyBox = document.getElementById('chatMessage');
    if (suggestBtn && replyBox) {
        var note = document.getElementById('aiSuggestNote');
        var label = suggestBtn.querySelector('span');
        var defaultNote = note ? note.textContent : '';

        suggestBtn.addEventListener('click', function () {
            if (suggestBtn.disabled) return;
            if (replyBox.value.trim() && !confirm('Replace what you have typed with the AI suggestion?')) return;

            suggestBtn.disabled = true;
            label.textContent = 'Writing…';
            if (note) { note.textContent = 'Reading the conversation and the customer\'s orders…'; note.classList.remove('is-error'); }

            call({ action: 'suggest_reply', conversation_id: parseInt(suggestBtn.getAttribute('data-conversation'), 10) || 0 })
                .then(function (d) {
                    if (d && d.success) {
                        replyBox.value = d.reply;
                        replyBox.dispatchEvent(new Event('input')); // let the box grow to fit
                        replyBox.focus();
                        if (note) note.textContent = 'Draft ready — check it, edit if needed, then press Send.';
                    } else if (note) {
                        note.textContent = (d && d.error) || 'Could not write a suggestion. Please try again.';
                        note.classList.add('is-error');
                    }
                })
                .catch(function () {
                    if (note) { note.textContent = 'Network error. Please try again.'; note.classList.add('is-error'); }
                })
                .finally(function () {
                    suggestBtn.disabled = false;
                    label.textContent = 'Suggest reply';
                });
        });

        // Once the draft is sent or cleared, go back to the hint.
        function resetNote() {
            if (note) { note.textContent = defaultNote; note.classList.remove('is-error'); }
        }
        replyBox.addEventListener('input', function () { if (!replyBox.value) resetNote(); });
        if (replyBox.form) replyBox.form.addEventListener('submit', resetNote);
    }
})();

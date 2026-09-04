<?php

/**
 * Payment / order success animation — shared by all four checkout endings:
 *   1. Cart checkout, Cash on Delivery       -> order_confirmation.php
 *   2. Cart checkout, online payment         -> payment-qr.php (after proof upload)
 *   3. Custom design order, Cash on Delivery -> custom-order-tracking.php
 *   4. Custom design order, online payment   -> custom-order-tracking.php
 *
 * The overlay is layered ON TOP of the existing confirmation pages — none of
 * their markup changes, so removing this include leaves those pages working.
 *
 * It auto-dismisses through a pure CSS animation, so it also clears itself when
 * JavaScript is unavailable. JS only adds early dismissal (click / Esc) and
 * restores body scrolling.
 */

if (!function_exists('setPaymentSuccessFlash')) {

    /**
     * Stash a one-shot success flash just before redirecting to a landing page.
     */
    function setPaymentSuccessFlash(array $data): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['payment_success_flash'] = $data;
        }
    }

    /**
     * Read and CLEAR the flash. Returns null when there is nothing to celebrate,
     * so revisiting or refreshing a confirmation/tracking page never replays the
     * animation. When $expectOrderId is given, a flash left over from a different
     * order is discarded instead of shown on the wrong page.
     */
    function takePaymentSuccessFlash(?int $expectOrderId = null): ?array
    {
        if (empty($_SESSION['payment_success_flash']) || !is_array($_SESSION['payment_success_flash'])) {
            return null;
        }
        $flash = $_SESSION['payment_success_flash'];
        unset($_SESSION['payment_success_flash']);

        if ($expectOrderId !== null && (int) ($flash['order_id'] ?? 0) !== $expectOrderId) {
            return null;
        }
        return $flash;
    }

    /**
     * Print the overlay. Options:
     *   title    string  headline (default "Payment Successful")
     *   message  string  supporting line
     *   badge    string  e.g. "Order #1042" — shown in a pill
     *   amount   float   optional peso amount, rendered large
     *   tone     string  'success' (green) or 'pending' (amber, for
     *                    "awaiting verification" style endings)
     */
    function renderPaymentSuccess(array $opts = []): void
    {
        static $printedAssets = false;
        static $instance      = 0;

        $overlayId = 'tphPaySuccess' . (++$instance);

        $title   = (string) ($opts['title'] ?? 'Payment Successful');
        $message = (string) ($opts['message'] ?? '');
        $badge   = (string) ($opts['badge'] ?? '');
        $amount  = isset($opts['amount']) && $opts['amount'] !== null ? (float) $opts['amount'] : null;
        $tone    = ($opts['tone'] ?? 'success') === 'pending' ? 'pending' : 'success';

        if (!$printedAssets) {
            $printedAssets = true;
            echo <<<'CSS'
<style id="tph-ps-styles">
.tph-ps-overlay{
    position:fixed; inset:0; z-index:20000;
    display:flex; align-items:center; justify-content:center; padding:1.25rem;
    background:rgba(12,12,14,.62);
    -webkit-backdrop-filter:blur(10px); backdrop-filter:blur(10px);
    opacity:0; overflow:hidden;
    animation:tph-ps-fade-in .38s ease forwards, tph-ps-fade-out .5s ease 3.1s forwards;
}
@keyframes tph-ps-fade-in{to{opacity:1}}
@keyframes tph-ps-fade-out{to{opacity:0; visibility:hidden; pointer-events:none}}

.tph-ps-card{
    position:relative; z-index:2;
    width:100%; max-width:420px;
    background:var(--bg-white,#fff); color:var(--text-dark,#1a1a1a);
    border:1px solid var(--border-light,#e8e8e8); border-radius:22px;
    padding:2.5rem 1.75rem 2rem; text-align:center;
    box-shadow:0 24px 70px rgba(0,0,0,.32);
    opacity:0; transform:translateY(22px) scale(.94);
    animation:tph-ps-pop .62s cubic-bezier(.16,1,.3,1) .12s forwards;
}
@keyframes tph-ps-pop{to{opacity:1; transform:translateY(0) scale(1)}}

/* --- the animated badge --- */
.tph-ps-badge-wrap{position:relative; width:104px; height:104px; margin:0 auto 1.35rem}
.tph-ps-ring{
    position:absolute; inset:0; border-radius:50%;
    border:2px solid var(--tph-ps-accent,#27ae60);
    opacity:0; transform:scale(.6);
    animation:tph-ps-ring 1.7s cubic-bezier(.16,1,.3,1) infinite;
}
.tph-ps-ring:nth-of-type(2){animation-delay:.5s}
@keyframes tph-ps-ring{
    0%{opacity:.55; transform:scale(.62)}
    70%{opacity:0; transform:scale(1.35)}
    100%{opacity:0; transform:scale(1.35)}
}
.tph-ps-disc{
    position:absolute; inset:0; border-radius:50%;
    background:var(--tph-ps-soft,rgba(39,174,96,.12));
    transform:scale(0);
    animation:tph-ps-disc .55s cubic-bezier(.16,1,.3,1) .18s forwards;
}
@keyframes tph-ps-disc{to{transform:scale(1)}}
.tph-ps-mark{position:relative; width:104px; height:104px; display:block}
.tph-ps-mark circle,.tph-ps-mark path{
    fill:none; stroke:var(--tph-ps-accent,#27ae60);
    stroke-linecap:round; stroke-linejoin:round;
}
.tph-ps-mark circle{
    stroke-width:2.5; stroke-dasharray:151; stroke-dashoffset:151;
    animation:tph-ps-draw .75s cubic-bezier(.65,0,.45,1) .25s forwards;
}
.tph-ps-mark path{
    stroke-width:4.5; stroke-dasharray:48; stroke-dashoffset:48;
    animation:tph-ps-draw .42s cubic-bezier(.65,0,.45,1) .82s forwards;
}
@keyframes tph-ps-draw{to{stroke-dashoffset:0}}

/* --- copy --- */
.tph-ps-rise{opacity:0; transform:translateY(12px); animation:tph-ps-rise .5s ease forwards}
.tph-ps-title{font-size:1.5rem; font-weight:800; margin:0 0 .45rem; letter-spacing:-.01em; animation-delay:1.02s}
.tph-ps-msg{font-size:.92rem; line-height:1.5; color:var(--text-medium,#6b6b6b); margin:0 auto; max-width:31ch; animation-delay:1.14s}
.tph-ps-amount{font-size:2rem; font-weight:800; letter-spacing:-.02em; margin:1.1rem 0 .1rem; animation-delay:1.24s}
.tph-ps-pill{
    display:inline-block; margin-top:1.1rem; padding:.4rem .95rem;
    background:var(--bg-light,#f6f6f6); border:1px solid var(--border-light,#e8e8e8);
    border-radius:999px; font-size:.82rem; font-weight:700;
    animation-delay:1.32s;
}
.tph-ps-hint{
    margin:1.35rem 0 0; font-size:.72rem; letter-spacing:.05em; text-transform:uppercase;
    color:var(--text-light,#9a9aa2); animation-delay:1.5s;
}
@keyframes tph-ps-rise{to{opacity:1; transform:translateY(0)}}

/* --- confetti --- */
.tph-ps-confetti{position:absolute; inset:0; z-index:1; overflow:hidden; pointer-events:none}
.tph-ps-confetti i{
    position:absolute; top:-12%; width:9px; height:14px; opacity:0;
    animation:tph-ps-fall linear forwards;
}
.tph-ps-confetti i.tph-ps-round{border-radius:50%; height:9px}
@keyframes tph-ps-fall{
    0%{opacity:0; transform:translate3d(0,0,0) rotate(0deg)}
    8%{opacity:1}
    75%{opacity:1}
    100%{opacity:0; transform:translate3d(var(--tph-ps-drift,0px),108vh,0) rotate(var(--tph-ps-spin,540deg))}
}

@media (max-width:420px){
    .tph-ps-card{padding:2rem 1.25rem 1.6rem; border-radius:18px}
    .tph-ps-title{font-size:1.3rem}
    .tph-ps-amount{font-size:1.7rem}
}

/* Respect a reduced-motion preference: show the result, skip the motion. */
@media (prefers-reduced-motion:reduce){
    .tph-ps-overlay{animation:tph-ps-fade-in .01s ease forwards, tph-ps-fade-out .01s ease 3.1s forwards}
    .tph-ps-card,.tph-ps-rise{opacity:1; transform:none; animation:none}
    .tph-ps-disc{transform:scale(1); animation:none}
    .tph-ps-ring{display:none}
    .tph-ps-mark circle,.tph-ps-mark path{stroke-dashoffset:0; animation:none}
    .tph-ps-confetti{display:none}
}
</style>
CSS;
        }

        $accent = $tone === 'pending' ? '#e6a020' : '#27ae60';
        $soft   = $tone === 'pending' ? 'rgba(230,160,32,.14)' : 'rgba(39,174,96,.12)';
        $colors = $tone === 'pending'
            ? ['#e6a020', '#c8a96e', '#f2c66d', '#1a1a1a', '#e8d3a9']
            : ['#27ae60', '#c8a96e', '#4cd07d', '#1a1a1a', '#a8e6c0'];

        echo '<div class="tph-ps-overlay" id="' . $overlayId . '" role="status" aria-live="polite"'
            . ' style="--tph-ps-accent:' . $accent . '; --tph-ps-soft:' . $soft . '">';

        // Confetti — randomised server-side so every order looks a little different.
        echo '<div class="tph-ps-confetti" aria-hidden="true">';
        for ($i = 0; $i < 18; $i++) {
            $left     = mt_rand(2, 96);
            $delay    = mt_rand(0, 900) / 1000;
            $duration = mt_rand(2200, 3400) / 1000;
            $drift    = mt_rand(-90, 90);
            $spin     = mt_rand(360, 1080);
            $color    = $colors[$i % count($colors)];
            $round    = $i % 3 === 0 ? ' tph-ps-round' : '';
            echo '<i class="' . $round . '" style="left:' . $left . '%;'
                . 'background:' . $color . ';'
                . 'animation-delay:' . $delay . 's;'
                . 'animation-duration:' . $duration . 's;'
                . '--tph-ps-drift:' . $drift . 'px;'
                . '--tph-ps-spin:' . $spin . 'deg"></i>';
        }
        echo '</div>';

        echo '<div class="tph-ps-card">';
        echo   '<div class="tph-ps-badge-wrap">';
        echo     '<span class="tph-ps-ring" aria-hidden="true"></span>';
        echo     '<span class="tph-ps-ring" aria-hidden="true"></span>';
        echo     '<span class="tph-ps-disc" aria-hidden="true"></span>';
        echo     '<svg class="tph-ps-mark" viewBox="0 0 52 52" aria-hidden="true">'
               .   '<circle cx="26" cy="26" r="24"/>'
               .   '<path d="M14.5 27.5l7.2 7.2 15.8-16"/>'
               . '</svg>';
        echo   '</div>';

        echo   '<h2 class="tph-ps-title tph-ps-rise">' . htmlspecialchars($title, ENT_QUOTES) . '</h2>';
        if ($message !== '') {
            echo '<p class="tph-ps-msg tph-ps-rise">' . htmlspecialchars($message, ENT_QUOTES) . '</p>';
        }
        if ($amount !== null) {
            echo '<div class="tph-ps-amount tph-ps-rise">&#8369;' . number_format($amount, 2) . '</div>';
        }
        if ($badge !== '') {
            echo '<div class="tph-ps-pill tph-ps-rise">' . htmlspecialchars($badge, ENT_QUOTES) . '</div>';
        }
        echo   '<p class="tph-ps-hint tph-ps-rise">Tap anywhere to continue</p>';
        echo '</div>';
        echo '</div>';

        echo <<<JS
<script>
(function () {
    var overlay = document.getElementById('{$overlayId}');
    if (!overlay) return;

    var body = document.body;
    var prevOverflow = body.style.overflow;
    var closed = false;
    body.style.overflow = 'hidden';

    function onKey(e) {
        if (e.key === 'Escape' || e.key === 'Esc') close();
    }

    function release() {
        body.style.overflow = prevOverflow;
        document.removeEventListener('keydown', onKey);
    }

    function remove() {
        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
    }

    function close() {
        if (closed) return;
        closed = true;
        release();
        overlay.style.animation = 'none';
        overlay.style.transition = 'opacity .3s ease';
        overlay.style.opacity = '0';
        overlay.style.pointerEvents = 'none';
        setTimeout(remove, 320);
    }

    overlay.addEventListener('click', close);
    document.addEventListener('keydown', onKey);

    // Backstop: the CSS fade-out finishes at ~3.6s. Tidy up after it either way
    // so scrolling is restored and the node never lingers over the page.
    setTimeout(function () {
        if (closed) return;
        closed = true;
        release();
        remove();
    }, 3700);
})();
</script>
JS;
    }
}

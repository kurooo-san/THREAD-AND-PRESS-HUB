<?php
// Determine current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Get sidebar badge counts if not already set
if (!isset($pending_designs_count)) {
    $pending_designs_count = 0;
    $_cd_check = $conn->query("SHOW TABLES LIKE 'custom_designs'");
    if ($_cd_check && $_cd_check->num_rows > 0) {
        $pending_designs_count = (int)$conn->query("SELECT COUNT(*) as c FROM custom_designs WHERE status = 'pending'")->fetch_assoc()['c'];
    }
}
if (!isset($pending_custom_orders)) {
    $pending_custom_orders = 0;
    $_co_check = $conn->query("SHOW TABLES LIKE 'custom_orders'");
    if ($_co_check && $_co_check->num_rows > 0) {
        $pending_custom_orders = (int)$conn->query("SELECT COUNT(*) as c FROM custom_orders WHERE status IN ('pending_payment','payment_uploaded')")->fetch_assoc()['c'];
    }
}
// Manual QR payments awaiting verification (badge on Payments link)
if (!isset($pending_payment_verifications)) {
    $pending_payment_verifications = 0;
    $_pv_check = $conn->query("SHOW TABLES LIKE 'payment_submissions'");
    if ($_pv_check && $_pv_check->num_rows > 0) {
        $pending_payment_verifications = (int)$conn->query("SELECT COUNT(*) as c FROM payment_submissions WHERE status = 'pending_verification'")->fetch_assoc()['c'];
    }
}

// Account chip. header.php normally computes these; recompute defensively so
// this include also works if a page ever pulls it in on its own.
if (!isset($navUserName) || $navUserName === '') {
    $navUserName = trim((string)($_SESSION['user_name'] ?? ''));
    if ($navUserName === '') { $navUserName = 'Account'; }
}
if (!isset($navInitials) || $navInitials === '') {
    $navInitials = '';
    foreach (preg_split('/\s+/', $navUserName) as $_p) {
        if ($_p === '') { continue; }
        $navInitials .= mb_strtoupper(mb_substr($_p, 0, 1));
        if (mb_strlen($navInitials) >= 2) { break; }
    }
    if ($navInitials === '') { $navInitials = 'U'; }
}
$navFirstName = strtok($navUserName, ' ');
if ($navFirstName === false || $navFirstName === '') { $navFirstName = $navUserName; }

/**
 * 16px stroke icons (stroke-width 2). Inline so they inherit currentColor,
 * which is what drives the amber stroke on the active item.
 */
function adminIcon(string $name): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'products'  => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'orders'    => '<path d="M4 2v20l2.5-1.5L9 22l2.5-1.5L14 22l2.5-1.5L19 22V2l-2.5 1.5L14 2l-2.5 1.5L9 2 6.5 3.5z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'payments'  => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'qr'        => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 20h1M17 20v1"/>',
        'designs'   => '<circle cx="13.5" cy="6.5" r="1.2"/><circle cx="17.5" cy="10.5" r="1.2"/><circle cx="8.5" cy="7.5" r="1.2"/><circle cx="6.5" cy="12.5" r="1.2"/><path d="M12 2a10 10 0 1 0 0 20c.9 0 1.6-.7 1.6-1.6 0-.4-.2-.8-.5-1.1-.3-.3-.4-.7-.4-1.1 0-.9.7-1.6 1.6-1.6H16a6 6 0 0 0 6-6c0-4.9-4.5-8.6-10-8.6z"/>',
        'custom'    => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/>',
        'contact'   => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'support'   => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>',
        'audit'     => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6M9 16h4"/>',
        'store'     => '<path d="M3 9 5 3h14l2 6"/><path d="M4 9v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M9 21v-6h6v6"/>',
        'signout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'bell'      => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        // Row actions
        'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'ban'       => '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
        'unlock'    => '<rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
        'trash'     => '<path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>',
        'save'      => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'print'     => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/>',
        'close'     => '<path d="M18 6 6 18M6 6l12 12"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'chevrons'  => '<path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/>',
    ];
    $d = $paths[$name] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/** One nav row. $match may be a single page name or a list of them. */
function adminNavLink(string $href, string $icon, string $label, $match, string $currentPage, int $badge = 0): void
{
    $match  = (array) $match;
    $active = in_array($currentPage, $match, true) ? ' active' : '';
    echo '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" class="ad-side-link' . $active . '">'
        . adminIcon($icon)
        . '<span>' . htmlspecialchars($label) . '</span>';
    if ($badge > 0) {
        echo '<span class="ad-side-badge">' . ($badge > 99 ? '99+' : $badge) . '</span>';
    }
    echo '</a>';
}
?>
<script>
// Restore the desktop collapsed state before the sidebar paints (no flash).
try { if (localStorage.getItem('tph_admin_sidebar') === 'collapsed') document.documentElement.classList.add('ad-collapsed'); } catch (e) {}
</script>
<div class="admin-layout" style="display:flex;">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="ad-side-head">
            <img class="ad-logo logo-light" src="../images/logo/logo_sm.png" alt="Thread &amp; Press logo">
            <img class="ad-logo logo-dark" src="../images/logo/logo_white_sm.png" alt="Thread &amp; Press logo">
            <span class="ad-wordmark">
                <b>Thread &amp; Press</b>
                <span>Admin</span>
            </span>
            <button class="ad-side-close d-lg-none" id="sidebarClose" type="button" aria-label="Close menu"><?php echo adminIcon('close'); ?></button>
        </div>

        <nav class="ad-side-nav">
            <?php adminNavLink('dashboard.php', 'dashboard', 'Dashboard', 'dashboard.php', $currentPage); ?>

            <div class="ad-side-label">Management</div>
            <?php
            adminNavLink('products.php', 'products', 'Products', 'products.php', $currentPage);
            adminNavLink('orders.php', 'orders', 'Orders', ['orders.php', 'order_details.php'], $currentPage);
            adminNavLink('users.php', 'users', 'Users', 'users.php', $currentPage);
            // Online payment is handled by PayMongo now, so 'Payment Settings'
            // (the manual QR / bank-transfer channel config) no longer changes
            // anything a customer can reach. The page still exists for the
            // historical proofs, it is just not advertised in the nav.
            adminNavLink('payment-verification.php', 'payments', 'Payments', 'payment-verification.php', $currentPage, (int)$pending_payment_verifications);
            adminNavLink('custom-designs.php', 'designs', 'Custom Designs', 'custom-designs.php', $currentPage, (int)$pending_designs_count);
            adminNavLink('custom-orders.php', 'custom', 'Custom Orders', 'custom-orders.php', $currentPage, (int)$pending_custom_orders);
            ?>

            <div class="ad-side-label">Communication</div>
            <?php
            adminNavLink('contact-management.php', 'contact', 'Contact Messages', 'contact-management.php', $currentPage);
            adminNavLink('support-chat.php', 'support', 'Support Chat', ['support-chat.php', 'chatbot-faq.php'], $currentPage);
            adminNavLink('audit-log.php', 'audit', 'Audit Log', 'audit-log.php', $currentPage);
            ?>
        </nav>

        <div class="ad-side-foot">
            <button type="button" class="ad-side-link ad-collapse-btn d-none d-lg-flex" id="sidebarCollapse" aria-label="Collapse sidebar" aria-expanded="true" aria-controls="adminSidebar">
                <?php echo adminIcon('chevrons'); ?><span>Collapse</span>
            </button>
            <button type="button" class="ad-side-link theme-toggle" data-theme-toggle aria-label="Toggle dark mode">
                <i class="fas fa-moon theme-icon-moon"></i><i class="fas fa-sun theme-icon-sun"></i>
                <span class="theme-label-dark">Dark mode</span><span class="theme-label-light">Light mode</span>
            </button>
            <a href="../index.php" class="ad-side-link"><?php echo adminIcon('store'); ?><span>View Store</span></a>
            <div class="ad-user-chip">
                <a href="profile.php" class="ad-avatar" aria-label="My profile"><?php echo htmlspecialchars($navInitials); ?></a>
                <a href="profile.php" class="ad-user-text" style="text-decoration:none;">
                    <b><?php echo htmlspecialchars($navFirstName); ?></b>
                    <span>Administrator</span>
                </a>
                <a href="logout.php" class="ad-signout" title="Sign out" aria-label="Sign out"><?php echo adminIcon('signout'); ?></a>
            </div>
        </div>
    </aside>
    <script>
    // Desktop collapse / expand + hover tooltips while collapsed.
    (function () {
        var root = document.documentElement;
        var side = document.getElementById('adminSidebar');
        var btn  = document.getElementById('sidebarCollapse');
        if (!side || !btn) return;

        function isCollapsed() { return root.classList.contains('ad-collapsed'); }
        function sync() {
            var c = isCollapsed();
            btn.setAttribute('aria-expanded', String(!c));
            btn.setAttribute('aria-label', c ? 'Expand sidebar' : 'Collapse sidebar');
            btn.querySelector('span').textContent = c ? 'Expand' : 'Collapse';
        }

        var tip = document.createElement('div');
        tip.className = 'ad-side-tip';
        tip.setAttribute('role', 'tooltip');
        document.body.appendChild(tip);

        function hide() { tip.classList.remove('show'); }
        function show(el) {
            if (!isCollapsed() || window.innerWidth < 992) return hide();
            var label = el.getAttribute('aria-label') || (el.querySelector('span') || {}).textContent || '';
            var badge = el.querySelector('.ad-side-badge');
            tip.textContent = label.trim() + (badge ? ' (' + badge.textContent + ')' : '');
            var r = el.getBoundingClientRect();
            tip.style.left = (side.getBoundingClientRect().right + 10) + 'px';
            tip.style.top  = (r.top + r.height / 2) + 'px';
            tip.classList.add('show');
        }
        function onTarget(e) {
            var el = e.target.closest('.ad-side-link, .ad-avatar');
            el ? show(el) : hide();
        }

        btn.addEventListener('click', function () {
            var c = root.classList.toggle('ad-collapsed');
            try { localStorage.setItem('tph_admin_sidebar', c ? 'collapsed' : 'expanded'); } catch (e) {}
            sync();
            hide();
        });
        side.addEventListener('mouseover', onTarget);
        side.addEventListener('focusin', onTarget);
        side.addEventListener('mouseleave', hide);
        side.addEventListener('focusout', hide);
        side.querySelector('.ad-side-nav').addEventListener('scroll', hide);
        // Let width-aware widgets (charts, tables) re-measure once the slide ends.
        side.addEventListener('transitionend', function (e) {
            if (e.target === side && e.propertyName === 'width') window.dispatchEvent(new Event('resize'));
        });
        sync();
    })();
    </script>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="admin-main-content">
        <!-- Mobile sidebar toggle -->
        <button class="sidebar-toggle d-lg-none" id="sidebarToggle" type="button" aria-label="Open menu"><?php echo adminIcon('menu'); ?></button>

        <!-- Admin Notification Bell -->
        <div id="adminNotifWrap">
            <button id="adminNotifBtn" type="button" aria-label="Notifications">
                <?php echo adminIcon('bell'); ?>
                <span id="adminNotifBadge" style="display:none;">0</span>
            </button>
            <div id="adminNotifPanel" style="display:none;">
                <div style="padding:12px 14px;background:#17181b;color:#fff;font-weight:600;display:flex;justify-content:space-between;align-items:center;font-size:13px;">
                    <span>Notifications</span>
                    <button id="adminNotifClear" type="button" style="font-size:11px;padding:3px 9px;border-radius:7px;border:1px solid rgba(255,255,255,.25);background:transparent;color:#fff;cursor:pointer;">Mark seen</button>
                </div>
                <div id="adminNotifList" style="max-height:360px;overflow-y:auto;">
                    <div class="text-muted small p-3 text-center">Loading…</div>
                </div>
            </div>
        </div>
        <script>
        (function(){
            const KEY = 'tph_admin_notif_since';
            const btn   = document.getElementById('adminNotifBtn');
            const panel = document.getElementById('adminNotifPanel');
            const badge = document.getElementById('adminNotifBadge');
            const list  = document.getElementById('adminNotifList');
            const clear = document.getElementById('adminNotifClear');
            if (!btn) return;

            function getSince() {
                const v = parseInt(localStorage.getItem(KEY) || '0', 10);
                return isNaN(v) ? 0 : v;
            }
            function setSince(v) { localStorage.setItem(KEY, String(v)); }

            function buildItem(label, count, href) {
                if (!count) return '';
                return `<a href="${href}" style="display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid #f1eee8;text-decoration:none;color:#16171a;font-size:13px;">
                    <span style="flex:1 1 auto;font-weight:600;">${label}</span>
                    <span style="min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#e8a05a;color:#1b1206;font-size:11px;font-weight:700;line-height:20px;text-align:center;">${count}</span>
                </a>`;
            }

            function render(data) {
                const total = data.total_attention || 0;
                if (total > 0) {
                    badge.textContent = total > 99 ? '99+' : total;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
                let html = '';
                html += buildItem('New Orders',          data.new_orders,       'orders.php');
                html += buildItem('Payments to Verify',  data.pending_payments, 'payment-verification.php');
                html += buildItem('Pending Designs',     data.pending_designs,  'custom-designs.php');
                html += buildItem('Custom Orders',       data.pending_custom,   'custom-orders.php');
                html += buildItem('Unread Chats',        data.unread_chats,     'support-chat.php');
                html += buildItem('Contact Messages',    data.unread_contacts,  'contact-management.php');
                html += buildItem('Low Stock',           data.low_stock,        'products.php');
                if (!html) {
                    html = '<div style="padding:26px 14px;text-align:center;color:#97928a;font-size:13px;">All caught up</div>';
                }
                list.innerHTML = html;
            }

            function poll() {
                fetch('../includes/admin-notifications.php?since=' + getSince(), { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(render)
                    .catch(()=>{});
            }

            btn.addEventListener('click', () => {
                panel.style.display = (panel.style.display === 'none') ? '' : 'none';
                poll();
            });
            clear.addEventListener('click', (e) => {
                e.stopPropagation();
                setSince(Math.floor(Date.now()/1000));
                poll();
            });
            document.addEventListener('click', (e) => {
                if (!btn.contains(e.target) && !panel.contains(e.target)) panel.style.display = 'none';
            });

            poll();
            setInterval(poll, 30000); // every 30s
        })();
        </script>

<?php
require 'includes/config.php';
$pageTitle = 'Privacy Policy';
include 'includes/header/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div style="background:#fff; padding:2.5rem; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.06);">
                <h1 style="font-weight:800; margin-bottom:0.25rem;">Privacy Policy</h1>
                <p class="text-muted" style="font-size:0.9rem;">Last updated: <?php echo date('F j, Y'); ?></p>

                <p>Thread &amp; Press Hub ("we", "us", "our") respects your privacy. This Privacy Policy
                explains how we collect, use, and protect your personal information when you use our website
                and services.</p>

                <h4 class="mt-4">1. Information We Collect</h4>
                <ul>
                    <li><strong>Account information:</strong> full name, email, phone, delivery address, and account type (regular, PWD, or senior).</li>
                    <li><strong>Order &amp; payment information:</strong> products ordered, billing details, and payment method (we do not store full card numbers).</li>
                    <li><strong>Custom design uploads:</strong> images and design specifications you submit.</li>
                    <li><strong>Technical data:</strong> IP address, browser type, device, pages visited, and login attempts (for security).</li>
                    <li><strong>Cookies:</strong> session cookies, "remember me" tokens, and preference cookies. See section 5.</li>
                </ul>

                <h4 class="mt-4">2. How We Use Your Information</h4>
                <ul>
                    <li>To process orders, payments, and deliveries.</li>
                    <li>To provide customer support and respond to inquiries.</li>
                    <li>To authenticate accounts (including Multi-Factor Authentication via email).</li>
                    <li>To prevent fraud, abuse, and unauthorized access (rate limiting, audit logs).</li>
                    <li>To send transactional emails (order confirmations, password resets, verification codes).</li>
                </ul>

                <h4 class="mt-4">3. Legal Basis</h4>
                <p>We process your data under the Philippine Data Privacy Act of 2012 (RA 10173) on the basis of
                your consent, performance of a contract (your orders), legitimate interest (security),
                and legal obligations.</p>

                <h4 class="mt-4">4. Data Sharing</h4>
                <p>We do <strong>not</strong> sell your personal information. We share data only with:</p>
                <ul>
                    <li>Payment processors (e.g., GCash, Maya) to complete transactions.</li>
                    <li>Delivery partners to fulfill your orders.</li>
                    <li>Email service providers to send transactional messages.</li>
                    <li>Government authorities, when required by law.</li>
                </ul>

                <h4 class="mt-4">5. Cookies</h4>
                <p>We use the following types of cookies:</p>
                <ul>
                    <li><strong>Strictly necessary</strong> — session, CSRF, and authentication cookies. These cannot be disabled.</li>
                    <li><strong>Functional</strong> — "remember me" tokens, dark mode, and cart preferences.</li>
                    <li><strong>Analytics</strong> — only loaded if you accept the cookie banner.</li>
                </ul>
                <p>You can manage your choice anytime by clicking
                    <a href="#" onclick="if(window.tphResetCookieConsent){tphResetCookieConsent();}return false;">Reset cookie preferences</a>.
                </p>

                <h4 class="mt-4">6. Data Retention</h4>
                <p>We keep account data for as long as your account is active. Order records are retained for
                up to 5 years for accounting and tax compliance.</p>

                <h4 class="mt-4">7. Security</h4>
                <p>We protect your data with bcrypt password hashing, prepared SQL statements,
                CSRF protection, rate limiting, secure session cookies (HttpOnly, SameSite),
                and HSTS / HTTPS in production.</p>

                <h4 class="mt-4">8. Your Rights</h4>
                <p>Under RA 10173 you have the right to access, correct, delete, or object to processing of
                your personal data. To exercise these rights, contact us at
                <a href="mailto:support@threadandpress.com">support@threadandpress.com</a>.</p>

                <h4 class="mt-4">9. Children</h4>
                <p>Our services are not directed to children under 13. We do not knowingly collect data from minors.</p>

                <h4 class="mt-4">10. Changes</h4>
                <p>We may update this Privacy Policy from time to time. The "Last updated" date above will reflect changes.</p>

                <h4 class="mt-4">11. Contact</h4>
                <p>Thread &amp; Press Hub<br>
                Email: <a href="mailto:support@threadandpress.com">support@threadandpress.com</a><br>
                Phone: +63 (2) 8123-4567<br>
                Address: 123 Fashion Ave, Cainta, Rizal, Philippines</p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>

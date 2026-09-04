    </main>

    <!-- Footer Features Strip -->
    <section class="footer-features">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-3 mb-md-0">
                    <div class="feature-item justify-content-center">
                        <div class="feature-icon"><i class="fas fa-truck"></i></div>
                        <div>
                            <h6>Nationwide Delivery</h6>
                            <p>Flat ₱50 shipping fee</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3 mb-md-0">
                    <div class="feature-item justify-content-center">
                        <div class="feature-icon"><i class="fas fa-shield-halved"></i></div>
                        <div>
                            <h6>Secure Payment</h6>
                            <p>100% secure checkout</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="feature-item justify-content-center">
                        <div class="feature-icon"><i class="fas fa-rotate-left"></i></div>
                        <div>
                            <h6>Easy Returns</h6>
                            <p>30-day return policy</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="feature-item justify-content-center">
                        <div class="feature-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div>
                            <h6>Cash on Delivery</h6>
                            <p>Pay when you receive</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="row">
                <!-- Brand Column -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="footer-brand">
                        <span class="brand-logo">TP</span> Thread &amp; Press Hub
                    </div>
                    <p>Your destination for quality apparel and accessories. We combine style, comfort, and sustainability in every piece we create.</p>

                    <div class="footer-social">
                        <a href="https://facebook.com" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://instagram.com" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                        <a href="https://twitter.com" target="_blank" rel="noopener"><i class="fab fa-twitter"></i></a>
                        <a href="https://youtube.com" target="_blank" rel="noopener"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <!-- Shop Links -->
                <div class="col-lg-2 col-md-3 col-6 mb-4 offset-lg-1">
                    <h6>Shop</h6>
                    <ul>
                        <li><a href="shop.php">New Arrivals</a></li>
                        <li><a href="shop.php">Best Sellers</a></li>
                        <li><a href="shop.php?gender=mens">Men</a></li>
                        <li><a href="shop.php?gender=womens">Women</a></li>
                        <li><a href="shop.php?gender=kids">Kids</a></li>
                        <li><a href="shop.php?category=accessories">Accessories</a></li>
                        <li><a href="custom-design.php">Custom Design</a></li>
                    </ul>
                </div>

                <!-- Support Links -->
                <div class="col-lg-2 col-md-3 col-6 mb-4">
                    <h6>Support</h6>
                    <ul>
                        <li><a href="contact.php">Contact Us</a></li>
                        <li><a href="contact.php">FAQs</a></li>
                        <li><a href="contact.php">Shipping Info</a></li>
                        <li><a href="contact.php">Returns &amp; Exchanges</a></li>
                        <li><a href="shop.php">Size Guide</a></li>
                        <li><a href="orders.php">Track Order</a></li>
                    </ul>
                </div>

                <!-- Company Links -->
                <div class="col-lg-2 col-md-3 col-6 mb-4">
                    <h6>Company</h6>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="about.php">Our Story</a></li>
                        <li><a href="contact.php">Get in Touch</a></li>
                        <li><a href="custom-design.php">Custom Designs</a></li>
                        <li><a href="about.php">Terms of Service</a></li>
                        <li><a href="privacy-policy.php">Privacy Policy</a></li>
                    </ul>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div>
                    <div class="footer-contact">
                        <span class="footer-contact-item"><i class="fas fa-envelope"></i> support@threadandpress.com</span>
                        <span class="footer-contact-item"><i class="fas fa-phone"></i> +63 (2) 8123-4567</span>
                        <span class="footer-contact-item"><i class="fas fa-map-marker-alt"></i> 123 Fashion Ave, Cainta, Rizal, Philippines</span>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-center align-items-center mt-3 pt-3" style="border-top: 1px solid var(--border-light);">
                <small style="color: var(--text-light);">&copy; <?php echo date('Y'); ?> Thread &amp; Press Hub. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../js/animations.js' : 'js/animations.js'; ?>"></script>
    <script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../js/enhancements.js' : 'js/enhancements.js'; ?>"></script>
    <script>
        function showToast(message, type = 'info') {
            const toastHtml = `
                <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            const toastContainer = document.querySelector('.toast-container') || document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.innerHTML = toastHtml;
            if (!document.querySelector('.toast-container')) {
                document.body.appendChild(toastContainer);
            }
            const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
            toast.show();
        }
    </script>
    
    <?php if (strpos($_SERVER['PHP_SELF'], '/admin/') === false): ?>
    <!-- Chatbot Widget -->
    <div id="chatbot-widget" class="chatbot-widget" data-logged-in="<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>">
        <div class="chat-header">
            <div class="chat-header-info">
                <div class="chat-header-avatar" id="chat-header-avatar"><i class="fas fa-robot"></i></div>
                <div class="chat-header-text">
                    <div class="chat-title" id="chat-header-title">AI Assistant <span class="chat-header-badge">Gemini</span></div>
                    <div class="chat-subtitle" id="chat-header-subtitle">Powered by Google AI</div>
                </div>
            </div>
            <button id="chat-close" class="chat-close-btn">
                <i class="fas fa-ellipsis-h"></i>
            </button>
        </div>

        <!-- Tab Switcher -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="chat-tabs">
            <button class="chat-tab active" data-tab="ai" id="tab-ai">
                <i class="fas fa-robot"></i> AI Assistant
            </button>
            <button class="chat-tab" data-tab="support" id="tab-support">
                <i class="fas fa-headset"></i> Live Support
                <span class="chat-tab-badge" id="support-unread-badge" style="display:none;">0</span>
            </button>
        </div>

        <!-- AI Assistant Panel -->
        <div class="chat-panel active" id="panel-ai">
            <div class="chat-quick-actions">
                <button class="chat-quick-action" onclick="sendQuickChat('Find Products')"><i class="fas fa-search"></i> Find Products</button>
                <button class="chat-quick-action" onclick="sendQuickChat('Style Advice')"><i class="fas fa-magic"></i> Style Advice</button>
                <button class="chat-quick-action" onclick="sendQuickChat('Order Help')"><i class="fas fa-box"></i> Order Help</button>
            </div>
            <div id="chat-messages" class="chat-messages">
                <div class="chat-message bot-message">
                    Hi there! I'm your AI shopping assistant powered by Gemini. I can help you find products, give style advice, track orders, and answer any questions about Thread &amp; Press Hub. How can I help you today?
                </div>
            </div>
            <div class="chat-input-area">
                <input type="text" id="chat-input" class="chat-input" placeholder="Type your message..." autocomplete="off">
                <button id="chat-send" class="chat-send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>

        <!-- Live Support Panel -->
        <div class="chat-panel" id="panel-support">
            <!-- Conversation List View -->
            <div id="support-conv-list" class="support-widget-view">
                <div class="support-widget-convs" id="support-conversations">
                    <div class="support-widget-loading"><i class="fas fa-spinner fa-spin"></i> Loading conversations...</div>
                </div>
                <div class="support-widget-new">
                    <button class="btn btn-sm btn-primary w-100" id="btn-new-conversation">
                        <i class="fas fa-plus"></i> New Conversation
                    </button>
                </div>
            </div>

            <!-- New Conversation Form -->
            <div id="support-new-conv" class="support-widget-view" style="display:none;">
                <div class="support-widget-form">
                    <div class="support-widget-form-header">
                        <button class="btn btn-sm btn-outline-secondary" id="btn-back-to-list">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <span>New Conversation</span>
                    </div>
                    <form id="new-conv-form">
                        <input type="text" name="subject" class="form-control form-control-sm mb-2" placeholder="Subject (e.g. Order Inquiry)" required maxlength="255">
                        <textarea name="message" class="form-control form-control-sm mb-2" rows="3" placeholder="Describe your issue..." required></textarea>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-paper-plane"></i> Start Conversation
                        </button>
                    </form>
                </div>
            </div>

            <!-- Active Chat View -->
            <div id="support-chat-view" class="support-widget-view" style="display:none;">
                <div class="support-widget-chat-header">
                    <button class="btn btn-sm btn-outline-secondary" id="btn-back-to-convs">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <span id="support-chat-subject">Conversation</span>
                    <span class="badge" id="support-chat-status"></span>
                </div>
                <div id="support-chat-messages" class="chat-messages"></div>
                <div class="support-widget-img-preview" id="support-img-preview" style="display:none;">
                    <img id="support-preview-img" src="" alt="Preview">
                    <button type="button" class="support-widget-img-remove" id="support-remove-img"><i class="fas fa-times"></i></button>
                </div>
                <div class="chat-input-area" id="support-input-area">
                    <label class="chat-attach-btn" for="support-image-input" title="Attach image">
                        <i class="fas fa-image"></i>
                    </label>
                    <input type="file" id="support-image-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                    <input type="text" id="support-input" class="chat-input" placeholder="Type your message..." autocomplete="off">
                    <button id="support-send" class="chat-send-btn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="support-closed-msg" id="support-closed-msg" style="display:none;">
                    <i class="fas fa-lock"></i> This conversation is closed.
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="chat-login-prompt">
            <div class="chat-login-icon"><i class="fas fa-lock"></i></div>
            <h5>Sign In Required</h5>
            <p>Please log in to your account to chat with our AI assistant. We'll be able to help you with orders, recommendations, and more!</p>
            <a href="login.php" class="chat-login-btn"><i class="fas fa-sign-in-alt me-2"></i>Sign In</a>
            <p class="chat-login-register">Don't have an account? <a href="register.php">Sign Up</a></p>
        </div>
        <?php endif; ?>
        <div class="chat-footer-note" id="chat-footer-note">Powered by Google Gemini AI</div>
    </div>
    
    <!-- Chatbot Toggle Button -->
    <button id="chatbot-toggle" class="chatbot-toggle">
        <i class="fas fa-comment-dots" id="chatbot-toggle-icon"></i>
    </button>
    
    <script>
        function sendQuickChat(msg) {
            var input = document.getElementById('chat-input');
            if (input) { input.value = msg; document.getElementById('chat-send').click(); }
        }
        var toggleBtn = document.getElementById('chatbot-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                var icon = document.getElementById('chatbot-toggle-icon');
                var widget = document.getElementById('chatbot-widget');
                if (widget && widget.classList.contains('active')) {
                    icon.className = 'fas fa-comment-dots';
                } else {
                    icon.className = 'fas fa-chevron-down';
                }
            });
        }
    </script>
    <script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../js/chatbot.js' : 'js/chatbot.js'; ?>"></script>
    <?php endif; ?>

    <?php if (strpos($_SERVER['PHP_SELF'], '/admin/') === false): ?>
    <!-- Cookie Consent Banner -->
    <div id="tph-cookie-banner" role="dialog" aria-live="polite" aria-label="Cookie consent"
         style="display:none; position:fixed; left:1rem; right:1rem; bottom:1rem; z-index:9999;
                max-width:560px; margin-left:auto; background:#1f2937; color:#f9fafb;
                border-radius:14px; box-shadow:0 12px 40px rgba(0,0,0,0.25); padding:1.1rem 1.25rem;
                font-size:0.9rem; line-height:1.45;">
        <div style="display:flex; align-items:flex-start; gap:0.75rem;">
            <i class="fas fa-cookie-bite" style="color:#fbbf24; font-size:1.4rem; margin-top:2px;"></i>
            <div style="flex:1;">
                <strong style="display:block; margin-bottom:0.25rem;">We value your privacy</strong>
                We use cookies to keep you signed in, remember your cart, and improve your experience.
                Read our <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../privacy-policy.php' : 'privacy-policy.php'; ?>"
                    style="color:#fbbf24; text-decoration:underline;">Privacy Policy</a>.
            </div>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:0.85rem; justify-content:flex-end;">
            <button type="button" id="tph-cookie-decline"
                    style="background:transparent; color:#f9fafb; border:1px solid #4b5563; padding:0.45rem 1rem;
                           border-radius:8px; cursor:pointer; font-size:0.85rem;">
                Necessary only
            </button>
            <button type="button" id="tph-cookie-accept"
                    style="background:#10b981; color:#fff; border:none; padding:0.45rem 1.15rem;
                           border-radius:8px; cursor:pointer; font-size:0.85rem; font-weight:600;">
                Accept all
            </button>
        </div>
    </div>
    <script>
        (function () {
            var KEY = 'tph_cookie_consent';
            var banner = document.getElementById('tph-cookie-banner');
            if (!banner) return;

            function setConsent(value) {
                try {
                    var oneYear = 60 * 60 * 24 * 365;
                    document.cookie = KEY + '=' + value + '; max-age=' + oneYear +
                        '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
                    localStorage.setItem(KEY, value);
                } catch (e) {}
                banner.style.display = 'none';
                if (value === 'all' && typeof window.tphLoadAnalytics === 'function') {
                    window.tphLoadAnalytics();
                }
            }

            function getConsent() {
                var m = document.cookie.match(new RegExp('(?:^|; )' + KEY + '=([^;]*)'));
                if (m) return decodeURIComponent(m[1]);
                try { return localStorage.getItem(KEY); } catch (e) { return null; }
            }

            // Expose reset for the privacy policy page
            window.tphResetCookieConsent = function () {
                document.cookie = KEY + '=; max-age=0; path=/';
                try { localStorage.removeItem(KEY); } catch (e) {}
                banner.style.display = 'block';
            };

            if (!getConsent()) {
                banner.style.display = 'block';
            }

            document.getElementById('tph-cookie-accept').addEventListener('click', function () {
                setConsent('all');
            });
            document.getElementById('tph-cookie-decline').addEventListener('click', function () {
                setConsent('necessary');
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>

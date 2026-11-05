<?php
require 'includes/config.php';
$pageTitle = 'Home';
$bodyClass = 'home-page';
include 'includes/header/header.php';
?>

    <link rel="stylesheet" href="css/home-ai.css?v=<?php echo @filemtime(__DIR__ . '/css/home-ai.css'); ?>">
    <link rel="stylesheet" href="css/hero-modern.css?v=<?php echo @filemtime(__DIR__ . '/css/hero-modern.css'); ?>">

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-bg" style="background-image: url('images/hero/hero-bg-sm.jpg');"></div>
        <div class="container position-relative" style="z-index: 2;">
            <div class="row align-items-center" style="min-height: 85vh;">
                <div class="col-lg-7 hero-content">
                    <div class="hero-panel">
                        <span class="hero-badge">Ready-to-wear &middot; Custom printing</span>
                        <h1>Wear it ready-made,<br>or print your own.</h1>
                        <p class="hero-subtitle">Clothes for men, women and kids, plus a Design Studio for printed tees, hoodies, polos, couple sets and company uniforms. Try it on with AI before you check out.</p>
                        <div class="hero-buttons">
                            <a href="shop.php" class="btn btn-hero">Shop Now <i class="fas fa-arrow-right ms-2"></i></a>
                            <a href="try-on.php" class="btn btn-hero-ai"><i class="fas fa-wand-magic-sparkles"></i> Try It On with AI</a>
                            <a href="about.php" class="btn btn-hero-outline hero-learn-more">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Bar -->
    <section class="features-bar home-section home-features-section">
        <div class="container">
            <div class="home-surface">
                <div class="row home-rail home-rail-features">
                    <div class="col-md-3 col-6 mb-3 mb-md-0">
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fas fa-truck"></i></div>
                            <div>
                                <h6>Nationwide Delivery</h6>
                                <p><?php require_once __DIR__ . '/includes/delivery-zones.php'; echo htmlspecialchars(deliveryFeeFromText()); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3 mb-md-0">
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fas fa-shield-halved"></i></div>
                            <div>
                                <h6>Secure Payment</h6>
                                <p>100% secure checkout</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fas fa-gem"></i></div>
                            <div>
                                <h6>Premium Quality</h6>
                                <p>Handpicked materials</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fas fa-rotate-left"></i></div>
                            <div>
                                <h6>Easy Returns</h6>
                                <p>30-day return policy</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- AI Virtual Try-On Spotlight -->
    <section class="home-section">
        <div class="container ai-spotlight-wrap">
            <div class="ai-spotlight" id="ai-tryon">
                <div class="ai-spotlight-glow ai-glow-1"></div>
                <div class="ai-spotlight-glow ai-glow-2"></div>
                <div class="container position-relative">
                    <div class="row align-items-center g-5">
                        <!-- Copy -->
                        <div class="col-lg-6 reveal">
                            <span class="ai-badge"><i class="fas fa-wand-magic-sparkles"></i> Powered by Google Gemini AI</span>
                            <h2 class="ai-title">See It On <span class="ai-accent">You</span><br>Before You Buy.</h2>
                            <p class="ai-sub">No fitting room? No problem. Open your camera or upload a photo, pick an outfit, and instantly see yourself wearing it — powered by real AI, in just a few seconds.</p>

                            <div class="ai-steps">
                                <div class="ai-step">
                                    <span class="ai-step-num">1</span>
                                    <div><strong>Capture</strong><small>Camera or upload photo</small></div>
                                </div>
                                <span class="ai-step-arrow"><i class="fas fa-arrow-right"></i></span>
                                <div class="ai-step">
                                    <span class="ai-step-num">2</span>
                                    <div><strong>AI Generates</strong><small>Gemini renders the fit</small></div>
                                </div>
                                <span class="ai-step-arrow"><i class="fas fa-arrow-right"></i></span>
                                <div class="ai-step">
                                    <span class="ai-step-num">3</span>
                                    <div><strong>Wear It</strong><small>Save, compare, buy</small></div>
                                </div>
                            </div>

                            <div class="ai-cta-row">
                                <a href="try-on.php" class="btn btn-ai-primary"><i class="fas fa-camera"></i> Launch Virtual Try-On</a>
                                <a href="try-on.php" class="btn btn-ai-ghost"><i class="fas fa-hat-wizard"></i> Ask the AI Stylist</a>
                            </div>
                        </div>

                        <!-- Viewfinder mock -->
                        <div class="col-lg-6 reveal">
                            <div class="ai-viewfinder">
                                <span class="vf-live"><span class="vf-dot"></span> LIVE</span>
                                <span class="vf-tag">AI Try-On</span>
                                <span class="vf-corner vf-tl"></span>
                                <span class="vf-corner vf-tr"></span>
                                <span class="vf-corner vf-bl"></span>
                                <span class="vf-corner vf-br"></span>
                                <div class="vf-scan"></div>
                                <i class="fas fa-user vf-silhouette" aria-hidden="true"></i>
                                <div class="vf-chip vf-chip-1"><i class="fas fa-shirt"></i> Polo Shirt</div>
                                <div class="vf-chip vf-chip-2"><i class="fas fa-wand-magic-sparkles"></i> Best match for you</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    // Real counts for the category cards (they used to say "250+" etc.).
    // Same rules as the shop links they open: Men/Women/Kids by gender,
    // Accessories by category, active products only.
    $categoryCounts = ['mens' => null, 'womens' => null, 'kids' => null, 'accessories' => null];
    if ($res = $conn->query("SELECT gender, COUNT(*) AS c FROM products WHERE status = 'active' GROUP BY gender")) {
        while ($row = $res->fetch_assoc()) {
            if (array_key_exists($row['gender'], $categoryCounts)) $categoryCounts[$row['gender']] = (int) $row['c'];
        }
    }
    if ($res = $conn->query("SELECT COUNT(*) AS c FROM products WHERE status = 'active' AND category = 'accessories'")) {
        $categoryCounts['accessories'] = (int) $res->fetch_assoc()['c'];
    }
    $countLabel = function ($key) use ($categoryCounts) {
        $n = $categoryCounts[$key];
        if ($n === null) return 'Shop now';
        return $n . ' product' . ($n === 1 ? '' : 's');
    };
    ?>

    <!-- Shop by Category -->
    <section class="py-5 home-section">
        <div class="container">
            <div class="home-surface">
                <div class="section-heading reveal">
                    <h2>Shop by Category</h2>
                    <p>Browse our curated collections for every style and occasion</p>
                </div>
                <div class="row g-4 home-rail home-rail-cats">
                    <div class="col-md-3 col-6">
                        <a href="shop.php?gender=mens" class="category-card">
                            <img src="images/hero/mens-card-sm.jpg" alt="Men" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='https://placehold.co/400x500/1a1a1a/ffffff?text=Men'">
                            <div class="category-overlay">
                                <h4>Men</h4>
                                <span><?php echo $countLabel('mens'); ?></span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="shop.php?gender=womens" class="category-card">
                            <img src="images/hero/womens-card-sm.jpg" alt="Women" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='https://placehold.co/400x500/333333/ffffff?text=Women'">
                            <div class="category-overlay">
                                <h4>Women</h4>
                                <span><?php echo $countLabel('womens'); ?></span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="shop.php?gender=kids" class="category-card">
                            <img src="images/hero/kids-card-sm.jpg" alt="Kids" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='https://placehold.co/400x500/555555/ffffff?text=Kids'">
                            <div class="category-overlay">
                                <h4>Kids</h4>
                                <span><?php echo $countLabel('kids'); ?></span>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="shop.php?category=accessories" class="category-card">
                            <img src="images/hero/accessories-card.jpg" alt="Accessories" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='https://placehold.co/400x500/777777/ffffff?text=Accessories'">
                            <div class="category-overlay">
                                <h4>Accessories</h4>
                                <span><?php echo $countLabel('accessories'); ?></span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3D Design Studio spotlight -->
    <section class="home-section">
        <div class="container ai-spotlight-wrap">
            <div class="ai-spotlight studio-spotlight" id="design-studio">
                <div class="ai-spotlight-glow ai-glow-1"></div>
                <div class="container position-relative">
                    <div class="row align-items-center g-5">
                        <!-- Starts on the flat shirt. The Design Studio's real 3D model only
                             downloads when 3D is tapped; the flat shirt stays if 3D cannot run. -->
                        <div class="col-lg-6 order-2 order-lg-1 reveal">
                            <div class="studio-stage">
                                <div class="studio-view" role="group" aria-label="Preview type">
                                    <button type="button" class="studio-view-btn active" id="studioView2d" aria-pressed="true">2D</button>
                                    <button type="button" class="studio-view-btn" id="studioView3d" aria-pressed="false"><i class="fas fa-cube" aria-hidden="true"></i> 3D</button>
                                </div>
                                <div class="studio-3d" id="garment3d" data-showcase>
                                    <div id="garment3dStatus" hidden></div>
                                </div>
                                <div class="studio-turn" aria-hidden="true">
                                    <svg class="studio-shirt" viewBox="0 0 400 470" xmlns="http://www.w3.org/2000/svg">
                                        <defs>
                                            <radialGradient id="studioFabric" cx="50%" cy="30%" r="80%">
                                                <stop offset="0%" stop-color="#ffffff" stop-opacity="0.22"/>
                                                <stop offset="100%" stop-color="#000000" stop-opacity="0.18"/>
                                            </radialGradient>
                                            <path id="studioArc" d="M130,175 Q200,120 270,175"/>
                                        </defs>
                                        <g class="studio-shirt-fill">
                                            <path d="M120,60 L100,60 Q60,60 50,100 L30,160 L70,180 L90,120 L90,420 Q90,440 110,440 L290,440 Q310,440 310,420 L310,120 L330,180 L370,160 L350,100 Q340,60 300,60 L280,60 Q270,40 250,30 L200,20 L150,30 Q130,40 120,60 Z"/>
                                        </g>
                                        <path d="M120,60 L100,60 Q60,60 50,100 L30,160 L70,180 L90,120 L90,420 Q90,440 110,440 L290,440 Q310,440 310,420 L310,120 L330,180 L370,160 L350,100 Q340,60 300,60 L280,60 Q270,40 250,30 L200,20 L150,30 Q130,40 120,60 Z" fill="url(#studioFabric)"/>
                                        <ellipse cx="200" cy="55" rx="55" ry="20" fill="none" stroke="rgba(0,0,0,0.18)" stroke-width="2"/>
                                        <text class="studio-print" font-size="30" font-weight="800" text-anchor="middle"><textPath href="#studioArc" startOffset="50%">TEAM</textPath></text>
                                        <text class="studio-print" x="200" y="265" font-size="96" font-weight="900" text-anchor="middle">23</text>
                                        <text x="330" y="150" font-size="24" text-anchor="middle">⭐</text>
                                    </svg>
                                </div>
                                <div class="studio-chip studio-chip-1"><i class="fas fa-swatchbook"></i> 5 ready templates</div>
                                <div class="studio-chip studio-chip-2"><i class="fas fa-wand-magic-sparkles"></i> AI artwork &amp; logos</div>
                                <div class="studio-swatches" role="group" aria-label="Shirt colour">
                                    <button type="button" class="studio-swatch active" style="background:#FF4136" data-color="#FF4136" data-ink="#FFFFFF" aria-label="Red" aria-pressed="true"></button>
                                    <button type="button" class="studio-swatch" style="background:#001F3F" data-color="#001F3F" data-ink="#FFDC00" aria-label="Navy" aria-pressed="false"></button>
                                    <button type="button" class="studio-swatch" style="background:#FFFFFF" data-color="#FFFFFF" data-ink="#1a1a1a" aria-label="White" aria-pressed="false"></button>
                                    <button type="button" class="studio-swatch" style="background:#2ECC40" data-color="#2ECC40" data-ink="#FFFFFF" aria-label="Green" aria-pressed="false"></button>
                                </div>
                            </div>
                        </div>

                        <!-- Copy -->
                        <div class="col-lg-6 order-1 order-lg-2 reveal">
                            <span class="ai-badge"><i class="fas fa-palette"></i> 3D Design Studio</span>
                            <h2 class="ai-title">Design It. <span class="ai-accent">See It in 3D.</span><br>Wear It.</h2>
                            <p class="ai-sub">Start from a ready-made template or a blank shirt. Add your own text, artwork, sleeve prints and company logo, then turn it around in live 3D before you order.</p>

                            <div class="ai-steps">
                                <div class="ai-step">
                                    <span class="ai-step-num">1</span>
                                    <div><strong>Design</strong><small>Templates, text, AI art</small></div>
                                </div>
                                <span class="ai-step-arrow"><i class="fas fa-arrow-right"></i></span>
                                <div class="ai-step">
                                    <span class="ai-step-num">2</span>
                                    <div><strong>Preview</strong><small>Live 3D, any colour</small></div>
                                </div>
                                <span class="ai-step-arrow"><i class="fas fa-arrow-right"></i></span>
                                <div class="ai-step">
                                    <span class="ai-step-num">3</span>
                                    <div><strong>Order</strong><small>Print-ready files</small></div>
                                </div>
                            </div>

                            <div class="ai-cta-row">
                                <a href="custom-design.php" class="btn btn-ai-primary"><i class="fas fa-palette"></i> Start Designing</a>
                                <a href="custom-design.php?templates=1" class="btn btn-ai-ghost"><i class="fas fa-swatchbook"></i> Browse Templates</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <?php
    $featured_result = $conn->query("SELECT * FROM products WHERE status = 'active' ORDER BY id DESC LIMIT 8");
    ?>
    <section class="py-5 home-section" style="background: var(--bg-light);">
        <div class="container">
            <div class="home-surface">
                <div class="d-flex justify-content-between align-items-end mb-4 section-heading-split">
                    <div class="section-heading text-start mb-0">
                        <h2>New Arrivals</h2>
                        <p>The latest pieces added to our shop</p>
                    </div>
                    <a href="shop.php" class="btn btn-outline-dark btn-sm" style="white-space:nowrap;">View All <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="row g-4 home-rail">
                    <?php if ($featured_result && $featured_result->num_rows > 0): ?>
                        <?php while ($p = $featured_result->fetch_assoc()): ?>
                            <div class="col-lg-3 col-md-4 col-6">
                                <div class="product-card">
                                    <div class="product-image-wrapper">
                                        <a href="product.php?id=<?php echo (int)$p['id']; ?>" aria-label="View <?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>" style="display:block;">
                                        <img src="<?php echo htmlspecialchars(productThumb($p['image'])); ?>" loading="lazy" decoding="async" alt="<?php echo htmlspecialchars($p['name']); ?>" class="product-image" onerror="this.onerror=null;this.src='https://placehold.co/300x380/f0f0f0/999?text=<?php echo urlencode($p['name']); ?>'">
                                        </a>
                                        <div class="product-actions">
                                            <!-- A colour and size must be chosen first, so this opens the product page
                                                 (there is no add-to-cart function on the home page). -->
                                            <a class="product-action-btn" href="product.php?id=<?php echo (int)$p['id']; ?>" title="Choose colour &amp; size">
                                                <i class="fas fa-shopping-bag"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="product-body">
                                        <h5 class="product-name"><a href="product.php?id=<?php echo (int)$p['id']; ?>" style="color:inherit; text-decoration:none;"><?php echo htmlspecialchars($p['name']); ?></a></h5>
                                        <div class="product-price">₱<?php echo number_format($p['price'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <h5>No products yet</h5>
                                <p>Check back soon for our latest arrivals!</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Promo Banner -->
    <section class="home-section">
        <div class="container">
            <div class="promo-banner">
                <img src="images/hero/promo-banner.jpg" alt="<?php echo brandHtml('name'); ?> collection">
                <div class="promo-content container text-center">
                    <span class="hero-badge" style="background:rgba(255,255,255,0.2); color:#fff; border-color:rgba(255,255,255,0.3);">New Arrivals</span>
                    <h2>Discover Our Premium Collection</h2>
                    <p>Explore curated styles crafted with quality fabrics and timeless design — made to fit every occasion.</p>
                    <a href="shop.php" class="btn btn-light btn-lg px-5">Shop Now <i class="fas fa-arrow-right ms-2"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- Special Discounts  -->
    <section class="py-5 home-section">
        <div class="container">
            <div class="home-surface">
                <div class="section-heading reveal">
                    <h2>Special Discounts</h2>
                    <p>We support our community with exclusive discounts</p>
                </div>
                <div class="row g-4 justify-content-center home-discounts">
                    <div class="col-md-5">
                        <div class="discount-card">
                            <div class="discount-icon"><i class="fas fa-wheelchair"></i></div>
                            <h5>PWD Discount</h5>
                            <p class="discount-rate">20% OFF</p>
                            <p class="discount-copy">Valid PWD ID required at checkout</p>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="discount-card">
                            <div class="discount-icon"><i class="fas fa-users"></i></div>
                            <h5>Senior Citizen Discount</h5>
                            <p class="discount-rate">20% OFF</p>
                            <p class="discount-copy">Valid Senior Citizen ID required at checkout</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    require_once 'includes/apparel-config.php';
    $studioShirt = getApparelConfig()['tshirt'];
    ?>
    <script type="importmap">
    {
        "imports": {
            "three": "./js/lib/three/build/three.module.js",
            "three/addons/": "./js/lib/three/examples/jsm/"
        }
    }
    </script>
    <script>
    // Design Studio spotlight: the swatches recolour the shirt, flat or 3D.
    (function () {
        var fill = document.querySelector('.studio-shirt-fill');
        var stage = document.querySelector('.studio-stage');
        if (!fill || !stage) return;

        var MODEL = <?php echo json_encode($studioShirt['model3d']); ?>;
        var ASPECT = <?php echo (float) ($studioShirt['area']['h'] / $studioShirt['area']['w']); ?>;
        var color = '#FF4136', ink = '#FFFFFF';

        // The same "TEAM 23" as the flat shirt, drawn for the print area.
        function printArt() {
            var cv = document.createElement('canvas');
            cv.width = 700;
            cv.height = Math.round(700 * ASPECT);
            var c = cv.getContext('2d');
            c.fillStyle = ink;
            c.textAlign = 'center';
            c.font = '800 130px Arial, Helvetica, sans-serif';
            var word = 'TEAM', R = 620, cx = 350, cy = 230 + R;
            var total = 0;
            word.split('').forEach(function (ch) { total += c.measureText(ch).width + 8; });
            var at = -total / 2;
            word.split('').forEach(function (ch) {
                var w = c.measureText(ch).width + 8;
                c.save();
                c.translate(cx, cy);
                c.rotate((at + w / 2) / R);
                c.fillText(ch, 0, -R);
                c.restore();
                at += w;
            });
            c.font = '900 440px Arial, Helvetica, sans-serif';
            c.fillText('23', cx, 680);
            return cv;
        }

        function sleeveArt() {
            var cv = document.createElement('canvas');
            cv.width = cv.height = 256;
            var c = cv.getContext('2d');
            c.font = '170px Arial, sans-serif';
            c.textAlign = 'center';
            c.textBaseline = 'middle';
            c.fillText('⭐', 128, 138);
            return cv;
        }

        function paint3D() {
            if (!window.Design3D) return;
            Design3D.show(MODEL, color, 1, ASPECT);
            var art = printArt();
            Design3D.setArtwork(0, 'front', art);
            Design3D.setArtwork(0, 'back', art);
        }

        document.querySelectorAll('.studio-swatch').forEach(function (b) {
            b.addEventListener('click', function () {
                document.querySelectorAll('.studio-swatch').forEach(function (x) {
                    x.classList.toggle('active', x === b);
                    x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
                });
                color = b.dataset.color;
                ink = b.dataset.ink;
                fill.style.fill = color;
                document.querySelectorAll('.studio-print').forEach(function (t) { t.style.fill = ink; });
                paint3D();
            });
        });

        // ---- 2D / 3D ----
        var btn2d = document.getElementById('studioView2d');
        var btn3d = document.getElementById('studioView3d');
        var label3d = btn3d.innerHTML;
        var wants3D = false, loading = false, built = false;

        function markButtons() {
            btn2d.classList.toggle('active', !wants3D);
            btn3d.classList.toggle('active', wants3D);
            btn2d.setAttribute('aria-pressed', wants3D ? 'false' : 'true');
            btn3d.setAttribute('aria-pressed', wants3D ? 'true' : 'false');
        }

        // The flat shirt only leaves once the model is really there.
        function applyView() {
            markButtons();
            stage.classList.toggle('is-3d', wants3D && built);
            stage.classList.toggle('is-loading-3d', loading);
            btn3d.innerHTML = loading ? '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> 3D' : label3d;
        }

        function cannot3D() {
            wants3D = false;
            loading = false;
            btn3d.disabled = true;
            btn3d.title = '3D preview is not available on this device';
            applyView();
            if (typeof showToast === 'function') showToast('3D preview is not available on this device.', 'info');
        }

        window.addEventListener('design3d-built', function () {
            built = true;
            loading = false;
            paint3D();
            applyView();
        });
        // Also sent when the model fails to load.
        window.addEventListener('design3d-ready', function () {
            if (!built && loading && window.Design3D && !Design3D.canShow(MODEL.src)) cannot3D();
        });

        function load3D() {
            loading = true;
            applyView();
            import('./js/design-3d.js?v=<?php echo @filemtime(__DIR__ . '/js/design-3d.js'); ?>').then(function () {
                if (!window.Design3D) { cannot3D(); return; }   // no WebGL
                Design3D.setArtwork(0, 'left', sleeveArt());
                paint3D();
            }).catch(cannot3D);
        }

        btn2d.addEventListener('click', function () {
            wants3D = false;
            applyView();
        });
        btn3d.addEventListener('click', function () {
            wants3D = true;
            if (!built && !loading) load3D();
            else applyView();
        });
    })();

    (function () {
        var els = document.querySelectorAll('.reveal');
        if (!els.length) return;
        if (!('IntersectionObserver' in window)) {
            els.forEach(function (e) { e.classList.add('reveal-in'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) {
                    en.target.classList.add('reveal-in');
                    io.unobserve(en.target);
                }
            });
        }, { threshold: 0.15 });
        els.forEach(function (e) { io.observe(e); });
    })();
    </script>

    <?php include 'includes/footer/footer.php'; ?>
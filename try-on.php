<?php

declare(strict_types=1);

/**
 * Virtual Try-On (LiveLook) page.
 *
 * Recreates the LiveLook layout — live camera stage on the left, a product
 * catalog card and an "original feed" thumbnail on the right — but powered by
 * the existing Thread & Press Hub catalog and Gemini (via includes/tryon-ajax.php).
 *
 * Login is required (same as the shop). Only "wearable" categories are offered
 * for try-on; accessories are excluded.
 */

// This page uses getUserMedia for the live camera feed, so it must opt in to the
// camera/microphone Permissions-Policy before config.php emits its security headers.
define('ALLOW_CAMERA', true);

require 'includes/config.php';
redirectToLogin();

$pageTitle = 'Virtual Try-On';

// Categories that make sense to render on a person.
$wearableCategories = ['t-shirts', 'hoodies', 'dresses', 'pants'];

$placeholders = implode(',', array_fill(0, count($wearableCategories), '?'));
$types        = str_repeat('s', count($wearableCategories));

$sql = "SELECT id, name, price, image, category, gender
        FROM products
        WHERE status = 'active' AND category IN ($placeholders) AND image <> ''
        ORDER BY category, name";

$tryOnProducts = [];
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($types, ...$wearableCategories);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $tryOnProducts[] = [
            'id'     => (int) $row['id'],
            'name'   => $row['name'],
            'price'  => (float) $row['price'],
            'image'  => $row['image'],
            'gender' => $row['gender'] ?? '',
        ];
    }
    $stmt->close();
}

$csrfToken = generateCsrfToken();
?>

<?php include 'includes/header/header.php'; ?>

<link rel="stylesheet" href="css/try-on.css">

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb" style="font-size:0.85rem;">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="shop.php" class="text-decoration-none">Shop</a></li>
            <li class="breadcrumb-item active" aria-current="page">Virtual Try-On</li>
        </ol>
    </nav>

    <div class="tryon-wrap">
        <!-- Top bar -->
        <div class="tryon-topbar">
            <div class="tryon-brand">
                <span class="live-dot" aria-hidden="true"></span>
                <span>LiveLook Try-On</span>
            </div>
            <div class="tryon-topbar-actions">
                <button id="btnCart" class="tryon-icon-btn" type="button" aria-label="Saved looks">
                    <i class="fas fa-bookmark" aria-hidden="true"></i>
                    <span id="cartBadge" class="badge-count" style="display:none;">0</span>
                </button>
                <button id="btnEnd" class="tryon-end-btn" type="button">END</button>
            </div>
        </div>

        <div class="tryon-grid">
            <!-- Main stage -->
            <div class="tryon-stage" id="tryonStage">
                <span class="stage-tag" id="tryonLiveTag">
                    <span class="live-dot" aria-hidden="true"></span> LIVE
                </span>

                <video id="tryonLive" class="mirrored" playsinline muted autoplay></video>
                <img id="tryonResult" class="result" alt="Virtual try-on result" style="display:none;">
                <img id="tryonUpload" class="result" alt="Uploaded photo" style="display:none;">

                <!-- Before / After comparison slider -->
                <div class="tryon-compare" id="tryonCompare">
                    <img id="compareAfter" alt="With garment">
                    <img id="compareBefore" alt="Original">
                    <div class="compare-handle" id="compareHandle"><span></span></div>
                    <span class="compare-tag compare-tag-before">Before</span>
                    <span class="compare-tag compare-tag-after">After</span>
                </div>

                <!-- Loading overlay -->
                <div class="tryon-overlay" id="tryonLoading">
                    <div class="spinner" aria-hidden="true"></div>
                    <p>Generating your try-on… this usually takes a few seconds.</p>
                </div>

                <!-- Permission / error overlay -->
                <div class="tryon-overlay" id="tryonError">
                    <i class="fas fa-video-slash icon-lg" aria-hidden="true"></i>
                    <p id="tryonErrorText">Requesting camera access…</p>
                </div>

                <!-- AI Stylist suggestions overlay -->
                <div class="tryon-overlay tryon-suggest" id="tryonSuggest">
                    <h4 class="suggest-title"><i class="fas fa-hat-wizard" aria-hidden="true"></i> AI Stylist suggests</h4>
                    <div id="suggestList" class="suggest-list"></div>
                    <button id="suggestClose" class="tryon-btn tryon-btn-ghost" type="button">
                        <i class="fas fa-xmark" aria-hidden="true"></i> Close
                    </button>
                </div>

                <!-- Stage controls -->
                <div class="tryon-stage-controls">
                    <button id="btnTryOn" class="tryon-btn tryon-btn-primary" type="button" disabled>
                        <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Try On
                    </button>
                    <button id="btnStylist" class="tryon-btn tryon-btn-ghost" type="button" disabled>
                        <i class="fas fa-hat-wizard" aria-hidden="true"></i> AI Stylist
                    </button>
                    <button id="btnUpload" class="tryon-btn tryon-btn-ghost" type="button">
                        <i class="fas fa-upload" aria-hidden="true"></i> Upload Photo
                    </button>
                    <button id="btnCamera" class="tryon-btn tryon-btn-ghost" type="button">
                        <i class="fas fa-video" aria-hidden="true"></i> Camera On
                    </button>
                    <button id="btnSwitchCam" class="tryon-btn tryon-btn-ghost" type="button" title="Switch camera">
                        <i class="fas fa-camera-rotate" aria-hidden="true"></i>
                    </button>
                    <button id="btnUseCamera" class="tryon-btn tryon-btn-ghost" type="button" style="display:none;">
                        <i class="fas fa-video" aria-hidden="true"></i> Use Camera
                    </button>
                    <button id="btnLive" class="tryon-btn tryon-btn-ghost" type="button" style="display:none;">
                        <i class="fas fa-arrow-rotate-left" aria-hidden="true"></i> Live again
                    </button>
                    <button id="btnSave" class="tryon-btn tryon-btn-ghost" type="button" style="display:none;">
                        <i class="fas fa-bookmark" aria-hidden="true"></i> Save look
                    </button>
                    <button id="btnDownload" class="tryon-btn tryon-btn-ghost" type="button" style="display:none;">
                        <i class="fas fa-download" aria-hidden="true"></i> Download
                    </button>
                    <button id="btnCompare" class="tryon-btn tryon-btn-ghost" type="button" style="display:none;">
                        <i class="fas fa-arrows-left-right" aria-hidden="true"></i> Compare
                    </button>
                    <input type="file" id="uploadInput" accept="image/*" style="display:none;">
                </div>
            </div>

            <!-- Right column -->
            <div class="tryon-side">
                <!-- Product catalog card -->
                <div class="tryon-card">
                    <h3>Product Catalog</h3>
                    <div class="tryon-gender" id="tryonGender">
                        <button type="button" class="tg-btn active" data-gender="all">All</button>
                        <button type="button" class="tg-btn" data-gender="mens">Men</button>
                        <button type="button" class="tg-btn" data-gender="womens">Women</button>
                        <button type="button" class="tg-btn" data-gender="kids">Kids</button>
                    </div>
                    <div class="catalog-figure">
                        <img id="catalogImg" src="" alt="">
                    </div>
                    <div class="catalog-meta">
                        <div class="name" id="catalogName">—</div>
                        <div class="price" id="catalogPrice"></div>
                    </div>
                    <div class="catalog-nav">
                        <button id="catalogPrev" type="button" aria-label="Previous product">
                            <i class="fas fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <span class="counter" id="catalogCounter">0/0</span>
                        <button id="catalogNext" type="button" aria-label="Next product">
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <button id="btnAddCart" class="tryon-btn tryon-btn-primary tryon-card-cart" type="button">
                        <i class="fas fa-cart-plus" aria-hidden="true"></i> Add to Cart
                    </button>
                </div>

                <!-- Original feed thumbnail -->
                <div class="tryon-card">
                    <h3>Original Feed</h3>
                    <div class="original-feed">
                        <video id="tryonThumb" playsinline muted autoplay></video>
                        <span class="stage-tag">Raw camera</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Saved-looks drawer (acts as the "cart") -->
<div class="tryon-drawer-backdrop" id="tryonDrawerBackdrop"></div>
<aside class="tryon-drawer" id="tryonDrawer" aria-label="Saved looks">
    <div class="tryon-drawer-head">
        <h3>Saved Looks</h3>
        <button id="drawerClose" class="tryon-icon-btn" type="button" aria-label="Close">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <div class="tryon-drawer-body" id="drawerBody"></div>
</aside>

<div class="tryon-toast" id="tryonToast" role="status" aria-live="polite"></div>

<script>
    window.TRYON_CONFIG = {
        products: <?php echo json_encode($tryOnProducts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        csrfToken: <?php echo json_encode($csrfToken); ?>,
        endpoints: {
            tryOn: 'includes/tryon-ajax.php',
            save: 'includes/tryon-save.php',
            suggest: 'includes/tryon-suggest.php'
        }
    };
</script>
<script src="js/try-on.js"></script>

<?php include 'includes/footer/footer.php'; ?>

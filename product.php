<?php
require 'includes/config.php';
require_once 'includes/reviews.php';

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($productId <= 0) {
    header('Location: shop.php');
    exit();
}

// Only active products are reachable, matching shop.php's listing filter.
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: shop.php');
    exit();
}

$pageTitle = $product['name'];
$userId    = (int) ($_SESSION['user_id'] ?? 0);
$hasStock  = productsHasStockColumn();
$stockVal  = $hasStock ? (int) ($product['stock'] ?? 0) : null;

// ---------------------------------------------------------------
// Review submission (POST -> redirect -> GET, so a refresh cannot
// resubmit the form).
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!verifyCsrfToken()) {
        $_SESSION['review_flash'] = ['type' => 'err', 'msg' => 'Your session expired. Please try again.'];
    } elseif ($userId <= 0) {
        $_SESSION['review_flash'] = ['type' => 'err', 'msg' => 'Please sign in to leave a review.'];
    } else {
        $result = saveProductReview(
            $userId,
            $productId,
            (int) ($_POST['rating'] ?? 0),
            (string) ($_POST['title'] ?? ''),
            (string) ($_POST['body'] ?? '')
        );
        $_SESSION['review_flash'] = $result['ok']
            ? ['type' => 'ok',  'msg' => 'Thanks — your review is now live.']
            : ['type' => 'err', 'msg' => $result['error']];
    }
    header('Location: product.php?id=' . $productId . '#reviews');
    exit();
}

$flash = $_SESSION['review_flash'] ?? null;
unset($_SESSION['review_flash']);

// ---------------------------------------------------------------
// Review data
// ---------------------------------------------------------------
$summary     = reviewSummary($productId);
$distribution = reviewDistribution($productId);
$reviews     = productReviews($productId);
$myReview    = $userId > 0 ? userReview($userId, $productId) : null;
$canReview   = $userId > 0 && hasPurchasedProduct($userId, $productId);

$colors = array_values(array_filter(array_map('trim', explode(',', (string) $product['available_colors']))));
$sizes  = array_values(array_filter(array_map('trim', explode(',', (string) $product['available_sizes']))));

// Related: same category, excluding this one.
$related = [];
$rel = $conn->prepare(
    "SELECT id, name, price, image FROM products
     WHERE status = 'active' AND category = ? AND id <> ?
     ORDER BY RAND() LIMIT 4"
);
if ($rel) {
    $rel->bind_param('si', $product['category'], $productId);
    $rel->execute();
    $related = $rel->get_result()->fetch_all(MYSQLI_ASSOC);
    $rel->close();
}

/** Star glyph used by the rating picker. */
function pdStarSvg(): string
{
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.6l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3-5.8 3 1.1-6.5L2.6 9.4l6.5-.9z"/></svg>';
}
?>
<?php include 'includes/header/header.php'; ?>
<link rel="stylesheet" href="css/product-modern.css?v=<?php echo @filemtime(__DIR__ . '/css/product-modern.css'); ?>">

<div class="pd-wrap">

    <nav class="pd-crumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a> <span>/</span>
        <a href="shop.php">Shop</a> <span>/</span>
        <a href="shop.php?category=<?php echo urlencode($product['category']); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $product['category']))); ?></a>
        <span>/</span>
        <span><?php echo htmlspecialchars($product['name']); ?></span>
    </nav>

    <div class="pd-top">
        <div class="pd-media">
            <img src="images/products/<?php echo htmlspecialchars($product['image']); ?>"
                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                 onerror="this.src='https://placehold.co/600x750/f0f0f0/999?text=<?php echo urlencode($product['name']); ?>'">
        </div>

        <div class="pd-info">
            <div class="pd-eyebrow"><?php echo htmlspecialchars(ucfirst($product['gender'] ?? '')); ?> · <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $product['category']))); ?></div>
            <h1 class="pd-title"><?php echo htmlspecialchars($product['name']); ?></h1>

            <div class="pd-rating-line">
                <?php echo renderStars((float) $summary['avg'], 17); ?>
                <?php if ($summary['count'] > 0): ?>
                    <strong><?php echo number_format((float) $summary['avg'], 1); ?></strong>
                    <a href="#reviews"><?php echo (int) $summary['count']; ?> review<?php echo $summary['count'] === 1 ? '' : 's'; ?></a>
                <?php else: ?>
                    <span>No reviews yet</span>
                <?php endif; ?>
            </div>

            <div class="pd-price">₱<?php echo number_format((float) $product['price'], 2); ?></div>

            <?php if ($stockVal !== null): ?>
                <?php if ($stockVal <= 0): ?>
                    <div class="pd-stock out">Out of stock</div>
                <?php elseif ($stockVal <= 5): ?>
                    <div class="pd-stock low">Only <?php echo $stockVal; ?> left in stock</div>
                <?php else: ?>
                    <div class="pd-stock ok">In stock</div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($product['description'])): ?>
                <p class="pd-desc"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            <?php endif; ?>

            <?php if ($colors): ?>
            <div class="pd-field">
                <span class="pd-label">Colour<?php echo count($colors) > 1 ? 's' : ''; ?> — <span id="pdColorName">Choose one</span></span>
                <div class="pd-swatches">
                    <?php foreach ($colors as $c): ?>
                        <button type="button" class="pd-swatch" data-color="<?php echo htmlspecialchars($c, ENT_QUOTES); ?>"
                                style="background-color: <?php echo getColorCode($c); ?>;"
                                title="<?php echo htmlspecialchars($c, ENT_QUOTES); ?>"
                                aria-label="<?php echo htmlspecialchars($c, ENT_QUOTES); ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($sizes): ?>
            <div class="pd-field">
                <span class="pd-label">Size</span>
                <div class="pd-sizes">
                    <?php foreach ($sizes as $s): ?>
                        <button type="button" class="pd-size" data-size="<?php echo htmlspecialchars($s, ENT_QUOTES); ?>"><?php echo htmlspecialchars($s); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="pd-field">
                <span class="pd-label">Quantity</span>
                <div class="pd-qty">
                    <button type="button" id="pdMinus" aria-label="Decrease quantity">−</button>
                    <input type="number" id="pdQty" value="1" min="1" max="<?php echo $stockVal !== null && $stockVal > 0 ? $stockVal : 99; ?>" aria-label="Quantity">
                    <button type="button" id="pdPlus" aria-label="Increase quantity">+</button>
                </div>
            </div>

            <div class="pd-actions">
                <button type="button" class="pd-btn pd-btn-primary" id="pdAddBtn"
                        <?php echo ($stockVal !== null && $stockVal <= 0) ? 'disabled' : ''; ?>>
                    <?php echo ($stockVal !== null && $stockVal <= 0) ? 'Out of Stock' : 'Add to Cart'; ?>
                </button>
                <?php if (!($stockVal !== null && $stockVal <= 0)): ?>
                <button type="button" class="pd-btn pd-btn-buy" id="pdBuyBtn">Buy Now</button>
                <?php endif; ?>
                <a href="shop.php" class="pd-btn pd-btn-ghost">Continue shopping</a>
            </div>
        </div>
    </div>

    <!-- ============ Reviews ============ -->
    <section class="pd-reviews" id="reviews">
        <h2 class="pd-h2">Ratings &amp; Reviews</h2>

        <?php if ($flash): ?>
            <div class="pd-alert <?php echo $flash['type'] === 'ok' ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($flash['msg']); ?></div>
        <?php endif; ?>

        <div class="pd-rv-summary">
            <div class="pd-rv-score">
                <b><?php echo $summary['count'] > 0 ? number_format((float) $summary['avg'], 1) : '—'; ?></b>
                <?php echo renderStars((float) $summary['avg'], 18); ?>
                <small><?php echo (int) $summary['count']; ?> review<?php echo $summary['count'] === 1 ? '' : 's'; ?></small>
            </div>
            <div>
                <?php foreach ([5, 4, 3, 2, 1] as $star):
                    $c   = $distribution[$star] ?? 0;
                    $pct = $summary['count'] > 0 ? ($c / $summary['count']) * 100 : 0;
                ?>
                <div class="pd-rv-bar">
                    <i><?php echo $star; ?> star</i>
                    <u><b style="width: <?php echo $pct; ?>%;"></b></u>
                    <em><?php echo $c; ?></em>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($userId <= 0): ?>
            <div class="pd-rv-note">
                <a href="login.php">Sign in</a> to leave a review. Only customers who have ordered this item can rate it.
            </div>
        <?php elseif (!$canReview): ?>
            <div class="pd-rv-note">
                Only customers who have ordered this item can review it — that is what keeps the ratings honest.
            </div>
        <?php else: ?>
            <?php if ($myReview): ?>
            <?php // One review per customer per product: once posted, the (prefilled)
                  // edit form tucks away behind "Edit your review" instead of
                  // sitting open as if nothing was submitted. Opens itself if a
                  // save just failed, so the error and the form are side by side. ?>
            <details class="pd-rv-edit"<?php echo ($flash && $flash['type'] !== 'ok') ? ' open' : ''; ?>>
                <summary class="pd-rv-mine">
                    <span class="pd-rv-mine-text">
                        <?php echo renderStars((float) $myReview['rating'], 15); ?>
                        You've reviewed this item — thank you!
                    </span>
                    <span class="pd-rv-mine-btn">
                        <span class="pd-when-closed">Edit your review</span>
                        <span class="pd-when-open">Close</span>
                    </span>
                </summary>
            <?php endif; ?>
            <form class="pd-rv-form" method="POST" action="product.php?id=<?php echo $productId; ?>">
                <?php echo csrfTokenField(); ?>
                <input type="hidden" name="submit_review" value="1">

                <div class="pd-field">
                    <span class="pd-label"><?php echo $myReview ? 'Update your rating' : 'Your rating'; ?></span>
                    <div class="pd-star-pick">
                        <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                            <input type="radio" name="rating" id="pdStar<?php echo $star; ?>" value="<?php echo $star; ?>"
                                   <?php echo (int) ($myReview['rating'] ?? 0) === $star ? 'checked' : ''; ?> required>
                            <label for="pdStar<?php echo $star; ?>" title="<?php echo $star; ?> star<?php echo $star === 1 ? '' : 's'; ?>"><?php echo pdStarSvg(); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pd-field">
                    <span class="pd-label">Headline <span style="text-transform:none;letter-spacing:0;font-weight:400;">(optional)</span></span>
                    <input type="text" name="title" class="pd-input" maxlength="120"
                           value="<?php echo htmlspecialchars((string) ($myReview['title'] ?? ''), ENT_QUOTES); ?>"
                           placeholder="Sums up your experience">
                </div>

                <div class="pd-field">
                    <span class="pd-label">Your review <span style="text-transform:none;letter-spacing:0;font-weight:400;">(optional)</span></span>
                    <textarea name="body" class="pd-input" maxlength="2000" placeholder="How was the fit, fabric and print?"><?php echo htmlspecialchars((string) ($myReview['body'] ?? '')); ?></textarea>
                </div>

                <button type="submit" class="pd-btn pd-btn-primary" style="flex:0 0 auto;">
                    <?php echo $myReview ? 'Update review' : 'Post review'; ?>
                </button>
            </form>
            <?php if ($myReview): ?>
            </details>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($reviews): ?>
            <div>
                <?php foreach ($reviews as $r): ?>
                <article class="pd-rv-item">
                    <div class="pd-rv-head">
                        <?php echo renderStars((float) $r['rating'], 15); ?>
                        <span class="pd-rv-who"><?php echo htmlspecialchars($r['fullname']); ?></span>
                        <?php if (!empty($r['is_verified'])): ?>
                            <span class="pd-rv-verified">Verified Purchase</span>
                        <?php endif; ?>
                        <span class="pd-rv-when"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></span>
                    </div>
                    <?php if (!empty($r['title'])): ?>
                        <h3 class="pd-rv-title"><?php echo htmlspecialchars($r['title']); ?></h3>
                    <?php endif; ?>
                    <?php if (!empty($r['body'])): ?>
                        <p class="pd-rv-body"><?php echo htmlspecialchars($r['body']); ?></p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="pd-empty">No reviews yet. Be the first to rate this item.</div>
        <?php endif; ?>
    </section>

    <?php if ($related): ?>
    <section class="pd-related">
        <h2 class="pd-h2">You might also like</h2>
        <div class="pd-related-grid">
            <?php foreach ($related as $rp): ?>
            <a class="pd-related-card" href="product.php?id=<?php echo (int) $rp['id']; ?>">
                <img src="<?php echo htmlspecialchars(productThumb($rp['image'])); ?>" loading="lazy" decoding="async" alt="<?php echo htmlspecialchars($rp['name']); ?>"
                     onerror="this.src='https://placehold.co/300x375/f0f0f0/999?text=<?php echo urlencode($rp['name']); ?>'">
                <div>
                    <h6><?php echo htmlspecialchars($rp['name']); ?></h6>
                    <b>₱<?php echo number_format((float) $rp['price'], 2); ?></b>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>

<script src="js/buy-now.js"></script>
<script>
(function () {
    var PRODUCT = {
        id:     <?php echo (int) $product['id']; ?>,
        name:   <?php echo json_encode($product['name']); ?>,
        price:  <?php echo (float) $product['price']; ?>,
        stock:  <?php echo $stockVal === null ? 'null' : (int) $stockVal; ?>,
        hasSizes: <?php echo $sizes ? 'true' : 'false'; ?>,
        hasColors: <?php echo $colors ? 'true' : 'false'; ?>
    };

    var picked = { color: '', size: '' };

    function selectFrom(selector, key, onPick) {
        document.querySelectorAll(selector).forEach(function (el) {
            el.addEventListener('click', function () {
                document.querySelectorAll(selector).forEach(function (o) { o.classList.remove('selected'); });
                el.classList.add('selected');
                picked[key] = el.dataset[key];
                if (onPick) { onPick(el); }
            });
        });
    }

    selectFrom('.pd-swatch', 'color', function (el) {
        var label = document.getElementById('pdColorName');
        if (label) { label.textContent = el.dataset.color; }
    });
    selectFrom('.pd-size', 'size');

    // Quantity stepper
    var qty = document.getElementById('pdQty');
    var max = PRODUCT.stock !== null && PRODUCT.stock > 0 ? PRODUCT.stock : 99;
    function clamp() {
        var v = parseInt(qty.value, 10);
        if (isNaN(v) || v < 1) { v = 1; }
        if (v > max) { v = max; }
        qty.value = v;
        return v;
    }
    document.getElementById('pdMinus').addEventListener('click', function () { qty.value = clamp() - 1; clamp(); });
    document.getElementById('pdPlus').addEventListener('click', function () { qty.value = clamp() + 1; clamp(); });
    qty.addEventListener('change', clamp);

    // Mirrors shop.php exactly: same 'cart' key and the same item shape
    // { id, name, price, quantity, color, size }, so both pages share one cart.
    function updateCartCount() {
        var cart = [];
        try { cart = JSON.parse(localStorage.getItem('cart')) || []; } catch (e) { cart = []; }
        var count = cart.reduce(function (n, i) { return n + (parseInt(i.quantity, 10) || 0); }, 0);
        var badge = document.getElementById('navCartCount');
        if (badge) {
            if (count > 0) { badge.style.display = 'inline-block'; badge.textContent = count; }
            else { badge.style.display = 'none'; }
        }
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') { showToast(msg, type); }
        else { alert(msg); }
    }

    /**
     * Validate stock and the colour/size choice.
     *
     * Shared by Add to Cart and Buy Now so both demand exactly the same thing.
     *
     * @returns {{quantity:number, color:string, size:string}|null}
     */
    function readChoice() {
        if (PRODUCT.stock !== null && PRODUCT.stock <= 0) {
            toast('This item is out of stock.', 'error');
            return null;
        }

        var missing = [];
        if (PRODUCT.hasColors && !picked.color) { missing.push('colour'); }
        if (PRODUCT.hasSizes  && !picked.size)  { missing.push('size'); }
        if (missing.length) {
            toast('Please select ' + missing.join(' and ') + '.', 'error');
            return null;
        }

        return {
            quantity: clamp(),
            color: picked.color || 'Default',
            size: PRODUCT.hasSizes ? picked.size : 'N/A'
        };
    }

    // Straight to checkout with this one item; the cart is left untouched.
    var buyBtn = document.getElementById('pdBuyBtn');
    if (buyBtn) {
        buyBtn.addEventListener('click', function () {
            var choice = readChoice();
            if (!choice) { return; }
            buyNowCheckout({
                id: PRODUCT.id,
                name: PRODUCT.name,
                price: PRODUCT.price,
                quantity: choice.quantity,
                color: choice.color,
                size: choice.size
            });
        });
    }

    /**
     * Put the page back to unselected after the item has gone in the cart.
     *
     * Here the highlight is a CSS class rather than inline styles, but the
     * `picked` object and the quantity have to be reset as well or the next
     * add would silently reuse the previous choice.
     */
    function clearProductSelection() {
        document.querySelectorAll('.pd-swatch.selected, .pd-size.selected')
            .forEach(function (el) { el.classList.remove('selected'); });
        picked.color = '';
        picked.size = '';
        var label = document.getElementById('pdColorName');
        // Back to the prompt the page ships with, not blank.
        if (label) { label.textContent = 'Choose one'; }
        if (qty) { qty.value = 1; }
    }

    var addBtn = document.getElementById('pdAddBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var choice = readChoice();
            if (!choice) { return; }

            var quantity = choice.quantity;
            var color = choice.color;
            var size  = choice.size;

            var cart = [];
            try { cart = JSON.parse(localStorage.getItem('cart')) || []; } catch (e) { cart = []; }

            var existing = cart.find(function (i) {
                return i.id == PRODUCT.id && i.color === color && i.size === size;
            });
            if (existing) {
                existing.quantity += quantity;
                toast(PRODUCT.name + ' quantity updated in cart!', 'success');
            } else {
                cart.push({
                    id: PRODUCT.id,
                    name: PRODUCT.name,
                    price: PRODUCT.price,
                    quantity: quantity,
                    color: color,
                    size: size
                });
                toast(PRODUCT.name + ' added to cart!', 'success');
            }

            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartCount();

            // Reset the page the same way the shop cards do, so the two pages
            // behave alike after a successful add.
            clearProductSelection();
        });
    }

    updateCartCount();
})();
</script>

<?php include 'includes/footer/footer.php'; ?>

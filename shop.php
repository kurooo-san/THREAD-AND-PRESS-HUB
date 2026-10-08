<?php
require 'includes/config.php';
// Guests may browse; the cart, checkout and design studio still need an account.

$pageTitle = 'Shop';

// Labels for the filters. Only these values are accepted from the URL, so
// nothing a visitor types into ?gender= or ?category= reaches the page.
$gender_labels = ['mens' => 'Men', 'womens' => 'Women', 'kids' => 'Kids'];
$gender_titles = ['mens' => "Men's", 'womens' => "Women's", 'kids' => 'Kids'];
$type_labels   = ['t-shirts' => 'T-Shirts', 'hoodies' => 'Hoodies', 'pants' => 'Pants',
                  'dresses' => 'Dresses', 'accessories' => 'Accessories'];
// Types that can be tried on in the Virtual Try-On (same list as try-on.php).
$tryon_types   = ['t-shirts', 'hoodies', 'dresses', 'pants'];
$sort_options  = ['newest', 'price-low', 'price-high', 'name'];

// Get filter parameters from GET request
$selected_gender   = isset($gender_labels[$_GET['gender'] ?? '']) ? $_GET['gender'] : null;
$selected_category = isset($type_labels[$_GET['category'] ?? '']) ? $_GET['category'] : null;
$selected_color    = trim((string) ($_GET['color'] ?? '')) ?: null;
$selected_size     = trim((string) ($_GET['size'] ?? '')) ?: null;
$selected_sort     = in_array($_GET['sort'] ?? '', $sort_options, true) ? $_GET['sort'] : 'newest';
$search_query      = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

/**
 * A shop.php link that keeps every current filter, search and sort, with
 * $changes applied (null removes one). Changing one filter used to drop the
 * search and the sort.
 */
$shopUrl = function (array $changes = []) use ($selected_gender, $selected_category, $selected_color, $selected_size, $selected_sort, $search_query) {
    $q = array_merge([
        'search'   => $search_query,
        'gender'   => $selected_gender,
        'category' => $selected_category,
        'color'    => $selected_color,
        'size'     => $selected_size,
        'sort'     => $selected_sort === 'newest' ? null : $selected_sort,
    ], $changes);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return 'shop.php' . ($q ? '?' . http_build_query($q) : '');
};

// Build the WHERE clause based on filters
$where_clauses = ["status = 'active'"];
$params = [];
$types = "";

if ($search_query !== '') {
    $where_clauses[] = "(name LIKE ? OR description LIKE ? OR category LIKE ?)";
    $like = '%' . $search_query . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

if ($selected_gender) {
    $where_clauses[] = "gender = ?";
    $params[] = $selected_gender;
    $types .= "s";
}

if ($selected_category) {
    $where_clauses[] = "category = ?";
    $params[] = $selected_category;
    $types .= "s";
}

if ($selected_color) {
    $where_clauses[] = "FIND_IN_SET(?, available_colors)";
    $params[] = $selected_color;
    $types .= "s";
}

if ($selected_size) {
    $where_clauses[] = "FIND_IN_SET(?, available_sizes)";
    $params[] = $selected_size;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Sorting
$order_sql = match($selected_sort) {
    'price-low' => 'price ASC',
    'price-high' => 'price DESC',
    'name' => 'name ASC',
    default => 'created_at DESC'
};

// Get all products
$stmt = $conn->prepare("SELECT * FROM products WHERE $where_sql ORDER BY $order_sql");
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products_result = $stmt->get_result();

// Materialised so the star ratings can be fetched in ONE grouped query.
// Looking them up inside the render loop would be a round trip per card,
// and this grid renders 35+ of them.
require_once 'includes/reviews.php';
$products_list   = $products_result->fetch_all(MYSQLI_ASSOC);
$rating_summary  = reviewSummaries(array_column($products_list, 'id'));

// Get unique colors and sizes for filters
$colors_result = $conn->query("SELECT DISTINCT available_colors FROM products WHERE status = 'active'");
$sizes_result = $conn->query("SELECT DISTINCT available_sizes FROM products WHERE status = 'active'");

$all_colors = [];
$all_sizes = [];

if ($colors_result && $colors_result->num_rows > 0) {
    while ($row = $colors_result->fetch_assoc()) {
        $colors = array_map('trim', explode(',', $row['available_colors']));
        $all_colors = array_merge($all_colors, $colors);
    }
    $all_colors = array_unique($all_colors);
    sort($all_colors);
}

if ($sizes_result && $sizes_result->num_rows > 0) {
    while ($row = $sizes_result->fetch_assoc()) {
        $sizes = array_map('trim', explode(',', $row['available_sizes']));
        $all_sizes = array_merge($all_sizes, $sizes);
    }
}

// Define size order
$size_order = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
$clothing_sizes = array_intersect($size_order, array_unique($all_sizes));
$numeric_sizes = array_filter(array_unique($all_sizes), 'is_numeric');
sort($numeric_sizes, SORT_NUMERIC);
$all_sizes = array_values(array_merge($clothing_sizes, $numeric_sizes));
?>

<?php include 'includes/header/header.php'; ?>

<link rel="stylesheet" href="css/shop-modern.css?v=<?php echo @filemtime(__DIR__ . '/css/shop-modern.css'); ?>">
<link rel="stylesheet" href="css/product-modern.css?v=<?php echo @filemtime(__DIR__ . '/css/product-modern.css'); ?>"><!-- star rating styles on the cards -->

<?php
// Count total products for display
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE $where_sql");
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_products = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$count_stmt->close();
?>

<div class="container py-4">
    <?php include 'includes/guest-banner.php'; ?>
    <!-- Page Header -->
    <div class="mb-4 shop-head">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb" style="font-size:0.85rem;">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
                <?php if ($selected_gender || $selected_category): ?>
                    <li class="breadcrumb-item"><a href="shop.php" class="text-decoration-none">Shop</a></li>
                <?php else: ?>
                    <li class="breadcrumb-item active">Shop</li>
                <?php endif; ?>
                <?php if ($selected_gender): ?>
                    <?php if ($selected_category): ?>
                        <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($shopUrl(['category' => null, 'color' => null, 'size' => null])); ?>" class="text-decoration-none"><?php echo $gender_labels[$selected_gender]; ?></a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active"><?php echo $gender_labels[$selected_gender]; ?></li>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($selected_category): ?>
                    <li class="breadcrumb-item active"><?php echo $type_labels[$selected_category]; ?></li>
                <?php endif; ?>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h1 style="font-weight:800; font-size:2rem; margin:0;">
                <?php
                if ($search_query !== '') {
                    echo 'Search: "' . htmlspecialchars($search_query) . '"';
                } elseif ($selected_gender && $selected_category) {
                    echo $gender_titles[$selected_gender] . ' ' . $type_labels[$selected_category];   // e.g. Men's Hoodies
                } elseif ($selected_gender) {
                    echo $gender_titles[$selected_gender] . ' Collection';
                } elseif ($selected_category) {
                    echo $type_labels[$selected_category];
                } else {
                    echo 'All Products';
                }
                ?>
            </h1>
            <a href="custom-design.php" class="btn btn-dark shop-design-cta" style="border-radius:12px; padding:0.6rem 1.5rem; font-weight:600; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem;">
                <i class="fas fa-palette"></i> Design Your Apparel
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <?php $activeFilterCount = count(array_filter([$selected_gender, $selected_category, $selected_color, $selected_size])); ?>
            <div class="shop-sidebar sticky-top" id="shopSidebar" style="top: 90px;">
                <div class="d-flex justify-content-between align-items-center mb-3 shop-filter-head">
                    <h5 style="font-weight:700; margin:0;">Filters<?php if ($activeFilterCount): ?> <span class="shop-filter-count"><?php echo $activeFilterCount; ?></span><?php endif; ?></h5>
                    <div class="d-flex align-items-center gap-3">
                        <?php if ($activeFilterCount): ?>
                            <a href="shop.php" class="text-decoration-none small">Clear all</a>
                        <?php endif; ?>
                        <!-- Phones and tablets: filters fold away so products come first. -->
                        <button type="button" class="shop-filter-toggle d-lg-none" aria-expanded="false" aria-controls="shopSidebar" onclick="toggleShopFilters(this)">
                            <span>Show</span> <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>

                <!-- Shop for: who it is for. Combines with Type. -->
                <div class="filter-section">
                    <h6>Shop for</h6>
                    <div class="filter-pills">
                        <a href="<?php echo htmlspecialchars($shopUrl(['gender' => null])); ?>" class="filter-pill <?php echo !$selected_gender ? 'active' : ''; ?>">Everyone</a>
                        <?php foreach ($gender_labels as $g => $label): ?>
                            <a href="<?php echo htmlspecialchars($shopUrl(['gender' => $g])); ?>" class="filter-pill <?php echo $selected_gender === $g ? 'active' : ''; ?>"><?php echo $label; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Type: what it is. Combines with Shop for (e.g. Men + Hoodies). -->
                <div class="filter-section">
                    <h6>Type</h6>
                    <div class="filter-pills">
                        <a href="<?php echo htmlspecialchars($shopUrl(['category' => null])); ?>" class="filter-pill <?php echo !$selected_category ? 'active' : ''; ?>">All types</a>
                        <?php foreach ($type_labels as $t => $label): ?>
                            <a href="<?php echo htmlspecialchars($shopUrl(['category' => $t])); ?>" class="filter-pill <?php echo $selected_category === $t ? 'active' : ''; ?>"><?php echo $label; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Colors -->
                <?php if (!empty($all_colors)): ?>
                <div class="filter-section">
                    <h6>Colors</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($all_colors as $color): ?>
                            <button type="button" class="color-swatch <?php echo $selected_color === $color ? 'active' : ''; ?>"
                                  style="background-color: <?php echo getColorCode($color); ?>;"
                                  title="<?php echo htmlspecialchars($color); ?>" aria-label="<?php echo htmlspecialchars($color); ?>"
                                  aria-pressed="<?php echo $selected_color === $color ? 'true' : 'false'; ?>"
                                  onclick="filterByColor(<?php echo htmlspecialchars(json_encode((string) $color), ENT_QUOTES); ?>)"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sizes -->
                <?php if (!empty($all_sizes)): ?>
                <div class="filter-section">
                    <h6>Sizes</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($all_sizes as $size): ?>
                            <button type="button" class="size-filter-btn <?php echo $selected_size === $size ? 'active' : ''; ?>"
                                    onclick="filterBySize(<?php echo htmlspecialchars(json_encode((string) $size), ENT_QUOTES); ?>)">
                                <?php echo htmlspecialchars($size); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Products Section -->
        <div class="col-lg-9">
            <!-- Toolbar -->
            <div class="shop-toolbar">
                <span class="text-muted" style="font-size:0.88rem;">Showing <strong><?php echo $total_products; ?></strong> products</span>
                <div class="d-flex align-items-center gap-3">
                    <select class="form-select form-select-sm" style="width:auto; border-radius:8px;" onchange="sortProducts(this.value)">
                        <option value="newest" <?php echo $selected_sort === 'newest' ? 'selected' : ''; ?>>Sort by: Newest</option>
                        <option value="price-low" <?php echo $selected_sort === 'price-low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price-high" <?php echo $selected_sort === 'price-high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name" <?php echo $selected_sort === 'name' ? 'selected' : ''; ?>>Name: A-Z</option>
                    </select>
                </div>
            </div>

            <?php
            $chips = [];
            if ($search_query !== '')  $chips[] = ['“' . $search_query . '”', ['search' => null], null];
            if ($selected_gender)      $chips[] = [$gender_labels[$selected_gender], ['gender' => null], null];
            if ($selected_category)    $chips[] = [$type_labels[$selected_category], ['category' => null], null];
            if ($selected_color)       $chips[] = [$selected_color, ['color' => null], getColorCode($selected_color)];
            if ($selected_size)        $chips[] = ['Size ' . $selected_size, ['size' => null], null];
            ?>
            <?php if ($chips): ?>
            <div class="active-filters">
                <?php foreach ($chips as [$label, $remove, $swatch]): ?>
                    <a class="active-chip" href="<?php echo htmlspecialchars($shopUrl($remove)); ?>" title="Remove this filter">
                        <?php if ($swatch): ?><span class="active-chip-swatch" style="background:<?php echo $swatch; ?>;"></span><?php endif; ?>
                        <?php echo htmlspecialchars($label); ?> <i class="fas fa-xmark" aria-hidden="true"></i>
                    </a>
                <?php endforeach; ?>
                <?php if (count($chips) > 1): ?>
                    <a class="active-chip-clear" href="shop.php">Clear all</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Product Grid -->
            <div class="row g-3">
                <?php if (!empty($products_list)): ?>
                    <?php foreach ($products_list as $product): ?>
                        <div class="col-lg-4 col-md-6 col-6">
                            <div class="product-card">
                                <div class="product-image-wrapper">
                                    <a href="product.php?id=<?php echo (int)$product['id']; ?>" aria-label="View <?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>" style="display:block;">
                                    <img src="<?php echo htmlspecialchars(productThumb($product['image'])); ?>" loading="lazy" decoding="async"
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="product-image"
                                         onerror="this.onerror=null;this.src='https://placehold.co/300x380/f0f0f0/999?text=<?php echo urlencode($product['name']); ?>'">
                                    </a>
                                    <?php if (in_array($product['category'], $tryon_types, true)): ?>
                                    <a class="tryon-chip" href="try-on.php?product=<?php echo (int)$product['id']; ?>" title="See it on you with the Virtual Try-On">
                                        <i class="fas fa-camera" aria-hidden="true"></i> Try On
                                    </a>
                                    <?php endif; ?>
                                    <div class="product-actions">
                                        <button class="product-action-btn" title="Quick Add" 
                                                onclick="quickAddModal(<?php echo (int)$product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name']), ENT_QUOTES); ?>', <?php echo (float)$product['price']; ?>, '<?php echo htmlspecialchars(addslashes($product['available_colors']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($product['available_sizes']), ENT_QUOTES); ?>')">
                                            <i class="fas fa-shopping-bag"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="product-body">
                                    <h5 class="product-name">
                                        <a href="product.php?id=<?php echo (int)$product['id']; ?>" style="color:inherit; text-decoration:none;"><?php echo htmlspecialchars($product['name']); ?></a>
                                    </h5>
                                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                                    <?php $rs = $rating_summary[(int)$product['id']] ?? null; ?>
                                    <a class="rv-card-rating" href="product.php?id=<?php echo (int)$product['id']; ?>#reviews" style="text-decoration:none;">
                                        <?php echo renderStars($rs ? (float)$rs['avg'] : 0.0, 13); ?>
                                        <small><?php echo $rs ? '(' . (int)$rs['count'] . ')' : 'No reviews'; ?></small>
                                    </a>
                                    <?php
                                        $stockVal = isset($product['stock']) ? (int)$product['stock'] : null;
                                        if ($stockVal !== null) {
                                            if ($stockVal <= 0) {
                                                echo '<div class="mt-1"><span class="badge bg-danger">Out of Stock</span></div>';
                                            } elseif ($stockVal <= 5) {
                                                echo '<div class="mt-1"><span class="badge bg-warning text-dark">Only ' . $stockVal . ' left</span></div>';
                                            }
                                        }
                                    ?>
                                    
                                    <!-- Inline Color/Size Selectors (look: .color-option / .size-option in shop-modern.css) -->
                                    <div class="card-options">
                                        <?php
                                        $product_colors = array_filter(array_map('trim', explode(',', (string) $product['available_colors'])));
                                        foreach ($product_colors as $color):
                                        ?>
                                            <span class="color-option" data-product="<?php echo (int)$product['id']; ?>" data-color="<?php echo htmlspecialchars($color); ?>"
                                                  style="background-color:<?php echo getColorCode($color); ?>;"
                                                  title="<?php echo htmlspecialchars($color); ?>"></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="card-options">
                                        <?php
                                        $product_sizes = array_filter(array_map('trim', explode(',', (string) $product['available_sizes'])));
                                        foreach ($product_sizes as $size):
                                        ?>
                                            <span class="size-option" data-product="<?php echo (int)$product['id']; ?>" data-size="<?php echo htmlspecialchars($size); ?>"><?php echo htmlspecialchars($size); ?></span>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="mt-2 card-buttons">
                                        <?php
                                        $isOOS = isset($product['stock']) && (int)$product['stock'] <= 0;
                                        // Built once and reused by both buttons so they always act on the
                                        // same product id, name and price.
                                        $pArgs = (int)$product['id'] . ", '"
                                               . htmlspecialchars(addslashes($product['name']), ENT_QUOTES)
                                               . "', " . (float)$product['price'] . ", 1";
                                        ?>
                                        <button type="button" class="btn btn-dark btn-sm w-100 card-btn"
                                                <?php echo $isOOS ? 'disabled' : ''; ?>
                                                onclick="<?php echo $isOOS ? "showToast('Out of stock','error')" : "addToCart($pArgs)"; ?>">
                                            <i class="fas fa-shopping-bag me-1"></i> <?php echo $isOOS ? 'Out of Stock' : 'Add to Cart'; ?>
                                        </button>
                                        <?php if (!$isOOS): ?>
                                        <button type="button" class="btn btn-sm w-100 mt-1 card-btn card-btn-buy"
                                                onclick="buyNowFromCard(<?php echo $pArgs; ?>)">
                                            <i class="fas fa-bolt me-1"></i> Buy Now
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-filter"></i>
                            <h5>No products found</h5>
                            <p>Try adjusting your filters or browse all products</p>
                            <a href="shop.php" class="btn btn-dark">View All Products</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Add: the bag button on each card opens this sheet (a bottom sheet
     on phones, a dialog on desktop). Colour/size options come from the card
     itself and the add goes through addToCart()/buyNowFromCard(). -->
<div class="app-sheet" id="quickAddSheet" role="dialog" aria-modal="true" aria-labelledby="qaName" hidden>
    <div class="app-sheet-backdrop" data-sheet-close></div>
    <div class="app-sheet-panel">
        <div class="app-sheet-handle" aria-hidden="true"></div>
        <div class="qa-product">
            <img id="qaImg" src="" alt="">
            <div>
                <div class="qa-name" id="qaName"></div>
                <div class="qa-price" id="qaPrice"></div>
            </div>
        </div>
        <div id="qaColorsWrap">
            <div class="app-sheet-label">Colour — <span id="qaColorName">choose one</span></div>
            <div class="qa-options" id="qaColors"></div>
        </div>
        <div id="qaSizesWrap">
            <div class="app-sheet-label">Size</div>
            <div class="qa-options" id="qaSizes"></div>
        </div>
        <div class="app-sheet-label">Quantity</div>
        <div class="qa-qty">
            <button type="button" onclick="qaStep(-1)" aria-label="Decrease quantity">&minus;</button>
            <span id="qaQty">1</span>
            <button type="button" onclick="qaStep(1)" aria-label="Increase quantity">+</button>
        </div>
        <div class="qa-actions">
            <button type="button" class="app-btn app-btn-primary" onclick="qaSubmit('cart')"><i class="fas fa-bag-shopping"></i> Add to Cart</button>
            <button type="button" class="app-btn app-btn-accent" onclick="qaSubmit('buy')"><i class="fas fa-bolt"></i> Buy Now</button>
        </div>
    </div>
</div>

<script>window.IS_LOGGED_IN = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;</script>
<script src="js/buy-now.js?v=<?php echo @filemtime(__DIR__ . '/js/buy-now.js'); ?>"></script>
<script>
// Fallback showToast function in case footer hasn't loaded
if (typeof showToast === 'undefined') {
    function showToast(message, type = 'info') {
        // Simple fallback toast
        const toast = document.createElement('div');
        toast.style.cssText = 'position: fixed; top: 20px; right: 20px; padding: 15px 20px; background: ' + 
            (type === 'error' ? '#dc3545' : type === 'success' ? '#28a745' : '#17a2b8') + 
            '; color: white; border-radius: 4px; z-index: 9999; box-shadow: 0 2px 8px rgba(0,0,0,0.2);';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
}

function toggleShopFilters(btn) {
    const open = document.getElementById('shopSidebar').classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.querySelector('span').textContent = open ? 'Hide' : 'Show';
}

function filterByColor(color) {
    const params = new URLSearchParams(window.location.search);
    if (params.get('color') === color) {
        params.delete('color');
    } else {
        params.set('color', color);
    }
    window.location.href = '?' + params.toString();
}

function filterBySize(size) {
    const params = new URLSearchParams(window.location.search);
    if (params.get('size') === size) {
        params.delete('size');
    } else {
        params.set('size', size);
    }
    window.location.href = '?' + params.toString();
}

function sortProducts(sort) {
    const params = new URLSearchParams(window.location.search);
    if (sort === 'newest') {
        params.delete('sort');
    } else {
        params.set('sort', sort);
    }
    window.location.href = '?' + params.toString();
}

// Colour / size choice on a card. The highlight is the .selected class
// (styled in shop-modern.css); clicking the chosen one again clears it.
function initOptionSelection() {
    document.querySelectorAll('.color-option, .size-option').forEach(el => {
        // Reachable and usable from the keyboard too (Tab, then Enter/Space).
        el.tabIndex = 0;
        el.setAttribute('role', 'button');
        el.setAttribute('aria-label', el.classList.contains('color-option') ? 'Colour ' + el.dataset.color : 'Size ' + el.dataset.size);
        el.setAttribute('aria-pressed', 'false');
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
        });
        el.addEventListener('click', function (e) {
            e.preventDefault();
            const group = this.classList.contains('color-option') ? '.color-option' : '.size-option';
            const wasSelected = this.classList.contains('selected');
            document.querySelectorAll(`${group}[data-product="${this.dataset.product}"]`).forEach(o => {
                o.classList.remove('selected');
                o.setAttribute('aria-pressed', 'false');
            });
            if (!wasSelected) {
                this.classList.add('selected');
                this.setAttribute('aria-pressed', 'true');
            }
        });
    });
}

/** Put one card's colour/size back to unselected. */
function clearCardSelection(productId) {
    document.querySelectorAll(`.color-option[data-product="${productId}"], .size-option[data-product="${productId}"]`)
        .forEach(el => { el.classList.remove('selected'); el.setAttribute('aria-pressed', 'false'); });
}

/**
 * Read and validate the colour/size a card has selected.
 *
 * Shared by Add to Cart and Buy Now so the two can never disagree about what
 * the customer has to choose before they can buy.
 *
 * @returns {{ok: boolean, color: string, size: string}}
 */
function readCardSelection(productId) {
    const colorEl = document.querySelector(`.color-option[data-product="${productId}"].selected`);
    const sizeEl  = document.querySelector(`.size-option[data-product="${productId}"].selected`);

    let color = colorEl ? colorEl.dataset.color : '';
    let size  = sizeEl ? sizeEl.dataset.size : '';

    const hasSizes  = document.querySelectorAll(`.size-option[data-product="${productId}"]`).length > 0;
    const hasColors = document.querySelectorAll(`.color-option[data-product="${productId}"]`).length > 0;

    const missing = [];
    if (hasColors && !color) missing.push('color');
    if (hasSizes && !size)   missing.push('size');
    if (missing.length) {
        showToast(`Please select ${missing.join(' and ')}`, 'error');
        return { ok: false, color: '', size: '' };
    }

    return { ok: true, color: color || 'Default', size: hasSizes ? size : 'N/A' };
}

/** Straight to checkout with just this item; the cart is left alone. */
function buyNowFromCard(productId, productName, price, quantity) {
    quantity = parseInt(quantity) || 1;
    const pick = readCardSelection(productId);
    if (!pick.ok) return;

    buyNowCheckout({
        id: productId,
        name: productName,
        price: parseFloat(price),
        quantity: quantity,
        color: pick.color,
        size: pick.size
    });
}

function addToCart(productId, productName, price, quantity) {
    try {
        quantity = parseInt(quantity) || 1;
        if (quantity < 1) {
            showToast('Please select a valid quantity', 'error');
            return;
        }

        const pick = readCardSelection(productId);
        if (!pick.ok) return;
        let color = pick.color;
        let size  = pick.size;

        // Guest: remember this pick and add it automatically after login.
        if (!requireLogin({ id: productId, name: productName, price: parseFloat(price),
                            quantity: quantity, color: color, size: size }, 'cart')) return;

        // Using localStorage to store cart data. A damaged cart is treated as
        // empty, or nothing could ever be added again.
        let cart = [];
        try { cart = JSON.parse(localStorage.getItem('cart')) || []; } catch (e) { cart = []; }
        if (!Array.isArray(cart)) cart = [];
        
        // Check if same configuration already in cart
        let existingItem = cart.find(item => item.id == productId && item.color === color && item.size === size);
        
        if (existingItem) {
            existingItem.quantity += quantity;
            showToast(`${productName} quantity updated in cart!`, 'success');
        } else {
            cart.push({
                id: productId,
                name: productName,
                price: parseFloat(price),
                quantity: quantity,
                color: color,
                size: size
            });
            showToast(`${productName} added to cart!`, 'success');
        }
        
        localStorage.setItem('cart', JSON.stringify(cart));

        // The item is in the cart now, so reset the card ready for the next
        // pick. Only on success: a failed add returns earlier and keeps the
        // customer's half-finished selection.
        clearCardSelection(productId);

        // Update cart count in navbar
        updateCartCount();
        
    } catch (error) {
        console.error('Error adding to cart:', error);
        showToast('Error adding item to cart. Please try again.', 'error');
    }
}

// initialize option click handlers on page load
document.addEventListener('DOMContentLoaded', initOptionSelection);

function updateCartCount() {
    // A damaged 'cart' in storage must not break the page (this runs on load).
    let cart = [];
    try { cart = JSON.parse(localStorage.getItem('cart')) || []; } catch (e) { cart = []; }
    if (!Array.isArray(cart)) cart = [];
    let count = cart.reduce((sum, item) => sum + (parseInt(item.quantity, 10) || 0), 0);
    // update any inline badge near cart icon
    let badge = document.querySelector('.nav-link i.fa-shopping-cart + .badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
        } else {
            badge.remove();
        }
    }
    // update new navCartCount element
    let navBadge = document.getElementById('navCartCount');
    if (navBadge) {
        if (count > 0) {
            navBadge.style.display = 'inline-block';
            navBadge.textContent = count;
        } else {
            navBadge.style.display = 'none';
        }
    }
}

// Update cart count on page load
updateCartCount();

// ===== Quick Add sheet =====
let qa = null;

function quickAddModal(productId, productName, price) {
    const colorEls = [...document.querySelectorAll(`.color-option[data-product="${productId}"]`)];
    const sizeEls  = [...document.querySelectorAll(`.size-option[data-product="${productId}"]`)];
    const card = (colorEls[0] || sizeEls[0] || document.body).closest('.product-card');
    const cardAdd = card ? card.querySelector('.product-body button.btn-dark') : null;
    if (cardAdd && cardAdd.disabled) { showToast('Out of stock', 'error'); return; }

    qa = { id: productId, name: productName, price: price, color: '', size: '', qty: 1,
           hasColors: colorEls.length > 0, hasSizes: sizeEls.length > 0 };

    const img = card ? card.querySelector('img.product-image') : null;
    document.getElementById('qaImg').src = img ? img.src : '';
    document.getElementById('qaName').textContent = productName;
    document.getElementById('qaPrice').textContent = '₱' + Number(price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('qaQty').textContent = '1';
    document.getElementById('qaColorName').textContent = 'choose one';

    const colors = document.getElementById('qaColors');
    colors.innerHTML = '';
    colorEls.forEach(src => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'qa-color';
        b.style.background = src.style.backgroundColor;
        b.title = src.dataset.color;
        b.setAttribute('aria-label', src.dataset.color);
        b.onclick = () => {
            qa.color = src.dataset.color;
            colors.querySelectorAll('.qa-color').forEach(x => x.classList.toggle('is-selected', x === b));
            document.getElementById('qaColorName').textContent = src.dataset.color;
        };
        colors.appendChild(b);
    });
    document.getElementById('qaColorsWrap').hidden = !qa.hasColors;

    const sizes = document.getElementById('qaSizes');
    sizes.innerHTML = '';
    sizeEls.forEach(src => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'qa-size';
        b.textContent = src.dataset.size;
        b.onclick = () => {
            qa.size = src.dataset.size;
            sizes.querySelectorAll('.qa-size').forEach(x => x.classList.toggle('is-selected', x === b));
        };
        sizes.appendChild(b);
    });
    document.getElementById('qaSizesWrap').hidden = !qa.hasSizes;

    AppSheet.open('quickAddSheet');
}

function qaStep(delta) {
    if (!qa) return;
    qa.qty = Math.max(1, Math.min(99, qa.qty + delta));
    document.getElementById('qaQty').textContent = qa.qty;
}

// Copy the sheet's choice onto the card, then use the card's own add/buy so
// validation and the cart format stay in one place.
function qaSubmit(mode) {
    if (!qa) return;
    const missing = [];
    if (qa.hasColors && !qa.color) missing.push('color');
    if (qa.hasSizes && !qa.size) missing.push('size');
    if (missing.length) { showToast(`Please select ${missing.join(' and ')}`, 'error'); return; }

    clearCardSelection(qa.id);
    if (qa.color) document.querySelector(`.color-option[data-product="${qa.id}"][data-color="${CSS.escape(qa.color)}"]`)?.classList.add('selected');
    if (qa.size) document.querySelector(`.size-option[data-product="${qa.id}"][data-size="${CSS.escape(qa.size)}"]`)?.classList.add('selected');

    const item = qa;
    AppSheet.close();
    if (mode === 'buy') {
        buyNowFromCard(item.id, item.name, item.price, item.qty);
    } else {
        addToCart(item.id, item.name, item.price, item.qty);
    }
}
</script>

<?php 
// Canonical copy now lives in includes/config.php so product.php can use it
// too. Guarded rather than deleted, so this file still works standalone.
if (!function_exists('getColorCode')) {
function getColorCode($colorName) {
    $colors = [
        'Black' => '#000000',
        'White' => '#FFFFFF',
        'Navy' => '#001F3F',
        'Gray' => '#808080',
        'Red' => '#FF4136',
        'Blue' => '#0074D9',
        'Green' => '#2ECC40',
        'Yellow' => '#FFDC00',
        'Pink' => '#FF69B4',
        'Purple' => '#B10DC9',
        'Brown' => '#8B4513',
        'Maroon' => '#800000',
        'Khaki' => '#F0E68C',
        'Beige' => '#F5F5DC',
        'Orange' => '#FF7F00'
    ];
    return $colors[$colorName] ?? '#999999';
}
}
?>

<?php include 'includes/footer/footer.php'; ?>

<?php
require 'includes/config.php';
redirectToLogin();

$pageTitle = 'Shop';

// Get filter parameters from GET request
$selected_gender = $_GET['gender'] ?? null;
$selected_category = $_GET['category'] ?? null;
$selected_color = $_GET['color'] ?? null;
$selected_size = $_GET['size'] ?? null;
$selected_sort = $_GET['sort'] ?? 'newest';
$search_query   = isset($_GET['search']) ? trim($_GET['search']) : '';

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

if ($selected_gender && in_array($selected_gender, ['mens', 'womens', 'kids'])) {
    $where_clauses[] = "gender = ?";
    $params[] = $selected_gender;
    $types .= "s";
}

if ($selected_category && in_array($selected_category, ['accessories', 't-shirts', 'hoodies', 'pants', 'dresses'])) {
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

<link rel="stylesheet" href="css/shop-modern.css">

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
    <!-- Page Header -->
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb" style="font-size:0.85rem;">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item active">Shop</li>
                <?php if ($selected_gender): ?>
                    <li class="breadcrumb-item active"><?php echo ucfirst(str_replace('s', "'s", $selected_gender)); ?></li>
                <?php elseif ($selected_category): ?>
                    <li class="breadcrumb-item active"><?php echo ucfirst(htmlspecialchars($selected_category)); ?></li>
                <?php endif; ?>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h1 style="font-weight:800; font-size:2rem; margin:0;">
                <?php 
                if ($search_query !== '')          echo 'Search: "' . htmlspecialchars($search_query) . '"';
                elseif ($selected_gender === 'womens') echo "Women's Collection";
                elseif ($selected_gender === 'mens')   echo "Men's Collection";
                elseif ($selected_gender === 'kids')   echo "Kids Collection";
                elseif ($selected_category === 'accessories') echo "Accessories";
                else echo "All Products";
                ?>
            </h1>
            <a href="custom-design.php" class="btn btn-dark" style="border-radius:12px; padding:0.6rem 1.5rem; font-weight:600; font-size:0.9rem; display:flex; align-items:center; gap:0.5rem;">
                <i class="fas fa-palette"></i> Design Your Apparel
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <div class="shop-sidebar sticky-top" style="top: 90px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 style="font-weight:700; margin:0;">Filters</h5>
                    <?php if ($selected_gender || $selected_category || $selected_color || $selected_size): ?>
                        <a href="shop.php" class="text-decoration-none small">Clear all</a>
                    <?php endif; ?>
                </div>

                <!-- Categories -->
                <div class="filter-section">
                    <h6>Categories</h6>
                    <?php
                    $colorSizeParams = '';
                    if ($selected_color) $colorSizeParams .= '&color=' . urlencode($selected_color);
                    if ($selected_size) $colorSizeParams .= '&size=' . urlencode($selected_size);
                    ?>
                    <div class="filter-links">
                        <a href="shop.php<?php echo $colorSizeParams ? '?' . ltrim($colorSizeParams, '&') : ''; ?>" class="filter-link <?php echo !$selected_gender && !$selected_category ? 'active' : ''; ?>">All Products</a>
                        <a href="?gender=mens<?php echo $colorSizeParams; ?>" class="filter-link <?php echo $selected_gender === 'mens' ? 'active' : ''; ?>">Men</a>
                        <a href="?gender=womens<?php echo $colorSizeParams; ?>" class="filter-link <?php echo $selected_gender === 'womens' ? 'active' : ''; ?>">Women</a>
                        <a href="?gender=kids<?php echo $colorSizeParams; ?>" class="filter-link <?php echo $selected_gender === 'kids' ? 'active' : ''; ?>">Kids</a>
                        <a href="?category=accessories<?php echo $colorSizeParams; ?>" class="filter-link <?php echo $selected_category === 'accessories' ? 'active' : ''; ?>">Accessories</a>
                    </div>
                </div>

                <!-- Colors -->
                <?php if (!empty($all_colors)): ?>
                <div class="filter-section">
                    <h6>Colors</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($all_colors as $color): ?>
                            <span class="color-swatch <?php echo $selected_color === $color ? 'active' : ''; ?>"
                                  style="background-color: <?php echo getColorCode($color); ?>;<?php echo $selected_color === $color ? ' outline:3px solid var(--accent-green, #2ECC40); outline-offset:2px; transform:scale(1.15);' : ''; ?>"
                                  title="<?php echo htmlspecialchars($color); ?>"
                                  onclick="filterByColor('<?php echo htmlspecialchars($color); ?>')"></span>
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
                                    onclick="filterBySize('<?php echo $size; ?>')">
                                <?php echo $size; ?>
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

            <?php if ($selected_color || $selected_size): ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php if ($selected_color): ?>
                    <span class="badge rounded-pill d-flex align-items-center gap-1" style="background:var(--accent-green, #2ECC40); font-size:0.78rem; padding:0.4rem 0.8rem; cursor:pointer;" onclick="filterByColor('<?php echo htmlspecialchars($selected_color); ?>')">
                        <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:<?php echo getColorCode($selected_color); ?>; border:1px solid rgba(255,255,255,0.5);"></span>
                        <?php echo htmlspecialchars($selected_color); ?> <i class="fas fa-times ms-1" style="font-size:0.65rem;"></i>
                    </span>
                <?php endif; ?>
                <?php if ($selected_size): ?>
                    <span class="badge rounded-pill d-flex align-items-center gap-1" style="background:var(--accent-green, #2ECC40); font-size:0.78rem; padding:0.4rem 0.8rem; cursor:pointer;" onclick="filterBySize('<?php echo htmlspecialchars($selected_size); ?>')">
                        Size: <?php echo htmlspecialchars($selected_size); ?> <i class="fas fa-times ms-1" style="font-size:0.65rem;"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Product Grid -->
            <div class="row g-3">
                <?php if ($products_result && $products_result->num_rows > 0): ?>
                    <?php while ($product = $products_result->fetch_assoc()): ?>
                        <div class="col-lg-4 col-md-6 col-6">
                            <div class="product-card">
                                <div class="product-image-wrapper">
                                    <img src="images/products/<?php echo htmlspecialchars($product['image']); ?>"
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="product-image"
                                         onerror="this.src='https://placehold.co/300x380/f0f0f0/999?text=<?php echo urlencode($product['name']); ?>'">
                                    <div class="product-actions">
                                        <button class="product-action-btn" title="Quick Add" 
                                                onclick="quickAddModal(<?php echo (int)$product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name']), ENT_QUOTES); ?>', <?php echo (float)$product['price']; ?>, '<?php echo htmlspecialchars(addslashes($product['available_colors']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($product['available_sizes']), ENT_QUOTES); ?>')">
                                            <i class="fas fa-shopping-bag"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="product-body">
                                    <h5 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
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
                                    
                                    <!-- Inline Color/Size Selectors -->
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        <?php 
                                        $product_colors = array_map('trim', explode(',', $product['available_colors']));
                                        foreach ($product_colors as $color): 
                                        ?>
                                            <span class="color-option" data-product="<?php echo $product['id']; ?>" data-color="<?php echo htmlspecialchars($color); ?>"
                                                  style="display:inline-block; width:16px; height:16px; border-radius:50%; background-color:<?php echo getColorCode($color); ?>; border:2px solid #e0e0e0; cursor:pointer;"
                                                  title="<?php echo htmlspecialchars($color); ?>"></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        <?php 
                                        $product_sizes = array_map('trim', explode(',', $product['available_sizes']));
                                        $product_sizes = array_filter($product_sizes);
                                        if (!empty($product_sizes)):
                                        foreach ($product_sizes as $size): 
                                        ?>
                                            <span class="size-option" data-product="<?php echo $product['id']; ?>" data-size="<?php echo htmlspecialchars($size); ?>"
                                                  style="font-size:0.7rem; padding:2px 7px; border-radius:4px; background:#f0f0f0; cursor:pointer; display:inline-block;">
                                                  <?php echo htmlspecialchars($size); ?></span>
                                        <?php endforeach; endif; ?>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <?php $isOOS = isset($product['stock']) && (int)$product['stock'] <= 0; ?>
                                        <button type="button" class="btn btn-dark btn-sm w-100" style="border-radius:8px; font-size:0.8rem;<?php echo $isOOS ? ' opacity:0.5; cursor:not-allowed;' : ''; ?>"
                                                <?php echo $isOOS ? 'disabled' : ''; ?>
                                                onclick="<?php echo $isOOS ? "showToast('Out of stock','error')" : "addToCart(".(int)$product['id'].", '".htmlspecialchars(addslashes($product['name']), ENT_QUOTES)."', ".(float)$product['price'].", 1)"; ?>">
                                            <i class="fas fa-shopping-bag me-1"></i> <?php echo $isOOS ? 'Out of Stock' : 'Add to Cart'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
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

// handle color/size selection
function initOptionSelection() {
    console.log('Initializing option selection...');
    
    // Setup color options
    const colorOptions = document.querySelectorAll('.color-option');
    console.log('Found color options:', colorOptions.length);
    
    colorOptions.forEach(el => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const pid = this.dataset.product;
            const color = this.dataset.color;
            console.log('Color clicked - Product:', pid, 'Color:', color);
            
            if (this.classList.contains('selected')) {
                this.classList.remove('selected');
                console.log('Color deselected:', color);
            } else {
                // Select this color and deselect others
                document.querySelectorAll(`.color-option[data-product="${pid}"]`).forEach(e => {
                    e.classList.remove('selected');
                    e.style.border = '2px solid #999';
                    e.style.transform = 'scale(1)';
                });
                this.classList.add('selected');
                this.style.border = '3px solid var(--accent-green)';
                this.style.transform = 'scale(1.1)';
                console.log('Color selected:', color);
            }
        });
    });
    
    // Setup size options
    const sizeOptions = document.querySelectorAll('.size-option');
    console.log('Found size options:', sizeOptions.length);
    
    sizeOptions.forEach(el => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const pid = this.dataset.product;
            const size = this.dataset.size;
            console.log('Size clicked - Product:', pid, 'Size:', size);
            
            if (this.classList.contains('selected')) {
                this.classList.remove('selected');
                this.style.backgroundColor = '#f0f0f0';
                this.style.color = '#000';
                console.log('Size deselected:', size);
            } else {
                // Select this size and deselect others
                document.querySelectorAll(`.size-option[data-product="${pid}"]`).forEach(e => {
                    e.classList.remove('selected');
                    e.style.backgroundColor = '#f0f0f0';
                    e.style.color = '#000';
                });
                this.classList.add('selected');
                this.style.backgroundColor = 'var(--accent-green)';
                this.style.color = 'white';
                this.style.fontWeight = 'bold';
                console.log('Size selected:', size);
            }
        });
    });
}

function addToCart(productId, productName, price, quantity) {
    try {
        console.log('Adding to cart:', { productId, productName, price, quantity });
        
        quantity = parseInt(quantity) || 1;
        if (quantity < 1) {
            showToast('Please select a valid quantity', 'error');
            return;
        }

        // read selected options
        let colorEl = document.querySelector(`.color-option[data-product="${productId}"].selected`);
        let sizeEl = document.querySelector(`.size-option[data-product="${productId}"].selected`);
        
        console.log('Selected elements:', { colorEl, sizeEl });
        
        let color = colorEl ? colorEl.dataset.color : '';
        let size = sizeEl ? sizeEl.dataset.size : '';

        // Check if product has size options available
        let hasSizes = document.querySelectorAll(`.size-option[data-product="${productId}"]`).length > 0;

        console.log('Selected color and size:', { color, size, hasSizes });

        if (!color || (!size && hasSizes)) {
            let missingItems = [];
            if (!color) missingItems.push('color');
            if (!size && hasSizes) missingItems.push('size');
            showToast(`Please select ${missingItems.join(' and ')}`, 'error');
            return;
        }

        if (!hasSizes) size = 'N/A';

        // Using localStorage to store cart data
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        
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
        console.log('Cart updated:', cart);
        
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
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    let count = cart.reduce((sum, item) => sum + item.quantity, 0);
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
</script>

<?php 
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
?>

<?php include 'includes/footer/footer.php'; ?>

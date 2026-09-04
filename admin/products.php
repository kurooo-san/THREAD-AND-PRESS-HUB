<?php
require '../includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Manage Products';
$error = '';
$success = '';
if (isset($_GET['ai_added'])) {
    $success = 'AI-generated product saved to the store!';
}
$hasStock = productsHasStockColumn();
$hasDesign = productsHasDesignColumn();

// Quick stock update (AJAX or inline form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_stock_update'])) {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission.';
    } elseif (!$hasStock) {
        $error = 'Stock column not present. Run migrate_system_fixes.sql first.';
    } else {
        $pid = (int)($_POST['product_id'] ?? 0);
        $newStock = max(0, (int)($_POST['stock'] ?? 0));
        $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->bind_param("ii", $newStock, $pid);
        if ($stmt->execute()) {
            logAudit('stock_updated', 'product', $pid, "Stock set to $newStock");
            $success = "Stock updated for product #$pid (now $newStock).";
        } else {
            $error = 'Failed to update stock.';
        }
        $stmt->close();
    }
}

// Handle add/update product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id']) && !isset($_POST['quick_stock_update'])) {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission.';
    } else {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = sanitizeInput($_POST['category'] ?? '');
    $gender = sanitizeInput($_POST['gender'] ?? 'mens');
    $status = sanitizeInput($_POST['status'] ?? 'active');
    $image = $_FILES['image']['name'] ?? '';

    // Server-side image validation
    if (!empty($image)) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['image']['type'];
        $file_ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $real_type = $finfo->file($_FILES['image']['tmp_name']);
        if (!in_array($real_type, $allowed_types) || !in_array($file_ext, $allowed_exts)) {
            $error = 'Invalid image file type! Only JPG, PNG, GIF, and WEBP are allowed.';
        }
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $error = 'Image file size must be less than 5MB.';
        }
    }

    $available_colors = isset($_POST['available_colors']) ? implode(',', array_map('sanitizeInput', $_POST['available_colors'])) : '';
    $available_sizes = ($category === 'accessories') ? '' : (isset($_POST['available_sizes']) ? implode(',', array_map('sanitizeInput', $_POST['available_sizes'])) : '');
    $stock_input = isset($_POST['stock']) ? max(0, (int)$_POST['stock']) : 100;

    if (!empty($error)) {
        // Image validation already failed above
    } elseif (empty($name) || $price <= 0 || empty($category)) {
        $error = 'Please fill in all required fields with valid values!';
    } else {
        if ($product_id === 0) {
            // Add new product
            if (empty($image)) {
                $error = 'Please upload an image!';
            } else {
                $image = 'apparel_' . time() . '_' . basename($image);
                $target_path = '../images/products/' . $image;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    if ($hasStock) {
                        $stmt = $conn->prepare("INSERT INTO products (name, description, price, category, gender, available_colors, available_sizes, image, status, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssdssssssi", $name, $description, $price, $category, $gender, $available_colors, $available_sizes, $image, $status, $stock_input);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO products (name, description, price, category, gender, available_colors, available_sizes, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssdssssss", $name, $description, $price, $category, $gender, $available_colors, $available_sizes, $image, $status);
                    }
                    
                    if ($stmt->execute()) {
                        logAudit('product_added', 'product', $conn->insert_id, "Product: $name");
                        $success = 'Product added successfully!';
                    } else {
                        $error = 'Failed to add product!';
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to upload image!';
                }
            }
        } else {
            // Update product
            if (!empty($image)) {
                $image = 'apparel_' . time() . '_' . basename($image);
                $target_path = '../images/products/' . $image;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    if ($hasStock) {
                        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, gender = ?, available_colors = ?, available_sizes = ?, image = ?, status = ?, stock = ? WHERE id = ?");
                        $stmt->bind_param("ssdssssssii", $name, $description, $price, $category, $gender, $available_colors, $available_sizes, $image, $status, $stock_input, $product_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, gender = ?, available_colors = ?, available_sizes = ?, image = ?, status = ? WHERE id = ?");
                        $stmt->bind_param("ssdssssssi", $name, $description, $price, $category, $gender, $available_colors, $available_sizes, $image, $status, $product_id);
                    }
                } else {
                    $error = 'Failed to upload image!';
                }
            } else {
                if ($hasStock) {
                    $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, gender = ?, available_colors = ?, available_sizes = ?, status = ?, stock = ? WHERE id = ?");
                    $stmt->bind_param("ssdsssssii", $name, $description, $price, $category, $gender, $available_colors, $available_sizes, $status, $stock_input, $product_id);
                } else {
                    $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, gender = ?, available_colors = ?, available_sizes = ?, status = ? WHERE id = ?");
                    $stmt->bind_param("ssdsssssi", $name, $description, $price, $category, $gender, $available_colors, $available_sizes, $status, $product_id);
                }
            }
            
            if (isset($stmt) && $stmt->execute()) {
                logAudit('product_updated', 'product', $product_id, "Product: $name");
                $success = 'Product updated successfully!';
            } else {
                $error = 'Failed to update product!';
            }
            if (isset($stmt)) $stmt->close();
        }
    }
    } // end CSRF check
}

// Handle delete product (POST only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!verifyCsrfToken()) {
        $error = 'Invalid form submission.';
    } else {
        $delete_id = intval($_POST['delete_id']);

        // Check if product is referenced by any order
        $check = $conn->prepare("SELECT COUNT(*) AS c FROM order_items WHERE product_id = ?");
        $check->bind_param("i", $delete_id);
        $check->execute();
        $hasOrders = (int)($check->get_result()->fetch_assoc()['c'] ?? 0) > 0;
        $check->close();

        if ($hasOrders) {
            // Soft-delete: mark as inactive so it won't show in shop but order history is preserved
            $stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                logAudit('product_archived', 'product', $delete_id, 'Product archived (has order history)');
                $success = 'Product is linked to existing orders, so it was archived (set to inactive) instead of deleted. Order history is preserved.';
            } else {
                $error = 'Failed to archive product!';
            }
            $stmt->close();
        } else {
            // Safe to hard-delete
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                logAudit('product_deleted', 'product', $delete_id, 'Product deleted');
                $success = 'Product deleted successfully!';
            } else {
                $error = 'Failed to delete product!';
            }
            $stmt->close();
        }
    }
}

// Get all products
$products = $conn->query("SELECT * FROM products ORDER BY category, name");
?>

<?php include '../includes/header/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
    <div class="ad-head">
        <div>
            <h1>Manage Products</h1>
            <p>Add, edit and restock everything in the catalogue.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- AI Product Generator (drafts only — nothing is saved until you approve) -->
    <div class="ad-card" style="margin-bottom:18px;" id="aiProductCard">
        <div class="ad-card-head" style="margin-bottom:12px;">
            <div>
                <h2>AI Generate Product <span class="ad-tag">Gemini</span></h2>
                <p>Describe a product idea — the AI drafts the photo, name, description and price. Nothing is added to the store until you review and click Save.</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <input type="text" class="form-control" id="aiProdPrompt" maxlength="400" style="flex:1; min-width:240px;" placeholder="e.g. vintage sunset graphic tee for men">
            <button type="button" class="ad-btn-dark" style="height:42px;margin-left:0;" id="aiProdGenerateBtn" onclick="aiProdGenerate()">Generate</button>
        </div>

        <!-- Draft preview (hidden until a draft exists) -->
        <div id="aiProdPreview" style="display:none; margin-top:1.25rem;">
            <div class="row g-3">
                <div class="col-md-4">
                    <img id="aiProdImg" src="" alt="AI generated product" style="width:100%; border-radius:12px; border:1px solid var(--border-light,#e5e5e5);">

                    <!-- The print artwork on its own — what actually goes to the printer -->
                    <div id="aiProdDesignBox" style="display:none; margin-top:12px; padding:12px; border:1px dashed #ddc9a8; border-radius:12px; background:#faf6ef;">
                        <div style="display:flex; align-items:center; gap:6px; font-size:10.5px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#8a5a12; margin-bottom:8px;">
                            <span style="display:inline-flex;width:14px;height:14px;"><?php echo adminIcon('print'); ?></span> Print file
                        </div>
                        <img id="aiProdDesignImg" src="" alt="Print-ready design artwork" style="width:100%; border-radius:8px; background:#fff; border:1px solid var(--border-light,#e5e5e5);">
                        <button type="button" class="ad-btn-ghost" style="width:100%;justify-content:center;margin-top:8px;" id="aiProdDesignBtn">Download design (PNG)</button>
                        <small class="text-muted d-block mt-1" style="font-size:0.7rem; line-height:1.3;">
                            Background removed on download — ready for DTG / screen printing.
                        </small>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" id="aiProdName" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" class="form-control" id="aiProdPrice" min="1" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select class="form-select" id="aiProdGender">
                                <option value="mens">Men</option>
                                <option value="womens">Women</option>
                                <option value="kids">Kids</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="aiProdCategory">
                                <option value="t-shirts">T-Shirts</option>
                                <option value="hoodies">Hoodies</option>
                                <option value="pants">Pants</option>
                                <option value="dresses">Dresses</option>
                                <option value="accessories">Accessories</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Stock</label>
                            <input type="number" class="form-control" id="aiProdStock" min="0" value="100">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="aiProdDesc" rows="2" maxlength="500"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Colors <small class="text-muted">(comma-separated)</small></label>
                            <input type="text" class="form-control" id="aiProdColors" placeholder="Black, White">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sizes <small class="text-muted">(comma-separated; blank for accessories)</small></label>
                            <input type="text" class="form-control" id="aiProdSizes" placeholder="S, M, L, XL">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="ad-btn-dark" style="height:40px;margin-left:0;" id="aiProdSaveBtn" onclick="aiProdSave()">Save to Store</button>
                        <button type="button" class="ad-btn-ghost" onclick="aiProdGenerate()">Regenerate</button>
                        <button type="button" class="ad-btn-ghost" onclick="document.getElementById('aiProdPreview').style.display='none'">Discard</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Form -->
    <div class="ad-card" style="margin-bottom:18px;">
        <div class="ad-card-head">
            <h2>Add New Product</h2>
        </div>
        <form method="POST" enctype="multipart/form-data" id="addProductForm">
            <?php echo csrfTokenField(); ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Product Name *</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Price *</label>
                    <input type="number" class="form-control" name="price" step="0.01" min="0.01" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Gender *</label>
                    <select class="form-control" name="gender" id="addGender" required>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="mens">Men</option>
                        <option value="womens">Women</option>
                        <option value="kids">Kids</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Category *</label>
                    <select class="form-control" name="category" id="addCategory" required>
                        <option value="" disabled selected>Select Category</option>
                        <option value="t-shirts">T-Shirts</option>
                        <option value="hoodies">Hoodies</option>
                        <option value="pants">Pants</option>
                        <option value="dresses">Dresses</option>
                        <option value="accessories">Accessories</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <?php if ($hasStock): ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Initial Stock *</label>
                    <input type="number" class="form-control" name="stock" min="0" value="100" required>
                    <small class="text-muted">Quantity in inventory</small>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Available Colors *</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php
                        $colorOptions = ['Black','White','Navy','Red','Blue','Gray','Pink','Yellow','Green','Maroon','Brown','Orange','Purple','Beige'];
                        foreach ($colorOptions as $color): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="available_colors[]" value="<?php echo $color; ?>" id="addColor<?php echo $color; ?>">
                                <label class="form-check-label" for="addColor<?php echo $color; ?>"><?php echo $color; ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6 mb-3" id="addSizesGroup">
                    <label class="form-label">Available Sizes *</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php
                        $sizeOptions = ['XS','S','M','L','XL','XXL'];
                        foreach ($sizeOptions as $size): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="available_sizes[]" value="<?php echo $size; ?>" id="addSize<?php echo $size; ?>">
                                <label class="form-check-label" for="addSize<?php echo $size; ?>"><?php echo $size; ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Product Image *</label>
                <input type="file" class="form-control" name="image" accept="image/*" required>
            </div>

            <button type="submit" class="ad-btn-dark ad-btn-block">Add Product</button>
        </form>
    </div>

    <!-- Products List -->
    <div class="ad-card">
        <div class="ad-card-head">
            <h2>Products List</h2>
        </div>
        <div class="ad-table-wrap">
            <table class="ad-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Category</th>
                        <th>Colors</th>
                        <th>Sizes</th>
                        <th>Price</th>
                        <?php if ($hasStock): ?><th>Stock</th><?php endif; ?>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($product = $products->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td>
                            <img src="../images/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;">
                        </td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo ucfirst($product['gender'] ?? ''); ?></td>
                        <td><?php echo ucfirst(str_replace('-', ' ', $product['category'])); ?></td>
                        <td><small><?php echo htmlspecialchars($product['available_colors'] ?? ''); ?></small></td>
                        <td><small><?php echo htmlspecialchars($product['available_sizes'] ?? '-'); ?></small></td>
                        <td>₱<?php echo number_format($product['price'], 2); ?></td>
                        <?php if ($hasStock):
                            $st = (int)($product['stock'] ?? 0);
                            $stockClass = $st <= 0 ? 'danger' : ($st <= 5 ? 'warning' : 'success');
                        ?>
                        <td>
                            <form method="POST" class="ad-stock">
                                <?php echo csrfTokenField(); ?>
                                <input type="hidden" name="quick_stock_update" value="1">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="number" name="stock" min="0" value="<?php echo $st; ?>" class="form-control form-control-sm" required>
                                <button type="submit" class="ad-act ad-act-icon" title="Save stock"><?php echo adminIcon('save'); ?></button>
                            </form>
                            <?php if ($st <= 5): ?>
                                <small class="ad-stock-note <?php echo $st <= 0 ? 'out' : 'warn'; ?>"><?php echo $st <= 0 ? 'Out of stock' : 'Low stock'; ?></small>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <span class="ad-pill <?php echo $product['status'] === 'active' ? 's-completed' : 's-other'; ?>">
                                <?php echo ucfirst($product['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="ad-actions">
                                <button type="button" class="ad-act" title="Edit product" onclick='openEditModal(<?php echo json_encode($product, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                    <?php echo adminIcon('edit'); ?><span class="ad-act-text">Edit</span>
                                </button>
                                <?php if ($hasDesign && !empty($product['design_asset'])): ?>
                                <button type="button" class="ad-act ad-act-icon" title="Download print file"
                                        onclick="downloadPrintFile('../images/products/<?php echo rawurlencode($product['design_asset']); ?>', <?php echo htmlspecialchars(json_encode($product['name']), ENT_QUOTES); ?>, this)">
                                    <?php echo adminIcon('print'); ?>
                                </button>
                                <?php endif; ?>
                                <form method="POST" onsubmit="return confirm('Delete this product?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $product['id']; ?>">
                                    <?php echo csrfTokenField(); ?>
                                    <button type="submit" class="ad-act ad-act-danger ad-act-icon" title="Delete product"><?php echo adminIcon('trash'); ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    </div><!-- /admin-main-content -->
</div><!-- /admin-layout -->

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#17181b; color:#fff; border-bottom:0;">
                <h5 class="modal-title" id="editProductModalLabel" style="font-size:16px;font-weight:700;">Edit Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editProductForm">
                <div class="modal-body">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="product_id" id="editProductId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name *</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price *</label>
                            <input type="number" class="form-control" name="price" id="editPrice" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender *</label>
                            <select class="form-control" name="gender" id="editGender" required>
                                <option value="mens">Men</option>
                                <option value="womens">Women</option>
                                <option value="kids">Kids</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-control" name="category" id="editCategory" required>
                                <option value="t-shirts">T-Shirts</option>
                                <option value="hoodies">Hoodies</option>
                                <option value="pants">Pants</option>
                                <option value="dresses">Dresses</option>
                                <option value="accessories">Accessories</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" id="editStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Available Colors *</label>
                            <div class="d-flex flex-wrap gap-2" id="editColorsGroup">
                                <?php foreach ($colorOptions as $color): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="available_colors[]" value="<?php echo $color; ?>" id="editColor<?php echo $color; ?>">
                                        <label class="form-check-label" for="editColor<?php echo $color; ?>"><?php echo $color; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3" id="editSizesGroup">
                            <label class="form-label">Available Sizes *</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($sizeOptions as $size): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="available_sizes[]" value="<?php echo $size; ?>" id="editSize<?php echo $size; ?>">
                                        <label class="form-check-label" for="editSize<?php echo $size; ?>"><?php echo $size; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                    </div>
                    <?php if ($hasStock): ?>
                    <div class="mb-3">
                        <label class="form-label">Stock *</label>
                        <input type="number" class="form-control" name="stock" id="editStock" min="0" required>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Current Image</label>
                        <div><img id="editCurrentImage" src="" alt="Current" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #ddd;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Change Image <small class="text-muted">(leave empty to keep current)</small></label>
                        <input type="file" class="form-control" name="image" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ad-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="ad-btn-dark" style="margin-left:8px;">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../js/admin-sidebar.js"></script>
<script>
// Toggle sizes visibility based on category selection
function toggleSizes(categorySelect, sizesGroupId) {
    const sizesGroup = document.getElementById(sizesGroupId);
    if (!sizesGroup) return;
    if (categorySelect.value === 'accessories') {
        sizesGroup.style.display = 'none';
        sizesGroup.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);
    } else {
        sizesGroup.style.display = '';
    }
}

const addCategory = document.getElementById('addCategory');
if (addCategory) {
    addCategory.addEventListener('change', function() {
        toggleSizes(this, 'addSizesGroup');
    });
}

const editCategory = document.getElementById('editCategory');
if (editCategory) {
    editCategory.addEventListener('change', function() {
        toggleSizes(this, 'editSizesGroup');
    });
}

function openEditModal(product) {
    document.getElementById('editProductId').value = product.id;
    document.getElementById('editName').value = product.name;
    document.getElementById('editPrice').value = product.price;
    document.getElementById('editGender').value = product.gender || 'mens';
    document.getElementById('editCategory').value = product.category;
    document.getElementById('editStatus').value = product.status;
    document.getElementById('editDescription').value = product.description || '';
    const editStockEl = document.getElementById('editStock');
    if (editStockEl) editStockEl.value = (product.stock != null) ? product.stock : 0;
    document.getElementById('editCurrentImage').src = '../images/products/' + product.image;

    // Reset and set colors
    const colors = (product.available_colors || '').split(',').map(c => c.trim());
    document.querySelectorAll('#editColorsGroup input[type=checkbox]').forEach(cb => {
        cb.checked = colors.includes(cb.value);
    });

    // Reset and set sizes
    const sizes = (product.available_sizes || '').split(',').map(s => s.trim());
    document.querySelectorAll('#editSizesGroup input[type=checkbox]').forEach(cb => {
        cb.checked = sizes.includes(cb.value);
    });

    // Toggle sizes visibility
    toggleSizes(document.getElementById('editCategory'), 'editSizesGroup');

    const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
    modal.show();
}
</script>

<script>
// ===== AI Product Generator (draft -> review -> save) =====
const AI_PROD_CSRF = <?php echo json_encode(generateCsrfToken()); ?>;
let aiProdImage = ''; // generated image filename returned by the server
let aiProdDesign = ''; // print artwork filename ('' when the AI could not produce one)

// Downloads a generated design as a print-ready PNG. The model is asked for the
// artwork on a pure white background; we knock that background out to alpha here
// so the file can go straight to DTG / screen printing. Canvas-side on purpose —
// the GD extension is not enabled in this XAMPP build.
function downloadPrintFile(url, baseName, btn) {
    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Preparing…'; }

    const restore = () => { if (btn) { btn.disabled = false; btn.innerHTML = orig; } };
    const slug = (baseName || 'design').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'design';

    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);

        try {
            const frame = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const px = frame.data;
            for (let i = 0; i < px.length; i += 4) {
                if (px[i] > 242 && px[i + 1] > 242 && px[i + 2] > 242) px[i + 3] = 0;
            }
            ctx.putImageData(frame, 0, 0);
        } catch (e) {
            // Tainted canvas — hand over the artwork with its background intact.
        }

        canvas.toBlob((blob) => {
            if (!blob) { alert('Could not prepare the print file.'); restore(); return; }
            const href = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = href;
            a.download = slug + '-print.png';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(href);
            restore();
        }, 'image/png');
    };
    img.onerror = () => { alert('Could not load the design file.'); restore(); };
    img.src = url;
}

document.getElementById('aiProdDesignBtn')?.addEventListener('click', function () {
    if (!aiProdDesign) return;
    downloadPrintFile(
        '../images/products/' + encodeURIComponent(aiProdDesign),
        document.getElementById('aiProdName').value,
        this
    );
});

async function aiProdGenerate() {
    const prompt = document.getElementById('aiProdPrompt').value.trim();
    if (!prompt) { alert('Describe the product first (e.g. "vintage sunset graphic tee for men").'); return; }

    const btn = document.getElementById('aiProdGenerateBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Generating…';

    try {
        const res = await fetch('ai-product-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': AI_PROD_CSRF },
            body: JSON.stringify({ action: 'generate', prompt: prompt })
        });
        const data = await res.json();
        if (!data.success) { alert(data.error || 'Generation failed.'); return; }

        const d = data.draft;
        aiProdImage = d.image;
        document.getElementById('aiProdImg').src = d.image_url + '?t=' + Date.now();

        aiProdDesign = d.design || '';
        const designBox = document.getElementById('aiProdDesignBox');
        if (aiProdDesign) {
            document.getElementById('aiProdDesignImg').src = d.design_url + '?t=' + Date.now();
            designBox.style.display = 'block';
        } else {
            designBox.style.display = 'none';
        }

        document.getElementById('aiProdName').value = d.name;
        document.getElementById('aiProdPrice').value = d.price;
        document.getElementById('aiProdGender').value = d.gender;
        document.getElementById('aiProdCategory').value = d.category;
        document.getElementById('aiProdDesc').value = d.description;
        document.getElementById('aiProdColors').value = d.colors.join(', ');
        document.getElementById('aiProdSizes').value = d.sizes.join(', ');
        document.getElementById('aiProdPreview').style.display = 'block';
    } catch (e) {
        alert('Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function aiProdSave() {
    if (!aiProdImage) { alert('Generate a product first.'); return; }

    const btn = document.getElementById('aiProdSaveBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…';

    const csv = (id) => document.getElementById(id).value.split(',').map(s => s.trim()).filter(Boolean);

    try {
        const res = await fetch('ai-product-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': AI_PROD_CSRF },
            body: JSON.stringify({
                action: 'save',
                image: aiProdImage,
                design: aiProdDesign,
                name: document.getElementById('aiProdName').value,
                description: document.getElementById('aiProdDesc').value,
                price: parseFloat(document.getElementById('aiProdPrice').value) || 0,
                gender: document.getElementById('aiProdGender').value,
                category: document.getElementById('aiProdCategory').value,
                stock: parseInt(document.getElementById('aiProdStock').value) || 100,
                colors: csv('aiProdColors'),
                sizes: csv('aiProdSizes')
            })
        });
        const data = await res.json();
        if (!data.success) { alert(data.error || 'Save failed.'); return; }
        // Reload so the new product appears in the table below.
        window.location.href = 'products.php?ai_added=1';
    } catch (e) {
        alert('Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}
</script>

<?php include '../includes/footer/footer.php'; ?>
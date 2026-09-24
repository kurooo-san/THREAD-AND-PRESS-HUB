<?php
require 'includes/config.php';
require_once 'includes/delivery-zones.php';
require_once 'includes/paymongo.php';   // online payment runs through the gateway
require_once 'includes/addresses.php'; // saved delivery addresses (max 3)

// Only offer online payment when the gateway is actually usable.
$gatewayReady = paymongoIsConfigured() && paymongoTableExists();
redirectToLogin();

$pageTitle = 'Checkout';
$error = '';
$success = '';

// Get user discount type and address from database
$user_discount = 'regular';
$user_address = '';
$user_data = null;
$user_stmt = $conn->prepare("SELECT user_type, street_address, barangay, city, province, zipcode FROM users WHERE id = ?");
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $user_discount = $user_data['user_type'];
    $address_parts = array_filter([
        $user_data['street_address'] ?? '',
        $user_data['barangay'] ?? '',
        $user_data['city'] ?? '',
        $user_data['province'] ?? '',
        $user_data['zipcode'] ?? ''
    ]);
    $user_address = implode(', ', $address_parts);
}
$user_stmt->close();

// "Buy Now" skips the cart: checkout runs off a single item held in its own
// localStorage key, so whatever is already in the cart survives untouched.
$buy_now = !empty($_POST['buy_now']);

// Saved addresses. The default is pre-selected so the common case is one click.
$saved_addresses = addressList((int) $_SESSION['user_id']);
$default_address = addressDefault((int) $_SESSION['user_id']);
$can_save_more   = addressCanAdd((int) $_SESSION['user_id']);

// Check if subtotal exists and is greater than 0
$subtotal_from_session = floatval($_POST['subtotal'] ?? 0);
if ($subtotal_from_session <= 0) {
    // Check if coming from cart page with empty cart
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: cart.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
    $delivery_address = sanitizeInput($_POST['delivery_address'] ?? '');

    // Which saved address (if any) was picked. 0 means "a new one typed below".
    // addressGet() scopes by user id, so a forged id simply resolves to null
    // and we fall through to the typed address instead of leaking someone
    // else's details or charging their zone.
    $chosen_address_id = (int) ($_POST['address_id'] ?? 0);
    $chosen_address    = $chosen_address_id > 0
        ? addressGet((int) $_SESSION['user_id'], $chosen_address_id)
        : null;

    // The province that decides the shipping fee. For a saved address it comes
    // from the stored row. For a one-off address the customer typed, the
    // structured Province field is used — it is a cleaner signal than guessing
    // at the free-text street line, and it means shipping a gift to Cebu is
    // charged the Cebu rate instead of the rate of whatever is on file.
    $zone_province = $chosen_address['province'] ?? null;
    if ($chosen_address === null) {
        $typed_province = sanitizeInput($_POST['new_province'] ?? '');
        if ($typed_province !== '') {
            $zone_province = $typed_province;
        }
    }

    if ($chosen_address !== null) {
        $delivery_address = addressFormat($chosen_address);
    }
    $delivery_method = sanitizeInput($_POST['delivery_method'] ?? 'delivery');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    $discount_type = sanitizeInput($_POST['discount_type'] ?? 'regular');
    $subtotal = floatval($_POST['subtotal'] ?? 0);

    // Validate discount type matches user account type
    if ($discount_type !== 'regular' && $discount_type !== $user_discount) {
        $discount_type = 'regular';
    }

    $delivery_zone = sanitizeInput($_POST['delivery_zone'] ?? DELIVERY_ZONE_DEFAULT);

    // For store pickup, set address and zero delivery fee
    if ($delivery_method === 'pickup') {
        $delivery_address = 'Store Pickup';
    }

    // Validate cart is not empty
    if ($subtotal <= 0) {
        $error = 'Your cart is empty! Please add items before checking out.';
    } elseif (empty($payment_method) || ($delivery_method === 'delivery' && empty($delivery_address))) {
        $error = 'Please fill in all required fields!';
    } elseif ($payment_method === 'paymongo' && !$gatewayReady) {
        $error = 'Online payment is temporarily unavailable. Please choose Cash on Delivery.';
    } elseif (!in_array($payment_method, ['cod', 'paymongo'], true)) {
        // Online payment is PayMongo only. Anything else is a forged value.
        $error = 'Please choose a valid payment method.';
    } elseif ($delivery_method === 'pickup' && $payment_method !== 'cod') {
        // Store pickup is settled in cash at the counter. Enforced here and not
        // only by hiding the option, or a crafted POST could book a pickup
        // order against an online channel that will never be reconciled.
        $error = 'Store pickup is cash only. Please choose Cash on Pickup, or switch to delivery.';
    } elseif ($delivery_method === 'delivery' && !isDeliveryZone($delivery_zone)) {
        $error = 'Please choose a delivery area so we can compute the shipping fee.';
    } elseif (!verifyCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        // Server-side price validation: re-check prices from database
        $cart_items_raw = $_POST['cart_items'] ?? '[]';
        $items_to_validate = json_decode($cart_items_raw, true);
        $validated_subtotal = 0;
        $price_mismatch = false;

        if (is_array($items_to_validate)) {
            foreach ($items_to_validate as &$item) {
                $pid = intval($item['id']);
                $check_stmt = $conn->prepare("SELECT price FROM products WHERE id = ? AND status = 'active'");
                $check_stmt->bind_param("i", $pid);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $db_price = floatval($check_result->fetch_assoc()['price']);
                    if (abs($db_price - floatval($item['price'])) > 0.01) {
                        $price_mismatch = true;
                        $item['price'] = $db_price; // correct the price
                    }
                    $validated_subtotal += $db_price * intval($item['quantity']);
                } else {
                    $error = 'One or more products in your cart are no longer available.';
                    break;
                }
                $check_stmt->close();
            }
            unset($item);
        }

        // The subtotal is ALWAYS recomputed from database prices, never taken
        // from the form. Previously it was only replaced when an individual
        // item price disagreed, so a request that carried correct item prices
        // alongside a forged `subtotal` was charged the forged amount — a
        // ₱499 shirt could be bought for ₱1.
        if (empty($error) && is_array($items_to_validate) && $items_to_validate !== []) {
            $subtotal = $validated_subtotal;
            if ($price_mismatch) {
                $cart_items_raw = json_encode($items_to_validate);
            }
        }

        if (empty($error)) {
        // Calculate discount
        $discount_percent = calculateDiscount($discount_type);
        $discount_calc = applyDiscount($subtotal, $discount_percent);
        $discount_amount = $discount_calc['discount_amount'];
        $discounted_total = $discount_calc['total'];

        // Coupon (optional, server-side validated)
        $coupon_code_in   = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $coupon_discount  = 0.0;
        $coupon_row       = null;
        if ($coupon_code_in !== '') {
            $cv = validateCoupon($coupon_code_in, $subtotal);
            if ($cv['ok']) {
                $coupon_discount = (float)$cv['discount'];
                $coupon_row      = $cv['coupon'];
                // Coupon applies to discounted subtotal but cannot exceed it
                $coupon_discount = min($coupon_discount, $discounted_total);
                $discounted_total -= $coupon_discount;
            } else {
                // Don't block the order — just ignore the invalid coupon.
                $coupon_warning = $cv['message'];
                $coupon_discount = 0.0;
                $coupon_row      = null;
            }
        }

        // Priced from includes/delivery-zones.php, never from the posted form —
        // the browser sends which area was chosen, not what it costs. The
        // chosen area is then checked against the delivery address, so a
        // Mindanao order cannot be booked at the Rizal rate.
        // NO fallback to the profile province: that was overriding a deliberate
        // "ship somewhere else" with whatever address happened to be on file.
        // When $zone_province is null the customer's own choice stands, and
        // resolveDeliveryZone() flags an obvious mismatch for admin review.
        $zone_result  = resolveDeliveryZone(
            $delivery_zone,
            $delivery_address,
            $delivery_method,
            $zone_province
        );
        $delivery_fee = $zone_result['fee'];
        $delivery_zone = $zone_result['zone'];
        if (!empty($zone_result['flag'])) {
            $notes = trim($notes . "\n[" . $zone_result['flag'] . ']');
        }

        // VAT (12%) — added on top of the discounted goods total (VAT-exclusive
        // pricing). Delivery fee is not VATed.
        $vat_rate   = 0.12;
        $vat_amount = round($discounted_total * $vat_rate, 2);

        $final_total = $discounted_total + $vat_amount + $delivery_fee;

        if (!empty($error)) {
            // bail before insert if coupon failed
        } else {
        // Pre-flight (non-authoritative) stock check — gives a friendly error
        // when there's clearly not enough stock. The authoritative check is
        // the atomic tryReserveStock() inside the transaction below.
        if (productsHasStockColumn() && is_array($items_to_validate)) {
            foreach ($items_to_validate as $it) {
                $pid_check = (int)$it['id'];
                $qty_need  = (int)$it['quantity'];
                $st = getProductStock($pid_check);
                if ($st < $qty_need) {
                    $error = 'Sorry, one or more items in your cart no longer have enough stock.';
                    break;
                }
            }
        }
        }

        if (empty($error)) {
        // Get validated cart data
        $cart_items = $cart_items_raw;

        // Detect optional columns for INSERT
        $hasCouponCols = false;
        $cc = $conn->query("SHOW COLUMNS FROM orders LIKE 'coupon_code'");
        if ($cc && $cc->num_rows > 0) $hasCouponCols = true;
        $hasPaymentStatusCol = false;
        $pc = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_status'");
        if ($pc && $pc->num_rows > 0) $hasPaymentStatusCol = true;

        // Determine initial payment_status
        $initial_pay_status = 'unpaid';
        if ($payment_method === 'cod') {
            $initial_pay_status = 'unpaid'; // collected on delivery
        }

        // ============================================================
        // Begin transaction — order, items, and stock must be atomic.
        // ============================================================
        $conn->begin_transaction();
        $tx_ok = true;
        $order_id = 0;

        try {
            // Build dynamic INSERT depending on schema
            if ($hasCouponCols && $hasPaymentStatusCol) {
                $stmt = $conn->prepare("INSERT INTO orders (user_id, subtotal, discount_amount, discount_type, coupon_code, coupon_discount, delivery_fee, total, payment_method, payment_status, delivery_address, notes, status, created_at)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $coupon_code_save = $coupon_row ? $coupon_row['code'] : null;
                $stmt->bind_param("iddssdidssss",
                    $_SESSION['user_id'],
                    $subtotal,
                    $discount_amount,
                    $discount_type,
                    $coupon_code_save,
                    $coupon_discount,
                    $delivery_fee,
                    $final_total,
                    $payment_method,
                    $initial_pay_status,
                    $delivery_address,
                    $notes
                );
            } else {
                $stmt = $conn->prepare("INSERT INTO orders (user_id, subtotal, discount_amount, discount_type, delivery_fee, total, payment_method, delivery_address, notes, status, created_at)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $stmt->bind_param("iddsidsss",
                    $_SESSION['user_id'],
                    $subtotal,
                    $discount_amount,
                    $discount_type,
                    $delivery_fee,
                    $final_total,
                    $payment_method,
                    $delivery_address,
                    $notes
                );
            }

            if (!$stmt->execute()) {
                throw new Exception('Failed to create order: ' . $stmt->error);
            }
            $order_id = $conn->insert_id;
            $stmt->close();

            // Persist VAT separately if the schema has a column for it (optional).
            // The amount is already included in `total`; this just records the split.
            foreach (['vat', 'vat_amount'] as $vatColName) {
                $vatColCheck = $conn->query("SHOW COLUMNS FROM orders LIKE '$vatColName'");
                if ($vatColCheck && $vatColCheck->num_rows > 0) {
                    $vatStmt = $conn->prepare("UPDATE orders SET `$vatColName` = ? WHERE id = ?");
                    $vatStmt->bind_param("di", $vat_amount, $order_id);
                    $vatStmt->execute();
                    $vatStmt->close();
                    break;
                }
            }

            // Insert order items + atomically reserve stock
            $items = json_decode($cart_items, true);
            if (!is_array($items) || count($items) === 0) {
                throw new Exception('Cart is empty.');
            }

            $columns_check = $conn->query("SHOW COLUMNS FROM order_items LIKE 'color'");
            $has_color_size = ($columns_check && $columns_check->num_rows > 0);
            $has_stock_col = productsHasStockColumn();

            foreach ($items as $item) {
                $product_id    = intval($item['id']);
                $quantity      = intval($item['quantity']);
                $unit_price    = floatval($item['price']);
                $item_subtotal = $unit_price * $quantity;
                $item_color    = isset($item['color']) ? $item['color'] : '';
                $item_size     = isset($item['size']) ? $item['size'] : '';

                if ($quantity <= 0 || $quantity > 999) {
                    throw new Exception('Invalid quantity for one of the items.');
                }

                // Atomic stock reservation — fails if not enough stock left
                if ($has_stock_col && !tryReserveStock($product_id, $quantity)) {
                    throw new Exception('Sorry, one or more items just went out of stock.');
                }

                if ($has_color_size) {
                    $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, color, size)
                                                VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $item_stmt->bind_param("iiiddss", $order_id, $product_id, $quantity, $unit_price, $item_subtotal, $item_color, $item_size);
                } else {
                    $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                                                VALUES (?, ?, ?, ?, ?)");
                    $item_stmt->bind_param("iidid", $order_id, $product_id, $quantity, $unit_price, $item_subtotal);
                }

                if (!$item_stmt->execute()) {
                    $err = $item_stmt->error;
                    $item_stmt->close();
                    throw new Exception('Failed to insert order item: ' . $err);
                }
                $item_stmt->close();
            }

            // Increment coupon usage (inside the transaction)
            if (!empty($coupon_row)) {
                incrementCouponUsage((int)$coupon_row['id']);
            }

            // Link any pending custom designs to this order
            $designStmt = $conn->prepare("UPDATE custom_designs SET order_id = ? WHERE user_id = ? AND order_id IS NULL AND status = 'pending'");
            $designStmt->bind_param("ii", $order_id, $_SESSION['user_id']);
            $designStmt->execute();
            $designStmt->close();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $tx_ok = false;
            error_log('[checkout] ' . $e->getMessage());
            $error = $e->getMessage();
        }

        if ($tx_ok && $order_id > 0) {
            // "Save this address" on a typed address, done only after the order
            // actually succeeded so a failed checkout leaves nothing behind.
            if ($chosen_address === null
                && $delivery_method === 'delivery'
                && !empty($_POST['save_address'])
                && addressCanAdd((int) $_SESSION['user_id'])) {
                addressAdd((int) $_SESSION['user_id'], [
                    'label'          => sanitizeInput($_POST['new_label'] ?? 'New address'),
                    'street_address' => sanitizeInput($_POST['new_street'] ?? ''),
                    'barangay'       => sanitizeInput($_POST['new_barangay'] ?? ''),
                    'city'           => sanitizeInput($_POST['new_city'] ?? ''),
                    'province'       => sanitizeInput($_POST['new_province'] ?? ''),
                    'zipcode'        => sanitizeInput($_POST['new_zipcode'] ?? ''),
                ]);
            }

            // order_confirmation.php clears localStorage; it must clear the
            // Buy Now key for a Buy Now order and leave the real cart alone.
            $_SESSION['last_order_mode'] = $buy_now ? 'buynow' : 'cart';

            $_SESSION['last_order_id'] = $order_id;
            $_SESSION['payment_method'] = $payment_method;
            $_SESSION['order_total'] = $final_total;

            // Send order confirmation email (non-fatal)
            try {
                require_once 'includes/email-helper.php';
                sendOrderConfirmationEmail($conn, $order_id);
            } catch (Throwable $mailEx) {
                error_log('[checkout] email failed: ' . $mailEx->getMessage());
            }

            // Redirect to payment or order confirmation.
            // Online payments go to PayMongo's hosted checkout, which handles
            // GCash / Maya / GrabPay / card on their side.
            if ($payment_method === 'cod') {
                require_once 'includes/payment-success.php';
                setPaymentSuccessFlash([
                    'order_id' => $order_id,
                    'kind'     => 'order_cod',
                    'total'    => $final_total,
                ]);
                header("Location: order_confirmation.php?order_id=" . $order_id);
            } else {
                header("Location: paymongo-checkout.php?order_id=" . $order_id);
            }
            exit();
        }
        } // end stock+coupon ok block
    } // end price validation check
    }
}

?>

<?php include 'includes/header/header.php'; ?>

<div class="container my-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="cart.php">Cart</a></li>
            <li class="breadcrumb-item active">Checkout</li>
        </ol>
    </nav>
    <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 2rem;">Checkout</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" id="checkoutForm">
        <?php echo csrfTokenField(); ?>
        <div class="row">
            <div class="col-lg-8">
                <!-- Delivery Method -->
                <div class="card mb-4" style="border: 1px solid var(--border-light); border-radius: var(--radius-md);">
                    <div class="card-body p-4">
                        <h5 class="mb-3" style="font-weight: 600;"><i class="fas fa-shipping-fast" style="margin-right: 0.5rem;"></i> Delivery Method</h5>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="delivery_method" id="deliveryMethod" value="delivery" checked onchange="toggleDeliveryAddress()">
                            <label class="form-check-label" for="deliveryMethod" style="cursor: pointer;">
                                <strong>Deliver to Address</strong> — We'll ship to your saved address
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="delivery_method" id="pickupMethod" value="pickup" onchange="toggleDeliveryAddress()">
                            <label class="form-check-label" for="pickupMethod" style="cursor: pointer;">
                                <strong>Store Pickup</strong> — Pick up at our studio for free
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="card mb-4" id="addressCard" style="border: 1px solid var(--border-light); border-radius: var(--radius-md);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0" style="font-weight: 600;"><i class="fas fa-map-marker-alt" style="margin-right: 0.5rem;"></i> Delivery Address</h5>
                            <a href="profile.php" class="btn btn-sm btn-outline-dark" style="border-radius: 8px; font-size: 0.8rem; font-weight: 600;">
                                <i class="fas fa-edit me-1"></i> Edit Address
                            </a>
                        </div>
                        <?php
                        // Each saved address carries the zone its province implies, so picking
                        // one updates the fee without a round trip. The server recomputes it
                        // from the stored row anyway — this is only for the on-screen total.
                        $addressZoneMap = [];
                        foreach ($saved_addresses as $sa) {
                            $addressZoneMap[(int) $sa['id']] = guessDeliveryZone($sa['province'] ?? null, $sa['city'] ?? null);
                        }
                        $defaultAddressId = $default_address ? (int) $default_address['id'] : 0;
                        ?>

                        <?php if ($saved_addresses !== []): ?>
                            <div id="addressChoices" style="margin-bottom: 1rem;">
                                <?php foreach ($saved_addresses as $sa): $sid = (int) $sa['id']; ?>
                                <label class="address-option" for="addr<?php echo $sid; ?>"
                                       style="display:block; border:1px solid #e9ecef; border-radius:10px; padding:0.85rem 1rem; margin-bottom:0.6rem; cursor:pointer;">
                                    <div style="display:flex; align-items:start; gap:0.65rem;">
                                        <input class="form-check-input mt-1" type="radio" name="address_id"
                                               id="addr<?php echo $sid; ?>" value="<?php echo $sid; ?>"
                                               data-zone="<?php echo htmlspecialchars($addressZoneMap[$sid], ENT_QUOTES); ?>"
                                               data-address="<?php echo htmlspecialchars(addressFormat($sa), ENT_QUOTES); ?>"
                                               onchange="onAddressPicked(this)"
                                               <?php echo $sid === $defaultAddressId ? 'checked' : ''; ?>>
                                        <div>
                                            <strong style="font-size:0.88rem;"><?php echo htmlspecialchars((string) $sa['label']); ?></strong>
                                            <?php if (!empty($sa['is_default'])): ?>
                                                <span class="badge bg-success" style="font-size:0.65rem;">Default</span>
                                            <?php endif; ?>
                                            <p style="margin:0.2rem 0 0; font-size:0.85rem; color:#555;"><?php echo htmlspecialchars(addressFormat($sa)); ?></p>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach; ?>

                                <label class="address-option" for="addrNew"
                                       style="display:block; border:1px dashed #cfcfcf; border-radius:10px; padding:0.85rem 1rem; cursor:pointer;">
                                    <div style="display:flex; align-items:start; gap:0.65rem;">
                                        <input class="form-check-input mt-1" type="radio" name="address_id" id="addrNew" value="0"
                                               data-zone="" data-address="" onchange="onAddressPicked(this)">
                                        <div>
                                            <strong style="font-size:0.88rem;">Use a different address</strong>
                                            <p style="margin:0.2rem 0 0; font-size:0.8rem; color:#777;">Ship this order somewhere else, just once.</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php else: ?>
                            <div style="background: #fff3cd; border-radius: 10px; padding: 1rem; margin-bottom: 1rem;">
                                <p style="margin: 0; font-size: 0.88rem; color: #856404;">
                                    <i class="fas fa-exclamation-triangle me-1"></i> No saved address yet — enter one below.
                                    You can tick &ldquo;save&rdquo; to reuse it next time.
                                </p>
                            </div>
                            <input type="hidden" name="address_id" id="addrNew" value="0">
                        <?php endif; ?>

                        <?php // Shown only while "use a different address" is selected. ?>
                        <div id="newAddressPanel" style="<?php echo $saved_addresses !== [] ? 'display:none;' : ''; ?> border:1px solid #e9ecef; border-radius:10px; padding:1rem; margin-bottom:1rem;">
                            <div class="form-group mb-2">
                                <label class="form-label" style="font-size:0.82rem; font-weight:600;">Full Address *</label>
                                <textarea class="form-control" name="delivery_address" id="deliveryAddressInput" rows="2"
                                          placeholder="House/Unit No., Street, Barangay, City, Province"></textarea>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label" style="font-size:0.78rem;">City</label>
                                    <input type="text" class="form-control form-control-sm" name="new_city" id="newCity" placeholder="City">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" style="font-size:0.78rem;">Province</label>
                                    <input type="text" class="form-control form-control-sm" name="new_province" id="newProvince"
                                           placeholder="Province" onchange="updateOrderSummary()">
                                </div>
                            </div>
                            <?php if ($can_save_more): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="save_address" id="saveAddress" value="1">
                                <label class="form-check-label" for="saveAddress" style="font-size:0.82rem;">
                                    Save this address to my profile
                                    (<?php echo count($saved_addresses); ?> of <?php echo ADDRESS_MAX_PER_USER; ?> used)
                                </label>
                            </div>
                            <div id="saveAddressExtra" style="display:none; margin-top:0.5rem;">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" name="new_label" placeholder="Label (Home, Dorm…)">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" name="new_street" placeholder="Street address">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control form-control-sm" name="new_barangay" placeholder="Barangay">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="text" class="form-control form-control-sm" name="new_zipcode" placeholder="Zip" maxlength="4">
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <p class="text-muted mt-2" style="font-size:0.78rem;">
                                You already have <?php echo ADDRESS_MAX_PER_USER; ?> saved addresses, so this one is used for this order only.
                            </p>
                            <?php endif; ?>
                        </div>

                        <?php // Kept for the pickup path, which needs no address panel at all. ?>
                        <input type="hidden" name="delivery_address" id="deliveryAddressHidden"
                               value="<?php echo htmlspecialchars($default_address ? addressFormat($default_address) : '', ENT_QUOTES); ?>" disabled>

                        <?php
                        // The zone follows the SELECTED address. With one picked the dropdown is
                        // locked, because the server decides the fee from the stored province and
                        // an editable control would promise a choice that does not exist.
                        $zoneFromProfile = $default_address
                            ? detectDeliveryZone($default_address['province'] ?? null)
                            : detectDeliveryZone($user_data['province'] ?? null);
                        $zoneLocked      = $saved_addresses !== [] && $zoneFromProfile !== null;
                        $preselectedZone = $zoneFromProfile ?? DELIVERY_ZONE_DEFAULT;
                        ?>
                        <div class="form-group mb-3">
                            <label class="form-label" for="deliveryZone">Delivery Area *</label>
                            <select class="form-control" name="delivery_zone" id="deliveryZone"
                                    onchange="updateOrderSummary()"
                                    <?php echo $zoneLocked ? 'disabled' : ''; ?>>
                                <?php foreach (DELIVERY_ZONES as $zoneKey => $zone): ?>
                                    <option value="<?php echo htmlspecialchars($zoneKey, ENT_QUOTES); ?>"
                                            <?php echo $zoneKey === $preselectedZone ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($zone['label']); ?> — ₱<?php echo number_format((float)$zone['fee'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($zoneLocked): ?>
                                <?php // A disabled select submits nothing, so the value travels here. ?>
                                <input type="hidden" name="delivery_zone" id="deliveryZoneLocked" value="<?php echo htmlspecialchars($preselectedZone, ENT_QUOTES); ?>">
                                <small class="text-muted" style="font-size: 0.78rem;" id="zoneLockedHint">
                                    Set from the address you picked above.
                                    Choose <strong>Use a different address</strong> to ship somewhere else.
                                </small>
                            <?php else: ?>
                                <small class="text-muted" style="font-size: 0.78rem;">Shipping is charged by area. Store pickup is free.</small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Special Instructions</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Any special requests or instructions"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="card mb-4" style="border: 1px solid var(--border-light); border-radius: var(--radius-md);">
                    <div class="card-body p-4">
                        <h5 class="mb-3" style="font-weight: 600;"><i class="fas fa-wallet" style="margin-right: 0.5rem;"></i> Payment Method</h5>
                        <div class="form-check mb-3"<?php echo $gatewayReady ? '' : ' style="display:none;"'; ?>>
                            <input class="form-check-input" type="radio" name="payment_method" id="paymongo" value="paymongo" <?php echo $gatewayReady ? 'required' : 'disabled'; ?>>
                            <label class="form-check-label" for="paymongo" style="cursor: pointer;">
                                <i class="fas fa-wallet" style="color: var(--primary); margin-right: 0.5rem;"></i>
                                <strong>Pay Online</strong> - GCash, Maya, GrabPay, or credit/debit card. You are taken to our secure payment partner to finish.
                            </label>
                        </div>
                        <div class="form-check mb-3" id="codOption">
                            <input class="form-check-input" type="radio" name="payment_method" id="cod" value="cod" required>
                            <label class="form-check-label" for="cod" style="cursor: pointer;">
                                <i class="fas fa-money-bill-wave" style="color: var(--primary); margin-right: 0.5rem;"></i>
                                <strong id="codLabel">Cash on Delivery</strong> <span id="codHint">- Pay when order arrives</span>
                            </label>
                        </div>
                        <div id="pickupCashNote" style="display:none; background:#f0f7f2; border:1px solid #cfe4d6; border-radius:10px; padding:0.75rem 1rem;">
                            <p style="margin:0; font-size:0.85rem; color:#2c6e3f;">
                                <i class="fas fa-store me-1"></i> Store pickup is settled in cash at the counter, so online payment is unavailable for this option.
                            </p>
                        </div>
                        <div class="mt-3" style="background: var(--bg-light); border-radius: var(--radius-sm); padding: 0.75rem 1rem;">
                            <small class="text-muted"><i class="fas fa-info-circle" style="margin-right: 0.25rem;"></i>
                                <?php if ($gatewayReady): ?>
                                    Online payments are completed on PayMongo's secure page and confirmed automatically — nothing to upload. Cash lets you pay on delivery or at pickup.
                                <?php else: ?>
                                    Online payment is temporarily unavailable. You can pay in cash on delivery or at pickup.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Discount Section -->
                <div class="discount-section">
                    <h6><i class="fas fa-ticket-alt"></i> Discount &amp; Benefits</h6>
                    <p class="text-muted mb-3">Your account type: <strong><?php echo ucfirst(str_replace('_', ' ', $user_discount)); ?></strong></p>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="discount_type" id="regular" value="regular" <?php echo $user_discount === 'regular' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="regular">
                            <strong>No Discount</strong> - Regular pricing
                        </label>
                    </div>

                    <?php if ($user_discount === 'pwd'): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="discount_type" id="pwd" value="pwd" checked>
                        <label class="form-check-label" for="pwd">
                            <i class="fas fa-wheelchair" style="color: var(--primary);"></i>
                            <strong>PWD Discount - 20% OFF</strong>
                        </label>
                    </div>
                    <?php endif; ?>

                    <?php if ($user_discount === 'senior'): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="discount_type" id="senior" value="senior" checked>
                        <label class="form-check-label" for="senior">
                            <i class="fas fa-users" style="color: var(--primary);"></i>
                            <strong>Senior Citizen Discount - 20% OFF</strong>
                        </label>
                    </div>
                    <?php endif; ?>

                    <small class="text-muted">
                        <i class="fas fa-lock"></i> Valid ID will be required for verification during delivery
                    </small>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="col-lg-4">
                <div class="order-summary sticky-top" style="top: 20px;">
                    <h5 class="mb-4" style="font-weight: 700;">Order Summary</h5>
                    
                    <div id="orderItems" style="max-height: 300px; overflow-y: auto; margin-bottom: 1.5rem;">
                        <!-- Items will be loaded here -->
                    </div>

                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span id="summarySubtotal">₱0.00</span>
                    </div>

                    <div class="summary-row">
                        <span>Discount (<span id="discountTypeLabel">Regular</span>):</span>
                        <span id="summaryDiscount" style="color: var(--primary); font-weight: 600;">₱0.00</span>
                    </div>

                    <div class="summary-row" id="couponRow" style="display:none;">
                        <span>Coupon (<span id="couponCodeLabel"></span>):</span>
                        <span id="summaryCoupon" style="color: #16a34a; font-weight: 600;">-₱0.00</span>
                    </div>

                    <div class="summary-row">
                        <span>VAT (12%):</span>
                        <span id="summaryVat">₱0.00</span>
                    </div>

                    <div class="summary-row">
                        <span>Delivery Fee:</span>
                        <span id="summaryDelivery">₱<?php echo number_format(deliveryFee(guessDeliveryZone($user_data['province'] ?? null, $user_data['city'] ?? null)), 2); ?></span>
                    </div>

                    <div class="summary-row total">
                        <span>Total Amount:</span>
                        <span id="summaryTotal">₱0.00</span>
                    </div>

                    <input type="hidden" name="subtotal" id="hiddenSubtotal">
                    <input type="hidden" name="cart_items" id="cartItemsInput">
                    <?php // Carries Buy Now mode through the Place Order POST. Without it the
                          // order lands as a normal cart order and order_confirmation.php
                          // wipes the basket the customer still had waiting. ?>
                    <input type="hidden" name="buy_now" value="<?php echo $buy_now ? '1' : ''; ?>">

                    <button type="submit" class="btn btn-dark btn-lg w-100 mt-4" style="border-radius: var(--radius-sm); padding: 0.75rem;">
                        Place Order <i class="fas fa-arrow-right" style="margin-left: 0.5rem;"></i>
                    </button>

                    <a href="cart.php" class="btn btn-outline-dark w-100 mt-2" style="border-radius: var(--radius-sm); padding: 0.75rem;">
                        <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i> Back to Cart
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Fees come from includes/delivery-zones.php so the summary and the recorded
// total are computed from one table. The server re-derives the fee on submit;
// this copy only drives what the customer sees.
const DELIVERY_ZONE_FEES = <?php echo json_encode(deliveryZonesForJs()); ?>;

/** Fee for the current delivery method and selected area. */
function currentDeliveryFee() {
    const method = document.querySelector('input[name="delivery_method"]:checked');
    if (method && method.value === 'pickup') return 0;
    const sel = document.getElementById('deliveryZone');
    const fee = sel ? DELIVERY_ZONE_FEES[sel.value] : undefined;
    return typeof fee === 'number' ? fee : <?php echo json_encode(deliveryFee(DELIVERY_ZONE_DEFAULT)); ?>;
}
const VAT_RATE = 0.12;
const DISCOUNT_RATES = {
    'regular': 0,
    'pwd': 0.20,
    'senior': 0.20
};

// Coupon discount tracked on the client; server re-validates on submit.
let appliedCoupon = { code: '', discount: 0 };

function validateCouponClient() {
    const inp = document.getElementById('couponCodeInput');
    const msg = document.getElementById('couponMessage');
    const code = (inp.value || '').trim().toUpperCase();
    inp.value = code;
    msg.textContent = '';
    msg.className = 'small mt-2';

    if (!code) {
        appliedCoupon = { code: '', discount: 0 };
        updateOrderSummary();
        return;
    }

    const subtotal = parseFloat(document.getElementById('hiddenSubtotal').value) || 0;
    const fd = new FormData();
    fd.append('code', code);
    fd.append('subtotal', subtotal);

    fetch('includes/validate-coupon.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                appliedCoupon = { code: data.code, discount: parseFloat(data.discount) || 0 };
                msg.textContent = '✓ ' + data.message + ' Discount: ₱' + appliedCoupon.discount.toFixed(2);
                msg.classList.add('text-success');
            } else {
                appliedCoupon = { code: '', discount: 0 };
                msg.textContent = '✗ ' + data.message;
                msg.classList.add('text-danger');
            }
            updateOrderSummary();
        })
        .catch(() => {
            msg.textContent = 'Could not validate coupon. Try again.';
            msg.classList.add('text-danger');
        });
}

// 'buynow' = a single item bought directly; 'cart' = the normal basket.
const CHECKOUT_MODE = <?php echo json_encode($buy_now ? 'buynow' : 'cart'); ?>;
// 'cartSelection' holds only the lines the customer ticked in the cart, so
// unticked items are left behind instead of being ordered by accident.
const CART_KEY      = CHECKOUT_MODE === 'buynow' ? 'buyNow' : 'cartSelection';
const SUBTOTAL_KEY  = CHECKOUT_MODE === 'buynow' ? 'buyNowSubtotal' : 'subtotal';

function loadOrderData() {
    let cart = JSON.parse(localStorage.getItem(CART_KEY)) || [];
    let subtotal = parseFloat(localStorage.getItem(SUBTOTAL_KEY)) || 0;
    // Fall back to the whole cart if the key is missing (an old form being
    // re-posted, say) so checkout never renders an empty order.
    if (cart.length === 0) {
        cart = JSON.parse(localStorage.getItem('cart')) || [];
        subtotal = parseFloat(localStorage.getItem('subtotal')) || 0;
    }
    
    // Display cart items
    let itemsHtml = '';
    cart.forEach(item => {
        itemsHtml += `
            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,0.1);">
                <div>
                    <strong>${item.name}</strong> <br>
                    <small class="text-muted">Color: ${item.color || 'N/A'} | Size: ${item.size || 'N/A'}</small>
                </div>
                <span>₱${(item.price * item.quantity).toFixed(2)}</span>
            </div>
        `;
    });
    
    document.getElementById('orderItems').innerHTML = itemsHtml;
    document.getElementById('hiddenSubtotal').value = subtotal;
    document.getElementById('cartItemsInput').value = JSON.stringify(cart);
    
    updateOrderSummary();
}

function updateOrderSummary() {
    const subtotal = parseFloat(document.getElementById('hiddenSubtotal').value) || 0;
    // No radio is checked when the account type is neither regular, pwd nor
    // senior (an admin, say). Reading .value off null threw, which aborted the
    // whole summary and left Subtotal/VAT/Total showing 0.00.
    const discountPick = document.querySelector('input[name="discount_type"]:checked');
    const discountType = discountPick ? discountPick.value : 'regular';
    const discountRate = DISCOUNT_RATES[discountType];
    const discountAmount = subtotal * discountRate;
    let discountedTotal = subtotal - discountAmount;

    // Coupon
    const couponRow = document.getElementById('couponRow');
    if (appliedCoupon.code && appliedCoupon.discount > 0) {
        const couponAmt = Math.min(appliedCoupon.discount, discountedTotal);
        discountedTotal -= couponAmt;
        couponRow.style.display = '';
        document.getElementById('couponCodeLabel').textContent = appliedCoupon.code;
        document.getElementById('summaryCoupon').textContent = '-₱' + couponAmt.toFixed(2);
    } else {
        couponRow.style.display = 'none';
    }

    const vat = discountedTotal * VAT_RATE;
    const deliveryFee = currentDeliveryFee();
    const total = discountedTotal + vat + deliveryFee;

    document.getElementById('summarySubtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('summaryDiscount').textContent = '₱' + discountAmount.toFixed(2);
    document.getElementById('summaryVat').textContent = '₱' + vat.toFixed(2);
    document.getElementById('summaryDelivery').textContent = '₱' + deliveryFee.toFixed(2);
    document.getElementById('summaryTotal').textContent = '₱' + total.toFixed(2);

    // Update discount label
    const labels = {'regular': 'No Discount', 'pwd': 'PWD (20%)', 'senior': 'Senior (20%)'};
    document.getElementById('discountTypeLabel').textContent = labels[discountType];
}

// Listen for discount type changes
document.querySelectorAll('input[name="discount_type"]').forEach(input => {
    input.addEventListener('change', updateOrderSummary);
});

// Load on page load
loadOrderData();

/**
 * React to picking a saved address, or "use a different address".
 *
 * The fee shown here is only the on-screen figure — checkout.php recomputes it
 * from the stored province of whichever address id is posted, so nothing here
 * can talk the server into a cheaper zone.
 */
function onAddressPicked(radio) {
    const panel    = document.getElementById('newAddressPanel');
    const textarea = document.getElementById('deliveryAddressInput');
    const zoneSel  = document.getElementById('deliveryZone');
    const zoneHid  = document.getElementById('deliveryZoneLocked');
    const hint     = document.getElementById('zoneLockedHint');
    const usingNew = radio.value === '0';

    if (panel) panel.style.display = usingNew ? 'block' : 'none';

    // A hidden required field blocks submit, so only demand it while visible.
    if (textarea) {
        if (usingNew) textarea.setAttribute('required', 'required');
        else          textarea.removeAttribute('required');
    }

    if (zoneSel) {
        if (usingNew) {
            // No stored province to trust yet — let them choose.
            zoneSel.disabled = false;
            if (zoneHid) zoneHid.disabled = true;
            if (hint) hint.style.display = 'none';
        } else {
            const zone = radio.getAttribute('data-zone') || '';
            if (zone) {
                zoneSel.value = zone;
                if (zoneHid) { zoneHid.disabled = false; zoneHid.value = zone; }
                zoneSel.disabled = true;
                if (hint) hint.style.display = '';
            }
        }
    }

    // Highlight the chosen card.
    document.querySelectorAll('.address-option').forEach(function (el) {
        const input = el.querySelector('input[name="address_id"]');
        el.style.borderColor = (input && input.checked) ? 'var(--accent-green, #2d6a4f)' : '#e9ecef';
    });

    updateOrderSummary();
}

// Reveal the label/street fields only when they actually want it saved.
(function () {
    const cb = document.getElementById('saveAddress');
    const extra = document.getElementById('saveAddressExtra');
    if (!cb || !extra) return;
    cb.addEventListener('change', function () {
        extra.style.display = cb.checked ? 'block' : 'none';
    });
})();

// Apply the pre-checked address on first paint.
(function () {
    const picked = document.querySelector('input[name="address_id"]:checked');
    if (picked) onAddressPicked(picked);
})();

// Toggle delivery address visibility based on delivery method
function toggleDeliveryAddress() {
    const method = document.querySelector('input[name="delivery_method"]:checked').value;
    const addressCard = document.getElementById('addressCard');
    const addressInput = document.getElementById('deliveryAddressInput');
    const deliveryFeeEl = document.getElementById('summaryDelivery');
    const codOption = document.getElementById('codOption');
    const codRadio = document.getElementById('cod');
    const onlineRadio = document.getElementById('paymongo');

    const onlineOption  = onlineRadio ? onlineRadio.closest('.form-check') : null;
    const pickupNote    = document.getElementById('pickupCashNote');
    const codLabel      = document.getElementById('codLabel');
    const codHint       = document.getElementById('codHint');

    if (method === 'pickup') {
        addressCard.style.display = 'none';
        // Pickup is cash at the counter: show only cash, hide the online
        // channel. The server rejects the pair anyway; this keeps the form
        // from offering a combination it will refuse.
        codOption.style.display = 'block';
        if (onlineOption) onlineOption.style.display = 'none';
        if (pickupNote) pickupNote.style.display = 'block';
        if (codLabel) codLabel.textContent = 'Cash on Pickup';
        if (codHint) codHint.textContent = '- Pay at the store when you collect';
        codRadio.checked = true;
        if (onlineRadio) onlineRadio.checked = false;
        // Pickup needs no address at all; the server stamps "Store Pickup".
        if (addressInput) addressInput.removeAttribute('required');
        // Set delivery fee to 0 for pickup
        deliveryFeeEl.textContent = '₱0.00';
        updateOrderSummaryWithPickup();
    } else {
        addressCard.style.display = 'block';
        codOption.style.display = 'block';
        if (onlineOption) onlineOption.style.display = 'block';
        if (pickupNote) pickupNote.style.display = 'none';
        if (codLabel) codLabel.textContent = 'Cash on Delivery';
        if (codHint) codHint.textContent = '- Pay when order arrives';
        // Back to delivery: re-apply whatever address is currently picked.
        const picked = document.querySelector('input[name="address_id"]:checked');
        if (picked) onAddressPicked(picked);
        updateOrderSummary();
    }
}

function updateOrderSummaryWithPickup() {
    const subtotal = parseFloat(document.getElementById('hiddenSubtotal').value) || 0;
    // No radio is checked when the account type is neither regular, pwd nor
    // senior (an admin, say). Reading .value off null threw, which aborted the
    // whole summary and left Subtotal/VAT/Total showing 0.00.
    const discountPick = document.querySelector('input[name="discount_type"]:checked');
    const discountType = discountPick ? discountPick.value : 'regular';
    const discountRate = DISCOUNT_RATES[discountType];
    const discountAmount = subtotal * discountRate;
    let discountedTotal = subtotal - discountAmount;

    const couponRow = document.getElementById('couponRow');
    if (appliedCoupon.code && appliedCoupon.discount > 0) {
        const couponAmt = Math.min(appliedCoupon.discount, discountedTotal);
        discountedTotal -= couponAmt;
        couponRow.style.display = '';
        document.getElementById('couponCodeLabel').textContent = appliedCoupon.code;
        document.getElementById('summaryCoupon').textContent = '-₱' + couponAmt.toFixed(2);
    } else {
        couponRow.style.display = 'none';
    }

    const vat = discountedTotal * VAT_RATE;
    const total = discountedTotal + vat; // no delivery fee

    document.getElementById('summarySubtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('summaryDiscount').textContent = '₱' + discountAmount.toFixed(2);
    document.getElementById('summaryVat').textContent = '₱' + vat.toFixed(2);
    document.getElementById('summaryTotal').textContent = '₱' + total.toFixed(2);
    
    const labels = {'regular': 'No Discount', 'pwd': 'PWD (20%)', 'senior': 'Senior (20%)'};
    document.getElementById('discountTypeLabel').textContent = labels[discountType];
}
</script>

<?php include 'includes/footer/footer.php'; ?>

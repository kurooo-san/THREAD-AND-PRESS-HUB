<?php
require 'includes/config.php';
redirectToLogin();

$pageTitle = 'Shopping Cart';

// The cart can show the discount and VAT accurately because both depend only
// on the account type and the items. Delivery cannot be known here -- it comes
// from the address chosen at checkout -- so it is not guessed.
$cartUserType     = 'regular';
$cartDiscountRate = 0.0;
$cartDiscountLabel = 'Discount';
$ctStmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
if ($ctStmt) {
    $ctStmt->bind_param('i', $_SESSION['user_id']);
    $ctStmt->execute();
    if ($row = $ctStmt->get_result()->fetch_assoc()) {
        $cartUserType = (string) $row['user_type'];
    }
    $ctStmt->close();
}
if ($cartUserType === 'pwd') {
    $cartDiscountRate  = 0.20;
    $cartDiscountLabel = 'PWD discount (20%)';
} elseif ($cartUserType === 'senior') {
    $cartDiscountRate  = 0.20;
    $cartDiscountLabel = 'Senior discount (20%)';
}
?>

<?php include 'includes/header/header.php'; ?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb" style="font-size:0.85rem;">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="shop.php" class="text-decoration-none">Shop</a></li>
            <li class="breadcrumb-item active">Cart</li>
        </ol>
    </nav>
    <h1 style="font-weight:800; font-size:2rem; margin-bottom:1.5rem;">Shopping Cart</h1>

    <div class="row g-4">
        <div class="col-lg-8">
            <div id="cartItems">
                <!-- Cart items will be loaded here via JavaScript -->
            </div>
        </div>

        <div class="col-lg-4">
            <div class="order-summary">
                <h5 style="font-weight: 700; margin-bottom:1.25rem;">Order Summary</h5>
                
                <div class="summary-row">
                    <span>Subtotal <small class="text-muted" id="selCount"></small></span>
                    <span id="subtotal">&#8369;0.00</span>
                </div>

                <?php if ($cartDiscountRate > 0): ?>
                <div class="summary-row">
                    <span><?php echo htmlspecialchars($cartDiscountLabel); ?></span>
                    <span id="discount" style="color: #16a34a; font-weight: 600;">-&#8369;0.00</span>
                </div>
                <?php endif; ?>

                <div class="summary-row">
                    <span>VAT (12%)</span>
                    <span id="vat">&#8369;0.00</span>
                </div>

                <?php // The cart cannot know the shipping fee: it depends on the address
                      // chosen at checkout (&#8369;50-&#8369;300 by area, free for pickup). Showing a
                      // fixed &#8369;50 here made the cart promise a total the checkout then
                      // exceeded, so say plainly that it is added later. ?>
                <div class="summary-row">
                    <span>Delivery Fee</span>
                    <span class="text-muted" style="font-size:0.85rem;">Added at checkout</span>
                </div>

                <div class="summary-row total">
                    <span>Estimated Total</span>
                    <span id="total">&#8369;0.00</span>
                </div>
                <p class="text-muted" style="font-size:0.76rem; margin:0.5rem 0 0;">
                    Shipping is worked out from your delivery address on the next step.
                    Store pickup is free.
                </p>

                <form id="checkoutForm" method="POST" action="checkout.php" style="margin-top: 1rem;">
                    <input type="hidden" name="subtotal" id="formSubtotal" value="0">
                    <input type="hidden" name="cart_items" id="formCartItems" value="[]">
                    <button type="submit" id="checkoutBtn" class="btn btn-dark btn-lg w-100" style="border-radius:12px; font-weight:600; opacity: 0.5; cursor: not-allowed; pointer-events: none;">
                        Proceed to Checkout <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </form>

                <a href="shop.php" class="btn btn-outline-dark w-100 mt-2" style="border-radius:12px;">
                    Continue Shopping
                </a>
            </div>
        </div>
    </div>
</div>

<script>
/* Rates mirrored from checkout.php so the cart's estimate matches what the
   next page actually charges. Delivery is deliberately NOT guessed here — it
   depends on the address picked at checkout. */
const VAT_RATE      = 0.12;
const DISCOUNT_RATE = <?php echo json_encode($cartDiscountRate); ?>;

/**
 * A stable key for one cart line.
 *
 * Selection is remembered by this rather than by array index, because
 * removing an item shifts every index after it and would silently move the
 * ticks onto the wrong products.
 */
function lineKey(item) {
    return [item.id, item.color || '', item.size || ''].join('|');
}

/** Keys the customer has UNticked. Anything not listed is selected. */
function readDeselected() {
    try { return JSON.parse(localStorage.getItem('cartDeselected')) || []; }
    catch (e) { return []; }
}

function writeDeselected(keys) {
    try { localStorage.setItem('cartDeselected', JSON.stringify(keys)); } catch (e) {}
}

function isSelected(item) {
    return readDeselected().indexOf(lineKey(item)) === -1;
}

/** Tick or untick one line, then recompute. */
function toggleSelect(idx) {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const item = cart[idx];
    if (!item) { return; }

    const key = lineKey(item);
    const off = readDeselected();
    const at  = off.indexOf(key);
    if (at === -1) { off.push(key); } else { off.splice(at, 1); }
    writeDeselected(off);
    loadCart();
}

/** Tick or untick everything at once. */
function toggleSelectAll(checked) {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    writeDeselected(checked ? [] : cart.map(lineKey));
    loadCart();
}

function loadCart() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const cartItemsContainer = document.getElementById('cartItems');

    if (cart.length === 0) {
        cartItemsContainer.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h5>Your cart is empty</h5>
                <p>Looks like you haven't added any items yet</p>
                <a href="shop.php" class="btn btn-dark" style="border-radius:12px;">Start Shopping</a>
            </div>
        `;
        updateSummary([]);
        return;
    }

    const selectedCount = cart.filter(isSelected).length;

    let html = `
        <div class="d-flex align-items-center justify-content-between mb-2" style="padding:0 0.25rem;">
            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.88rem; font-weight:600;">
                <input type="checkbox" class="form-check-input mt-0" id="selectAll"
                       ${selectedCount === cart.length ? 'checked' : ''}
                       onchange="toggleSelectAll(this.checked)">
                Select all
            </label>
            <span class="text-muted" style="font-size:0.82rem;">${selectedCount} of ${cart.length} selected</span>
        </div>
    `;

    cart.forEach((item, idx) => {
        const on = isSelected(item);
        html += `
            <div class="cart-item" style="${on ? '' : 'opacity:0.55;'}">
                <div class="d-flex align-items-start" style="padding-right:0.75rem; align-self:flex-start;">
                    <input type="checkbox" class="form-check-input mt-1" ${on ? 'checked' : ''}
                           aria-label="Include ${item.name} in this order"
                           onchange="toggleSelect(${idx})">
                </div>
                <div class="cart-item-details" style="flex: 1;">
                    <h5 style="font-weight:600; margin-bottom:0.25rem;">${item.name}</h5>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Color: ${item.color || 'N/A'} &middot; Size: ${item.size || 'N/A'}</p>
                    <p style="font-weight:600; margin: 0.5rem 0;">&#8369;${item.price.toFixed(2)}</p>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-dark" style="border-radius:8px; width:32px; height:32px; padding:0;" onclick="updateQuantity(${idx}, -1)">&minus;</button>
                        <input type="number" value="${item.quantity}" min="1" class="form-control form-control-sm text-center" style="width:50px; border-radius:8px;" onchange="setQuantity(${idx}, this.value)">
                        <button class="btn btn-sm btn-outline-dark" style="border-radius:8px; width:32px; height:32px; padding:0;" onclick="updateQuantity(${idx}, 1)">+</button>
                    </div>
                </div>
                <div class="text-end d-flex flex-column align-items-end justify-content-between">
                    <div class="cart-item-price" style="font-size:1.1rem; font-weight:700;">&#8369;${(item.price * item.quantity).toFixed(2)}</div>
                    <button class="btn btn-sm text-muted mt-2" style="font-size:0.82rem;" onclick="removeFromCart(${idx})">
                        <i class="fas fa-trash-alt"></i> Remove
                    </button>
                </div>
            </div>
        `;
    });

    cartItemsContainer.innerHTML = html;
    updateSummary(cart);
}

function updateQuantity(index, change) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    if (cart[index]) {
        cart[index].quantity += change;
        if (cart[index].quantity < 1) cart[index].quantity = 1;
        localStorage.setItem('cart', JSON.stringify(cart));
        loadCart();
    }
}

function setQuantity(index, quantity) {
    quantity = Math.max(1, parseInt(quantity) || 1);
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    if (cart[index]) {
        cart[index].quantity = quantity;
        localStorage.setItem('cart', JSON.stringify(cart));
        loadCart();
    }
}

function removeFromCart(index) {
    if (!confirm('Remove this item from cart?')) return;

    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    if (cart[index]) {
        // Drop it from the deselected list too, so a later item that happens to
        // reuse the same key does not inherit a stale tick.
        const key = lineKey(cart[index]);
        writeDeselected(readDeselected().filter(k => k !== key));
        cart.splice(index, 1);
        localStorage.setItem('cart', JSON.stringify(cart));
        loadCart();
        updateCartCount();
    }
}

function updateSummary(cart) {
    const chosen   = cart.filter(isSelected);
    const subtotal = chosen.reduce((sum, item) => sum + (item.price * item.quantity), 0);

    const discount   = subtotal * DISCOUNT_RATE;
    const afterDisc  = subtotal - discount;
    const vat        = afterDisc * VAT_RATE;
    // Delivery is added at checkout, once an address is known.
    const estimated  = afterDisc + vat;

    document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('vat').textContent      = '₱' + vat.toFixed(2);
    document.getElementById('total').textContent    = '₱' + estimated.toFixed(2);

    const discEl = document.getElementById('discount');
    if (discEl) { discEl.textContent = '-₱' + discount.toFixed(2); }

    const countEl = document.getElementById('selCount');
    if (countEl) {
        countEl.textContent = cart.length ? `(${chosen.length} item${chosen.length === 1 ? '' : 's'})` : '';
    }

    // Only the ticked lines go to checkout.
    document.getElementById('formSubtotal').value  = subtotal;
    document.getElementById('formCartItems').value = JSON.stringify(chosen);

    const checkoutBtn = document.getElementById('checkoutBtn');
    const canCheckout = chosen.length > 0;
    checkoutBtn.style.opacity       = canCheckout ? '1' : '0.5';
    checkoutBtn.style.cursor        = canCheckout ? 'pointer' : 'not-allowed';
    checkoutBtn.style.pointerEvents = canCheckout ? 'auto' : 'none';
    checkoutBtn.disabled            = !canCheckout;

    // checkout.php reads these; cartSelection keeps the unticked items out of
    // the order without removing them from the cart.
    localStorage.setItem('cartSelection', JSON.stringify(chosen));
    localStorage.setItem('subtotal', subtotal);
    localStorage.setItem('total', estimated);
}

// Prevent form submission when nothing is selected
document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.getElementById('checkoutForm');
    checkoutForm.addEventListener('submit', function(e) {
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        const chosen = cart.filter(isSelected);
        if (chosen.length === 0) {
            e.preventDefault();
            alert('Please tick at least one item to check out.');
            return false;
        }
    });
});

// helper to update cart badge in navbar
function updateCartCount() {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    let count = cart.reduce((sum, item) => sum + item.quantity, 0);
    // badge near icon
    let badge = document.querySelector('.nav-link i.fa-shopping-cart + .badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
        } else {
            badge.remove();
        }
    }
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

// Load cart on page load
loadCart();
updateCartCount();
</script>

<?php include 'includes/footer/footer.php'; ?>

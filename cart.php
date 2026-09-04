<?php
require 'includes/config.php';
redirectToLogin();

$pageTitle = 'Shopping Cart';
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
                    <span>Subtotal</span>
                    <span id="subtotal">₱0.00</span>
                </div>

                <div class="summary-row">
                    <span>Discount</span>
                    <span id="discount" style="color: #16a34a; font-weight: 600;">-₱0.00</span>
                </div>

                <div class="summary-row">
                    <span>Delivery Fee</span>
                    <span id="deliveryFee">₱50.00</span>
                </div>

                <div class="summary-row total">
                    <span>Total</span>
                    <span id="total">₱0.00</span>
                </div>

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
const DELIVERY_FEE = 50;

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

    let html = '';
    cart.forEach((item, idx) => {
        html += `
            <div class="cart-item">
                <div class="cart-item-details" style="flex: 1;">
                    <h5 style="font-weight:600; margin-bottom:0.25rem;">${item.name}</h5>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Color: ${item.color || 'N/A'} &middot; Size: ${item.size || 'N/A'}</p>
                    <p style="font-weight:600; margin: 0.5rem 0;">₱${item.price.toFixed(2)}</p>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-dark" style="border-radius:8px; width:32px; height:32px; padding:0;" onclick="updateQuantity(${idx}, -1)">−</button>
                        <input type="number" value="${item.quantity}" min="1" class="form-control form-control-sm text-center" style="width:50px; border-radius:8px;" onchange="setQuantity(${idx}, this.value)">
                        <button class="btn btn-sm btn-outline-dark" style="border-radius:8px; width:32px; height:32px; padding:0;" onclick="updateQuantity(${idx}, 1)">+</button>
                    </div>
                </div>
                <div class="text-end d-flex flex-column align-items-end justify-content-between">
                    <div class="cart-item-price" style="font-size:1.1rem; font-weight:700;">₱${(item.price * item.quantity).toFixed(2)}</div>
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
        cart.splice(index, 1);
        localStorage.setItem('cart', JSON.stringify(cart));
        loadCart();
    }
}

function updateSummary(cart) {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const total = subtotal + DELIVERY_FEE;
    
    document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('total').textContent = '₱' + total.toFixed(2);
    
    // Update form values
    document.getElementById('formSubtotal').value = subtotal;
    document.getElementById('formCartItems').value = JSON.stringify(cart);
    
    // Enable/Disable checkout button based on cart content
    const checkoutBtn = document.getElementById('checkoutBtn');
    const checkoutForm = document.getElementById('checkoutForm');
    
    if (cart.length === 0) {
        checkoutBtn.style.opacity = '0.5';
        checkoutBtn.style.cursor = 'not-allowed';
        checkoutBtn.style.pointerEvents = 'none';
        checkoutBtn.disabled = true;
    } else {
        checkoutBtn.style.opacity = '1';
        checkoutBtn.style.cursor = 'pointer';
        checkoutBtn.style.pointerEvents = 'auto';
        checkoutBtn.disabled = false;
    }
    
    // Store for backup
    localStorage.setItem('subtotal', subtotal);
    localStorage.setItem('total', total);
}

// Prevent form submission if cart is empty
document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.getElementById('checkoutForm');
    checkoutForm.addEventListener('submit', function(e) {
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        if (cart.length === 0) {
            e.preventDefault();
            alert('Your cart is empty! Please add items before proceeding to checkout.');
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

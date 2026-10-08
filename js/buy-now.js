/**
 * "Buy Now" — skip the cart and go straight to checkout with one item.
 *
 * The customer's real cart is deliberately NOT touched. The item is parked in
 * its own localStorage key ("buyNow") and checkout.php reads that instead when
 * it receives buy_now=1, so someone who already has three things in their
 * basket does not lose them by buying a fourth directly.
 *
 * Prices here are only for the on-screen figures — checkout.php re-reads every
 * price from the database before charging anything, so a tampered value in
 * localStorage cannot change what the customer actually pays.
 */
(function (global) {
    'use strict';

    // What a guest was doing when sent to login, so it can finish afterwards.
    var PENDING_KEY = 'pendingCartAction';
    var PENDING_TTL_MS = 30 * 60 * 1000;

    /**
     * Guests may browse, but the cart and checkout need an account. Sends a
     * guest to login (and back to this page afterwards) and returns false;
     * returns true when signed in. Pages set window.IS_LOGGED_IN; when it is
     * not set this never blocks.
     *
     * Pass the item and 'cart' or 'buy' to have that action finished
     * automatically once the guest is back from login.
     */
    function requireLogin(item, mode) {
        if (global.IS_LOGGED_IN !== false) { return true; }
        if (item) {
            try {
                localStorage.setItem(PENDING_KEY, JSON.stringify({
                    item: item, mode: mode === 'buy' ? 'buy' : 'cart', at: Date.now()
                }));
            } catch (e) { /* storage blocked: they just click again after login */ }
        }
        global.location.href = 'login.php?redirect=' +
            encodeURIComponent(global.location.pathname + global.location.search);
        return false;
    }

    /**
     * Put one line in the 'cart' localStorage list, merging with the same
     * product/colour/size. Same shape and rule as shop.php and product.php.
     */
    function addLineToCart(item) {
        var cart = [];
        try { cart = JSON.parse(localStorage.getItem('cart')) || []; } catch (e) { cart = []; }
        if (!Array.isArray(cart)) { cart = []; }

        var quantity = parseInt(item.quantity, 10) || 1;
        var existing = cart.find(function (i) {
            return i.id == item.id && i.color === item.color && i.size === item.size;
        });
        if (existing) {
            existing.quantity += quantity;
        } else {
            cart.push({
                id: item.id,
                name: item.name,
                price: parseFloat(item.price) || 0,
                quantity: quantity,
                color: item.color,
                size: item.size
            });
        }
        localStorage.setItem('cart', JSON.stringify(cart));
    }

    /**
     * Back from login with a saved action: finish it once. Runs before the
     * page's own scripts, so their on-load cart count already includes it.
     */
    function resumePendingAction() {
        if (global.IS_LOGGED_IN !== true) { return; }

        // Cleared before parsing, so even a damaged entry is used up once.
        var pending = null;
        try {
            var raw = localStorage.getItem(PENDING_KEY);
            localStorage.removeItem(PENDING_KEY);
            pending = JSON.parse(raw);
        } catch (e) { return; }
        if (!pending || !pending.item || !(Date.now() - pending.at < PENDING_TTL_MS)) { return; }

        var item = pending.item;
        if (pending.mode === 'buy') {
            document.addEventListener('DOMContentLoaded', function () { buyNowCheckout(item); });
            return;
        }

        try { addLineToCart(item); } catch (e) { return; }
        document.addEventListener('DOMContentLoaded', function () {
            if (global.showToast) { global.showToast(item.name + ' added to cart!', 'success'); }
        });
    }

    /**
     * Send one item to checkout.
     *
     * @param {{id:number|string, name:string, price:number, quantity:number,
     *          color:string, size:string}} item
     */
    function buyNowCheckout(item) {
        if (!requireLogin(item, 'buy')) { return; }

        var quantity = parseInt(item.quantity, 10) || 1;
        if (quantity < 1) { quantity = 1; }

        var price = parseFloat(item.price) || 0;
        var line = {
            id: item.id,
            name: item.name,
            price: price,
            quantity: quantity,
            color: item.color || 'Default',
            size: item.size || 'N/A'
        };

        try {
            localStorage.setItem('buyNow', JSON.stringify([line]));
            localStorage.setItem('buyNowSubtotal', String(price * quantity));
        } catch (e) {
            // Private mode or storage full: fall back to the cart route rather
            // than dropping the customer on an empty checkout.
            if (global.showToast) { global.showToast('Could not start checkout. Please use Add to Cart.', 'error'); }
            else { alert('Could not start checkout. Please use Add to Cart.'); }
            return;
        }

        // checkout.php is reached by POST (it expects a subtotal), so submit a
        // form rather than following a link.
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'checkout.php';
        form.style.display = 'none';

        [['subtotal', price * quantity], ['buy_now', '1']].forEach(function (pair) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = pair[0];
            input.value = pair[1];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    global.buyNowCheckout = buyNowCheckout;
    global.requireLogin = requireLogin;

    resumePendingAction();
})(window);

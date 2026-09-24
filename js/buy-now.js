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

    /**
     * Send one item to checkout.
     *
     * @param {{id:number|string, name:string, price:number, quantity:number,
     *          color:string, size:string}} item
     */
    function buyNowCheckout(item) {
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
})(window);

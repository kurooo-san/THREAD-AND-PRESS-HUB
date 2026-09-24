-- Bank Transfer Payment Channels (BDO / BPI / Security Bank / RCBC)
-- Run this SQL in phpMyAdmin or MySQL CLI.
--
-- Both payment_method columns are ENUMs, so they reject any value not listed
-- in their definition. Adding the four bank channels to includes/payment-config.php
-- is not enough on its own — without this migration an order paid by bank
-- transfer fails to insert.
--
-- Existing values are preserved; this only widens what is accepted.

ALTER TABLE orders
    MODIFY COLUMN payment_method
        ENUM('gcash','cod','maya','instapay','bdo','bpi','securitybank','rcbc')
        NOT NULL;

ALTER TABLE custom_order_payments
    MODIFY COLUMN payment_method
        ENUM('gcash','maya','cod','instapay','bdo','bpi','securitybank','rcbc')
        NOT NULL;

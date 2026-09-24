-- =====================================================================
-- PayMongo Checkout Sessions migration
--
-- Run this once in phpMyAdmin (or the MySQL CLI) before using the
-- PayMongo flow:   mysql -u root threadpresshub < migrate_paymongo.sql
--
-- Two things happen here:
--   1. 'paymongo' is added to the payment_method ENUMs, so an order paid
--      through the hosted page can actually be recorded.
--   2. paymongo_sessions is created. It maps a PayMongo checkout session
--      id back to one of our orders, which is what lets the return page
--      and the webhook agree on which order was just paid.
-- =====================================================================

ALTER TABLE orders
    MODIFY COLUMN payment_method
        ENUM('gcash','cod','maya','instapay','bdo','bpi','securitybank','rcbc','paymongo') NOT NULL;

ALTER TABLE custom_order_payments
    MODIFY COLUMN payment_method
        ENUM('gcash','maya','cod','instapay','bdo','bpi','securitybank','rcbc','paymongo') NOT NULL;

CREATE TABLE IF NOT EXISTS paymongo_sessions (
    id                  INT AUTO_INCREMENT PRIMARY KEY,

    -- PayMongo's id, e.g. cs_XXXXXXXXXXXXXXXXXXXXXXXX. Unique so a replayed
    -- webhook cannot create a second row for the same session.
    session_id          VARCHAR(100)    NOT NULL,

    -- Which of our two order types this session is paying for.
    order_kind          ENUM('order','custom') NOT NULL,
    order_id            INT             NOT NULL,
    user_id             INT             NOT NULL,

    -- Peso amount we asked PayMongo to collect. Compared against the amount
    -- PayMongo reports as paid, so a tampered or stale session is caught.
    amount              DECIMAL(10,2)   NOT NULL,

    status              ENUM('created','paid','failed','expired') NOT NULL DEFAULT 'created',
    checkout_url        TEXT            DEFAULT NULL,

    -- Filled in once payment succeeds.
    payment_id          VARCHAR(100)    DEFAULT NULL,
    payment_method_used VARCHAR(40)     DEFAULT NULL,

    -- The raw PayMongo payload, kept for admin troubleshooting and disputes.
    raw_payload         LONGTEXT        DEFAULT NULL,

    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_session (session_id),
    KEY idx_order (order_kind, order_id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

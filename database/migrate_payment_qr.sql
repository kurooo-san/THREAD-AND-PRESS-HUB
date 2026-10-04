-- =====================================================================
-- Manual QR Payment — schema migration
-- =====================================================================
-- Adds the `payment_submissions` table used by the manual QR payment
-- feature. This is ADDITIVE and non-destructive: it creates one new table
-- and does not alter or drop any existing table or data.
--
-- The existing `orders` table already carries the order-level payment
-- state we rely on:
--   payment_status ENUM('unpaid','pending_verification','verified','rejected')
--   payment_reference, payment_proof, payment_method
--
-- `payment_submissions` stores the full proof-of-payment record plus the
-- verification audit trail (who reviewed, when, and why a payment was
-- rejected). One order may have several submissions over time (e.g. the
-- customer re-submits after a rejection), so this is a 1-to-many table.
--
-- Run once:  mysql -u root threadpresshub < migrate_payment_qr.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS `payment_submissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `channel` VARCHAR(20) NOT NULL COMMENT 'instapay | gcash | maya',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `reference_number` VARCHAR(100) NOT NULL,
  -- Path RELATIVE to the protected storage root (see PAYMENT_PROOF_STORAGE).
  -- Never a web-accessible URL; served only through serve-proof.php.
  `proof_path` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending_verification','verified','rejected') NOT NULL DEFAULT 'pending_verification',
  `reject_reason` VARCHAR(500) DEFAULT NULL,
  `submitted_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` INT(11) DEFAULT NULL COMMENT 'admin users.id who approved/rejected',
  `reviewed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_status` (`status`),
  KEY `idx_reference` (`reference_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

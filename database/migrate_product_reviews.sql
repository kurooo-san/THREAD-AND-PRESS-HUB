-- Product Reviews Migration
-- Run this SQL in phpMyAdmin or MySQL CLI.
--
-- Purely additive: creates one new table and touches nothing that exists.
-- Every read is guarded by a SHOW TABLES check, so the storefront keeps
-- working normally if this migration has not been run yet.

CREATE TABLE IF NOT EXISTS product_reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    user_id     INT NOT NULL,

    -- 1..5, enforced in PHP and by the CHECK below on MySQL 8 / MariaDB 10.2+
    rating      TINYINT UNSIGNED NOT NULL,
    title       VARCHAR(120) DEFAULT NULL,
    body        TEXT DEFAULT NULL,

    -- Set when the reviewer has a non-cancelled order containing this product.
    is_verified TINYINT(1) NOT NULL DEFAULT 0,

    -- Lets an admin hide a review without deleting the row.
    status      ENUM('published','hidden') NOT NULL DEFAULT 'published',

    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- One review per customer per product. Re-submitting updates the row
    -- instead of stacking duplicates.
    UNIQUE KEY uniq_product_user (product_id, user_id),
    KEY idx_product_status (product_id, status),

    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_review_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

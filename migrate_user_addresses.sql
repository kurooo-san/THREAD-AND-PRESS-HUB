-- =====================================================================
-- Multiple delivery addresses (max 3 per customer)
--
-- Run once:  mysql -u root threadpresshub < migrate_user_addresses.sql
--
-- The users.street_address / barangay / city / province / zipcode columns
-- are KEPT and kept in sync with whichever address is the default. A lot of
-- existing code reads them (checkout prefill, invoices, the delivery-zone
-- lock), so removing them would break working features for no benefit.
-- Think of them as a cached copy of the default address.
-- =====================================================================

CREATE TABLE IF NOT EXISTS user_addresses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT             NOT NULL,

    -- Short name the customer picks, e.g. "Home", "Dorm", "Office".
    label           VARCHAR(40)     NOT NULL DEFAULT 'Home',

    street_address  VARCHAR(255)    NOT NULL,
    barangay        VARCHAR(100)    DEFAULT NULL,
    city            VARCHAR(100)    NOT NULL,
    province        VARCHAR(100)    NOT NULL,
    zipcode         VARCHAR(20)     DEFAULT NULL,

    -- Exactly one default per user; the app enforces it.
    is_default      TINYINT(1)      NOT NULL DEFAULT 0,

    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_user (user_id),
    CONSTRAINT fk_address_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill: every customer who already has an address on their profile gets
-- it as their first, default entry. Guarded so re-running adds nothing.
INSERT INTO user_addresses (user_id, label, street_address, barangay, city, province, zipcode, is_default)
SELECT u.id, 'Home',
       u.street_address,
       NULLIF(u.barangay, ''),
       COALESCE(NULLIF(u.city, ''), '-'),
       COALESCE(NULLIF(u.province, ''), '-'),
       NULLIF(u.zipcode, ''),
       1
  FROM users u
 WHERE u.street_address IS NOT NULL
   AND u.street_address <> ''
   AND NOT EXISTS (SELECT 1 FROM user_addresses a WHERE a.user_id = u.id);

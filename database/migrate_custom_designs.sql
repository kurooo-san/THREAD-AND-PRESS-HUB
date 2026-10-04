-- Custom Designs Table Migration
-- Run this SQL in phpMyAdmin or MySQL CLI

CREATE TABLE IF NOT EXISTS custom_designs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_type VARCHAR(50) NOT NULL DEFAULT 'tshirt',
    design_image LONGTEXT NOT NULL,
    design_data JSON DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('pending', 'approved', 'revision', 'completed', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    order_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add custom_design_id to order_items for linking
ALTER TABLE order_items ADD COLUMN custom_design_id INT DEFAULT NULL AFTER size;
ALTER TABLE order_items ADD FOREIGN KEY (custom_design_id) REFERENCES custom_designs(id) ON DELETE SET NULL;

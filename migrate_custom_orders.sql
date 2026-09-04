-- Custom Orders & Payments Migration
-- Thread & Press Hub - Custom Apparel Payment Flow

-- Custom Orders table
CREATE TABLE IF NOT EXISTS custom_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    design_id INT NOT NULL,
    design_image VARCHAR(500) NOT NULL,
    product_type VARCHAR(50) NOT NULL DEFAULT 'tshirt',
    apparel_color VARCHAR(20) DEFAULT '#FFFFFF',
    size VARCHAR(10) NOT NULL DEFAULT 'M',
    quantity INT NOT NULL DEFAULT 1,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    print_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    color_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_type VARCHAR(20) DEFAULT 'regular',
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pending_payment','payment_uploaded','payment_verified','processing','printing','ready_pickup','delivered','cancelled') DEFAULT 'pending_payment',
    notes TEXT DEFAULT NULL,
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (design_id) REFERENCES custom_designs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Custom Order Payments table
CREATE TABLE IF NOT EXISTS custom_order_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_order_id INT NOT NULL,
    payment_method ENUM('gcash','maya','cod') NOT NULL,
    payment_proof VARCHAR(500) DEFAULT NULL,
    reference_number VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status ENUM('pending','verified','rejected') DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (custom_order_id) REFERENCES custom_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat Bot FAQ Management Table
-- This table stores custom FAQ responses that can be managed by admins

CREATE TABLE IF NOT EXISTS `chatbot_faq` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` varchar(255) NOT NULL COMMENT 'Question keywords',
  `answer` longtext NOT NULL COMMENT 'Bot response',
  `category` varchar(50) DEFAULT 'general' COMMENT 'FAQ category',
  `priority` int(11) DEFAULT 100 COMMENT 'Priority for matching (higher = matched first)',
  `active` tinyint(1) DEFAULT 1 COMMENT 'Is this FAQ active?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'Admin user who created this',
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`),
  KEY `idx_active` (`active`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default FAQs
INSERT INTO `chatbot_faq` (`question`, `answer`, `category`, `priority`, `active`) VALUES

-- General Help
('help', 'I\'m here to help! You can ask me about:\n• 👕 Products & Collections\n• 💸 Prices & Discounts\n• 🚚 Delivery & Shipping\n• 💳 Payment Methods\n• 📦 Order Tracking\n• 🔄 Returns & Exchanges\n• 👥 Account Help\n\nWhat would you like to know?', 'general', 100, 1),

-- Products
('products clothing apparel', 'We offer premium apparel for women, men, and kids! 👕👔\n• High-quality fabrics\n• Trendy designs\n• Affordable luxury\n• Wide size range (XS-XXL)\n\nWould you like to browse our collection? 🛍️', 'products', 90, 1),

('mens men clothes', 'Our men\'s collection features:\n• Premium T-shirts & Polos (₱499-₱699)\n• Comfortable Hoodies (₱1,299-₱1,399)\n• Stylish Pants & Jeans (₱1,299-₱1,599)\n• Quality Accessories (₱699+)\n\nAll items available in multiple colors and sizes! 👕', 'products', 85, 1),

('womens women clothes', 'Our women\'s collection includes:\n• Elegant Dresses (₱1,999-₱2,499)\n• Cozy Hoodies (₱1,299-₱1,399)\n• Trendy Pants & Jeans (₱1,499-₱1,599)\n• Comfortable T-shirts (₱499-₱599)\n\nFind your perfect style today! ✨👗', 'products', 85, 1),

('kids children baby', 'We have adorable kids collection:\n• Fun Cartoon Tees (₱299)\n• Cozy Hoodies (₱899)\n• Durable Jeans (₱799)\n• Cute Dresses (₱599)\n• Accessories & Shoes (₱499-₱899)\n\nPerfect for all ages! 👶', 'products', 85, 1),

-- Pricing & Discounts
('price cost how much', 'Our prices are very competitive! 💰\n• Most items: ₱699 - ₱2,999\n• Accessories: Starting from ₱499\n• Seasonal sales: Up to 40% OFF\n\nFree shipping on orders above ₱1,500! 🎁', 'pricing', 80, 1),

('discount sale promo promotion', 'Great news! 🎉 We offer:\n• Regular promotions on seasonal items\n• Up to 40% off during sales\n• Free shipping on orders above ₱1,500\n• Special discounts for PWD & Senior Citizens\n• Loyalty rewards for frequent buyers\n\nCheck our Promotion page for current deals!', 'pricing', 80, 1),

('shipping delivery free', '🚚 Delivery Info:\n• Metro Manila: 1-2 business days\n• Provincial areas: 2-3 business days\n• Free shipping on orders above ₱1,500\n• Standard delivery fee: ₱50\n• All orders are carefully packaged', 'shipping', 75, 1),

('free shipping threshold minimum', 'Yes! We offer free shipping on orders above ₱1,500. 🎁\nThis applies to all areas in the Philippines!\nSave on shipping and enjoy your purchases! 🛍️', 'shipping', 75, 1),

-- Payment
('payment methods pay', '💳 We accept:\n• GCash (Mobile payment)\n• Cash on Delivery (COD)\n• Both options available for all orders\n• Secure transaction process\n• Fast payment confirmation', 'payment', 70, 1),

('gcash online payment', 'GCash Payment Info:\n📱 Quick and safe online payment\n✅ Instant confirmation\n🔒 Secure transaction\n📦 Get your order faster\n\nChoose GCash at checkout for quick processing!', 'payment', 70, 1),

('cod cash on delivery', 'Cash on Delivery (COD) Details:\n💵 Pay when your order arrives\n🚚 Perfect for local deliveries\n✅ No online payment stress\n🔒 Verify items before payment\n\nAvailable for all areas!', 'payment', 70, 1),

-- Orders & Tracking
('order track status', '📦 Need help with your order?\n• Check status: Login to your account\n• Track delivery: Get real-time updates\n• Returns: 30-day policy available\n• Cancellations: Contact support asap\n\nHow can I help you with your order?', 'orders', 95, 1),

('tracking trace delivery', '📍 Order Tracking:\n1. Log in to your account\n2. Go to \"Orders\" section\n3. Click on your order\n4. View real-time status updates\n5. Get delivery notifications\n\nNeed more help? Contact us!', 'orders', 95, 1),

-- Returns & Exchanges
('return refund policy', 'Return Policy 🔄:\n• 30-day return window from purchase\n• Item must be unused & in original condition\n• Tag and packaging must be intact\n• Free returns on damaged items\n• Contact support to start process\n\nWe want you to be 100% satisfied!', 'support', 60, 1),

('exchange swap change', 'Exchange Service 🔁:\n• Easy size/color changes\n• Within 30 days of purchase\n• Free shipping on exchanges\n• Quick turnaround time\n• Contact support to request\n\nWe make it easy!', 'support', 60, 1),

-- Account & Contact
('account profile manage', '👤 Account Management:\n• Create an account anytime\n• Save favorite items\n• View purchase history\n• Track orders\n• Manage delivery addresses\n• Profile settings & preferences', 'account', 50, 1),

('contact support help email phone', '📞 Contact Thread and Press Hub:\n• 📧 Email: support@threadpresshub.com\n• 📱 Phone: +63 (02) 8123-4567\n• 💬 Chat: Available 24/7 right here!\n• 📍 Serving all of Philippines\n\nWe\'re always happy to help!', 'support', 85, 1),

-- Closing Responses
('thank you thanks', 'Happy to help! 🙏 Enjoy your shopping experience at Thread and Press Hub!', 'general', 40, 1),

('bye goodbye', 'Thank you for visiting! 👋 Come back soon! 🛍️ Happy shopping! ✨', 'general', 40, 1);

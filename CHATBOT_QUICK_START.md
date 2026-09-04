# CHATBOT SYSTEM - QUICK START (5 MINUTES)

## ⚡ Step 1: Add Database Table (2 mins)

Open **phpMyAdmin** → Select `threadpresshub` database → Click **SQL** tab

Copy and paste this:
```sql
CREATE TABLE IF NOT EXISTS `chatbot_faq` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` varchar(255) NOT NULL,
  `answer` longtext NOT NULL,
  `category` varchar(50) DEFAULT 'general',
  `priority` int(11) DEFAULT 100,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default FAQs (20 sample questions)
INSERT INTO `chatbot_faq` (`question`, `answer`, `category`, `priority`, `active`) VALUES
('help', 'I\'m here to help! You can ask me about:\n• 👕 Products & Collections\n• 💸 Prices & Discounts\n• 🚚 Delivery & Shipping\n• 💳 Payment Methods\n• 📦 Order Tracking\n• 🔄 Returns & Exchanges\n• 👥 Account Help\n\nWhat would you like to know?', 'general', 100, 1),
('products clothing apparel', 'We offer premium apparel for women, men, and kids! 👕👔\n• High-quality fabrics\n• Trendy designs\n• Affordable luxury\n• Wide size range (XS-XXL)', 'products', 90, 1),
('shipping delivery', '🚚 Delivery Info:\n• Metro Manila: 1-2 business days\n• Provincial: 2-3 business days\n• Free shipping on orders above ₱1,500\n• Standard fee: ₱50', 'shipping', 80, 1),
('payment methods pay', '💳 We accept:\n• GCash (Mobile payment)\n• Cash on Delivery (COD)\n• Both available for all orders', 'payment', 75, 1),
('order track status', '📦 Need help with order?\n• Check status: Login to account\n• Track delivery: Real-time updates\n• Returns: 30-day policy available', 'orders', 95, 1),
('return refund policy', 'Return Policy 🔄:\n• 30-day return window\n• Item must be unused\n• Original packaging required\n• Free returns on damaged items', 'support', 60, 1),
('contact support', '📞 Contact us:\n• 📧 support@threadpresshub.com\n• 📱 (02) 8123-4567\n• 💬 Chat here 24/7', 'support', 85, 1),
('thank you thanks', '😊 Happy to help! Enjoy your shopping!', 'general', 40, 1),
('bye goodbye', 'Thank you! Come back soon! 👋 Happy shopping! ✨', 'general', 40, 1);
```

Click **Go** ✅

## ⚡ Step 2: Verify New Files Exist (1 min)

Check these were created:
- [ ] `includes/order-lookup.php`
- [ ] `includes/product-recommendations.php`
- [ ] `admin/chatbot-faq.php`
- [ ] `migrate_chatbot_faq.sql` (reference file)
- [ ] `CHATBOT_COMPLETE_GUIDE.md` (documentation)

## ⚡ Step 3: Check Updated Files (1 min)

These files were **enhanced** with new features:
- [ ] `js/chatbot.js` - Added product/order APIs + quick replies
- [ ] `css/style.css` - Added quick reply button styles

## ⚡ Step 4: Test It! (1 min)

1. Open http://localhost/thread-and-presshub/
2. Click **chat icon** (bottom right)
3. Try these:
   - Type: "order 1" → Should show order status ✅
   - Type: "hoodie" → Should show products ✅
   - Click quick reply buttons → Should auto-send ✅

---

## 🎯 What You Can Do Now

### Customers Can:
```
ask "Track order 5"         → Shows order details
ask "Show me hoodies"       → Lists product recommendations  
ask "What's your return policy?" → Gets FAQ answer
ask "How much is shipping?" → Automatic response
Click quick reply buttons    → Pre-filled questions
```

### Admins Can:
```
Visit /admin/chatbot-faq.php → Manage FAQ responses
Add new Q&A pairs            → Train the bot
Edit existing FAQs           → Keep content fresh
Delete old responses         → Clean up system
Set priority levels          → Control matching
```

---

## 📊 Quick Reference

| Feature | Command | File |
|---------|---------|------|
| Order Lookup | "order 123" | `includes/order-lookup.php` |
| Product Search | "shirt" | `includes/product-recommendations.php` |
| Quick Replies | Click buttons | `js/chatbot.js` |
| FAQ Management | `/admin/chatbot-faq.php` | `admin/chatbot-faq.php` |
| Chat History | `/chat_history.php` | Already exists |

---

## 🔑 Key Endpoints

- **Frontend:** `http://localhost/thread-and-presshub/` (floating widget)
- **Admin Panel:** `http://localhost/thread-and-presshub/admin/chatbot-faq.php`
- **Chat History:** `http://localhost/thread-and-presshub/chat_history.php`

---

## ✅ You're Done!

Your chatbot system is **complete and ready**. Start using it immediately!

📚 **For detailed info:** Read `CHATBOT_COMPLETE_GUIDE.md`

Questions? Check the troubleshooting section in the complete guide.

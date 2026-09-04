# Thread and Press Hub - Apparel Studio Ordering System

A modern, responsive PHP-based apparel studio ordering system with user authentication, GCash payment integration, and special discounts for PWD and Senior Citizens.

## Features

### 🛍️ Customer Features
- **User Authentication**: Register, login, and logout with secure password hashing
- **Shop Browsing**: Beautiful apparel catalog with categories and descriptions
- **Shopping Cart**: Add/remove items with quantity management
- **Special Discounts**:
  - PWD Discount: 12% off with valid PWD ID
  - Senior Citizen Discount: 15% off with valid Senior ID
- **Multiple Payment Options**:
  - GCash Pay: Digital payment with reference verification
  - Cash on Delivery: Pay when order arrives
- **Order Tracking**: View order history and current status
- **About & Contact Pages**: Learn more about us and reach out via contact form
- **User Profile**: Update personal information and manage account

### 👨‍💼 Admin Features
- **Dashboard**: Overview of sales, users, products, and orders
- **Product Management**: Add, edit, and manage menu items
- **Order Management**: View all orders and update their status
- **User Management**: Monitor customer accounts

### 🎨 Design Features
- Beautiful modern fashion design
- Fully responsive (mobile, tablet, desktop)
- Modern UI with smooth animations
- Professional color scheme and typography

## Requirements

- PHP 7.4 or higher
- MySQL/MariaDB
- Apache web server (with mod_rewrite enabled)
- Modern web browser

## Installation

### 1. Database Setup

1. Open phpMyAdmin (usually at `http://localhost/phpmyadmin`)
2. Create a new database named `threadpresshub`
3. Import the `threadpresshub.sql` file:
   - Select the `threadpresshub` database
   - Go to Import tab
   - Choose `threadpresshub.sql` file
   - Click Import

### 2. File Placement

Ensure all files are placed in: `C:\xampp\htdocs\threadpresshub\`

Directory structure should be:
```
thrad-and-presshub/
├── index.php
├── login.php
├── register.php
├── logout.php
├── shop.php
├── cart.php
├── checkout.php
├── orders.php
├── profile.php
├── order_confirmation.php
├── payment_gcash.php
├── admin/
│   ├── dashboard.php
│   ├── products.php
│   └── orders.php
├── includes/
│   ├── config.php
│   ├── header.php
│   └── footer.php
├── css/
│   └── style.css
├── images/
│   └── products/
├── about.php
├── contact.php
└── threadpresshub.sql
```

### 3. Create Images Directory

Create the `images/products/` directory if it doesn't exist:
```
mkdir C:\xampp\htdocs\threadpresshub\images\products
```

> **Database Migration**
> 
> If you're updating an existing installation and you already have an `order_items` table, run the following SQL in phpMyAdmin or via MySQL CLI to store product preferences:
> 
> ```sql
> ALTER TABLE order_items
>     ADD COLUMN color varchar(50) DEFAULT NULL,
>     ADD COLUMN size varchar(10) DEFAULT NULL;
> ```
> 
> New installations using the provided `threadpresshub.sql` already include these columns.

### 4. Access the Application

1. Start Apache and MySQL in XAMPP
2. Visit: `http://localhost/threadpresshub/`

## Default Admin Account

**Email:** admin@threadpresshub.com  
**Password:** admin123

⚠️ **IMPORTANT**: Change this password after first login!

## Usage

### For Customers

1. **Register**: Create an account on the register page
2. **Select Account Type**: Choose Regular, PWD, or Senior Citizen
3. **Browse Menu**: View available products
4. **Add to Cart**: Select quantity and add items
5. **Checkout**: Review order, select payment method, and apply discount if eligible
6. **Payment**: Complete payment via GCash or select Cash on Delivery
7. **Track Order**: View order status in "My Orders" page

### For Admins

1. **Login**: Use admin credentials
2. **Dashboard**: View key metrics
4. **Manage Products**: Add new apparel items and manage existing ones
4. **Manage Orders**: View and update order statuses
5. **Manage Users**: Monitor customer accounts

## Payment Integration

### GCash Payment

Currently configured for demonstration. To integrate real GCash API:

1. Register at GCash Developer Portal
2. Get API credentials
3. Update `payment_gcash.php` with actual API calls
4. Implement webhook for payment verification

Current implementation requires manual reference number verification.

## Discount System

- **PWD (Person with Disability)**: 12% discount
  - Requires valid PWD ID during checkout
- **Senior Citizens**: 15% discount
  - Requires valid Senior ID during checkout
- **Regular Users**: No discount

Discounts are applied at checkout and included in order total.

## Database Tables

### users
- User accounts with roles (customer, admin)
- PWD/Senior ID storage for discount verification

### products
- Apparel items with categories, sizes, colors, prices, and images

### orders
- Customer orders with totals and discount tracking

### order_items
- Individual items in each order

### gcash_transactions
- GCash payment records

## Security Features

- ✅ Password hashing with bcrypt
- ✅ SQL injection prevention with prepared statements
- ✅ Input sanitization
- ✅ Session-based authentication
- ✅ CSRF token ready (can be added)

## File Permissions

Ensure `images/products/` is writable:
```
chmod 755 images/products/
```

## Troubleshooting

### Database Connection Failed
- Check if MySQL is running
- Verify credentials in `includes/config.php`
- Ensure database `threadpresshub` exists

### Images Not Loading
- Verify `images/products/` directory exists and is writable
- Check image file permissions

### Admin Dashboard Not Loading
- Ensure logged-in user is admin type
- Check session settings in `includes/config.php`

## Customization

### Change Logo/Branding
- Edit `includes/header.php` - Change "Thread and Press Hub" text
- Update colors in `css/style.css` - Modify CSS variables

### Add New Product Categories
- Edit dropdown in `admin/products.php`
- Update category values in database

### Modify Discount Rates
- Edit `calculateDiscount()` function in `includes/config.php`
- Update discount percentages as needed

## Future Enhancements

- Real GCash API integration
- SMS notifications for order updates
- Email confirmations
- Customer reviews and ratings
- Loyalty rewards program
- Advanced analytics dashboard
- Online order scheduling
- Multiple language support

## Support

For issues or questions, please check:
1. Database connection in `includes/config.php`
2. File permissions on `images/products/` directory
3. Browser console for JavaScript errors

## License

This project is for educational and commercial use.

## Credits

Built with PHP, MySQL, Bootstrap 5, and Font Awesome Icons.

---

**Thread and Press Hub** - Premium apparel and custom designs for every style! 👕

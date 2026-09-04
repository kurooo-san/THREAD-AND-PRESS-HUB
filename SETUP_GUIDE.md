# Thread and Press Hub - Quick Setup Guide

## Step 1: Database Setup

### Option A: Using phpMyAdmin (Recommended)
1. Open your browser and go to `http://localhost/phpmyadmin`
2. Click on "New" to create a new database
3. Enter database name: `threadpresshub`
4. Click "Create"
5. Click on the `threadpresshub` database
6. Go to "Import" tab
7. Click "Choose File" and select `threadpresshub.sql`
8. Click "Go" or "Import"

### Option B: Using Command Line
```bash
mysql -u root -p < threadpresshub.sql
```

## Step 2: Verify File Structure

Ensure these folders exist:
- `C:\xampp\htdocs\threadpresshub\images\`
- `C:\xampp\htdocs\threadpresshub\images\products\`
- `C:\xampp\htdocs\threadpresshub\admin\`
- `C:\xampp\htdocs\threadpresshub\includes\`
- `C:\xampp\htdocs\threadpresshub\css\`

Create them if they don't exist.

## Step 3: Set File Permissions (Windows)

Right-click on `threadpresshub` folder → Properties:
1. Go to Security tab
2. Click Edit
3. Select Users/your username
4. Check "Modify" permission
5. Apply and OK

## Step 4: Start XAMPP

1. Open XAMPP Control Panel
2. Start Apache (click Start)
3. Start MySQL (click Start)

## Step 5: Access the Application

Open your browser and visit:
```
http://localhost/threadpresshub/
```

## First Login

**Admin Account:**
- Email: `admin@threadpresshub.com`
- Password: `admin123`

**Important:** Change this password immediately after logging in!

## Step 6: Add Products

1. Login as admin
2. Go to Admin Dashboard
3. Click "Manage Products"
4. Add your coffee menu items

## Troubleshooting

### Error: "Connection failed"
- Verify MySQL is running
- Check database name in `includes/config.php`
- Default config: host=localhost, user=root, password=(empty)

### Error: "No such file or directory"
- Check if `threadpresshub.sql` exists in the main folder
- Check file paths in includes/config.php

### Images not uploading
- Ensure `images/products/` folder exists
- Check folder permissions (set to writable)
- Check file upload size limit in php.ini

### Cart not working
- Check browser localStorage is enabled
- Clear browser cache
- Try in a different browser

## Features Overview

### Customer Dashboard
- Browse menu
- Add to cart
- Checkout with discounts
- Multiple payment options
- Track orders

### Admin Dashboard
- Manage products
- Manage orders
- Track revenue
- View user accounts

## Discount Types

1. **PWD** - 12% off (requires valid PWD ID)
2. **Senior** - 15% off (requires valid Senior ID)
3. **Regular** - No discount

## Payment Methods

1. **GCash** - Digital payment with reference verification
2. **Cash on Delivery** - Pay when order arrives

## Default Settings

- Delivery Fee: ₱50
- Currency: Philippine Pesos (₱)
- Timezone: Asia/Manila (can be changed in config.php)

## Database Tables

- `users` - Customer accounts
- `products` - Menu items
- `orders` - Customer orders
- `order_items` - Items in each order (now records selected color/size if applicable)
- `gcash_transactions` - Payment records

## Next Steps

1. ✅ Database setup
2. ✅ File structure verification
3. ✅ XAMPP running
4. ✅ Admin account configured
5. 📝 Add your coffee products
6. 🎨 Customize branding (optional)
7. 🚀 Ready to accept orders!

## Customization

### Change App Name
Edit in files:
- `includes/header.php` - Line with "threadpresshub"
- `css/style.css` - Branding colors

### Change Colors
In `css/style.css`, modify:
```css
--coffee-dark: #2d1810;
--accent-green: #00704a;
```

### Add New Discount
In `includes/config.php`, edit `calculateDiscount()` function

### Change Delivery Fee
In `checkout.php` and `cart.php`, update `DELIVERY_FEE` value

## Support Resources

- Check README.md for detailed documentation
- Verify all files are present
- Check browser console for errors (F12)
- Check PHP error logs in XAMPP

## Security Reminders

⚠️ Before going live:
1. Change admin password
2. Use HTTPS (SSL certificate)
3. Update database credentials
4. Implement proper GCash API integration
5. Regular backups
6. Keep PHP/MySQL updated

---

For detailed documentation, see `README.md`

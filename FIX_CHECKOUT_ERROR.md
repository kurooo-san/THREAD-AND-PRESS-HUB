# Fix: Add to Cart + Checkout Error

## Problem
When trying to checkout, you get this error:
```
Fatal error: Uncaught mysqli_sql_exception: Unknown column 'color' in 'field list'
```

## Root Cause
The `order_items` database table was missing `color` and `size` columns needed to store the selected color and size for each order item.

## Solution - Quick Fix (Recommended)

### Option 1: Automatic Migration (Easiest)
1. Open your browser and go to:
   ```
   http://localhost/thread-and-presshub/migrate.php
   ```

2. The script will automatically add the missing columns

3. You should see: **✓ Migration Successful!**

4. Then go back to [http://localhost/thread-and-presshub/shop.php](shop.php) and try checkout again

### Option 2: Manual SQL Migration
If the automatic migration doesn't work:

1. Open phpMyAdmin
2. Select `threadpresshub` database
3. Go to the **SQL** tab
4. Copy and paste this code:
   ```sql
   ALTER TABLE `order_items` ADD COLUMN `color` varchar(50) DEFAULT NULL AFTER `subtotal`;
   ALTER TABLE `order_items` ADD COLUMN `size` varchar(10) DEFAULT NULL AFTER `color`;
   ```
5. Click **Execute**

## What Changed

### Database Schema Update
The `order_items` table now has two new columns:

| Column | Type | Purpose |
|--------|------|---------|
| `color` | varchar(50) | Stores the color selected for the item |
| `size` | varchar(10) | Stores the size selected for the item |

### Files Updated
- ✅ **checkout.php** - Now handles missing columns gracefully
- ✅ **migrate.php** - Automatic migration runner (NEW)
- ✅ **migrate_add_color_size.sql** - SQL migration file (NEW)

## Testing

After running the migration:

1. Go to [Shop](shop.php)
2. Select a color and size for a product
3. Add to cart
4. Go to [Cart](cart.php)
5. Click **Proceed to Checkout**
6. Fill in delivery info and payment method
7. Click **Place Order**
8. ✓ Order should be created successfully!

## Verification

To verify the migration worked:

1. In phpMyAdmin
2. Select `threadpresshub` database
3. Click `order_items` table
4. You should see columns: id, order_id, product_id, quantity, unit_price, subtotal, **color**, **size**

## Rollback (If Needed)

If you need to remove these columns:
```sql
ALTER TABLE `order_items` DROP COLUMN `color`;
ALTER TABLE `order_items` DROP COLUMN `size`;
```

---

**⚠️ Recommended:** Use Option 1 (Automatic Migration) for fastest results!

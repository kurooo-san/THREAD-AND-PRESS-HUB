<?php
/**
 * Database Migration Runner
 * This script automatically adds missing columns to order_items table
 * Run this once by visiting: http://localhost/thread-and-presshub/migrate.php
 */

require 'includes/config.php';

$migration_complete = false;
$messages = [];

// Check if color column exists
$result = $conn->query("SHOW COLUMNS FROM order_items LIKE 'color'");
$color_exists = $result && $result->num_rows > 0;

// Check if size column exists
$result = $conn->query("SHOW COLUMNS FROM order_items LIKE 'size'");
$size_exists = $result && $result->num_rows > 0;

// Run migrations if needed
if (!$color_exists) {
    if ($conn->query("ALTER TABLE `order_items` ADD COLUMN `color` varchar(50) DEFAULT NULL AFTER `subtotal`")) {
        $messages[] = "✓ Added 'color' column to order_items table";
    } else {
        $messages[] = "✗ Failed to add 'color' column: " . $conn->error;
    }
}

if (!$size_exists) {
    if ($conn->query("ALTER TABLE `order_items` ADD COLUMN `size` varchar(10) DEFAULT NULL AFTER `color`")) {
        $messages[] = "✓ Added 'size' column to order_items table";
    } else {
        $messages[] = "✗ Failed to add 'size' column: " . $conn->error;
    }
}

// Check final status
$result = $conn->query("SHOW COLUMNS FROM order_items LIKE 'color'");
$color_final = $result && $result->num_rows > 0;

$result = $conn->query("SHOW COLUMNS FROM order_items LIKE 'size'");
$size_final = $result && $result->num_rows > 0;

if ($color_final && $size_final) {
    $migration_complete = true;
    $messages[] = "\n✓ MIGRATION COMPLETE! Database is ready to use checkout with color/size selection.";
} else {
    $messages[] = "\n⚠ Migration incomplete. Please check the errors above.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; display: flex; align-items: center; }
        .container { max-width: 600px; }
        .card { border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .card-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; }
        .success { color: #28a745; font-weight: 600; }
        .error { color: #dc3545; font-weight: 600; }
        .check { color: #28a745; margin-right: 8px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">🔧 Database Migration</h4>
            </div>
            <div class="card-body">
                <?php if ($migration_complete): ?>
                    <div class="alert alert-success" role="alert">
                        <h5>✓ Migration Successful!</h5>
                        <p>The database has been updated with color and size support.</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning" role="alert">
                        <h5>⚠ Migration Status</h5>
                    </div>
                <?php endif; ?>

                <h6 class="mt-4">Migration Results:</h6>
                <ul class="list-unstyled">
                    <?php foreach ($messages as $msg): ?>
                        <li class="mb-2">
                            <?php if (strpos($msg, '✓') === 0): ?>
                                <span class="success"><?php echo htmlspecialchars($msg); ?></span>
                            <?php elseif (strpos($msg, '✗') === 0): ?>
                                <span class="error"><?php echo htmlspecialchars($msg); ?></span>
                            <?php else: ?>
                                <strong><?php echo htmlspecialchars($msg); ?></strong>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <h6 class="mt-4">Database Schema:</h6>
                <p><small class="text-muted">order_items table now has:</small></p>
                <ul class="small">
                    <li>id - Order item ID</li>
                    <li>order_id - Link to orders table</li>
                    <li>product_id - Link to products table</li>
                    <li>quantity - Number of units</li>
                    <li>unit_price - Price per item</li>
                    <li>subtotal - quantity × unit_price</li>
                    <li><strong class="text-success">color</strong> - <small>NEW: Selected color</small></li>
                    <li><strong class="text-success">size</strong> - <small>NEW: Selected size</small></li>
                </ul>

                <hr>

                <div class="text-center mt-4">
                    <a href="shop.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> Go to Shop
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Go to Home
                    </a>
                </div>

                <?php if (!$migration_complete): ?>
                    <div class="alert alert-danger mt-4" role="alert">
                        <h6>⚠ Manual Fix Required</h6>
                        <p>If the migration failed, you can manually run the SQL:</p>
                        <pre>ALTER TABLE `order_items` ADD COLUMN `color` varchar(50) DEFAULT NULL AFTER `subtotal`;
ALTER TABLE `order_items` ADD COLUMN `size` varchar(10) DEFAULT NULL AFTER `color`;</pre>
                        <p class="mt-3"><small>Run this in phpMyAdmin → SQL tab on the threadpresshub database</small></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mt-4">
            <small class="text-white-50">
                <i class="fas fa-info-circle"></i> This migration adds color and size storage for checkout orders
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

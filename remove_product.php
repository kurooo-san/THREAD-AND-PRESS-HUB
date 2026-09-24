<?php
/**
 * One-shot cleanup script. GUARDED: it runs a DELETE, and was reachable over
 * HTTP by anyone.
 */
require 'includes/config.php';
require_once __DIR__ . '/includes/maintenance-guard.php';
requireCli('remove_product.php');

// Delete Men's Leather Belt
$query = "DELETE FROM products WHERE name = 'Men\\'s Leather Belt'";
$result = $conn->query($query);

if ($result) {
    echo "Product 'Men's Leather Belt' has been successfully removed.";
} else {
    echo "Error deleting product: " . $conn->error;
}

$conn->close();
?>

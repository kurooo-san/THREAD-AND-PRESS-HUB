<?php
require 'includes/config.php';

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

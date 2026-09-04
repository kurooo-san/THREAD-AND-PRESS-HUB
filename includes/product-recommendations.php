<?php
/**
 * Product Recommendations API
 * Recommends products based on user query
 * Endpoint: POST /includes/product-recommendations.php
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$query = strtolower(trim($input['query'] ?? ''));

if (empty($query)) {
    echo json_encode(['error' => 'Query is required']);
    exit();
}

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Keyword mapping for product categories
    $keywordMap = [
        'shirt' => ['shirts', 't-shirt', 'tee', 'polo'],
        'hoodie' => ['hoodie', 'sweatshirt', 'hoody', 'jacket'],
        'dress' => ['dress', 'gown', 'casual dress', 'formal'],
        'pants' => ['pants', 'jeans', 'trousers', 'shorts', 'bottoms'],
        'accessories' => ['cap', 'hat', 'bag', 'scarf', 'accessories', 'belt']
    ];
    
    $matchedCategory = null;
    
    // Find which category matches
    foreach ($keywordMap as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($query, $keyword) !== false) {
                $matchedCategory = $category;
                break 2;
            }
        }
    }
    
    // Search for products
    $searchQuery = "%{$query}%";
    
    $sql = "SELECT id, name, price, category FROM products 
            WHERE LOWER(name) LIKE ? OR LOWER(category) LIKE ? 
            ORDER BY RAND() LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $searchQuery, $searchQuery);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response = "🛍️ **Great Choice!** Here are some recommendations:\n\n";
        $products = [];
        
        while ($product = $result->fetch_assoc()) {
            $products[] = $product;
            $emoji = '👕';
            if (stripos($product['category'], 'woman') !== false) $emoji = '👗';
            if (stripos($product['category'], 'kid') !== false) $emoji = '👶';
            if (stripos($product['category'], 'hoo') !== false) $emoji = '🧥';
            
            $response .= "{$emoji} **" . htmlspecialchars($product['name']) . "**\n";
            $response .= "   💰 ₱" . number_format($product['price'], 2) . "\n";
            $response .= "   Category: " . ucfirst($product['category']) . "\n\n";
        }
        
        $response .= "💡 **Next Step:** Visit our shop to see more details and add to cart! 🛒";
        
        echo json_encode([
            'success' => true,
            'type' => 'products_found',
            'message' => $response,
            'products' => $products,
            'count' => count($products)
        ]);
    } else {
        // No products found, provide helpful guidance
        $response = "Hmm, I didn't find exact matches for \"" . htmlspecialchars($query) . "\". 😅\n\n";
        $response .= "But I can help you find:\n";
        $response .= "👕 T-shirts & Polos\n";
        $response .= "🧥 Hoodies & Sweaters\n";
        $response .= "👗 Dresses\n";
        $response .= "👖 Pants & Jeans\n";
        $response .= "👒 Accessories\n\n";
        $response .= "💡 Try asking about any of these! 😊";
        
        echo json_encode([
            'success' => true,
            'type' => 'no_products',
            'message' => $response
        ]);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Product search failed: ' . $e->getMessage()
    ]);
}

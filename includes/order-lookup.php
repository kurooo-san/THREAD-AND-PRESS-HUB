<?php
/**
 * Order Lookup API
 * Allows chatbot to retrieve order information
 * Endpoint: POST /includes/order-lookup.php
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$query = trim($input['query'] ?? '');
$userId = $_SESSION['user_id'] ?? null;

if (empty($query)) {
    echo json_encode(['error' => 'Query is required']);
    exit();
}

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    // Check if query is an order ID (number) or a status request
    $orderId = null;
    $statusOnly = false;
    
    // Try to extract order ID from query like "123", "order 123", "track 123"
    if (preg_match('/\d+/', $query, $matches)) {
        $orderId = intval($matches[0]);
    }
    
    // If we found an order ID
    if ($orderId) {
        // If user is logged in, verify they own the order or show public info
        $sql = "SELECT o.*, 
                    COUNT(oi.id) as item_count,
                    GROUP_CONCAT(p.name, ', ') as product_names
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE o.id = ?";
        
        // Only owner can see full details without user ID, or anyone if they know the ID
        if ($userId) {
            $sql .= " AND (o.user_id = ? OR o.id = ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $orderId, $userId, $orderId);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $orderId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $order = $result->fetch_assoc();
            
            // Format status for readable display
            $statusDisplay = ucfirst(str_replace('_', ' ', $order['status']));
            $statusEmoji = [
                'pending' => '⏳',
                'confirmed' => '✅',
                'preparing' => '🔧',
                'out_for_delivery' => '🚚',
                'completed' => '🎉',
                'cancelled' => '❌'
            ];
            
            $emoji = $statusEmoji[$order['status']] ?? '📦';
            
            $response = "📦 **Order #" . $order['id'] . "**\n";
            $response .= "Status: {$emoji} {$statusDisplay}\n";
            $response .= "Items: " . $order['item_count'] . " product(s)\n";
            $response .= "Total: ₱" . number_format($order['total'], 2) . "\n";
            $payLookup = ['gcash' => '💳 GCash', 'maya' => '💳 Maya', 'cod' => '💵 Cash on Delivery'];
            $response .= "Payment: " . ($payLookup[$order['payment_method']] ?? ucfirst($order['payment_method'])) . "\n";
            $response .= "Order Date: " . date('F d, Y H:i A', strtotime($order['created_at'])) . "\n";
            
            if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled') {
                $response .= "\n⏩ **Next Steps:** Your order is being processed. You'll receive updates soon!";
            }
            
            echo json_encode([
                'success' => true,
                'type' => 'order_found',
                'message' => $response,
                'order_data' => [
                    'id' => $order['id'],
                    'status' => $order['status'],
                    'total' => $order['total'],
                    'items' => $order['item_count'],
                    'date' => date('F d, Y', strtotime($order['created_at']))
                ]
            ]);
            $stmt->close();
        } else {
            echo json_encode([
                'success' => true,
                'type' => 'order_not_found',
                'message' => "❌ Sorry, I couldn't find order #" . $orderId . ". \n\n💡 **Tips:**\n• Make sure you entered the correct order ID\n• Log in to view your orders\n• Contact support@threadpresshub.com if you need help"
            ]);
        }
    } else {
        // No order ID found, but user asked about orders
        if ($userId) {
            // Get recent orders for logged-in user
            $stmt = $conn->prepare("SELECT id, status, total, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $response = "📦 **Your Recent Orders:**\n\n";
                while ($order = $result->fetch_assoc()) {
                    $statusEmoji = [
                        'pending' => '⏳',
                        'confirmed' => '✅',
                        'preparing' => '🔧',
                        'out_for_delivery' => '🚚',
                        'completed' => '🎉',
                        'cancelled' => '❌'
                    ];
                    $emoji = $statusEmoji[$order['status']] ?? '📦';
                    $response .= "{$emoji} Order #{$order['id']} - " . ucfirst(str_replace('_', ' ', $order['status'])) . "\n";
                    $response .= "  ₱" . number_format($order['total'], 2) . " • " . date('M d, Y', strtotime($order['created_at'])) . "\n\n";
                }
                $response .= "💬 Ask me about a specific order ID to see more details!";
                
                echo json_encode([
                    'success' => true,
                    'type' => 'orders_list',
                    'message' => $response
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'type' => 'no_orders',
                    'message' => "📭 You don't have any orders yet! 😊\n\nWould you like to:\n• 👕 Browse our products\n• ❓ Ask about shipping\n• 💳 Learn about payment options"
                ]);
            }
            $stmt->close();
        } else {
            echo json_encode([
                'success' => true,
                'type' => 'order_help',
                'message' => "📦 **Order Tracking Help**\n\nYou can:\n• 🔍 Ask about a specific order (\"Check order 123\")\n• 📋 Log in to view all your orders\n• ☎️ Contact support@threadpresshub.com\n\n💡 Tip: Log in to see your complete order history!"
            ]);
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Order lookup failed: ' . $e->getMessage()
    ]);
}

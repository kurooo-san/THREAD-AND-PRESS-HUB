<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// API Key
$apiKey = defined('GEMINI_API_KEY') && GEMINI_API_KEY ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'Gemini API key is not configured. Please set GEMINI_API_KEY in your .env file.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

// Require login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please log in to use the chatbot.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');
$conversationHistory = $input['history'] ?? [];

if (empty($userMessage)) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit();
}

// --- Build dynamic context from database ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
$dynamicContext = '';

if (!$conn->connect_error) {
    try {
        // Fetch product catalog for context
        $productContext = '';
        $result = $conn->query("SELECT name, price, category, description FROM products ORDER BY category, name LIMIT 50");
        if ($result && $result->num_rows > 0) {
            $productContext = "\n\nCURRENT PRODUCT CATALOG:\n";
            while ($row = $result->fetch_assoc()) {
                $productContext .= "- " . $row['name'] . " | ₱" . number_format($row['price'], 2) . " | Category: " . $row['category'];
                if (!empty($row['description'])) {
                    $desc = mb_substr($row['description'], 0, 80);
                    $productContext .= " | " . $desc;
                }
                $productContext .= "\n";
            }
        }
        $dynamicContext .= $productContext;

        // Fetch user's recent orders if logged in
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $stmt = $conn->prepare("SELECT o.id, o.status, o.total, o.payment_method, o.created_at, 
                                        GROUP_CONCAT(p.name SEPARATOR ', ') as products
                                    FROM orders o 
                                    LEFT JOIN order_items oi ON o.id = oi.order_id 
                                    LEFT JOIN products p ON oi.product_id = p.id 
                                    WHERE o.user_id = ? 
                                    GROUP BY o.id 
                                    ORDER BY o.created_at DESC LIMIT 5");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $orderResult = $stmt->get_result();
                if ($orderResult && $orderResult->num_rows > 0) {
                    $dynamicContext .= "\n\nCUSTOMER'S RECENT ORDERS:\n";
                    while ($order = $orderResult->fetch_assoc()) {
                        $dynamicContext .= "- Order #" . $order['id'] . " | Status: " . ucfirst(str_replace('_', ' ', $order['status'])) 
                            . " | Total: ₱" . number_format($order['total'], 2) 
                            . " | Payment: " . ucfirst($order['payment_method']) 
                            . " | Date: " . date('M d, Y', strtotime($order['created_at']))
                            . " | Items: " . ($order['products'] ?: 'N/A') . "\n";
                    }
                }
                $stmt->close();
            }

            // Get user name for personalization
            $stmtUser = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
            if ($stmtUser) {
                $stmtUser->bind_param("i", $userId);
                $stmtUser->execute();
                $userResult = $stmtUser->get_result();
                if ($userResult && $row = $userResult->fetch_assoc()) {
                    $dynamicContext .= "\nCUSTOMER NAME: " . $row['fullname'] . "\n";
                }
                $stmtUser->close();
            }
        } else {
            $dynamicContext .= "\n\nNote: Customer is NOT logged in. If they ask about orders, suggest they log in first.\n";
        }
    } catch (Exception $e) {
        // DB errors should not break the chatbot - continue without extra context
        error_log('Chatbot DB context error: ' . $e->getMessage());
    }
}

// System prompt with real store data
$systemPrompt = "You are the AI customer support assistant for Thread and Press Hub, a premium online apparel store in the Philippines.

ROLE & BEHAVIOR:
- You are professional, warm, and knowledgeable about fashion and the store's products.
- Always provide helpful, accurate answers based on the real product data and order information provided below.
- Keep responses concise (2-4 sentences for simple questions, more for complex ones).
- Use relevant emojis sparingly to keep responses engaging but professional.
- Format product names in bold using **name** syntax.
- If a customer asks about a product, reference actual items from the catalog below with real prices.
- If a customer asks about their order, use the real order data below.
- Never make up products or prices that aren't in the catalog.
- If you don't have enough information, be honest and suggest contacting support.
- If asked something completely unrelated to fashion/shopping, briefly acknowledge and steer back.

STORE DETAILS:
- Store: Thread and Press Hub
- Email: support@threadpresshub.com  
- Phone: +63 (02) 8123-4567
- Delivery: 1-2 business days (Metro Manila), 2-3 business days (Provincial)
- Free shipping on orders above ₱1,500
- Standard shipping fee: ₱50 (₱0 for Store Pickup)
- VAT: a 12% VAT is added at checkout, computed on the discounted item total and shown as a separate line in the order summary
- Payment methods: GCash (mobile), Maya (digital wallet), and Cash on Delivery (COD)
- Return policy: 30 days, item must be unused with original tags
- Exchange: Free shipping on exchanges within 30 days
- PWD & Senior Citizen discounts: 20% off for verified PWD and Senior Citizens (must provide valid ID during registration)
- Website: Browse products at the Shop page

CUSTOM DESIGN SERVICE ('Design' / 'Custom Design' page):
- Customers design their own apparel on a built-in canvas with a live 3D mockup preview (front/back, spin, flip).
- Tools: brush, eraser, line, rectangle, circle, add text (fonts, bold/italic), upload your own logo/image, and animal stamps.
- AI DESIGN GENERATOR (powered by Google Gemini): type a description (e.g. 'minimalist mountain sunset') and the AI instantly creates the artwork and drops it onto the canvas. No drawing skills needed.
- Every element (text, image, AI artwork, stamp) can be DRAGGED, RESIZED, and ROTATED. A Layers panel lets you select, reorder (bring forward / send back), and delete each element.
- Choose garment type (T-shirt, Hoodie, Polo, Corporate, Couple Wear, etc.), apparel color, print size, size, and quantity. A live price estimate updates as you design.
- After submission, the design goes through admin review/approval, then the customer gets a final quote and can pay.
- Custom orders typically take 5-7 business days to produce after payment confirmation.
- Track custom order status on the 'My Custom Orders' page.

VIRTUAL TRY-ON ('Try-On' page, powered by Google Gemini AI):
- Customers can virtually 'wear' clothes before buying. Open the camera (or upload a full-body photo), pick a product, and the AI generates a realistic image of them wearing that exact garment in a few seconds.
- A 5-second countdown gives time to pose/step back; tip: stand far enough so the whole body is visible for the best result.
- AI STYLIST (Auto Mode): the AI looks at the customer and suggests the top 3 items from the catalog that best suit them, with reasons. Tap a suggestion to try it on instantly.
- A gender filter (All / Men / Women / Kids) scopes both the catalog and the AI Stylist suggestions.
- Extra tools: Before/After compare slider, switch front/back camera, Add to Cart, Download the look (watermarked), and Save / Delete looks in 'Saved Looks'.
- Only wearable items (t-shirts, hoodies, dresses, pants) can be tried on; accessories are excluded.

SIZE GUIDE:
- T-Shirts & Hoodies:
  * XS: Chest 32-34 in, Length 26 in (fits kids/petite)
  * S: Chest 34-36 in, Length 27 in
  * M: Chest 38-40 in, Length 28 in
  * L: Chest 42-44 in, Length 29 in
  * XL: Chest 46-48 in, Length 30 in
  * XXL: Chest 50-52 in, Length 31 in
- Pants & Jeans:
  * S: Waist 28-30 in
  * M: Waist 30-32 in
  * L: Waist 32-34 in
  * XL: Waist 34-36 in
  * XXL: Waist 36-38 in
- Dresses:
  * XS: Bust 30-32 in, Waist 24-26 in
  * S: Bust 32-34 in, Waist 26-28 in
  * M: Bust 34-36 in, Waist 28-30 in
  * L: Bust 38-40 in, Waist 30-32 in
  * XL: Bust 40-42 in, Waist 32-34 in
- Accessories (belts, sunglasses, etc.) are one-size or have no size selection — only colors.
- Tip: If between sizes, recommend sizing up for a more comfortable fit.

GARMENT CARE INSTRUCTIONS:
- T-Shirts: Machine wash cold, tumble dry low. Avoid bleach. Iron inside out for prints.
- Hoodies: Machine wash cold, hang dry recommended. Do not iron directly on prints or graphics.
- Pants/Jeans: Wash inside out in cold water. Hang dry to maintain fit and color.
- Dresses: Follow care label. Most can be hand washed or machine washed on delicate cycle.
- Custom printed items: Wash inside out, cold water only. Do not use fabric softener on printed areas.

PROMOTIONS & DISCOUNTS:
- Free shipping on orders ₱1,500 and above.
- 20% discount for PWD (Persons with Disability) — must upload valid PWD ID during registration.
- 20% discount for Senior Citizens — must upload valid Senior Citizen ID during registration.
- Check the Promotions page for seasonal sales and limited-time offers.

FREQUENTLY ASKED QUESTIONS:
- Q: How do I track my order? A: Go to 'My Orders' page after logging in. You'll see real-time status updates.
- Q: Can I cancel my order? A: Orders can be cancelled if status is still 'Pending'. Contact support for processing orders.
- Q: How do I apply my PWD/Senior discount? A: Upload your valid ID during registration. The 20% discount applies automatically at checkout.
- Q: Do you ship nationwide? A: Yes! We deliver across the Philippines. Metro Manila: 1-2 days, Provincial: 2-3 days.
- Q: What if my item doesn't fit? A: You can exchange within 30 days. Item must be unused with original tags attached.
- Q: How does the Virtual Try-On work? A: Go to the 'Try-On' page, allow the camera (or upload a full-body photo), pick a product, and the AI shows you wearing it in seconds. Stand far enough so your whole body is visible.
- Q: What is the AI Stylist? A: On the Try-On page, tap 'AI Stylist' and it suggests the top items that best suit you, with reasons. You can filter by Men/Women/Kids.
- Q: Can the AI create a design for me? A: Yes! On the 'Design' page, use the AI Design Generator — type your idea and Gemini creates the artwork and adds it to your canvas. You can then drag, resize, rotate, and layer it.
- Q: Is there VAT? A: Yes, a 12% VAT is added at checkout, calculated on your discounted item total and shown as a separate line before the total.
- Q: Can I save or download my try-on looks? A: Yes — tap 'Save look' to keep it in 'Saved Looks', or 'Download' to save the image (with our watermark). You can delete saved looks anytime." . $dynamicContext;

// Build conversation contents with history for multi-turn context
$contents = [];
if (!empty($conversationHistory) && is_array($conversationHistory)) {
    // Include up to last 10 turns for context
    $recentHistory = array_slice($conversationHistory, -10);
    foreach ($recentHistory as $turn) {
        if (isset($turn['role']) && isset($turn['text'])) {
            $role = $turn['role'] === 'user' ? 'user' : 'model';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $turn['text']]]
            ];
        }
    }
}
// Add current user message
$contents[] = [
    'role' => 'user',
    'parts' => [['text' => $userMessage]]
];

// API URL - gemini-2.5-flash (gemini-1.5-flash was deprecated)
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

$requestData = [
    'systemInstruction' => [
        'parts' => [['text' => $systemPrompt]]
    ],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.7,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 1024
    ],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE']
    ]
];

// Make API request
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestData),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Error handling
if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'Connection error: Unable to reach Gemini API. ' . $curlError]);
    exit();
}

if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['error']['message'] ?? 'Gemini API returned an error (HTTP ' . $httpCode . ')';
    // Return user-friendly message for quota errors
    if ($httpCode === 429) {
        echo json_encode(['success' => true, 'message' => "⚠️ Our AI assistant is temporarily busy due to high demand. Please try again in a minute, or contact us at support@threadpresshub.com for help."]);
    } else {
        echo json_encode(['success' => false, 'error' => $errorMsg]);
    }
    exit();
}

// Parse response
$apiResponse = json_decode($response, true);

if (!isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
    // Check if blocked by safety
    if (isset($apiResponse['candidates'][0]['finishReason']) && $apiResponse['candidates'][0]['finishReason'] === 'SAFETY') {
        echo json_encode(['success' => true, 'message' => "I'm sorry, I can't respond to that. Please ask me something about our products, orders, or store services! 😊"]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Unexpected response format from Gemini API.']);
    }
    exit();
}

$botMessage = $apiResponse['candidates'][0]['content']['parts'][0]['text'];

// Save chat history if user is logged in
if (isset($_SESSION['user_id']) && !$conn->connect_error) {
    $stmt = $conn->prepare("INSERT INTO chat_history (user_id, user_message, bot_response) VALUES (?, ?, ?)");
    if ($stmt) {
        $uid = $_SESSION['user_id'];
        $stmt->bind_param("iss", $uid, $userMessage, $botMessage);
        $stmt->execute();
        $stmt->close();
    }
}

if (isset($conn) && !$conn->connect_error) {
    $conn->close();
}

echo json_encode([
    'success' => true,
    'message' => $botMessage
]);
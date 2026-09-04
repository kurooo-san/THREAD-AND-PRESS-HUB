<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Require login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please log in to use AI suggestions.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$apiKey = defined('GEMINI_API_KEY') && GEMINI_API_KEY ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');

$input = json_decode(file_get_contents('php://input'), true);
$prompt = trim($input['prompt'] ?? '');
$apparelType = $input['apparel_type'] ?? 'tshirt';

if (empty($prompt)) {
    echo json_encode(['success' => false, 'error' => 'Please describe your design idea.']);
    exit();
}

// Validate apparel type
$validTypes = ['tshirt', 'hoodie', 'polo'];
if (!in_array($apparelType, $validTypes, true)) {
    $apparelType = 'tshirt';
}

$apparelNames = ['tshirt' => 'T-Shirt', 'hoodie' => 'Hoodie', 'polo' => 'Polo Shirt'];

// --- Fallback suggestion pools (used when API is unavailable) ---
$fallbackSuggestions = [
    'floral' => [
        ['name' => 'Botanical Bloom', 'colors' => ['#D4A5A5', '#F5E6CC', '#3E5C50', '#F9F1E6'], 'placement' => 'Center chest with smaller accents on left sleeve', 'description' => 'Delicate hand-drawn wildflowers in a soft, muted palette. Elegant linework gives it a modern botanical illustration feel.', 'tip' => 'Pair with neutral bottoms to let the floral design stand out.'],
        ['name' => 'Tropical Garden', 'colors' => ['#FF6B6B', '#FFE66D', '#06D6A0', '#1B4332'], 'placement' => 'Full front panel print', 'description' => 'Bold tropical leaves and flowers in vibrant colors. A lively, summer-ready design with high contrast.', 'tip' => 'Works best on white or light-colored fabric for max color pop.'],
        ['name' => 'Minimalist Petal', 'colors' => ['#2C2C2C', '#FFFFFF', '#E8B4B8'], 'placement' => 'Small left chest logo area', 'description' => 'A single elegant flower outline in fine strokes. Clean, understated, and sophisticated.', 'tip' => 'Great for everyday wear — subtle enough for casual or semi-formal settings.'],
    ],
    'geometric' => [
        ['name' => 'Prism Shift', 'colors' => ['#6C63FF', '#FF6584', '#FFC75F', '#F1F1F1'], 'placement' => 'Center chest, medium size', 'description' => 'Layered geometric shapes with gradient fills creating an optical illusion. Abstract and eye-catching.', 'tip' => 'Print on dark fabric (black or navy) for maximum visual impact.'],
        ['name' => 'Grid Culture', 'colors' => ['#1A1A1A', '#FFFFFF', '#00B4D8'], 'placement' => 'Full front with subtle back repeat', 'description' => 'Clean grid lines with selective color fills. Think modern architecture meets streetwear.', 'tip' => 'Keep accessories minimal — the design speaks for itself.'],
        ['name' => 'Sacred Geometry', 'colors' => ['#D4AF37', '#1A1A2E', '#E0E0E0'], 'placement' => 'Back panel, large format', 'description' => 'Interlocking circles and triangles forming a mandala-like pattern. Mysterious and artistic.', 'tip' => 'Gold foil printing elevates this design to premium level.'],
    ],
    'minimalist' => [
        ['name' => 'Clean Line', 'colors' => ['#2C2C2C', '#FFFFFF'], 'placement' => 'Small left chest', 'description' => 'Ultra-simple single continuous line drawing. Elegant and modern — less is more.', 'tip' => 'The thinner the line weight, the more refined the look.'],
        ['name' => 'Negative Space', 'colors' => ['#000000', '#F5F5F5', '#C0C0C0'], 'placement' => 'Center chest, medium size', 'description' => 'Uses the fabric color as part of the design via negative space cutouts. Clever and contemporary.', 'tip' => 'Works beautifully on heather gray or off-white fabric.'],
        ['name' => 'Type Only', 'colors' => ['#333333', '#FFFFFF', '#E74C3C'], 'placement' => 'Center chest with accent on back collar', 'description' => 'Bold typography with a single word or short phrase in a striking sans-serif font. Clean and statement-making.', 'tip' => 'Choose a word that resonates — simplicity in message matches the minimalist aesthetic.'],
    ],
    'vintage' => [
        ['name' => 'Retro Badge', 'colors' => ['#D4A574', '#2C1810', '#F5E6D0', '#8B4513'], 'placement' => 'Center chest', 'description' => 'Classic circular badge design with distressed textures and retro typography. Timeless Americana vibes.', 'tip' => 'Printing on a slightly off-white or cream fabric enhances the vintage feel.'],
        ['name' => 'Faded Glory', 'colors' => ['#B8860B', '#F0E6D2', '#5C4033'], 'placement' => 'Full front, distressed wash effect', 'description' => 'Worn-in graphic style that looks like a beloved thrift store find from day one.', 'tip' => 'Request discharge printing for authentic faded-into-fabric look.'],
        ['name' => '70s Sunset', 'colors' => ['#FF6B35', '#F7931E', '#FFD23F', '#1E3A5F'], 'placement' => 'Center chest with wraparound stripes', 'description' => 'Retro gradient stripes and rounded fonts inspired by 1970s surf and skate culture.', 'tip' => 'Pair with high-waisted jeans for full retro styling.'],
    ],
    'abstract' => [
        ['name' => 'Splash Zone', 'colors' => ['#FF1493', '#00CED1', '#FFD700', '#FFFFFF'], 'placement' => 'All-over print or large center', 'description' => 'Dynamic paint splash design with bold colors that appear spontaneous and energetic.', 'tip' => 'All-over printing makes this design extra impactful for festivals and events.'],
        ['name' => 'Ink Blot', 'colors' => ['#1A1A1A', '#4A90D9', '#FFFFFF'], 'placement' => 'Center chest, asymmetric', 'description' => 'Organic flowing ink shapes that create unique abstract forms. Artistic and conversation-starting.', 'tip' => 'Asymmetric placement adds visual interest and feels more artistic.'],
        ['name' => 'Color Block', 'colors' => ['#E63946', '#457B9D', '#F1FAEE', '#A8DADC'], 'placement' => 'Full front panel', 'description' => 'Bold overlapping color blocks creating a modern art composition. Think Mondrian meets streetwear.', 'tip' => 'Screen printing preserves the crisp edges that make this design pop.'],
    ],
    'streetwear' => [
        ['name' => 'Urban Edge', 'colors' => ['#1A1A1A', '#FF0000', '#FFFFFF'], 'placement' => 'Large back print with small front logo', 'description' => 'Bold graphics with strong contrast and cultural references. Designed to turn heads on the street.', 'tip' => 'Oversized fit complements streetwear designs best.'],
        ['name' => 'Glitch Art', 'colors' => ['#00FF41', '#FF00FF', '#000000', '#0ABDC6'], 'placement' => 'Center chest, digital distortion effect', 'description' => 'Digital glitch-inspired graphics with RGB color separation. Futuristic and tech-forward.', 'tip' => 'Looks incredible under UV/black light at concerts and events.'],
        ['name' => 'Tag Style', 'colors' => ['#FFD700', '#1A1A1A', '#FF4500'], 'placement' => 'Lower left front, graffiti angle', 'description' => 'Graffiti-inspired lettering with drip effects. Raw, authentic urban art energy.', 'tip' => 'Puff print adds texture and a premium streetwear feel.'],
    ],
    'nature' => [
        ['name' => 'Mountain Line', 'colors' => ['#2C3E50', '#ECF0F1', '#27AE60', '#F39C12'], 'placement' => 'Center chest, panoramic width', 'description' => 'Continuous line art depicting a mountain landscape with sunrise. Adventurous and serene.', 'tip' => 'Forest green or navy fabric pairs perfectly with nature themes.'],
        ['name' => 'Ocean Wave', 'colors' => ['#0077B6', '#00B4D8', '#CAF0F8', '#FFFFFF'], 'placement' => 'Lower front, rising from hem', 'description' => 'Stylized wave design in varying shades of blue. Captures the dynamic energy of the sea.', 'tip' => 'Placement starting from the hem creates a unique rising water effect.'],
        ['name' => 'Wild Spirit', 'colors' => ['#5C4033', '#D4A574', '#2D5016', '#F5F5DC'], 'placement' => 'Back panel, full size', 'description' => 'Detailed wilderness scene — trees, wildlife, and open sky. For those who carry nature wherever they go.', 'tip' => 'DTG (direct-to-garment) printing captures all the fine details.'],
    ],
    'default' => [
        ['name' => 'Modern Classic', 'colors' => ['#2C3E50', '#ECF0F1', '#E74C3C', '#3498DB'], 'placement' => 'Center chest', 'description' => 'A versatile design combining clean typography with a subtle graphic element. Works for any occasion.', 'tip' => 'Timeless colors ensure this design never goes out of style.'],
        ['name' => 'Creative Spark', 'colors' => ['#6C63FF', '#FF6584', '#2C2C2C', '#FFFFFF'], 'placement' => 'Left chest with accent on right sleeve', 'description' => 'Playful and artistic design that mixes illustration with bold color accents. Expresses creativity.', 'tip' => 'The asymmetric placement adds visual interest and a designer touch.'],
        ['name' => 'Bold Statement', 'colors' => ['#1A1A1A', '#FFFFFF', '#FFD700'], 'placement' => 'Large back print', 'description' => 'Strong visual impact with high contrast elements and premium feel. Designed to impress.', 'tip' => 'Back prints work best in large format — go big for maximum effect.'],
    ],
];

// Function to get fallback suggestions based on prompt keywords
function getFallbackSuggestions($prompt, $apparelType, $apparelNames, $fallbackSuggestions) {
    $prompt_lower = mb_strtolower($prompt);

    // Match keywords to categories
    $keywordMap = [
        'floral' => ['floral', 'flower', 'botanical', 'bloom', 'petal', 'rose', 'daisy', 'garden', 'plant'],
        'geometric' => ['geometric', 'geometry', 'triangle', 'circle', 'square', 'hexagon', 'polygon', 'grid', 'pattern', 'shapes'],
        'minimalist' => ['minimalist', 'minimal', 'simple', 'clean', 'subtle', 'understated', 'basic', 'plain'],
        'vintage' => ['vintage', 'retro', 'old school', 'classic', '70s', '80s', '90s', 'nostalgic', 'throwback', 'antique'],
        'abstract' => ['abstract', 'splash', 'paint', 'artistic', 'modern art', 'ink', 'watercolor', 'expressionist'],
        'streetwear' => ['streetwear', 'street', 'urban', 'graffiti', 'hip hop', 'skate', 'hype', 'drip', 'glitch'],
        'nature' => ['nature', 'mountain', 'ocean', 'wave', 'forest', 'tree', 'animal', 'wildlife', 'outdoor', 'adventure', 'sunset', 'sunrise', 'sea', 'lake'],
    ];

    $matchedCategory = 'default';
    $bestScore = 0;
    foreach ($keywordMap as $category => $keywords) {
        $score = 0;
        foreach ($keywords as $kw) {
            if (strpos($prompt_lower, $kw) !== false) {
                $score++;
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $matchedCategory = $category;
        }
    }

    $suggestions = $fallbackSuggestions[$matchedCategory];

    // Add apparel-specific tip to each suggestion
    $apparelName = $apparelNames[$apparelType] ?? 'T-Shirt';
    foreach ($suggestions as &$s) {
        $s['description'] = $s['description'] . " Perfect for a custom {$apparelName}.";
    }

    return $suggestions;
}

// If no API key, use fallback suggestions
if (empty($apiKey)) {
    $suggestions = getFallbackSuggestions($prompt, $apparelType, $apparelNames, $fallbackSuggestions);
    echo json_encode(['success' => true, 'suggestions' => $suggestions, 'source' => 'fallback']);
    exit();
}

$systemPrompt = "You are a creative apparel design consultant for Thread & Press Hub, a custom clothing studio. 
The customer wants design ideas for a {$apparelNames[$apparelType]}.

Provide exactly 3 design suggestions based on the customer's description. For each suggestion include:
1. A short creative name (max 5 words)
2. Color palette recommendation (2-4 colors with hex codes)  
3. Design placement advice (front, back, sleeve, etc.)
4. Brief visual description (1-2 sentences)
5. Style tip

Format your response as a JSON array with 3 objects, each having these keys:
- name (string)
- colors (array of hex color strings)
- placement (string) 
- description (string)
- tip (string)

Return ONLY the JSON array, no other text.";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($apiKey);

$payload = [
    'contents' => [
        ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\nCustomer's design idea: " . $prompt]]]
    ],
    'generationConfig' => [
        'temperature' => 0.8,
        'maxOutputTokens' => 1024,
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode !== 200) {
    // API unavailable — use fallback suggestions
    $suggestions = getFallbackSuggestions($prompt, $apparelType, $apparelNames, $fallbackSuggestions);
    echo json_encode(['success' => true, 'suggestions' => $suggestions, 'source' => 'fallback']);
    exit();
}

$data = json_decode($response, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($text)) {
    // Empty API response — use fallback suggestions
    $suggestions = getFallbackSuggestions($prompt, $apparelType, $apparelNames, $fallbackSuggestions);
    echo json_encode(['success' => true, 'suggestions' => $suggestions, 'source' => 'fallback']);
    exit();
}

// Extract JSON from response (may be wrapped in markdown code block)
$text = trim($text);
if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
    $text = trim($matches[1]);
}

$suggestions = json_decode($text, true);

if (!is_array($suggestions) || count($suggestions) === 0) {
    // JSON parse failed — use fallback suggestions
    $suggestions = getFallbackSuggestions($prompt, $apparelType, $apparelNames, $fallbackSuggestions);
    echo json_encode(['success' => true, 'suggestions' => $suggestions, 'source' => 'fallback']);
    exit();
}

echo json_encode(['success' => true, 'suggestions' => $suggestions]);

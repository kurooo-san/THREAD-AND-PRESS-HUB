<?php
declare(strict_types=1);

/**
 * Seed a demonstrable manual-QR-payment scenario.
 *
 * Creates ONE sample order that is "Awaiting Verification" with a generated
 * placeholder proof image and a matching payment_submissions record, so the
 * admin verification flow can be exercised end-to-end immediately.
 *
 * Idempotent: re-running will not create duplicates (it tags the order via a
 * marker in `notes`). Safe — it never modifies or deletes existing orders.
 *
 * Run:  C:\xampp\php\php.exe seed_payment_demo.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this seeder from the command line only.\n");
}

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/payment-proof-handler.php';

const DEMO_MARKER = '[QR_DEMO_SEED]';

// --- Already seeded? ---------------------------------------------------
$existing = $conn->query("SELECT id FROM orders WHERE notes LIKE '%" . $conn->real_escape_string(DEMO_MARKER) . "%' LIMIT 1");
if ($existing && $existing->num_rows > 0) {
    $row = $existing->fetch_assoc();
    echo "Demo order already exists (#{$row['id']}). Nothing to do.\n";
    echo "Open: payment-qr.php?order_id={$row['id']}  or  admin/payment-verification.php\n";
    exit;
}

// --- Pick a non-admin customer to own the order ------------------------
$cust = $conn->query("SELECT id FROM users WHERE user_type <> 'admin' ORDER BY id LIMIT 1");
if (!$cust || $cust->num_rows === 0) {
    exit("No non-admin user found to attach the demo order to. Create a customer first.\n");
}
$userId = (int)$cust->fetch_assoc()['id'];

// --- Pick any product for the line item --------------------------------
$prod = $conn->query("SELECT id, price FROM products ORDER BY id LIMIT 1");
if (!$prod || $prod->num_rows === 0) {
    exit("No products found to build a demo order. Add a product first.\n");
}
$p = $prod->fetch_assoc();
$productId = (int)$p['id'];
$unitPrice = (float)$p['price'];
$deliveryFee = 50.00;
$subtotal = $unitPrice;
$total = $subtotal + $deliveryFee;
$reference = 'DEMO' . random_int(100000, 999999);

$conn->begin_transaction();
try {
    // Order in awaiting-verification state.
    $address = '123 Demo Street, Cainta, Rizal, 1900';
    $notes   = 'Sample order for manual QR payment demo ' . DEMO_MARKER;
    $stmt = $conn->prepare(
        "INSERT INTO orders
            (user_id, subtotal, delivery_fee, total,
             payment_method, payment_reference, payment_status, delivery_address, notes, status)
         VALUES (?, ?, ?, ?, 'gcash', ?, 'pending_verification', ?, ?, 'pending')"
    );
    $stmt->bind_param('idddsss', $userId, $subtotal, $deliveryFee, $total, $reference, $address, $notes);
    $stmt->execute();
    $orderId = (int)$stmt->insert_id;
    $stmt->close();

    // Order line item.
    $li = $conn->prepare(
        "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, color, size)
         VALUES (?, ?, 1, ?, ?, 'Black', 'M')"
    );
    $li->bind_param('iidd', $orderId, $productId, $unitPrice, $subtotal);
    $li->execute();
    $li->close();

    // Generate a placeholder proof image into protected storage.
    $destDir = PAYMENT_PROOF_STORAGE . DIRECTORY_SEPARATOR . $orderId;
    if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
        throw new \RuntimeException('Could not create proof storage dir.');
    }
    $filename = bin2hex(random_bytes(16)) . '.png';
    $absPath  = $destDir . DIRECTORY_SEPARATOR . $filename;
    $img = imagecreatetruecolor(400, 300);
    $bg  = imagecolorallocate($img, 240, 244, 248);
    $fg  = imagecolorallocate($img, 30, 30, 30);
    imagefilledrectangle($img, 0, 0, 400, 300, $bg);
    imagestring($img, 5, 60, 110, 'DEMO PAYMENT RECEIPT', $fg);
    imagestring($img, 4, 60, 140, 'Ref: ' . $reference, $fg);
    imagestring($img, 4, 60, 165, 'Amount: PHP ' . number_format($total, 2), $fg);
    imagepng($img, $absPath);
    imagedestroy($img);
    $relPath = $orderId . '/' . $filename;

    // Submission record.
    $ps = $conn->prepare(
        "INSERT INTO payment_submissions
            (order_id, channel, amount, reference_number, proof_path, status)
         VALUES (?, 'gcash', ?, ?, ?, 'pending_verification')"
    );
    $ps->bind_param('idss', $orderId, $total, $reference, $relPath);
    $ps->execute();
    $ps->close();

    // Keep order.payment_proof consistent with the latest submission.
    $up = $conn->prepare("UPDATE orders SET payment_proof = ? WHERE id = ?");
    $up->bind_param('si', $relPath, $orderId);
    $up->execute();
    $up->close();

    $conn->commit();

    echo "Seeded demo order #{$orderId} (user #{$userId}), reference {$reference}, total PHP " . number_format($total, 2) . ".\n";
    echo "Customer view: payment-qr.php?order_id={$orderId}\n";
    echo "Admin review : admin/payment-verification.php\n";
} catch (\Throwable $e) {
    $conn->rollback();
    echo "Seed failed: " . $e->getMessage() . "\n";
    exit(1);
}

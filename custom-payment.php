<?php
require 'includes/config.php';
require_once __DIR__ . '/includes/payment-config.php'; // admin-configured QR channels (same source as the regular order payment)
require_once __DIR__ . '/includes/payment-success.php';
redirectToLogin();

$pageTitle = 'Custom Order Payment';

// Run migration if tables don't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'custom_orders'");
if ($tableCheck->num_rows === 0) {
    $migrationSQL = file_get_contents(__DIR__ . '/migrate_custom_orders.sql');
    if ($migrationSQL) {
        $conn->multi_query($migrationSQL);
        while ($conn->next_result()) {;}
    }
}

$orderId = intval($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    header("Location: custom-design.php");
    exit();
}

// Get order details
$stmt = $conn->prepare("SELECT co.*, cd.design_image as orig_design_image FROM custom_orders co LEFT JOIN custom_designs cd ON co.design_id = cd.id WHERE co.id = ? AND co.user_id = ?");
$stmt->bind_param("ii", $orderId, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: custom-design.php");
    exit();
}

// Check if already paid
$existingPayment = $conn->prepare("SELECT id FROM custom_order_payments WHERE custom_order_id = ?");
$existingPayment->bind_param("i", $orderId);
$existingPayment->execute();
$hasPaid = $existingPayment->get_result()->num_rows > 0;
$existingPayment->close();

$error = '';
$success = '';

// Same source of truth as the regular order payment page: only the channels an
// admin enabled in Payment Settings appear here (with their QR + account info).
$enabledChannels = paymentEnabledChannels();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasPaid) {
    $channel         = (string)($_POST['payment_method'] ?? '');
    $referenceNumber = trim($_POST['reference_number'] ?? '');
    $consent         = isset($_POST['consent']);

    if (function_exists('verifyCsrfToken') && !verifyCsrfToken()) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif ($channel === '') {
        $error = 'Please select a payment method.';
    } elseif ($channel === 'cod') {
        // Cash on Delivery — no proof needed.
        $stmt = $conn->prepare("INSERT INTO custom_order_payments (custom_order_id, payment_method, amount, payment_status) VALUES (?, 'cod', ?, 'pending')");
        $stmt->bind_param("id", $orderId, $order['total_price']);
        if ($stmt->execute()) {
            $conn->query("UPDATE custom_orders SET status = 'payment_uploaded' WHERE id = " . intval($orderId));
            try {
                require_once __DIR__ . '/includes/email-helper.php';
                sendCustomOrderConfirmationEmail($conn, $orderId);
            } catch (Throwable $mailEx) {
                error_log('[custom-payment] email failed: ' . $mailEx->getMessage());
            }
            setPaymentSuccessFlash([
                'order_id' => $orderId,
                'kind'     => 'custom_cod',
                'total'    => $order['total_price'],
            ]);
            header("Location: custom-order-tracking.php?order_id=" . $orderId);
            exit();
        } else {
            $error = 'Failed to process payment. Please try again.';
        }
        $stmt->close();
    } elseif (!array_key_exists($channel, $enabledChannels)) {
        $error = 'Please select a valid payment channel.';
    } elseif (!$consent) {
        $error = 'Please tick the consent box so we may use your screenshot to verify this payment.';
    } elseif ($referenceNumber === '') {
        $error = 'Please enter the reference number from your payment receipt.';
    } elseif (mb_strlen($referenceNumber) > 100) {
        $error = 'That reference number looks too long. Please check and re-enter it.';
    } else {
        // Online channel — needs a payment-proof screenshot. Stored under
        // uploads/payments/ (same as before) so the existing admin viewer works.
        $paymentProof = '';
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['payment_proof'];
            $finfo   = new finfo(FILEINFO_MIME_TYPE);
            $mime    = (string)$finfo->file($file['tmp_name']);
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

            if (!isset($allowed[$mime])) {
                $error = 'Only PNG, JPG, and WEBP images are allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'File size must be less than 5MB.';
            } else {
                $uploadDir = __DIR__ . '/uploads/payments/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext      = $allowed[$mime];
                $filename = 'payment_' . $orderId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $paymentProof = 'uploads/payments/' . $filename;
                } else {
                    $error = 'Failed to upload payment proof.';
                }
            }
        }

        if (empty($error) && empty($paymentProof)) {
            $error = 'Please upload your payment screenshot.';
        }

        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO custom_order_payments (custom_order_id, payment_method, payment_proof, reference_number, amount, payment_status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("isssd", $orderId, $channel, $paymentProof, $referenceNumber, $order['total_price']);
            if ($stmt->execute()) {
                $conn->query("UPDATE custom_orders SET status = 'payment_uploaded' WHERE id = " . intval($orderId));
                try {
                    require_once __DIR__ . '/includes/email-helper.php';
                    sendCustomOrderConfirmationEmail($conn, $orderId);
                } catch (Throwable $mailEx) {
                    error_log('[custom-payment] email failed: ' . $mailEx->getMessage());
                }
                setPaymentSuccessFlash([
                    'order_id' => $orderId,
                    'kind'     => 'custom_online',
                    'total'    => $order['total_price'],
                    'channel'  => $channel,
                ]);
                header("Location: custom-order-tracking.php?order_id=" . $orderId);
                exit();
            } else {
                $error = 'Failed to process payment. Please try again.';
            }
            $stmt->close();
        }
    }
}

$typeNames = ['tshirt' => 'T-Shirt', 'hoodie' => 'Hoodie', 'polo' => 'Polo'];
$typeName = $typeNames[$order['product_type']] ?? 'T-Shirt';
?>

<?php include 'includes/header/header.php'; ?>

<style>
.payment-container {
    max-width: 700px;
    margin: 0 auto;
    padding: 2rem 1rem;
}
.payment-header {
    text-align: center;
    margin-bottom: 2rem;
}
.payment-header h1 {
    font-size: 2rem;
    font-weight: 800;
}
.payment-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid var(--border-light, #e5e5e5);
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.payment-card-header {
    padding: 1rem 1.5rem;
    background: #fafafa;
    border-bottom: 1px solid #eee;
    font-weight: 700;
}
.payment-card-body {
    padding: 1.5rem;
}
.order-mini-summary {
    display: flex;
    gap: 1rem;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}
.order-mini-img {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid #eee;
}
.order-mini-info {
    flex: 1;
}
.order-mini-info h6 {
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.order-mini-info p {
    color: #888;
    font-size: 0.82rem;
    margin: 0;
}
.order-mini-price {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--accent-green, #2d6a4f);
}
.payment-method-options {
    display: grid;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.payment-method-option {
    border: 2px solid #e5e5e5;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 1rem;
}
.payment-method-option:hover {
    border-color: #aaa;
}
.payment-method-option.selected {
    border-color: var(--accent-green, #2d6a4f);
    background: rgba(45,106,79,0.04);
}
.payment-method-option input[type="radio"] {
    display: none;
}
.payment-method-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
}
.payment-method-icon.gcash { background: #007bff; }
.payment-method-icon.maya { background: #2ecc71; }
.payment-method-icon.cod { background: #f39c12; }
.payment-method-text h6 {
    font-weight: 700;
    margin: 0;
    font-size: 0.95rem;
}
.payment-method-text p {
    margin: 0;
    color: #888;
    font-size: 0.78rem;
}
.payment-proof-section {
    display: none;
    margin-top: 1rem;
    padding: 1.25rem;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #eee;
}
.payment-proof-section.show {
    display: block;
}
.upload-proof-area {
    border: 2px dashed #ddd;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #fff;
    margin-bottom: 1rem;
}
.upload-proof-area:hover {
    border-color: var(--accent-green, #2d6a4f);
}
.upload-proof-area i {
    font-size: 2rem;
    color: #bbb;
    margin-bottom: 0.5rem;
}
.upload-proof-area p {
    color: #888;
    font-size: 0.82rem;
    margin: 0;
}
.upload-proof-area img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    margin-top: 0.5rem;
}
.payment-instructions {
    background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    font-size: 0.85rem;
}
.payment-instructions h6 {
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--accent-green, #2d6a4f);
}
.payment-instructions ol {
    margin: 0;
    padding-left: 1.2rem;
}
.payment-instructions ol li {
    margin-bottom: 0.3rem;
}
.btn-pay {
    display: block;
    width: 100%;
    padding: 1rem;
    background: var(--accent-green, #2d6a4f);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 1.05rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-pay:hover {
    background: #245a42;
    transform: translateY(-1px);
}
.btn-pay:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.already-paid-msg {
    text-align: center;
    padding: 2rem;
}
.already-paid-msg i {
    font-size: 3rem;
    color: #27ae60;
    margin-bottom: 1rem;
}
/* Steps reuse */
.order-steps { display:flex; justify-content:center; gap:0; margin-bottom:2rem; }
.order-step { display:flex; align-items:center; gap:0.5rem; font-size:0.82rem; color:#bbb; font-weight:600; }
.order-step.active { color:var(--accent-green,#2d6a4f); }
.order-step.completed { color:#27ae60; }
.order-step .step-num { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700; background:#eee; color:#999; }
.order-step.active .step-num { background:var(--accent-green,#2d6a4f); color:#fff; }
.order-step.completed .step-num { background:#27ae60; color:#fff; }
.step-connector { width:40px; height:2px; background:#eee; margin:0 0.25rem; }
.step-connector.completed { background:#27ae60; }
@media (max-width:768px) { .order-steps { flex-wrap:wrap; gap:0.5rem; } .step-connector { display:none; } }

/* Channel pills + QR (mirrors the regular order payment page) */
.channel-pills { display:flex; flex-wrap:wrap; gap:0.5rem; }
.channel-option {
    display:inline-flex; align-items:center; gap:0.4rem;
    border:2px solid #e5e5e5; border-radius:999px;
    padding:0.5rem 1rem; cursor:pointer; user-select:none;
    font-size:0.9rem; font-weight:600; color:#333; transition:all 0.2s;
}
.channel-option:hover { border-color:#aaa; }
.channel-option.selected { border-color:var(--accent-green,#2d6a4f); background:rgba(45,106,79,0.06); color:var(--accent-green,#2d6a4f); }
.channel-option input { display:none; }
.qr-card { background:#fff; border:1px solid #eee; border-radius:12px; padding:1.25rem; text-align:center; }
.qr-img { max-width:240px; width:100%; height:auto; border:1px solid #eee; border-radius:8px; }
.qr-steps { margin:1rem 0 0; padding-left:1.2rem; font-size:0.9rem; }
.qr-steps li { margin-bottom:0.3rem; }
</style>

<div class="payment-container">
    <!-- Progress Steps -->
    <div class="order-steps">
        <div class="order-step completed"><span class="step-num"><i class="fas fa-check"></i></span> Design</div>
        <div class="step-connector completed"></div>
        <div class="order-step completed"><span class="step-num"><i class="fas fa-check"></i></span> Summary</div>
        <div class="step-connector completed"></div>
        <div class="order-step active"><span class="step-num">3</span> Payment</div>
        <div class="step-connector"></div>
        <div class="order-step"><span class="step-num">4</span> Tracking</div>
    </div>

    <div class="payment-header">
        <h1><i class="fas fa-credit-card me-2"></i>Payment</h1>
        <p class="text-muted">Choose your payment method and complete your order</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($hasPaid): ?>
        <div class="payment-card">
            <div class="payment-card-body already-paid-msg">
                <i class="fas fa-check-circle d-block"></i>
                <h4>Payment Already Submitted</h4>
                <p class="text-muted">Your payment for Order #<?php echo $orderId; ?> has been submitted and is being verified.</p>
                <a href="custom-order-tracking.php?order_id=<?php echo $orderId; ?>" class="btn btn-dark mt-3" style="border-radius:12px; padding:0.75rem 2rem;">
                    <i class="fas fa-truck me-2"></i>Track Your Order
                </a>
            </div>
        </div>
    <?php else: ?>

    <!-- Order Mini Summary -->
    <div class="order-mini-summary">
        <img src="<?php echo htmlspecialchars($order['design_image']); ?>" class="order-mini-img" alt="Design" onerror="this.src='https://placehold.co/80x80/f0f0f0/999?text=Design'">
        <div class="order-mini-info">
            <h6>Custom <?php echo htmlspecialchars($typeName); ?> — Order #<?php echo $orderId; ?></h6>
            <p>Size: <?php echo htmlspecialchars($order['size']); ?> · Qty: <?php echo (int)$order['quantity']; ?></p>
        </div>
        <div class="order-mini-price">₱<?php echo number_format($order['total_price'], 2); ?></div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="paymentForm">
        <?php echo function_exists('csrfTokenField') ? csrfTokenField() : ''; ?>

        <!-- Amount due -->
        <div class="payment-card">
            <div class="payment-card-body text-center">
                <div class="text-muted text-uppercase" style="letter-spacing:.05em; font-size:.78rem;">Amount due</div>
                <div style="font-size:2rem; font-weight:800; line-height:1.1;">Pay exactly ₱<?php echo number_format($order['total_price'], 2); ?></div>
                <div class="text-muted mt-1" style="font-size:.85rem;">Enter this exact amount when you scan — it speeds up verification.</div>
            </div>
        </div>

        <div class="payment-card">
            <div class="payment-card-header"><i class="fas fa-wallet me-2"></i>Choose how you want to pay</div>
            <div class="payment-card-body">
                <!-- Channel picker (online channels from admin Payment Settings + COD) -->
                <div class="channel-pills" role="radiogroup">
                    <?php foreach ($enabledChannels as $key => $ch): ?>
                    <label class="channel-option" onclick="selectChannel('<?php echo htmlspecialchars($key, ENT_QUOTES); ?>', true)">
                        <input type="radio" name="payment_method" value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>">
                        <span><?php echo htmlspecialchars((string)$ch['display_name'], ENT_QUOTES); ?></span>
                    </label>
                    <?php endforeach; ?>
                    <label class="channel-option" onclick="selectChannel('cod', false)">
                        <input type="radio" name="payment_method" value="cod">
                        <span><i class="fas fa-money-bill-wave me-1"></i> Cash on Delivery</span>
                    </label>
                </div>
                <?php if (empty($enabledChannels)): ?>
                    <p class="text-muted mt-2" style="font-size:.82rem;">Online payment is temporarily unavailable — you can still choose Cash on Delivery.</p>
                <?php endif; ?>

                <!-- Per-channel QR + account details + how-to-pay steps -->
                <div id="qrArea" style="display:none; margin-top:1.25rem;">
                    <?php foreach ($enabledChannels as $key => $ch):
                        $img     = trim((string)($ch['qr_image'] ?? ''));
                        $imgUrl  = $img !== '' ? PAYMENT_QR_IMAGE_URLBASE . rawurlencode($img) : '';
                        $acctNo  = trim((string)($ch['account_no'] ?? ''));
                        $hasAcct = $acctNo !== '' && $acctNo !== '—';
                    ?>
                    <div class="channel-panel" data-channel="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>" hidden>
                        <div class="qr-card">
                            <div class="fw-bold mb-2"><?php echo htmlspecialchars((string)$ch['display_name'], ENT_QUOTES); ?></div>
                            <?php if ($imgUrl !== ''): ?>
                                <img src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars((string)$ch['display_name'] . ' payment QR code', ENT_QUOTES); ?>" class="qr-img">
                            <?php else: ?>
                                <div class="alert alert-secondary mb-2">QR image not set for this channel — use the account details below.</div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <?php if ((string)$ch['account_name'] !== ''): ?>
                                    <div><strong>Account name:</strong> <?php echo htmlspecialchars((string)$ch['account_name'], ENT_QUOTES); ?></div>
                                <?php endif; ?>
                                <?php if ($hasAcct): ?>
                                    <div class="text-muted">…or send to <strong><?php echo htmlspecialchars($acctNo, ENT_QUOTES); ?></strong></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <ol class="qr-steps">
                            <li>Open your e-wallet or banking app.</li>
                            <li>Scan the QR code above.</li>
                            <li>Enter the <strong>exact</strong> amount: ₱<?php echo number_format($order['total_price'], 2); ?> <small class="text-muted">(this static QR doesn't pre-fill the amount).</small></li>
                            <li>Complete the payment and take a screenshot of the receipt.</li>
                            <li>Upload the screenshot and enter the reference number below.</li>
                        </ol>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Upload proof + reference + consent (online channels only) -->
                <div class="payment-proof-section" id="onlineProof">
                    <div class="mb-2">
                        <label class="form-label" style="font-size:0.85rem; font-weight:600;">Payment screenshot <span class="text-danger">*</span></label>
                        <div class="upload-proof-area" onclick="document.getElementById('proofUpload').click()" id="proofArea">
                            <i class="fas fa-cloud-upload-alt d-block"></i>
                            <p><strong>Click to upload payment screenshot</strong></p>
                            <p>PNG, JPG, WEBP (max 5MB)</p>
                        </div>
                        <input type="file" id="proofUpload" name="payment_proof" accept="image/png,image/jpeg,image/webp" style="display:none" onchange="previewProof(this)">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:0.85rem; font-weight:600;">Reference Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="reference_number" maxlength="100" placeholder="e.g., 1234 567 890123" value="<?php echo htmlspecialchars($_POST['reference_number'] ?? '', ENT_QUOTES); ?>">
                        <small class="text-muted">Find this on your payment receipt (often labelled "Ref No." or "Reference ID").</small>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="consent" name="consent">
                        <label class="form-check-label" for="consent" style="font-size:0.85rem;">
                            I consent to my screenshot being used <strong>only to verify this payment</strong>.
                        </label>
                    </div>
                </div>

                <!-- COD Notice -->
                <div class="payment-proof-section" id="codSection">
                    <div class="payment-instructions" style="background:linear-gradient(135deg, #fff8e1, #fff3cd);">
                        <h6 style="color:#f39c12;"><i class="fas fa-truck me-1"></i>Cash on Delivery</h6>
                        <p style="margin:0;">You will pay <strong>₱<?php echo number_format($order['total_price'], 2); ?></strong> when you receive your order. Please prepare the exact amount.</p>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-pay" id="payBtn" disabled>
            <i class="fas fa-lock me-2"></i>Complete Payment — ₱<?php echo number_format($order['total_price'], 2); ?>
        </button>
    </form>

    <a href="custom-order-summary.php?design_id=<?php echo (int)$order['design_id']; ?>&type=<?php echo htmlspecialchars($order['product_type']); ?>&color=<?php echo urlencode($order['apparel_color']); ?>&size=<?php echo htmlspecialchars($order['size']); ?>&qty=<?php echo (int)$order['quantity']; ?>&print_size=medium&discount=<?php echo htmlspecialchars($order['discount_type']); ?>" class="btn-back" style="display:block;width:100%;padding:0.75rem;background:#fff;color:#666;border:1px solid #ddd;border-radius:12px;font-size:0.95rem;font-weight:600;cursor:pointer;text-align:center;text-decoration:none;margin-top:0.75rem;">
        <i class="fas fa-arrow-left me-2"></i>Back to Order Summary
    </a>

    <?php endif; ?>
</div>

<script>
function selectChannel(key, isOnline) {
    // Highlight the selected pill + check its radio.
    document.querySelectorAll('.channel-option').forEach(o => o.classList.remove('selected'));
    const radio = document.querySelector('.channel-option input[value="' + key + '"]');
    if (radio) {
        radio.checked = true;
        radio.closest('.channel-option').classList.add('selected');
    }

    // Show the matching QR panel (online only).
    const qrArea = document.getElementById('qrArea');
    if (qrArea) qrArea.style.display = isOnline ? 'block' : 'none';
    document.querySelectorAll('.channel-panel').forEach(p => {
        p.hidden = (p.getAttribute('data-channel') !== key);
    });

    // Toggle proof+consent vs COD notice.
    document.getElementById('onlineProof').classList.toggle('show', isOnline);
    document.getElementById('codSection').classList.toggle('show', !isOnline);

    // Required fields only apply to online payments.
    const proof = document.getElementById('proofUpload');
    const consent = document.getElementById('consent');
    const ref = document.querySelector('input[name="reference_number"]');
    [proof, consent, ref].forEach(el => {
        if (!el) return;
        if (isOnline) el.setAttribute('required', 'required');
        else el.removeAttribute('required');
    });

    document.getElementById('payBtn').disabled = false;
}

function previewProof(input) {
    const file = input.files[0];
    if (!file) return;
    const area = document.getElementById('proofArea');
    const reader = new FileReader();
    reader.onload = function(e) {
        area.innerHTML = `<img src="${e.target.result}" alt="Payment Proof"><p style="margin-top:0.5rem; color:#27ae60; font-weight:600;"><i class="fas fa-check-circle me-1"></i>Screenshot uploaded</p>`;
    };
    reader.readAsDataURL(file);
}
</script>

<?php include 'includes/footer/footer.php'; ?>

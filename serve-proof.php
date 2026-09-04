<?php
declare(strict_types=1);

/**
 * Authenticated payment-proof image server.
 *
 * The only sanctioned way to read a payment screenshot. Files live in a
 * non-browsable storage folder; this handler enforces that the requester is
 * either an admin or the owner of the order the proof belongs to, then
 * streams the bytes with caching disabled.
 *
 * Usage:  serve-proof.php?id={payment_submissions.id}
 */

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/payment-proof-handler.php';

/** Emit a status code with a tiny text body and stop. */
function proofDeny(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

// --- Must be logged in -------------------------------------------------
if (!isLoggedIn()) {
    proofDeny(401, 'Authentication required.');
}

$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($submissionId <= 0) {
    proofDeny(400, 'Missing proof reference.');
}

// --- Look up the submission and its owning order -----------------------
$stmt = $conn->prepare(
    "SELECT ps.proof_path, o.user_id
       FROM payment_submissions ps
       JOIN orders o ON o.id = ps.order_id
      WHERE ps.id = ? LIMIT 1"
);
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['proof_path'])) {
    proofDeny(404, 'Proof not found.');
}

// --- Authorisation: admin OR the order's owner -------------------------
$isAdmin = (($_SESSION['user_type'] ?? '') === 'admin');
$isOwner = ((int)$row['user_id'] === (int)($_SESSION['user_id'] ?? 0));
if (!$isAdmin && !$isOwner) {
    proofDeny(403, 'You do not have permission to view this file.');
}

// --- Resolve & stream (traversal-safe) ---------------------------------
$absPath = paymentResolveProofPath($row['proof_path']);
if ($absPath === null || !is_file($absPath)) {
    proofDeny(404, 'Proof file is no longer available.');
}

$mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($absPath);
if (!isset(PAYMENT_PROOF_ALLOWED[$mime])) {
    proofDeny(415, 'Unsupported file.');
}

// Private, sensitive content: never cache in shared proxies/browsers.
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($absPath));
header('Content-Disposition: inline; filename="payment-proof.' . PAYMENT_PROOF_ALLOWED[$mime] . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

readfile($absPath);
exit;

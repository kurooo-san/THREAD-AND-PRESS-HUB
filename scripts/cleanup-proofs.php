<?php
declare(strict_types=1);

/**
 * Retention cleanup for payment proofs (Part 3).
 *
 * Deletes proof image FILES for orders that were completed or cancelled more
 * than PAYMENT_PROOF_RETENTION_DAYS ago, and nulls the stored path. The
 * submission RECORD (reference number, status, timestamps) is preserved for
 * the audit trail — only the personal-data image is removed.
 *
 * CLI only. Schedule via Windows Task Scheduler or cron, e.g. daily:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\thread-and-presshub\scripts\cleanup-proofs.php
 *
 * Add --dry-run to preview without deleting.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/payment-proof-handler.php';

$dryRun = in_array('--dry-run', $argv, true);
$days   = PAYMENT_PROOF_RETENTION_DAYS;

echo "Payment proof cleanup — retention: {$days} day(s)" . ($dryRun ? " [DRY RUN]" : "") . "\n";

// Find submissions whose order finished (completed/cancelled) before the
// cutoff and that still hold a proof file.
$stmt = $conn->prepare(
    "SELECT ps.id, ps.order_id, ps.proof_path
       FROM payment_submissions ps
       JOIN orders o ON o.id = ps.order_id
      WHERE ps.proof_path IS NOT NULL
        AND o.status IN ('completed','cancelled')
        AND o.updated_at < (NOW() - INTERVAL ? DAY)"
);
$stmt->bind_param('i', $days);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();

$deleted = 0;
$missing = 0;

while ($row = $rows->fetch_assoc()) {
    $sid  = (int)$row['id'];
    $oid  = (int)$row['order_id'];
    $abs  = paymentResolveProofPath($row['proof_path']);

    echo " - submission #{$sid} (order #{$oid}): ";

    if ($abs === null || !is_file($abs)) {
        echo "file already gone\n";
        $missing++;
    } elseif ($dryRun) {
        echo "would delete {$row['proof_path']}\n";
    } elseif (@unlink($abs)) {
        echo "deleted {$row['proof_path']}\n";
        $deleted++;
    } else {
        echo "FAILED to delete (check permissions)\n";
        continue;
    }

    if (!$dryRun) {
        // Null the path on the submission and matching order.
        $u = $conn->prepare("UPDATE payment_submissions SET proof_path = NULL WHERE id = ?");
        $u->bind_param('i', $sid);
        $u->execute();
        $u->close();

        $uo = $conn->prepare("UPDATE orders SET payment_proof = NULL WHERE id = ? AND payment_proof = ?");
        $uo->bind_param('is', $oid, $row['proof_path']);
        $uo->execute();
        $uo->close();
    }
}

echo "Done. Files removed: {$deleted}, already missing: {$missing}.\n";

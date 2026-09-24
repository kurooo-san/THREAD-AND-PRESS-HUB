<?php

/**
 * PayMongo — webhook receiver.
 *
 * This is the reliable half of the integration. The customer's browser may
 * never come back from PayMongo (closed tab, dead battery, flaky signal), but
 * PayMongo still posts the result here, so the order still gets marked paid.
 *
 * Register the endpoint once per environment:
 *
 *   curl https://api.paymongo.com/v1/webhooks \
 *     -u sk_test_XXXX: \
 *     -H 'Content-Type: application/json' \
 *     -d '{"data":{"attributes":{
 *           "url":"https://YOUR-DOMAIN/paymongo-webhook.php",
 *           "events":["checkout_session.payment.paid"]}}}'
 *
 * The response contains a secret_key — put it in .env as
 * PAYMONGO_WEBHOOK_SECRET. Without it every webhook is rejected, on purpose:
 * an unsigned request must never be able to mark an order paid.
 *
 * NOTE: PayMongo cannot reach http://localhost. On XAMPP the customer-facing
 * return page is what confirms payment; the webhook matters once deployed.
 */

require __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/paymongo.php';
require_once __DIR__ . '/includes/paymongo-fulfil.php';

// Webhooks are machine-to-machine: never emit HTML, never touch the session.
header('Content-Type: application/json');

/** Reply and stop. 2xx tells PayMongo to stop retrying. */
function paymongoWebhookRespond(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paymongoWebhookRespond(405, 'Method not allowed.');
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    paymongoWebhookRespond(400, 'Empty body.');
}

// Signature check first — before the payload is parsed or trusted at all.
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
if (!paymongoVerifyWebhookSignature($rawBody, $signature)) {
    error_log('[paymongo] webhook rejected: bad or missing signature');
    paymongoWebhookRespond(401, 'Invalid signature.');
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    paymongoWebhookRespond(400, 'Unreadable payload.');
}

$event     = $payload['data']['attributes'] ?? [];
$eventType = (string) ($event['type'] ?? '');
$resource  = $event['data'] ?? [];
$sessionId = (string) ($resource['id'] ?? '');

// Only the paid event does anything. Everything else is acknowledged so
// PayMongo does not keep retrying an event we simply do not act on.
if (strpos($eventType, 'payment.paid') === false) {
    paymongoWebhookRespond(200, 'Ignored event: ' . $eventType);
}

if ($sessionId === '') {
    paymongoWebhookRespond(200, 'No session id in payload.');
}

// paymongoFulfilSession re-reads the session from the API rather than
// believing this payload, and is safe to run twice for the same session.
$outcome = paymongoFulfilSession($sessionId, $resource['attributes'] ?? null);

if (!$outcome['ok']) {
    // 200 on "not one of ours": retrying will never change that answer.
    // 500 on a real failure so PayMongo retries and we do not lose a payment.
    $unknown = stripos($outcome['error'], 'not one of ours') !== false;
    error_log('[paymongo] webhook ' . $sessionId . ': ' . $outcome['error']);
    paymongoWebhookRespond($unknown ? 200 : 500, $outcome['error']);
}

paymongoWebhookRespond(200, $outcome['paid'] ? 'Order updated.' : 'Not paid yet.');

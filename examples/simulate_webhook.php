<?php
/**
 * HiPayFin Webhook Simulator
 * 
 * Simulates HiPayFin server sending a signed webhook event
 * to a target URL (e.g. webhook.site or your local handler).
 */
require_once __DIR__ . '/../autoload.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Support\Uuid;

// Target Webhook URL
$targetWebhookUrl = isset($argv[1]) 
    ? $argv[1] 
    : 'https://webhook.site/f34fdcdb-bce4-428b-919e-676134dcbad0';

// Secret used to sign the webhook
$webhookSecret = '35b0091558ae05393e31643b7da158fefbc5397a6eaa037054bcd0b5aff045de';

// 1. Prepare Webhook Event Payload (payment.confirmed)
$timestamp = (string)time();
$eventId = 'evt_' . Uuid::v4();
$orderRef = 'PI-' . date('Ymd') . sprintf('%05d', mt_rand(1, 99999));
$merchantOrderId = 'ORD-VN-' . time();
$amount = Currency::toMinorUnit(250000, 'VND'); // "250000"

$payload = array(
    'event'                => 'payment.confirmed',
    'event_id'             => $eventId,
    'created_at'           => gmdate('Y-m-d\TH:i:s.000\Z'),
    'order_ref'            => $orderRef,
    'merchant_order_id'    => $merchantOrderId,
    'merchant_customer_id' => 'CUST-DEMO-01',
    'amount'               => $amount,
    'wallet_currency'      => 'VND',
    'rail_currency'        => 'VND',
    'rate'                 => null,
    'confirmed_at'         => gmdate('Y-m-d\TH:i:s.000\Z'),
);

$rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES);

// 2. Compute HMAC-SHA256 Signature: sha256=lowercase(hash_hmac(secret, timestamp . "." . rawBody))
$payloadToSign = $timestamp . '.' . $rawBody;
$sigHex = hash_hmac('sha256', $payloadToSign, $webhookSecret);
$signature = 'sha256=' . strtolower($sigHex);

// 3. Prepare Webhook Headers (4 standard headers defined in section 9.1)
$headers = array(
    'Content-Type: application/json',
    'X-Webhook-Id: ' . $eventId,
    'X-Webhook-Timestamp: ' . $timestamp,
    'X-Webhook-Event: payment.confirmed',
    'X-Webhook-Signature: ' . $signature,
);

echo "Sending Webhook to: {$targetWebhookUrl}\n";
echo "Headers:\n  " . implode("\n  ", $headers) . "\n\n";
echo "Body:\n" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// 4. Send HTTP POST to target URL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetWebhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlErrno !== 0) {
    echo "FAILED: cURL Error ({$curlErrno}): {$curlError}\n";
    exit(1);
}

echo "RESULT: HTTP {$httpCode}\n";
echo "Response: " . substr($response, 0, 200) . "\n";
echo "==> Webhook simulation sent successfully!\n";
echo "You can check your received request at: https://webhook.site/#!/view/f34fdcdb-bce4-428b-919e-676134dcbad0\n";

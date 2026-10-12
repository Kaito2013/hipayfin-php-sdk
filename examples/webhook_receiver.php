<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Exceptions\HiPayFinSignatureException;

// Read raw request headers and body
$rawBody = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) ? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] : '';
$timestamp = isset($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP']) ? $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] : '';
$eventId = isset($_SERVER['HTTP_X_WEBHOOK_ID']) ? $_SERVER['HTTP_X_WEBHOOK_ID'] : '';

try {
    // 1. Verify and parse incoming webhook
    $event = $client->webhook->verifyAndParse($rawBody, $signature, $timestamp);

    // 2. Anti-duplicate processing check (idempotent consumer)
    // Example:
    // if (isEventProcessed($event['event_id'])) {
    //     http_response_code(200);
    //     echo json_encode(['status' => 'ignored_duplicate']);
    //     exit;
    // }

    // 3. Handle event types
    switch ($event['event']) {
        case 'payment.confirmed':
            // CRITICAL: Always use actual received amount from webhook
            $actualAmount = $event['amount'];
            $orderRef = $event['order_ref'];
            $merchantOrderId = $event['merchant_order_id'];
            // creditUserBalance($merchantOrderId, $actualAmount);
            break;

        case 'payment.rejected':
            // markOrderFailed($event['merchant_order_id'], $event['reason']);
            break;

        case 'payout.processed':
            // markPayoutCompleted($event['order_ref']);
            break;

        case 'payout.blocked':
            // markPayoutFailedAndRefundBalance($event['order_ref'], $event['reason']);
            break;
    }

    // 4. Return fast HTTP 200 OK (< 2 seconds)
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(array('status' => 'success'));
} catch (HiPayFinSignatureException $e) {
    http_response_code(401);
    echo json_encode(array('error' => 'Invalid signature or timestamp expired'));
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()));
}

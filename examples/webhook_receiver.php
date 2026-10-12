<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\OrderStatus;
use Lucas\HiPayFin\Support\WebhookEvent;
use Lucas\HiPayFin\Exceptions\HiPayFinSignatureException;

// Read raw request headers and body
$rawBody = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) ? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] : '';
$timestamp = isset($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP']) ? $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] : '';
$eventId = isset($_SERVER['HTTP_X_WEBHOOK_ID']) ? $_SERVER['HTTP_X_WEBHOOK_ID'] : '';

try {
    // 1. Verify signature & check timestamp drift (anti-replay <= 300s)
    $event = $client->webhook->verifyAndParse($rawBody, $signature, $timestamp);

    // 2. Anti-duplicate processing check (Idempotent Consumer based on event_id)
    // if (isEventProcessedInDatabase($event['event_id'])) {
    //     http_response_code(200);
    //     echo json_encode(['status' => 'ignored_duplicate']);
    //     exit;
    // }

    // 3. Tra cứu trạng thái hiện tại của đơn hàng trong Database
    // $currentLocalOrderStatus = getOrderStatusFromDatabase($event['merchant_order_id']);

    // 4. Handle event types with OrderStatus Lifecycle Rules
    switch ($event['event']) {
        case WebhookEvent::PAYMENT_CONFIRMED:
            // QUY TẮC SỐNG CÒN 1: Nếu đơn đang là EXPIRED nhưng tiền vào muộn, HiPayFin vẫn bắn
            // payment.confirmed. OrderStatus::canTransition('EXPIRED', 'CONFIRMED') === true!
            // QUY TẮC SỐNG CÒN 2: Luôn dùng số tiền THỰC TẾ nhận được ($event['amount'])
            $actualAmount = $event['amount'];
            $orderRef = $event['order_ref'];
            $merchantOrderId = $event['merchant_order_id'];

            // if (OrderStatus::canTransition($currentLocalOrderStatus, OrderStatus::CONFIRMED)) {
            //     updateOrderStatusInDb($merchantOrderId, OrderStatus::CONFIRMED, $orderRef);
            //     creditUserBalance($merchantOrderId, $actualAmount);
            // }
            break;

        case WebhookEvent::PAYMENT_REJECTED:
            $reason = isset($event['reason']) ? $event['reason'] : OrderStatus::FAILED;
            $newStatus = ($reason === 'EXPIRED') ? OrderStatus::EXPIRED : OrderStatus::FAILED;

            // QUY TẮC SỐNG CÒN 3: Không bao giờ hạ cấp đơn đã CONFIRMED về EXPIRED/FAILED
            // if (OrderStatus::canTransition($currentLocalOrderStatus, $newStatus)) {
            //     updateOrderStatusInDb($event['merchant_order_id'], $newStatus, null, $reason);
            // }
            break;

        case WebhookEvent::PAYOUT_PROCESSED:
            // markPayoutCompleted($event['order_ref']);
            break;

        case WebhookEvent::PAYOUT_BLOCKED:
            // markPayoutFailedAndRefundBalance($event['order_ref'], $event['reason']);
            break;
    }

    // 5. Luôn phản hồi HTTP 200 OK nhanh chóng (< 2 giây) để tránh HiPayFin retry
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

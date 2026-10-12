<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

try {
    $orderId = 'ORD-VN-' . time();
    $amountMinor = Currency::toMinorUnit(150000, 'VND'); // "150000"

    echo "Creating VND Payin Order: {$orderId}, Amount: {$amountMinor} VND...\n";

    $response = $client->payin->createVnd(array(
        'merchant_order_id'    => $orderId,
        'merchant_customer_id' => 'CUST-DEMO-01',
        'amount'               => $amountMinor,
        'customer_name'        => 'Nguyen Van A',
    ));

    echo "SUCCESS! Created Order:\n";
    echo "  Order Ref: " . $response['order_ref'] . "\n";
    echo "  Status:    " . $response['status'] . "\n";
    if (isset($response['instruction'])) {
        echo "  Payment Instruction: " . json_encode($response['instruction']) . "\n";
    }
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

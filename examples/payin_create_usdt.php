<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

try {
    $orderId = 'ORD-USDT-' . time();
    $amountMinor = Currency::toMinorUnit(10.5, 'USDT'); // "10500000"

    echo "Creating USDT Payin Order: {$orderId}, Amount: {$amountMinor} minor units (10.5 USDT)...\n";

    $response = $client->payin->createUsdt(array(
        'merchant_order_id'    => $orderId,
        'merchant_customer_id' => 'CUST-DEMO-01',
        'amount'               => $amountMinor,
    ));

    echo "SUCCESS! Created USDT Order:\n";
    echo "  Order Ref: " . $response['order_ref'] . "\n";
    echo "  Status:    " . $response['status'] . "\n";
    if (isset($response['instruction'])) {
        echo "  Address:   " . $response['instruction']['address'] . "\n";
        echo "  Network:   " . $response['instruction']['network'] . "\n";
    }
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

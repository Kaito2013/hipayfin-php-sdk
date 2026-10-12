<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

try {
    $payoutId = 'PO-VN-' . time();
    $amountMinor = Currency::toMinorUnit(100000, 'VND');

    echo "Submitting VND Payout Request: {$payoutId}, Amount: {$amountMinor} VND...\n";

    $response = $client->payout->createVnd(array(
        'merchant_order_id'    => $payoutId,
        'merchant_customer_id' => 'CUST-DEMO-01',
        'amount'               => $amountMinor,
        'bank_code'            => '970436', // Vietcombank
        'account_number'       => '0011001234567',
        'account_holder'       => 'NGUYEN VAN A',
    ));

    echo "SUCCESS! Payout Order Created:\n";
    echo "  Order Ref: " . $response['order_ref'] . "\n";
    echo "  Status:    " . $response['status'] . "\n";
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

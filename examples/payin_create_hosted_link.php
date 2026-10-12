<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

try {
    $orderId = 'ORD-LINK-' . time();
    // Currency::toMinorUnit(150000, 'KRW') -> "150000"
    $amountMinor = Currency::toMinorUnit(150000, 'KRW');

    echo "Creating Hosted Payin Link: {$orderId}, Amount: {$amountMinor} KRW...\n";

    $response = $client->payin->createHostedLink(array(
        'merchant_order_id'    => $orderId,
        'merchant_customer_id' => 'CUST-DEMO-01',
        'amount'               => $amountMinor,
        'target_currency'      => 'KRW',
    ));

    echo "\nSUCCESS! Hosted Payin Link Created:\n";
    echo "  Session Token:   " . $response['session_token'] . "\n";
    echo "  Hosted Page URL: " . $response['hosted_page_url'] . "\n";
    echo "  Expires At:      " . $response['expires_at'] . "\n";
    echo "\n👉 Open URL above in your browser to complete simulated checkout!\n";
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

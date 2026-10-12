<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

try {
    // 1. Check merchant active wallets
    echo "Checking merchant active wallets...\n";
    $wallets = $client->wallets->all();
    echo "Active Wallets: " . json_encode($wallets) . "\n\n";

    // 2. Query order by merchant_order_id or order_ref if provided
    $testOrderId = isset($argv[1]) ? $argv[1] : '';
    if (!empty($testOrderId)) {
        if (strpos($testOrderId, 'PI-') === 0 || strpos($testOrderId, 'PO-') === 0) {
            echo "Querying order by order_ref: {$testOrderId}...\n";
            $order = $client->orders->get($testOrderId);
        } else {
            echo "Querying order by merchant_order_id: {$testOrderId}...\n";
            $order = $client->orders->getByMerchantOrderId($testOrderId);
        }
        echo "Order Info:\n" . json_encode($order, JSON_PRETTY_PRINT) . "\n";
    }
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

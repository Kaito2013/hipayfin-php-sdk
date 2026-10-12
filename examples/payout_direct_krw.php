<?php
/**
 * Direct KRW Payout Flow (Rút tiền trực tiếp bằng KRW)
 * 
 * Luồng:
 * 1. Gọi preview (tùy chọn) để kiểm tra số tiền nhận được
 * 2. Gọi POST /payout/krw/create để tạo lệnh chi tiền
 */
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

$payoutId = 'PO-KRW-' . time();
$customerId = 'CUST-KRW-001';
$amount = '100001'; // Tối thiểu 100,001 KRW trên Sandbox
$bankName = 'Kookmin Bank';
$accountNumber = '11012345678';
$accountHolder = 'Hong Gildong';
$customerNameKr = '홍길동';

echo "========================================================\n";
echo "           HIPAYFIN DIRECT KRW PAYOUT TEST              \n";
echo "========================================================\n\n";

try {
    // 1. Kiểm tra preview tỷ giá & phí (tuỳ chọn)
    echo "[BƯỚC 1] Xem trước tỷ giá / số tiền rút (Preview)...\n";
    $preview = $client->payout->preview('krw', $amount);
    echo "  ✓ Preview thành công: Nhận " . number_format($preview['receive_amount']) . " " . $preview['receive_currency'] . "\n\n";

    // 2. Tạo lệnh chi tiền trực tiếp
    echo "[BƯỚC 2] Tạo lệnh Payout trực tiếp: {$payoutId}...\n";
    $payload = array(
        'merchant_order_id'    => $payoutId,
        'merchant_customer_id' => $customerId,
        'amount'               => $amount,
        'bank_name'            => $bankName,
        'account_number'       => $accountNumber,
        'account_holder'       => $accountHolder,
        'customer_name_kr'     => $customerNameKr,
    );

    $response = $client->payout->createKrw($payload);

    echo "🎉 THÀNH CÔNG! Đơn Payout KRW đã được tạo:\n";
    echo "  - Order Ref: " . $response['order_ref'] . "\n";
    echo "  - Status:    " . $response['status'] . "\n";
    echo "\n👉 Đang chờ nhà mạng/ngân hàng xử lý (PROVIDER_PENDING). Webhook sẽ bắn về khi tiền chuyển đi thành công!\n";

} catch (HiPayFinApiException $e) {
    echo "❌ API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    if (!empty($e->getMeta())) {
        echo "  Meta Details:\n";
        print_r($e->getMeta());
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

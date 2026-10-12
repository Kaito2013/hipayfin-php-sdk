<?php
/**
 * HiPayFin Payout Demo
 * 
 * Script mẫu kiểm thử tạo lệnh chi tiền / rút tiền (Direct KRW Payout).
 * Hỗ trợ chạy trực tiếp trên Terminal CLI hoặc mở qua Trình duyệt Web.
 */

// Tự động nạp thư viện SDK
require_once __DIR__ . '/autoload.php';

use Lucas\HiPayFin\Client;
use Lucas\HiPayFin\Config;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

// Thiết lập định dạng hiển thị cho Browser và CLI
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font-family: monospace; font-size: 14px; background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; line-height: 1.6;">';
}

echo "====================================================================\n";
echo "           HIPAYFIN MERCHANT SDK - PAYOUT DEMO TEST                 \n";
echo "====================================================================\n\n";

// 1. Khởi tạo HiPayFin Client
$client = new Client(array(
    'api_key'        => 'hk_live_e6CUp24YOaoMHiy7l57TOGv8ptJPgPPurgSCtWPS6zg',
    'hmac_secret'    => '1ba1a939f2f08daf0e4ad683e3560eb5984609f92fca17d2fa07f28fb8ae9338',
    'webhook_secret' => '62b78ed397fe4da89bb9477b39bffbaaf79c004676d14e0be0175c4ee0edb0b6',
    'environment'    => Config::ENV_SANDBOX,
));

// Thông tin lệnh rút tiền thử nghiệm
$payoutId = 'DEMO-PO-' . time();
$customerId = 'CUST-DEMO-01';
$amount = '100001'; // Tối thiểu 100,001 KRW trên Sandbox
$bankName = 'Kookmin Bank';
$accountNumber = '11012345678';
$accountHolder = 'Hong Gildong';
$customerNameKr = '홍길동'; // Tên tiếng Hàn bắt buộc theo quy định

try {
    // -------------------------------------------------------------
    // BƯỚC 1: Xem trước số tiền nhận được (POST /payout/krw/preview)
    // -------------------------------------------------------------
    echo "[BƯỚC 1] Xem trước tỷ giá và số tiền nhận (Preview)...\n";
    $preview = $client->payout->preview('krw', $amount);
    echo "  ✓ Preview thành công:\n";
    echo "    - Số tiền thực nhận : " . number_format($preview['receive_amount']) . " " . $preview['receive_currency'] . "\n\n";

    // -------------------------------------------------------------
    // BƯỚC 2: Tạo lệnh chi tiền trực tiếp (POST /payout/krw/create)
    // -------------------------------------------------------------
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

    $payout = $client->payout->createKrw($payload);
    $orderRef = $payout['order_ref'];

    echo "  ✓ Lệnh chi tiền đã được tiếp nhận thành công!\n\n";

    // -------------------------------------------------------------
    // BƯỚC 3: Tra cứu chi tiết đơn hàng vừa tạo (GET /orders/{ref})
    // -------------------------------------------------------------
    echo "[BƯỚC 3] Tra cứu thông tin chi tiết đơn hàng từ cổng...\n";
    $orderDetails = $client->orders->get($orderRef);

    echo "\n🎉 THÀNH CÔNG RỰC RỠ! Lệnh chi tiền đã được tạo trên HiPayFin:\n";
    echo "--------------------------------------------------------------------\n";
    echo "  • Mã đơn cổng (order_ref)     : {$orderRef}\n";
    echo "  • Mã đơn của bạn (order_id)   : {$payoutId}\n";
    echo "  • Loại giao dịch (kind)       : " . $orderDetails['kind'] . "\n";
    echo "  • Trạng thái (status)         : " . $orderDetails['status'] . "\n";
    echo "  • Số tiền rút (amount)        : " . number_format($orderDetails['amount']) . " KRW\n";
    echo "  • Phí giao dịch (fee)         : " . number_format($orderDetails['fee']) . " KRW\n";
    echo "--------------------------------------------------------------------\n";
    echo "  THÔNG TIN TÀI KHOẢN NGÂN HÀNG THỤ HƯỞNG:\n";
    echo "  • Ngân hàng                   : {$bankName}\n";
    echo "  • Số tài khoản nhận           : {$accountNumber}\n";
    echo "  • Tên chủ tài khoản           : {$accountHolder} ({$customerNameKr})\n";
    echo "--------------------------------------------------------------------\n";
    echo "💡 Trạng thái hiện tại là 'PROVIDER_PENDING' (chờ giải ngân).\n";
    echo "   Khi hoàn tất, HiPayFin sẽ bắn Webhook 'payout.processed' về server bạn!\n";

} catch (HiPayFinApiException $e) {
    echo "\n❌ LỖI GỌI API HIPAYFIN:\n";
    echo "  • HTTP Status Code : " . $e->getHttpStatusCode() . "\n";
    echo "  • Mã lỗi nội bộ    : " . $e->getErrorCode() . "\n";
    echo "  • Thông báo lỗi    : " . $e->getMessage() . "\n";
    if (!empty($e->getMeta())) {
        echo "  • Chi tiết Meta    :\n";
        print_r($e->getMeta());
    }
} catch (\Exception $e) {
    echo "\n❌ LỖI HỆ THỐNG: " . $e->getMessage() . "\n";
}

if (!$isCli) {
    echo '</pre>';
}

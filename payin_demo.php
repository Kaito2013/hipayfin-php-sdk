<?php
/**
 * HiPayFin Payin Demo
 * 
 * Script mẫu kiểm thử tạo đơn nạp tiền (Direct KRW Payin).
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
echo "           HIPAYFIN MERCHANT SDK - PAYIN DEMO TEST                  \n";
echo "====================================================================\n\n";

// 1. Khởi tạo HiPayFin Client
$client = new Client(array(
    'api_key'        => 'hk_live_e6CUp24YOaoMHiy7l57TOGv8ptJPgPPurgSCtWPS6zg',
    'hmac_secret'    => '1ba1a939f2f08daf0e4ad683e3560eb5984609f92fca17d2fa07f28fb8ae9338',
    'webhook_secret' => '62b78ed397fe4da89bb9477b39bffbaaf79c004676d14e0be0175c4ee0edb0b6',
    'environment'    => Config::ENV_SANDBOX,
));

// Thông tin giao dịch nạp thử nghiệm
$orderId = 'DEMO-PI-' . time();
$customerId = 'CUST-DEMO-01';
$amount = '150000'; // 150,000 KRW
$senderAccountName = 'Hong Gildong';
$senderNameKr = '홍길동'; // Tên tiếng Hàn bắt buộc
$senderBankCode = 'KOOKMIN';
$senderAccountNumber = '11012345678';
$senderDob = '970101';
$verifyCode = '6666'; // Mã verify code chuẩn trên Sandbox

try {
    // -------------------------------------------------------------
    // BƯỚC 1: Khởi tạo xác thực người nạp tiền (POST /payin/krw/preview)
    // -------------------------------------------------------------
    echo "[BƯỚC 1] Gửi yêu cầu xác thực người nạp tiền (Preview)...\n";
    $previewRes = $client->payin->previewKrw(array(
        'merchant_customer_id'  => $customerId,
        'amount'                => $amount,
        'sender_account_name'   => $senderAccountName,
        'sender_name_kr'        => $senderNameKr,
        'sender_bank_code'      => $senderBankCode,
        'sender_account_number' => $senderAccountNumber,
        'sender_dob'            => $senderDob,
    ));

    $verificationId = $previewRes['verification_id'];
    echo "  ✓ Xác thực khởi tạo thành công!\n";
    echo "    - Verification ID : {$verificationId}\n";
    echo "    - Trạng thái kế   : " . $previewRes['next_action'] . "\n\n";

    // -------------------------------------------------------------
    // BƯỚC 2: Xác nhận mã xác thực (POST /payin/krw/verify/confirm)
    // -------------------------------------------------------------
    echo "[BƯỚC 2] Xác nhận mã kiểm tra (Verify Code: {$verifyCode})...\n";
    $confirmRes = $client->payin->confirmKrwVerification($verificationId, $verifyCode);
    echo "  ✓ Mã xác thực được chấp nhận!\n\n";

    // -------------------------------------------------------------
    // BƯỚC 3: Tạo đơn nạp tiền trực tiếp (POST /payin/krw/create)
    // -------------------------------------------------------------
    echo "[BƯỚC 3] Tạo đơn hàng nạp tiền trực tiếp: {$orderId}...\n";
    $order = $client->payin->createKrw(array(
        'merchant_order_id'     => $orderId,
        'merchant_customer_id'  => $customerId,
        'amount'                => $amount,
        'sender_account_name'   => $senderAccountName,
        'sender_name_kr'        => $senderNameKr,
        'sender_bank_code'      => $senderBankCode,
        'sender_account_number' => $senderAccountNumber,
        'sender_dob'            => $senderDob,
        'verification_id'       => $verificationId,
    ));

    echo "\n🎉 THÀNH CÔNG RỰC RỠ! Đơn nạp tiền đã được tạo trên HiPayFin:\n";
    echo "--------------------------------------------------------------------\n";
    echo "  • Mã đơn cổng (order_ref)     : " . $order['order_ref'] . "\n";
    echo "  • Mã đơn của bạn (order_id)   : {$orderId}\n";
    echo "  • Trạng thái (status)         : " . $order['status'] . "\n";
    echo "  • Số tiền (amount)            : " . number_format($amount) . " KRW\n";

    if (isset($order['instruction'])) {
        $inst = $order['instruction'];
        echo "--------------------------------------------------------------------\n";
        echo "  THÔNG TIN TÀI KHOẢN ẢO (VIRTUAL ACCOUNT) ĐỂ CHUYỂN TIỀN:\n";
        echo "  • Ngân hàng                   : " . (isset($inst['bank']) ? $inst['bank'] : 'N/A') . "\n";
        echo "  • Số tài khoản (VA)           : " . (isset($inst['account']) ? $inst['account'] : 'N/A') . "\n";
        echo "  • Chủ tài khoản               : " . (isset($inst['holder']) ? $inst['holder'] : 'N/A') . "\n";
        echo "  • Loại tài khoản              : " . (isset($inst['kind']) ? $inst['kind'] : 'VA') . "\n";
    }
    echo "--------------------------------------------------------------------\n";
    echo "💡 Khi khách hàng chuyển tiền vào tài khoản trên, HiPayFin sẽ tự động\n";
    echo "   bắn Webhook sự kiện 'payment.confirmed' về webhook_receiver.php!\n";

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

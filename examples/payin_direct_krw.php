<?php
/**
 * Direct KRW Payin Flow (Không dùng Hosted Link)
 * 
 * Luồng gồm 3 bước:
 * 1. POST /payin/krw/preview (Khởi tạo xác thực danh tính chủ tài khoản)
 * 2. POST /payin/krw/verify/confirm (Nhập mã verify code: 6666)
 * 3. POST /payin/krw/create (Tạo đơn nạp tiền trực tiếp)
 */
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Support\Uuid;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

$orderId = 'ORD-KRW-DIR-' . time();
$customerId = 'CUST-KRW-001';
$amount = '150000'; // 150,000 KRW
$senderAccountName = 'Hong Gildong';
$senderNameKr = '홍길동'; // Tên tiếng Hàn theo yêu cầu
$senderBankCode = 'KOOKMIN';
$senderAccountNumber = '11012345678';
$senderDob = '970101';
$verifyCode = '6666'; // Mã verify code theo yêu cầu

echo "========================================================\n";
echo "   HIPAYFIN DIRECT KRW PAYIN TEST (WITHOUT HOSTED LINK) \n";
echo "========================================================\n\n";

try {
    // -------------------------------------------------------------
    // BƯỚC 1: Khởi tạo xác thực (POST /payin/krw/preview)
    // -------------------------------------------------------------
    echo "[BƯỚC 1] Bắt đầu xác thực người nạp tiền KRW...\n";
    $previewData = array(
        'merchant_customer_id'  => $customerId,
        'amount'                => $amount,
        'sender_account_name'   => $senderAccountName,
        'sender_name_kr'        => $senderNameKr,
        'sender_bank_code'      => $senderBankCode,
        'sender_account_number' => $senderAccountNumber,
        'sender_dob'            => $senderDob,
    );

    $previewRes = $client->payin->previewKrw($previewData);
    $verificationId = $previewRes['verification_id'];
    $nextAction = $previewRes['next_action'];

    echo "  ✓ Khởi tạo xác thực thành công!\n";
    echo "  - Verification ID: {$verificationId}\n";
    echo "  - Next Action:     {$nextAction}\n";
    echo "  - Expires At:      " . $previewRes['expires_at'] . "\n\n";

    // -------------------------------------------------------------
    // BƯỚC 2: Xác nhận mã verify code (POST /payin/krw/verify/confirm)
    // -------------------------------------------------------------
    echo "[BƯỚC 2] Xác nhận mã verify code: {$verifyCode}...\n";
    $confirmRes = $client->payin->confirmKrwVerification($verificationId, $verifyCode);
    echo "  Phản hồi từ server:\n";
    print_r($confirmRes);
    echo "\n";

    // -------------------------------------------------------------
    // BƯỚC 3: Tạo đơn nạp KRW trực tiếp (POST /payin/krw/create)
    // -------------------------------------------------------------
    echo "[BƯỚC 3] Tạo đơn Direct Payin KRW: {$orderId}...\n";
    $createPayload = array(
        'merchant_order_id'     => $orderId,
        'merchant_customer_id'  => $customerId,
        'amount'                => $amount,
        'sender_account_name'   => $senderAccountName,
        'sender_name_kr'        => $senderNameKr,
        'sender_bank_code'      => $senderBankCode,
        'sender_account_number' => $senderAccountNumber,
        'sender_dob'            => $senderDob,
        'verification_id'       => $verificationId,
    );

    $orderRes = $client->payin->createKrw($createPayload);

    echo "🎉 THÀNH CÔNG! Đơn hàng Payin KRW trực tiếp đã được tạo:\n";
    echo "  - Order Ref:  " . $orderRes['order_ref'] . "\n";
    echo "  - Status:     " . $orderRes['status'] . "\n";
    if (isset($orderRes['instruction'])) {
        echo "  - Hướng dẫn thanh toán: " . json_encode($orderRes['instruction'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

} catch (HiPayFinApiException $e) {
    echo "❌ API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    if (!empty($e->getMeta())) {
        echo "  Meta Details:\n";
        print_r($e->getMeta());
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

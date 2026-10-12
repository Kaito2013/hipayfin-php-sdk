<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

echo "====================================================\n";
echo "       HIPAYFIN KRW COMPREHENSIVE TEST RUN\n";
echo "====================================================\n\n";

// ----------------------------------------------------------------
// 1. Lấy danh sách ngân hàng xác thực KRW Payin (GET /payin/krw/banks)
// ----------------------------------------------------------------
echo "--- 1. Testing GET /payin/krw/banks ---\n";
try {
    $banksResponse = $client->payin->getBanks('krw');
    echo "SUCCESS! Currency: " . $banksResponse['currency'] . "\n";
    if (isset($banksResponse['banks']) && is_array($banksResponse['banks'])) {
        echo "Supported Banks (" . count($banksResponse['banks']) . "):\n";
        foreach (array_slice($banksResponse['banks'], 0, 5) as $b) {
            echo "  - [{$b['code']}] {$b['name']}\n";
        }
    }
} catch (HiPayFinApiException $e) {
    echo "Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
}
echo "\n";

// ----------------------------------------------------------------
// 2. Bắt đầu quy trình xác thực người nạp tiền KRW (POST /payin/krw/preview)
// ----------------------------------------------------------------
echo "--- 2. Testing KRW Payin Verification Preview (POST /payin/krw/preview) ---\n";
$verificationId = null;
try {
    $previewData = array(
        'merchant_customer_id'  => 'CUST-KRW-001',
        'amount'                => '150000',
        'sender_account_name'   => 'Hong Gildong',
        'sender_name_kr'        => '홍길동',
        'sender_bank_code'      => 'KOOKMIN',
        'sender_account_number' => '11012345678',
        'sender_dob'            => '970101',
    );
    $previewRes = $client->payin->previewKrw($previewData);
    echo "SUCCESS! Verification initiated:\n";
    echo "  Verification ID: " . $previewRes['verification_id'] . "\n";
    echo "  Next Action:     " . $previewRes['next_action'] . "\n";
    echo "  Expires At:      " . $previewRes['expires_at'] . "\n";
    $verificationId = $previewRes['verification_id'];
} catch (HiPayFinApiException $e) {
    echo "API Notice [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
}
echo "\n";

// ----------------------------------------------------------------
// 3. Nếu có verificationId, thử xác nhận mã OTP/1-won (POST /payin/krw/verify/confirm)
// ----------------------------------------------------------------
if ($verificationId) {
    echo "--- 3. Testing KRW Verification Confirm (POST /payin/krw/verify/confirm) ---\n";
    try {
        // Mã test sandbox thường là 1234 hoặc 0000
        $confirmRes = $client->payin->confirmKrwVerification($verificationId, '1234');
        echo "SUCCESS! Confirm result:\n";
        print_r($confirmRes);
    } catch (HiPayFinApiException $e) {
        echo "Notice [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
        print_r($e->getMeta());
    }
    echo "\n";
}

// ----------------------------------------------------------------
// 4. Tạo Hosted Payin Link KRW (POST /payin/link)
// ----------------------------------------------------------------
echo "--- 4. Testing KRW Hosted Payin Link (POST /payin/link) ---\n";
try {
    $orderId = 'ORD-KRW-LINK-' . time();
    $linkRes = $client->payin->createHostedLink(array(
        'merchant_order_id'    => $orderId,
        'merchant_customer_id' => 'CUST-KRW-001',
        'amount'               => Currency::toMinorUnit(200000, 'KRW'), // "200000"
        'target_currency'      => 'KRW',
    ));
    echo "SUCCESS! Created Hosted Checkout Link:\n";
    echo "  Order ID:        {$orderId}\n";
    echo "  Session Token:   " . $linkRes['session_token'] . "\n";
    echo "  Hosted Page URL: " . $linkRes['hosted_page_url'] . "\n";
    echo "  Expires At:      " . $linkRes['expires_at'] . "\n";
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
}
echo "\n";

// ----------------------------------------------------------------
// 5. Xem trước số tiền thực nhận Payout KRW (POST /payout/krw/preview)
// ----------------------------------------------------------------
echo "--- 5. Testing KRW Payout Preview (POST /payout/krw/preview) ---\n";
try {
    $payoutPreview = $client->payout->preview('krw', '150000');
    echo "SUCCESS! Payout Preview:\n";
    echo "  Receive Currency: " . $payoutPreview['receive_currency'] . "\n";
    echo "  Receive Amount:   " . $payoutPreview['receive_amount'] . "\n";
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
}
echo "\n";

echo "====================================================\n";
echo "              KRW TEST COMPLETED\n";
echo "====================================================\n";

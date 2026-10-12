# HiPayFin Merchant Payment Gateway PHP SDK (PHP 5.6+)

Bộ thư viện PHP SDK chính thức và độc lập dành cho cổng thanh toán **HiPayFin Merchant API** (Server-to-Server), được thiết kế tối ưu hóa cho **PHP 5.6 trở lên** (tương thích hoàn toàn với PHP 7.x và PHP 8.x), chuẩn PSR-4, thuần cURL và **không phụ thuộc bất kỳ thư viện bên thứ 3 nào (Zero-dependency)**.

---

## 🌟 Tính Năng Nổi Bật

- **Tương thích rộng rãi:** Chạy mượt mà từ PHP 5.6.0 đến PHP 8.2+.
- **Zero Runtime Dependencies:** Sử dụng `ext-curl`, `ext-json` và `ext-hash` có sẵn của PHP. Không cần Guzzle.
- **Ký số HMAC-SHA256 tự động:** Tự động sinh `X-Signature`, `X-Timestamp`, `X-Api-Key` cho mọi request.
- **Chống gửi trùng lệnh (Idempotency):** Hỗ trợ tự sinh `X-Idempotency-Key` chuẩn UUIDv4 cho các lệnh tạo đơn nạp/rút.
- **Xác thực Webhook an toàn:** Xác thực chữ ký `X-Webhook-Signature`, chống Replay Attack (cửa sổ 300 giây) và chống Timing Attack với `hash_equals`.
- **Hỗ trợ đầy đủ nghiệp vụ:**
  - **Payin:** VND (VietQR, Tài khoản ảo VA), USDT (TRC20 on-chain), KRW (Xác thực tài khoản 1-won), Hosted Checkout Link.
  - **Payout:** Chi tiền VND (VCB, BIDV, TCB...), USDT (TRC20), KRW, Hosted Payout Link.
  - **Orders & Wallets:** Tra cứu đơn theo `order_ref`, theo `merchant_order_id`, gửi bằng chứng giao dịch crypto `tx_hash`, danh sách ví active.
  - **Tiền tệ Minor Unit:** Helper `Currency` chuyển đổi số tiền thành chuỗi số nguyên an toàn, tránh lỗi sai số float.

---

## 📦 Cài Đặt (Installation)

### Cách 1: Sử dụng Composer (Khuyến nghị cho dự án hiện đại)

```bash
composer require lucas/hipayfin-php-sdk
```

Sau đó nạp file autoload của Composer:
```php
require_once __DIR__ . '/vendor/autoload.php';
```

### Cách 2: Sử dụng trực tiếp Autoloader độc lập (Dành cho CMS cũ / Không dùng Composer)

Tải hoặc copy thư mục `hipayfin` vào dự án của bạn (ví dụ trong WordPress theme/plugin hoặc CMS thuần PHP 5.6):
```php
require_once __DIR__ . '/hipayfin/autoload.php';
```

---

## 🚀 Khởi Tạo Client (Quick Start)

```php
use Lucas\HiPayFin\Client;
use Lucas\HiPayFin\Config;

$client = new Client([
    'api_key'        => 'hk_live_VbdBEm3for1GBYzuke6kYl0BHpx9R3afH_yK',
    'hmac_secret'    => '35b0091558ae05393e31643b7da158fefbc5397a6eaa037054bcd0b5aff045de',
    'webhook_secret' => 'your_webhook_signing_secret',
    'environment'    => Config::ENV_SANDBOX, // hoặc Config::ENV_PRODUCTION
    'timeout'        => 30, // Thời gian timeout cURL (giây)
]);
```

---

## 💳 1. Quy Trình Thu Tiền (Payin)

### A. Tạo đơn nạp VND trực tiếp (Direct Payin VND)
Trả về thông tin Tài khoản ảo (VA) hoặc mã QR để khách hàng quét thanh toán:

```php
use Lucas\HiPayFin\Support\Currency;

$order = $client->payin->createVnd([
    'merchant_order_id'    => 'ORD-VN-1001',
    'merchant_customer_id' => 'USER-001',
    'amount'               => Currency::toMinorUnit(250000, 'VND'), // "250000"
    'customer_name'        => 'Nguyen Van A',
]);

echo "Order Ref: " . $order['order_ref'];
print_r($order['instruction']); // Bank, Account number, QR info
```

### B. Tạo đơn nạp USDT TRC20 (Direct Payin USDT)
Trả về địa chỉ ví USDT (TRC20) để người dùng chuyển tiền on-chain:

```php
$order = $client->payin->createUsdt([
    'merchant_order_id'    => 'ORD-USDT-2001',
    'merchant_customer_id' => 'USER-001',
    'amount'               => Currency::toMinorUnit(10.5, 'USDT'), // "10500000" (6 số thập phân)
]);

echo "Địa chỉ nạp USDT: " . $order['instruction']['address'];
echo "Network: " . $order['instruction']['network']; // TRC20
```

### C. Sinh Hosted Checkout Link
Chuyển hướng khách hàng sang trang thanh toán do HiPayFin lưu trữ:

```php
$link = $client->payin->createHostedLink([
    'merchant_order_id'    => 'INV-3001',
    'merchant_customer_id' => 'USER-001',
    'amount'               => Currency::toMinorUnit(500000, 'VND'),
    'target_currency'      => 'VND',
]);

// Chuyển hướng người dùng:
header('Location: ' . $link['hosted_page_url']);
exit;
```

---

## 💸 2. Quy Trình Chi Tiền / Rút Tiền (Payout)

### A. Chi tiền VND về tài khoản ngân hàng khách hàng

```php
$payout = $client->payout->createVnd([
    'merchant_order_id'    => 'PO-VN-4001',
    'merchant_customer_id' => 'USER-001',
    'amount'               => Currency::toMinorUnit(150000, 'VND'),
    'bank_code'            => '970436', // Vietcombank
    'account_number'       => '0011001234567',
    'account_holder'       => 'NGUYEN VAN A',
]);

echo "Payout Order Ref: " . $payout['order_ref'];
echo "Trạng thái: " . $payout['status']; // PENDING_APPROVAL hoặc PENDING_EXECUTION
```

### B. Chi tiền USDT TRC20 về ví cá nhân của khách hàng

```php
$payout = $client->payout->createUsdt([
    'merchant_order_id'    => 'PO-USDT-5001',
    'merchant_customer_id' => 'USER-001',
    'amount'               => Currency::toMinorUnit(50, 'USDT'), // "50000000"
    'wallet_address'       => 'TLyqzVGLV1srkB7dToTApwdQEguDXHubQh',
    'network'              => 'TRC20',
]);
```

### C. Lấy danh sách ngân hàng Việt Nam hỗ trợ Payout

```php
$banks = $client->payout->getBanks('vnd');
print_r($banks['banks']); // Mã code, tên ngân hàng
```

---

## 🔍 3. Tra Cứu Đơn Hàng & Báo Cáo Giao Dịch

### Tra cứu đơn theo `order_ref` hoặc `merchant_order_id`
```php
// Tra cứu theo order_ref của cổng
$order = $client->orders->get('PI-2026101200088');

// Tra cứu theo mã đơn hàng của bạn (cứu cánh khi mạng rớt)
$order = $client->orders->getByMerchantOrderId('ORD-VN-1001');
```

### Báo cáo mã băm giao dịch USDT (`tx_hash`)
```php
$client->orders->reportEvidence('PI-2026101200088', '5e1c0d892f3a4b5c6d7e8f90123456789abcdef...');
```

---

## 🔔 4. Xử Lý Webhook An Toàn (Server-to-Server)

HiPayFin sẽ gửi thông báo bất đồng bộ về URL Webhook của bạn. Sử dụng `WebhookService` để xác thực:

```php
// File: webhook.php
require_once __DIR__ . '/autoload.php';

use Lucas\HiPayFin\Client;
use Lucas\HiPayFin\Exceptions\HiPayFinSignatureException;

$client = new Client([
    'api_key'        => 'hk_live_VbdBEm3for1GBYzuke6kYl0BHpx9R3afH_yK',
    'hmac_secret'    => '...',
    'webhook_secret' => 'your_webhook_signing_secret',
]);

$rawBody   = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) ? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] : '';
$timestamp = isset($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP']) ? $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] : '';

try {
    // Tự động kiểm tra chữ ký & chống Replay Attack (lệch giờ ±300s)
    $event = $client->webhook->verifyAndParse($rawBody, $signature, $timestamp);

    // Xử lý sự kiện:
    if ($event['event'] === 'payment.confirmed') {
        // QUAN TRỌNG: Cập nhật số dư theo số tiền THỰC TẾ nhận được từ webhook
        $amountReceived  = $event['amount']; 
        $merchantOrderId = $event['merchant_order_id'];
        
        // Cập nhật số dư cho người dùng trong Database...
    }

    // Luôn phản hồi HTTP 200 nhanh chóng (< 2 giây)
    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (HiPayFinSignatureException $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Chữ ký không hợp lệ hoặc request quá hạn']);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
```

---

## 🛡️ 5. Xử Lý Lỗi & Khắc Phục Đơn Trùng (`409 DUPLICATE_REQUEST`)

Khi gọi API bị lỗi, SDK sẽ ném ngoại lệ cấu trúc `HiPayFinApiException`:

```php
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;
use Lucas\HiPayFin\Exceptions\HiPayFinNetworkException;

try {
    $res = $client->payin->createVnd([...]);
} catch (HiPayFinApiException $e) {
    echo "HTTP Status: " . $e->getHttpStatusCode(); // ví dụ: 409
    echo "Error Code:  " . $e->getErrorCode();      // ví dụ: DUPLICATE_REQUEST
    echo "Message:     " . $e->getMessage();

    // Xử lý trường hợp gửi lại cùng Idempotency Key:
    if ($e->getErrorCode() === 'DUPLICATE_REQUEST') {
        $existingOrderRef = $e->getExistingId();
        // Dùng order_ref này tra cứu lại kết quả đơn thay vì báo lỗi
        $order = $client->orders->get($existingOrderRef);
    }
} catch (HiPayFinNetworkException $e) {
    // Xử lý timeout hoặc lỗi kết nối cURL
    echo "Lỗi mạng cURL: " . $e->getMessage();
}
```

---

## 🧪 6. Chạy Bộ Kiểm Thử (Unit Tests)

Bộ test suite được cấu hình sẵn runner kiểm thử chạy trực tiếp không cần cài đặt thêm thư viện:

```bash
php tests/run_tests.php
```

Hoặc chạy bằng PHPUnit chuẩn:
```bash
phpunit
```

---

## 📂 Các File Mẫu Chạy Thực Tế (Examples)

Xem thư mục `examples/`:
- `examples/payin_create_vnd.php`: Tạo đơn thu tiền VND.
- `examples/payin_create_usdt.php`: Tạo đơn thu tiền USDT TRC20.
- `examples/payout_create_vnd.php`: Tạo lệnh chi tiền VND.
- `examples/orders_query.php`: Tra cứu ví và đơn hàng.
- `examples/webhook_receiver.php`: Endpoint mẫu nhận Webhook.

---

## 📄 Bản Quyền (License)

Phát hành dưới giấy phép mã nguồn mở [MIT License](LICENSE).

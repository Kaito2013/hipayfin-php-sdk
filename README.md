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

### D. Tạo đơn nạp KRW trực tiếp (Direct Payin KRW — Không dùng Hosted Link)
Quy trình nạp KRW gồm 3 bước xác thực danh tính chủ tài khoản và cấp số Virtual Account (VA) ngân hàng Hàn Quốc:

```php
// Bước 1: Khởi tạo xác thực chủ tài khoản (POST /payin/krw/preview)
$preview = $client->payin->previewKrw([
    'merchant_customer_id'  => 'CUST-KRW-001',
    'amount'                => '150000', // Tối thiểu 100,001 KRW
    'sender_account_name'   => 'Hong Gildong',
    'sender_name_kr'        => '홍길동',
    'sender_bank_code'      => 'KOOKMIN',
    'sender_account_number' => '11012345678',
    'sender_dob'            => '970101',
]);
$verificationId = $preview['verification_id'];

// Bước 2: Xác nhận mã verify code (Sandbox test: 6666) (POST /payin/krw/verify/confirm)
$confirm = $client->payin->confirmKrwVerification($verificationId, '6666');

// Bước 3: Tạo đơn nạp tiền trực tiếp (POST /payin/krw/create)
$order = $client->payin->createKrw([
    'merchant_order_id'     => 'ORD-KRW-DIR-1001',
    'merchant_customer_id'  => 'CUST-KRW-001',
    'amount'                => '150000',
    'sender_account_name'   => 'Hong Gildong',
    'sender_name_kr'        => '홍길동',
    'sender_bank_code'      => 'KOOKMIN',
    'sender_account_number' => '11012345678',
    'sender_dob'            => '970101',
    'verification_id'       => $verificationId,
]);

echo "Order Ref: " . $order['order_ref'];
print_r($order['instruction']); // Bank, VA Account Number, Account Holder
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

### C. Chi tiền trực tiếp KRW về ngân hàng Hàn Quốc (Direct Payout KRW)

Thị trường Hàn Quốc yêu cầu bắt buộc tên tiếng Hàn (`customer_name_kr`) và tên ngân hàng (`bank_name`):

```php
// Xem trước số tiền nhận được (Preview - tuỳ chọn)
$preview = $client->payout->preview('krw', '100001');

// Tạo lệnh chi tiền trực tiếp:
$payout = $client->payout->createKrw([
    'merchant_order_id'    => 'PO-KRW-5001',
    'merchant_customer_id' => 'CUST-KRW-001',
    'amount'               => '100001',          // Chuỗi số nguyên, tối thiểu 100,001 KRW trên Sandbox
    'bank_name'            => 'Kookmin Bank',    // Tên ngân hàng
    'account_number'       => '11012345678',    // Số tài khoản người nhận
    'account_holder'       => 'Hong Gildong',    // Tên chủ tài khoản
    'customer_name_kr'     => '홍길동',          // Tên tiếng Hàn bắt buộc
]);

echo "Payout Order Ref: " . $payout['order_ref'];
echo "Trạng thái: " . $payout['status']; // PROVIDER_PENDING
```

### D. Lấy danh sách ngân hàng hỗ trợ Payout
```php
$vndBanks = $client->payout->getBanks('vnd');
$krwBanks = $client->payout->getBanks('krw');
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

## 🔔 4. Xử Lý Webhook An Toàn & Vòng Đời Đơn Hàng (Order Lifecycle) — ⚠️ CỰC KỲ QUAN TRỌNG

Webhook là cơ chế quan trọng nhất để hệ thống của bạn nhận thông báo trạng thái giao dịch một cách tức thì và tự động từ HiPayFin. Việc triển khai đúng quy chuẩn Webhook là điều kiện tiên quyết để không làm mất tiền của khách hàng và không làm sai lệch số dư.

### 4.1. Bảng 4 Sự Kiện Webhook Chuẩn (`WebhookEvent`)

HiPayFin **chỉ gửi Webhook khi đơn hàng chuyển sang các trạng thái kết thúc (Terminal Statuses)**:

| Sự Kiện (`WebhookEvent`) | Chiều Giao Dịch | Trạng Thái Đích (`OrderStatus`) | Ý Nghĩa Kỹ Thuật |
| :--- | :---: | :---: | :--- |
| **`payment.confirmed`** | **Payin** | `CONFIRMED` | Khách đã chuyển tiền thành công, tiền đã cộng vào ví Merchant. |
| **`payment.rejected`** | **Payin** | `EXPIRED` / `FAILED` | Khách không nạp tiền, hết thời gian chờ thanh toán (`reason: EXPIRED`), hoặc bị cổng từ chối. |
| **`payout.processed`** | **Payout** | `CONFIRMED` | Tiền chi/rút đã chuyển thành công vào tài khoản ngân hàng / ví crypto của khách. |
| **`payout.blocked`** | **Payout** | `REJECTED` / `FAILED` | Lệnh rút bị chặn (bị Operator từ chối ở bước `PENDING_APPROVAL`, sai STK, không đủ số dư, v.v.). |

---

### 4.2. 6 Quy Tắc Sống Còn Khi Xử Lý Webhook

#### 1. 🚨 Ngoại lệ Thanh Toán Muộn (`EXPIRED` ➔ `CONFIRMED` — Late Payment)
- **Tình huống thực tế:** Khi hết hạn thanh toán (ví dụ sau 15–30 phút khách chưa chuyển), HiPayFin chuyển trạng thái đơn sang `EXPIRED` và gửi webhook `payment.rejected` kèm `reason: "EXPIRED"`.
- **Tuy nhiên:** Nếu sau đó vài tiếng khách mới quét mã QR chuyển tiền vào STK cổng (hoặc ngân hàng bị treo giao dịch đến muộn), **HiPayFin vẫn sẽ ghi có đơn hàng và gửi tiếp webhook `payment.confirmed` về sau!**
- **Quy tắc thiết kế:** Backend của bạn **BẮT BUỘC PHẢI CHẤP NHẬN** cập nhật đơn hàng thành công (`CONFIRMED`) kể cả khi đơn đó hiện đang ở trạng thái `EXPIRED` trong Database.  
  👉 SDK đã tích hợp sẵn phương thức thẩm định:
  ```php
  OrderStatus::canTransition(OrderStatus::EXPIRED, OrderStatus::CONFIRMED); // true
  ```
  *(Nếu bạn khóa cứng đơn khi EXPIRED, khách hàng sẽ bị mất tiền mà không được cộng số dư trên website của bạn).*

#### 2. 🛡️ Quy Tắc Không Bao Giờ Hạ Cấp Trạng Thái (No Status Downgrade)
- Do cơ chế thử lại (Retry) và độ trễ mạng Internet, một webhook rejected/expired cũ có thể đến sau webhook confirmed.
- **Quy tắc vàng:** **Tuyệt đối không bao giờ hạ cấp đơn hàng từ `CONFIRMED` về `FAILED` hoặc `EXPIRED`**.
  ```php
  OrderStatus::canTransition(OrderStatus::CONFIRMED, OrderStatus::FAILED);  // false
  OrderStatus::canTransition(OrderStatus::CONFIRMED, OrderStatus::EXPIRED); // false
  ```
  *(Một khi đã `CONFIRMED`, trạng thái là tối thượng. Chỉ có can thiệp thủ công `REVERSED` do quản trị viên đối soát mới được phép cập nhật).*

#### 3. 💰 Đối Soát Số Tiền Thực Nhận (`amount` trong `payment.confirmed`)
- Cổng HiPayFin hỗ trợ cơ chế tự động khớp lệnh khi khách chuyển thiếu hoặc thừa (Auto-credit).
- **Trường `amount` trong webhook là số tiền THỰC TẾ đã nạp vào ví**, có thể khác với số tiền bạn yêu cầu lúc tạo đơn ban đầu!
- **Bắt buộc:** Bạn phải cập nhật số dư cho người dùng theo `amount` trong webhook này (`$event['amount']`), không dùng `amount` từ bản ghi tạo đơn ban đầu.

#### 4. 🔁 Chống Trùng Lặp Xử Lý (Idempotent Consumer với `event_id`)
- Trường `event_id` trong body webhook là duy nhất và giữ nguyên qua tất cả các lần gửi lại.
- Bạn phải lưu `event_id` vào Database với khóa duy nhất (`UNIQUE`). Nếu nhận lại `event_id` đã xử lý ➔ Trả ngay `HTTP 200 OK` và bỏ qua, không cộng tiền hai lần.

#### 5. ⚡ Phản Hồi HTTP 200 Nhanh Chóng (Dưới 2 Giây)
- Cổng thanh toán áp dụng timeout **10 giây**. Nếu server của bạn phản hồi chậm hoặc lỗi, HiPayFin sẽ tự động thử lại tối đa **5 lần** theo lịch:  
  `Lần 1: Ngay lập tức ➔ Lần 2: 1 phút ➔ Lần 3: 5 phút ➔ Lần 4: 30 phút ➔ Lần 5: 2 giờ`.
- **Khuyến nghị:** Sau khi xác thực chữ ký thành công, hãy lưu event vào hàng đợi (Queue / Database) và phản hồi ngay `HTTP 200 OK`. Các nghiệp vụ nặng (gửi email, gọi API bên thứ ba) hãy chạy nền (Background Job).

#### 6. 🧑‍💼 Xử Lý Đơn Do Operator Tạo Hộ (`op-...`)
- Quản trị viên HiPayFin có thể tạo link nạp/rút hộ bạn từ Merchant Portal. Các đơn này sẽ bắn webhook với `merchant_order_id` có tiền tố `op-` (ví dụ `op-1758857700000`).
- Hệ thống của bạn cần lưu vết các đơn này thay vì từ chối lỗi mã đơn hàng lạ.

---

### 4.3. Mã Nguồn Mẫu Triển Khai Webhook Chuẩn Doanh Nghiệp

File mẫu hoàn chỉnh tại [examples/webhook_receiver.php](file:///Users/hoaiminh/Sites/localhost/hipayfin/examples/webhook_receiver.php):

```php
<?php
require_once __DIR__ . '/autoload.php';

use Lucas\HiPayFin\Client;
use Lucas\HiPayFin\Support\OrderStatus;
use Lucas\HiPayFin\Support\WebhookEvent;
use Lucas\HiPayFin\Exceptions\HiPayFinSignatureException;

$client = new Client([
    'api_key'        => 'hk_live_...',
    'hmac_secret'    => '...',
    'webhook_secret' => 'your_webhook_signing_secret',
]);

// 1. Đọc dữ liệu thô và các headers xác thực
$rawBody   = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) ? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] : '';
$timestamp = isset($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP']) ? $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] : '';
$eventId   = isset($_SERVER['HTTP_X_WEBHOOK_ID'])        ? $_SERVER['HTTP_X_WEBHOOK_ID']        : '';

try {
    // 2. Xác thực chữ ký HMAC-SHA256 & chống Replay Attack (lệch giờ ±300s)
    $event = $client->webhook->verifyAndParse($rawBody, $signature, $timestamp);

    // 3. Kiểm tra chống xử lý trùng lặp (Idempotent Consumer)
    // if (isEventProcessedInDatabase($event['event_id'])) {
    //     http_response_code(200);
    //     echo json_encode(['status' => 'ignored_duplicate']);
    //     exit;
    // }

    // 4. Lấy trạng thái hiện tại của đơn hàng từ Database
    // $currentLocalStatus = getOrderStatusFromDatabase($event['merchant_order_id']);

    // 5. Xử lý sự kiện theo đúng Vòng đời Đơn hàng
    switch ($event['event']) {
        case WebhookEvent::PAYMENT_CONFIRMED:
            // QUY TẮC SỐNG CÒN 1: Nếu đơn đang EXPIRED nhưng tiền vào trễ, vẫn cho phép chuyển sang CONFIRMED!
            // QUY TẮC SỐNG CÒN 2: Luôn cộng tiền theo số tiền THỰC TẾ nhận được ($event['amount'])
            if (OrderStatus::canTransition($currentLocalStatus, OrderStatus::CONFIRMED)) {
                $actualAmount    = $event['amount'];
                $orderRef        = $event['order_ref'];
                $merchantOrderId = $event['merchant_order_id'];

                // updateOrderStatusInDb($merchantOrderId, OrderStatus::CONFIRMED, $orderRef);
                // creditUserBalance($merchantOrderId, $actualAmount);
            }
            break;

        case WebhookEvent::PAYMENT_REJECTED:
            $reason = isset($event['reason']) ? $event['reason'] : OrderStatus::FAILED;
            $newStatus = ($reason === 'EXPIRED') ? OrderStatus::EXPIRED : OrderStatus::FAILED;

            // QUY TẮC SỐNG CÒN 3: Tuyệt đối không bao giờ hạ cấp đơn đã CONFIRMED
            if (OrderStatus::canTransition($currentLocalStatus, $newStatus)) {
                // updateOrderStatusInDb($event['merchant_order_id'], $newStatus, null, $reason);
            }
            break;

        case WebhookEvent::PAYOUT_PROCESSED:
            // Đã chi tiền thành công cho khách
            // markPayoutCompleted($event['order_ref']);
            break;

        case WebhookEvent::PAYOUT_BLOCKED:
            // Chi tiền thất bại -> hoàn lại số dư nội bộ cho khách
            // markPayoutFailedAndRefundBalance($event['order_ref'], $event['reason']);
            break;
    }

    // 6. Luôn phản hồi HTTP 200 OK nhanh chóng (< 2 giây)
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);

} catch (HiPayFinSignatureException $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Chữ ký không hợp lệ hoặc request quá hạn (Replay Attack)']);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
```

---

### 4.4. Công Cụ Giả Lập Bắn Thử Webhook (`simulate_webhook.php`)

SDK tích hợp sẵn file giả lập [examples/simulate_webhook.php](file:///Users/hoaiminh/Sites/localhost/hipayfin/examples/simulate_webhook.php) giúp bạn tự bắn các request Webhook chuẩn để kiểm thử endpoint của mình (hoặc kiểm tra trên webhook.site):

```bash
# Bắn webhook thử nghiệm đến URL mong muốn:
php examples/simulate_webhook.php https://yourdomain.com/webhook.php
```

---

### 4.5. Cơ Chế Xác Thực Chữ Ký 2 Lớp (Auto-fallback Secret)

Trong hệ thống bảo mật của HiPayFin:
- **`hmac_secret`**: Dùng để ký các request API từ Merchant gửi lên server HiPayFin.
- **`webhook_secret`**: Khóa bí mật riêng do HiPayFin cấp để ký các Webhook gửi về cho Merchant.

SDK đã tích hợp sẵn cơ chế **Auto-fallback 2 lớp**:
1. Ưu tiên xác thực chữ ký bằng `webhook_secret` được cấu hình.
2. Nếu không khớp (ví dụ Merchant dùng chung key hoặc Operator cấu hình key riêng), SDK sẽ **tự động xác thực dự phòng bằng `hmac_secret`**.
3. **Chuẩn hóa chữ ký tự động**: Tự động loại bỏ khoảng trắng thừa (`trim`), hỗ trợ so sánh không phân biệt hoa thường và tự động xử lý tiền tố `sha256=`.

---

### 4.6. Hướng Dẫn Test Webhook Local Với Ngrok Domain Cố Định (`*.ngrok-free.dev`)

Để nhận Webhook từ HiPayFin trên môi trường Localhost mà **không bị đổi URL liên tục khi khởi động lại**:

1. **Khởi chạy Ngrok với Static Dev Domain**:
   ```bash
   ngrok http 80 --url=your-domain.ngrok-free.dev --host-header=localhost
   ```
   > [!IMPORTANT]
   > **Bắt buộc phải có cờ `--host-header=localhost`**: Giúp Apache/Nginx local nhận diện đúng Virtual Host của máy bạn, tránh bị trả về lỗi `HTTP 404 Not Found`.

2. **Gửi URL cố định một lần duy nhất cho Operator HiPayFin**:
   ```
   https://your-domain.ngrok-free.dev/hipayfin/examples/webhook_receiver.php
   ```

3. **Theo dõi và Replay Webhook trực tiếp qua Web UI**:
   Mở trình duyệt truy cập: [http://127.0.0.1:4040](http://127.0.0.1:4040) để xem chi tiết từng request, headers, body và bấm Replay kiểm thử lại bất kỳ lúc nào mà không cần tạo đơn mới.

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

Xem chi tiết trong thư mục `examples/`:
- `examples/payin_create_vnd.php`: Tạo đơn thu tiền VND trực tiếp (VietQR, VA).
- `examples/payin_create_usdt.php`: Tạo đơn thu tiền USDT TRC20 on-chain.
- `examples/payin_create_hosted_link.php`: Sinh Hosted Payin checkout link.
- `examples/payin_direct_krw.php`: Nạp tiền KRW trực tiếp (xác thực 3 bước, cấp VA ngân hàng).
- `examples/payout_create_vnd.php`: Tạo lệnh chi tiền VND về ngân hàng nội địa.
- `examples/payout_direct_krw.php`: Tạo lệnh chi tiền KRW trực tiếp về ngân hàng Hàn Quốc.
- `examples/orders_query.php`: Tra cứu số dư ví và chi tiết đơn hàng.
- `examples/webhook_receiver.php`: Endpoint mẫu nhận và xác thực Webhook chuẩn Production.
- `examples/simulate_webhook.php`: Tool giả lập tự bắn Webhook kiểm thử nội bộ.

---

## 📄 Bản Quyền (License)

Phát hành dưới giấy phép mã nguồn mở [MIT License](LICENSE).

# Thiết Kế Kỹ Thuật (Technical Design Spec): HiPayFin PHP SDK (PHP 5.6+)

- **Tài liệu:** HiPayFin Merchant Payment Gateway PHP SDK
- **Namespace:** `Lucas\HiPayFin\`
- **Phiên bản mục tiêu:** PHP >= 5.6.0 (Tương thích đồng thời PHP 7.x và PHP 8.x)
- **Chuẩn Autoload:** PSR-4 (`composer.json`) & Fallback Autoloader (`autoload.php`)
- **HTTP Client:** Pure cURL (Zero third-party runtime dependencies)
- **Ngày tạo:** 12/10/2026

---

## 1. Mục Tiêu & Bối Cảnh Nghiệp Vụ

Dự án cung cấp bộ thư viện SDK mã nguồn mở gọn nhẹ, an toàn và dễ tích hợp cho cổng thanh toán **HiPayFin Merchant API**. SDK hỗ trợ cả việc cài đặt qua Composer cho các dự án hiện đại và việc nhúng trực tiếp qua file `autoload.php` cho các website/CMS thế hệ cũ (PHP 5.6+, WordPress/WooCommerce, OpenCart, CodeIgniter, v.v.).

### Thông tin môi trường Sandbox tích hợp sẵn:
- **Sandbox Base URL:** `https://api-sandbox.hipayfin.com/api/v1`
- **Production Base URL:** `https://api.hipayfin.com/api/v1`
- **API Key (Sandbox):** `hk_live_VbdBEm3for1GBYzuke6kYl0BHpx9R3afH_yK`
- **HMAC Signing Secret (Sandbox):** `35b0091558ae05393e31643b7da158fefbc5397a6eaa037054bcd0b5aff045de`

---

## 2. Ràng Buộc Kỹ Thuật & Tương Thích PHP 5.6

Để tương thích tuyệt đối với PHP 5.6 mà không sinh lỗi cú pháp hay cảnh báo:
1. **Không dùng tính năng của PHP 7+:**
   - Không khai báo kiểu dữ liệu vô hướng trong tham số hàm (`int`, `string`, `bool`, `array` type hint chỉ dùng cho mảng `array $param`).
   - Không khai báo kiểu dữ liệu trả về (`: void`, `: string`).
   - Không dùng toán tử Null Coalescing (`??`), thay bằng `isset($a) ? $a : $b`.
   - Không dùng typed properties (PHP 7.4).
   - Không dùng anonymous classes hay spaceship operator `<=>`.
2. **Bảo mật và hàm băm:**
   - `hash_hmac('sha256', ...)` có sẵn từ PHP 5.1.2.
   - `hash_equals()` có sẵn từ PHP 5.6.0. SDK cung cấp fallback an toàn thời gian đề phòng trường hợp môi trường đặc biệt.
   - Sinh UUIDv4 dùng `openssl_random_pseudo_bytes` (PHP >= 5.3) kèm fallback `mt_rand`.
3. **Cú pháp mảng:**
   - Hỗ trợ cú pháp mảng ngắn `[]` (đã có từ PHP 5.4).

---

## 3. Cấu Trúc Thư Mục & Phân Tầng Module

```text
hipayfin/
├── src/
│   ├── Client.php                      // Entry point chính
│   ├── Config.php                      // Cấu hình môi trường, credentials, timeout
│   ├── Http/
│   │   ├── HttpClientInterface.php     // Interface cho tầng HTTP
│   │   ├── CurlHttpClient.php          // Cài đặt cURL thuần PHP 5.6
│   │   └── Response.php                // Đóng gói HTTP response
│   ├── Signature/
│   │   └── HmacSigner.php              // Ký số X-Signature & xác thực Webhook
│   ├── Services/
│   │   ├── AbstractService.php         // Base service & sanitization
│   │   ├── PayinService.php            // Direct Payin, Hosted Link, KRW Verify
│   │   ├── PayoutService.php           // Direct Payout, Hosted Link, Banks, Preview
│   │   ├── OrderService.php            // Tra cứu đơn, báo cáo tx_hash evidence
│   │   ├── WalletService.php           // Danh sách ví active
│   │   └── WebhookService.php          // Xác thực signature & parse payload
│   ├── Support/
│   │   ├── Currency.php                // Chuyển đổi & validate Minor Units
│   │   └── Uuid.php                    // Sinh UUIDv4 cho X-Idempotency-Key
│   └── Exceptions/
│       ├── HiPayFinException.php       // Base exception
│       ├── HiPayFinApiException.php    // Lỗi HTTP 4xx/5xx & API error envelope
│       ├── HiPayFinNetworkException.php // Lỗi cURL connection, timeout
│       ├── HiPayFinValidationException.php // Lỗi dữ liệu tham số client
│       └── HiPayFinSignatureException.php  // Lỗi xác thực chữ ký
├── tests/
│   ├── bootstrap.php
│   ├── CurrencyTest.php
│   ├── UuidTest.php
│   ├── HmacSignerTest.php
│   ├── PayinServiceTest.php
│   └── PayoutServiceTest.php
├── examples/
│   ├── config.php
│   ├── payin_create_vnd.php
│   ├── payin_create_usdt.php
│   ├── payout_create_vnd.php
│   ├── orders_query.php
│   └── webhook_receiver.php
├── autoload.php                        // Standalone PSR-4 autoloader
├── composer.json
├── phpunit.xml.dist
└── README.md
```

---

## 4. Chi Tiết Thiết Kế Các Module

### 4.1. `Config` & `Client`
- `Lucas\HiPayFin\Config`: Lưu giữ `apiKey`, `hmacSecret`, `webhookSecret`, `environment` (`sandbox` hoặc `production`), `baseUrl`, `timeout` (mặc định 30s), `connectTimeout` (mặc định 10s).
- `Lucas\HiPayFin\Client`: Nhận `Config` hoặc mảng cấu hình. Quản lý việc khởi tạo lười (lazy load) các Service thông qua magic `__get` hoặc public getter:
  - `$client->payin`: Đối tượng `PayinService`
  - `$client->payout`: Đối tượng `PayoutService`
  - `$client->orders`: Đối tượng `OrderService`
  - `$client->wallets`: Đối tượng `WalletService`
  - `$client->webhook`: Đối tượng `WebhookService`

### 4.2. `HmacSigner` (Ký số & Bảo mật)
- **Thuật toán ký:**
  - `PayloadToSign = timestamp . "." . rawBody`
  - Nếu request là GET: `rawBody` là rỗng `""` $\rightarrow$ Chuỗi ký là `timestamp . "."` (có dấu chấm cuối).
  - Nếu request là POST: `rawBody` là chuỗi JSON raw gửi qua mạng (`json_encode($data, JSON_UNESCAPED_SLASHES)`).
  - Chữ ký hex: `hash_hmac('sha256', $payloadToSign, $hmacSecret)`.
  - Header: `X-Signature: sha256=` + lowercase(hex).
  - Header: `X-Timestamp: ` epoch seconds.
  - Header: `X-Api-Key: ` api key.
- **Xác thực Webhook:**
  - Kiểm tra có đủ header `X-Webhook-Signature` và `X-Webhook-Timestamp`.
  - Kiểm tra replay attack: `abs(time() - (int)$timestamp) <= 300`.
  - Tính toán chữ ký kỳ vọng với `webhook_secret`.
  - So sánh chuỗi bằng `hash_equals($expected, $signature)`.

### 4.3. `CurlHttpClient` & Phản hồi Envelope
- Thực hiện gọi cURL với các options an toàn:
  - `CURLOPT_RETURNTRANSFER => true`
  - `CURLOPT_SSL_VERIFYPEER => true`
  - `CURLOPT_SSL_VERIFYHOST => 2`
  - `CURLOPT_TIMEOUT => $timeout`
- Kiểm tra mã lỗi mạng cURL: nếu `curl_errno` khác 0, ném `HiPayFinNetworkException`.
- Xử lý mã HTTP và Envelope:
  - Parse JSON bằng `json_decode($body, true)`.
  - Nếu HTTP code $\ge 400$ hoặc `$response['error'] !== null`:
    Ném `HiPayFinApiException` kèm đầy đủ `httpStatusCode`, `errorCode`, `message`, `meta`.
  - Nếu HTTP code 200 và `$response['error'] === null`:
    Trả về mảng trong `$response['data']`.

### 4.4. Các Service Nghiệp Vụ
- **`PayinService`:**
  - `createVnd(array $params, $idempotencyKey = null)` $\rightarrow$ `POST /payin/vnd/create`
  - `createUsdt(array $params, $idempotencyKey = null)` $\rightarrow$ `POST /payin/usdt/create`
  - `createKrw(array $params, $idempotencyKey = null)` $\rightarrow$ `POST /payin/krw/create`
  - `createHostedLink(array $params, $idempotencyKey = null)` $\rightarrow$ `POST /payin/link`
  - `getBanks($currency = 'krw')` $\rightarrow$ `GET /payin/{cur}/banks`
  - `previewKrw(array $params)` $\rightarrow$ `POST /payin/krw/preview`
  - `confirmKrwVerification($verificationId, $code)` $\rightarrow$ `POST /payin/krw/verify/confirm`
- **`PayoutService`:**
  - `createVnd(array $params, $idempotencyKey = null)` $\rightarrow$ `POST /payout/vnd/create`
  - `createUsdt(array $params, $idempotencyKey = null)` $\rightarrow$ `POST /payout/usdt/create`
  - `createKrw(array $params, $idempotencyKey = null)` $\rightarrow$ `POST /payout/krw/create`
  - `createHostedLink(array $params, $idempotencyKey = null)` $\rightarrow$ `POST /payout/link`
  - `getBanks($currency = 'vnd')` $\rightarrow$ `GET /payout/{cur}/banks`
  - `preview($currency, $amount)` $\rightarrow$ `POST /payout/{cur}/preview`
- **`OrderService`:**
  - `get($orderRef)` $\rightarrow$ `GET /orders/{ref}`
  - `getByMerchantOrderId($merchantOrderId)` $\rightarrow$ `GET /orders?merchant_order_id=...`
  - `reportEvidence($orderRef, $txHash)` $\rightarrow$ `POST /orders/{ref}/evidence`
- **`WalletService`:**
  - `all()` $\rightarrow$ `GET /wallets`
- **`WebhookService`:**
  - `verify($rawBody, $signature, $timestamp, $secret = null)`
  - `parse($rawBody)` $\rightarrow$ Trả về mảng dữ liệu webhook đã decode.
- **Sanitizer (Làm sạch Payload):**
  - Mọi hàm gọi POST tạo đơn tự động lọc bỏ các trường `null` hoặc chuỗi rỗng `""` (đặc biệt là trường `customer_name`), tuân thủ quy tắc kiểm tra nghiêm ngặt của HiPayFin.

### 4.5. Support Helpers
- **`Currency`:**
  - `Currency::toMinorUnit($amount, $currency)`:
    - VND/KRW: 0 chữ số thập phân, ép kiểu chuỗi số nguyên.
    - USDT: 6 chữ số thập phân, tính toán chính xác số thập phân bằng chuỗi/`bcmul` để tránh sai số `float`.
  - `Currency::fromMinorUnit($minorAmount, $currency)`:
    - Đổi ngược từ Minor unit sang đơn vị thông thường cho mục đích hiển thị.
  - `Currency::validateMinorUnit($minorAmount)`:
    - Kiểm tra chuỗi số nguyên dương khớp regex `^[1-9][0-9]*$`.
- **`Uuid`:**
  - `Uuid::v4()`: Sinh UUID version 4 ngẫu nhiên theo RFC 4122.

---

## 5. Xử Lý Ngoại Lệ (Exception Handling)

```text
\Exception
 └── Lucas\HiPayFin\Exceptions\HiPayFinException
      ├── HiPayFinApiException (HTTP Status, errorCode, message, meta, getExistingId())
      ├── HiPayFinNetworkException (curlErrno, message)
      ├── HiPayFinValidationException (chi tiết trường tham số không hợp lệ)
      └── HiPayFinSignatureException (lỗi sai chữ ký hoặc quá hạn 300s)
```

Trợ giúp hữu ích cho lập trình viên khi bắt lỗi `409 DUPLICATE_REQUEST`:
Phương thức `$e->getExistingId()` tự động trích xuất `order_ref` hoặc `session_token` từ `meta.existing_id`, giúp ứng dụng tra cứu lại đơn hàng cũ thay vì báo lỗi người dùng.

---

## 6. Kế Hoạch Kiểm Thử & Chạy Thử Thực Tế

1. **Unit Test Suite (PHPUnit):**
   - Kiểm thử toàn diện các lớp `Currency`, `Uuid`, `HmacSigner`, `WebhookService`.
   - Mock kết nối cURL để kiểm tra luồng đóng gói Envelope và ném Ngoại lệ của `PayinService` và `PayoutService`.
2. **Kịch bản Chạy Thử (Examples):**
   - `examples/config.php`: Khởi tạo sẵn Client với API Key và HMAC Secret Sandbox.
   - `examples/payin_create_vnd.php`: Gọi API Sandbox tạo đơn nạp tiền thật, in thông tin QR Code/VA.
   - `examples/payin_create_usdt.php`: Gọi API Sandbox tạo đơn nạp USDT TRC20 thật.
   - `examples/payout_create_vnd.php`: Gọi API Sandbox tạo lệnh chi tiền mẫu.
   - `examples/orders_query.php`: Tra cứu đơn hàng trên Sandbox.
   - `examples/webhook_receiver.php`: Endpoint mẫu nhận Webhook, xác thực chữ ký an toàn, chống replay attack, xử lý sự kiện `payment.confirmed` với số tiền `amount` thực nhận.

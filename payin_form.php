<?php
/**
 * HiPayFin Direct KRW Payin - 3-Step Interactive Form
 * 
 * Giao diện web trực quan để kiểm thử quy trình nạp tiền KRW 3 bước:
 * Bước 1: Khởi tạo xác thực người nạp (POST /payin/krw/preview)
 * Bước 2: Xác nhận mã kiểm tra OTP (POST /payin/krw/verify/confirm)
 * Bước 3: Tạo đơn nạp tiền trực tiếp & lấy tài khoản VA (POST /payin/krw/create)
 */

require_once __DIR__ . '/autoload.php';

use Lucas\HiPayFin\Client;
use Lucas\HiPayFin\Config;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

// 1. Khởi tạo HiPayFin Client
$client = new Client(array(
    'api_key'        => 'hk_live_e6CUp24YOaoMHiy7l57TOGv8ptJPgPPurgSCtWPS6zg',
    'hmac_secret'    => '1ba1a939f2f08daf0e4ad683e3560eb5984609f92fca17d2fa07f28fb8ae9338',
    'webhook_secret' => '62b78ed397fe4da89bb9477b39bffbaaf79c004676d14e0be0175c4ee0edb0b6',
    'environment'    => Config::ENV_SANDBOX,
));

// 2. Xử lý API AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    try {
        if ($action === 'preview') {
            // Bước 1: Preview KRW
            $customerId    = isset($_POST['merchant_customer_id']) ? trim($_POST['merchant_customer_id']) : 'CUST-KRW-001';
            $amount        = isset($_POST['amount']) ? trim($_POST['amount']) : '150000';
            $accountName   = isset($_POST['sender_account_name']) ? trim($_POST['sender_account_name']) : 'Hong Gildong';
            $nameKr        = isset($_POST['sender_name_kr']) ? trim($_POST['sender_name_kr']) : '홍길동';
            $bankCode      = isset($_POST['sender_bank_code']) ? trim($_POST['sender_bank_code']) : 'KOOKMIN';
            $accountNumber = isset($_POST['sender_account_number']) ? trim($_POST['sender_account_number']) : '11012345678';
            $dob           = isset($_POST['sender_dob']) ? trim($_POST['sender_dob']) : '970101';

            $previewData = array(
                'merchant_customer_id'  => $customerId,
                'amount'                => $amount,
                'sender_account_name'   => $accountName,
                'sender_name_kr'        => $nameKr,
                'sender_bank_code'      => $bankCode,
                'sender_account_number' => $accountNumber,
                'sender_dob'            => $dob,
            );

            $res = $client->payin->previewKrw($previewData);
            echo json_encode(array('success' => true, 'data' => $res));
            exit;

        } elseif ($action === 'confirm') {
            // Bước 2: Confirm OTP
            $verificationId = isset($_POST['verification_id']) ? trim($_POST['verification_id']) : '';
            $verifyCode     = isset($_POST['verify_code']) ? trim($_POST['verify_code']) : '6666';

            if (empty($verificationId)) {
                throw new \Exception('Thiếu Verification ID từ Bước 1');
            }

            $res = $client->payin->confirmKrwVerification($verificationId, $verifyCode);
            echo json_encode(array('success' => true, 'data' => $res));
            exit;

        } elseif ($action === 'create') {
            // Bước 3: Create Order
            $orderId        = isset($_POST['merchant_order_id']) && !empty($_POST['merchant_order_id']) 
                                ? trim($_POST['merchant_order_id']) 
                                : 'ORD-KRW-' . time();
            $customerId     = isset($_POST['merchant_customer_id']) ? trim($_POST['merchant_customer_id']) : 'CUST-KRW-001';
            $amount         = isset($_POST['amount']) ? trim($_POST['amount']) : '150000';
            $accountName    = isset($_POST['sender_account_name']) ? trim($_POST['sender_account_name']) : 'Hong Gildong';
            $nameKr         = isset($_POST['sender_name_kr']) ? trim($_POST['sender_name_kr']) : '홍길동';
            $bankCode       = isset($_POST['sender_bank_code']) ? trim($_POST['sender_bank_code']) : 'KOOKMIN';
            $accountNumber  = isset($_POST['sender_account_number']) ? trim($_POST['sender_account_number']) : '11012345678';
            $dob            = isset($_POST['sender_dob']) ? trim($_POST['sender_dob']) : '970101';
            $verificationId = isset($_POST['verification_id']) ? trim($_POST['verification_id']) : '';

            if (empty($verificationId)) {
                throw new \Exception('Thiếu Verification ID đã được xác thực');
            }

            $createData = array(
                'merchant_order_id'     => $orderId,
                'merchant_customer_id'  => $customerId,
                'amount'                => $amount,
                'sender_account_name'   => $accountName,
                'sender_name_kr'        => $nameKr,
                'sender_bank_code'      => $bankCode,
                'sender_account_number' => $accountNumber,
                'sender_dob'            => $dob,
                'verification_id'       => $verificationId,
            );

            $res = $client->payin->createKrw($createData);
            echo json_encode(array('success' => true, 'data' => $res));
            exit;
        }
    } catch (HiPayFinApiException $e) {
        http_response_code(400);
        echo json_encode(array(
            'success'   => false,
            'message'   => $e->getMessage(),
            'code'      => $e->getErrorCode(),
            'http_code' => $e->getHttpStatusCode(),
            'meta'      => $e->getMeta(),
        ));
        exit;
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(array(
            'success' => false,
            'message' => $e->getMessage(),
        ));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HiPayFin Direct KRW Payin - Quy trình 3 bước</title>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --card-border: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: #0f172a;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        body {
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            padding: 30px 15px;
            display: flex;
            justify-content: center;
        }
        .container {
            width: 100%;
            max-width: 820px;
        }
        .header {
            text-align: center;
            margin-bottom: 28px;
        }
        .header h1 {
            font-size: 26px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .badge-sandbox {
            background: #f59e0b;
            color: #000;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Stepper progress */
        .stepper {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 30px;
        }
        .stepper::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50px;
            right: 50px;
            height: 3px;
            background: var(--card-border);
            z-index: 1;
        }
        .step-progress-bar {
            position: absolute;
            top: 20px;
            left: 50px;
            height: 3px;
            background: var(--primary);
            z-index: 2;
            transition: width 0.3s ease;
            width: 0%;
        }
        .step-item {
            position: relative;
            z-index: 3;
            text-align: center;
            flex: 1;
        }
        .step-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 2px solid var(--card-border);
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        .step-item.active .step-circle {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
            box-shadow: 0 0 15px rgba(79, 70, 229, 0.4);
        }
        .step-item.completed .step-circle {
            border-color: var(--success);
            background: var(--success);
            color: #fff;
        }
        .step-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }
        .step-item.active .step-label {
            color: #fff;
        }
        .step-item.completed .step-label {
            color: var(--success);
        }

        /* Card Section */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 26px;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--card-border);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group.full {
            grid-column: span 2;
        }
        label {
            font-size: 13px;
            font-weight: 500;
            color: #cbd5e1;
        }
        label span.req {
            color: var(--danger);
        }
        input, select {
            background: var(--input-bg);
            border: 1px solid var(--card-border);
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus, select:focus {
            border-color: var(--primary);
        }
        input[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
            background: #162032;
        }
        .hint {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Buttons */
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 22px;
            justify-content: flex-end;
        }
        button {
            cursor: pointer;
            padding: 11px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-secondary {
            background: #334155;
            color: #f8fafc;
        }
        .btn-secondary:hover {
            background: #475569;
        }
        .btn-auto {
            background: #059669;
            color: #fff;
        }
        .btn-auto:hover {
            background: #047857;
        }

        /* Status & Alert Box */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            display: none;
            line-height: 1.5;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger);
            color: #fca5a5;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid var(--success);
            color: #6ee7b7;
        }
        .alert-info {
            background: rgba(79, 70, 229, 0.15);
            border: 1px solid var(--primary);
            color: #a5b4fc;
        }

        /* Result Section */
        .va-card {
            background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
            border: 1px solid #4338ca;
            border-radius: 12px;
            padding: 24px;
            margin-top: 20px;
        }
        .va-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px dashed #4338ca;
        }
        .va-title {
            font-size: 16px;
            font-weight: 700;
            color: #818cf8;
        }
        .va-status {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
        }
        .va-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .va-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .va-field-label {
            font-size: 12px;
            color: #94a3b8;
        }
        .va-field-value {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .copy-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            color: #cbd5e1;
        }
        .copy-btn:hover {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }

        /* Collapsible JSON output */
        details {
            margin-top: 16px;
            background: #0b1120;
            border-radius: 8px;
            border: 1px solid #1e293b;
            padding: 8px 12px;
        }
        summary {
            font-size: 12px;
            color: var(--text-muted);
            cursor: pointer;
            outline: none;
            user-select: none;
        }
        pre.json-view {
            margin-top: 8px;
            font-size: 11px;
            color: #38bdf8;
            overflow-x: auto;
            max-height: 250px;
            background: #020617;
            padding: 12px;
            border-radius: 6px;
        }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>
            HiPayFin Direct KRW Payin
            <span class="badge-sandbox">Sandbox</span>
        </h1>
        <p>Quy trình kiểm thử 3 bước tạo đơn nạp tiền trực tiếp vào tài khoản ảo (Virtual Account)</p>
    </div>

    <!-- Stepper -->
    <div class="stepper">
        <div class="step-progress-bar" id="progressBar"></div>
        <div class="step-item active" id="stepIndicator1">
            <div class="step-circle" id="circle1">1</div>
            <div class="step-label">1. Khởi tạo xác thực</div>
        </div>
        <div class="step-item" id="stepIndicator2">
            <div class="step-circle" id="circle2">2</div>
            <div class="step-label">2. Nhập Verify Code</div>
        </div>
        <div class="step-item" id="stepIndicator3">
            <div class="step-circle" id="circle3">3</div>
            <div class="step-label">3. Nhận Tài khoản VA</div>
        </div>
    </div>

    <!-- Thông báo Alert chung -->
    <div class="alert" id="alertBox"></div>

    <!-- BƯỚC 1: NHẬP THÔNG TIN & PREVIEW -->
    <div class="card" id="step1Card">
        <div class="card-title">
            <span>[BƯỚC 1] Khởi tạo xác thực thông tin người nạp tiền</span>
        </div>
        <form id="step1Form">
            <div class="form-grid">
                <div class="form-group">
                    <label>Mã khách hàng (merchant_customer_id) <span class="req">*</span></label>
                    <input type="text" id="merchant_customer_id" value="CUST-KRW-001" required>
                </div>
                <div class="form-group">
                    <label>Số tiền nạp (KRW) <span class="req">*</span></label>
                    <input type="number" id="amount" value="150000" min="100001" required>
                    <span class="hint">Tối thiểu: 100,001 KRW trên Sandbox</span>
                </div>
                <div class="form-group">
                    <label>Tên tài khoản tiếng Anh (sender_account_name) <span class="req">*</span></label>
                    <input type="text" id="sender_account_name" value="Hong Gildong" required>
                </div>
                <div class="form-group">
                    <label>Tên tiếng Hàn (sender_name_kr) <span class="req">*</span></label>
                    <input type="text" id="sender_name_kr" value="홍길동" required>
                </div>
                <div class="form-group">
                    <label>Mã ngân hàng (sender_bank_code) <span class="req">*</span></label>
                    <select id="sender_bank_code">
                        <option value="KOOKMIN" selected>KOOKMIN (KB Kookmin Bank)</option>
                        <option value="SHINHAN">SHINHAN (Shinhan Bank)</option>
                        <option value="WOORI">WOORI (Woori Bank)</option>
                        <option value="HANA">HANA (KEB Hana Bank)</option>
                        <option value="IBK">IBK (Industrial Bank of Korea)</option>
                        <option value="NH">NH (Nonghyup Bank)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Số tài khoản ngân hàng (sender_account_number) <span class="req">*</span></label>
                    <input type="text" id="sender_account_number" value="11012345678" required>
                </div>
                <div class="form-group">
                    <label>Ngày sinh YYMMDD (sender_dob) <span class="req">*</span></label>
                    <input type="text" id="sender_dob" value="970101" maxlength="6" required>
                    <span class="hint">Định dạng 6 số: YYMMDD (ví dụ: 970101)</span>
                </div>
                <div class="form-group">
                    <label>Mã đơn hàng nội bộ (merchant_order_id)</label>
                    <input type="text" id="merchant_order_id" placeholder="Tự sinh nếu để trống">
                    <span class="hint">Bỏ trống sẽ tự sinh dạng ORD-KRW-DIR-{timestamp}</span>
                </div>
            </div>

            <div class="btn-group">
                <button type="button" class="btn-auto" id="btnAutoRun">⚡ Chạy tự động cả 3 bước</button>
                <button type="submit" class="btn-primary" id="btnStep1">Tiếp tục Bước 2 (Preview) &rarr;</button>
            </div>
        </form>
    </div>

    <!-- BƯỚC 2: NHẬP MÃ XÁC NHẬN (OTP) -->
    <div class="card" id="step2Card" style="display: none;">
        <div class="card-title">
            <span>[BƯỚC 2] Nhập mã xác nhận người nạp tiền (Verify Code)</span>
        </div>
        <form id="step2Form">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Verification ID (sinh từ Bước 1)</label>
                    <input type="text" id="displayVerificationId" readonly>
                </div>
                <div class="form-group">
                    <label>Mã xác thực (Verify Code) <span class="req">*</span></label>
                    <input type="text" id="verify_code" value="6666" required autofocus>
                    <span class="hint">Trên Sandbox luôn dùng mã mặc định: <strong>6666</strong></span>
                </div>
                <div class="form-group">
                    <label>Hạn xác thực (Expires At)</label>
                    <input type="text" id="displayExpiresAt" readonly>
                </div>
            </div>

            <div class="btn-group">
                <button type="button" class="btn-secondary" id="btnBackTo1">&larr; Quay lại Bước 1</button>
                <button type="submit" class="btn-primary" id="btnStep2">Xác nhận mã & Tạo đơn hàng &rarr;</button>
            </div>
        </form>
    </div>

    <!-- BƯỚC 3: KẾT QUẢ ĐƠN HÀNG & THÔNG TIN VA -->
    <div class="card" id="step3Card" style="display: none;">
        <div class="card-title">
            <span>🎉 [BƯỚC 3] Đơn nạp tiền đã tạo thành công!</span>
        </div>

        <div class="alert alert-success" style="display: block;">
            Đơn hàng đã được ghi nhận trên cổng thanh toán với trạng thái <strong>AWAITING_PAYMENT</strong>. Vui lòng hướng dẫn khách hàng chuyển đúng số tiền vào tài khoản ảo (VA) dưới đây:
        </div>

        <div class="va-card">
            <div class="va-header">
                <div class="va-title">THÔNG TIN TÀI KHOẢN ẢO (VIRTUAL ACCOUNT)</div>
                <div class="va-status" id="resStatus">AWAITING_PAYMENT</div>
            </div>

            <div class="va-details">
                <div class="va-field">
                    <span class="va-field-label">Ngân hàng thụ hưởng</span>
                    <span class="va-field-value" id="resBank">--</span>
                </div>
                <div class="va-field">
                    <span class="va-field-label">Loại tài khoản</span>
                    <span class="va-field-value" id="resKind">VA</span>
                </div>
                <div class="va-field">
                    <span class="va-field-label">Số tài khoản ảo (VA)</span>
                    <span class="va-field-value">
                        <span id="resAccount" style="color: #38bdf8; font-size: 18px;">--</span>
                        <button type="button" class="copy-btn" onclick="copyText('resAccount')">Copy STK</button>
                    </span>
                </div>
                <div class="va-field">
                    <span class="va-field-label">Tên chủ tài khoản</span>
                    <span class="va-field-value" id="resHolder">--</span>
                </div>
                <div class="va-field">
                    <span class="va-field-label">Số tiền cần chuyển</span>
                    <span class="va-field-value">
                        <span id="resAmount" style="color: #4ade80; font-size: 18px;">--</span> KRW
                        <button type="button" class="copy-btn" onclick="copyText('resAmountRaw')">Copy tiền</button>
                        <span id="resAmountRaw" style="display: none;"></span>
                    </span>
                </div>
                <div class="va-field">
                    <span class="va-field-label">Mã đơn cổng (order_ref)</span>
                    <span class="va-field-value" id="resOrderRef" style="color: #f1f5f9;">--</span>
                </div>
                <div class="va-field">
                    <span class="va-field-label">Mã đơn Merchant (order_id)</span>
                    <span class="va-field-value" id="resMerchantOrderId" style="color: #94a3b8; font-size: 13px;">--</span>
                </div>
            </div>
        </div>

        <details>
            <summary>Xem phản hồi JSON gốc từ HiPayFin API</summary>
            <pre class="json-view" id="rawJsonResponse"></pre>
        </details>

        <div class="btn-group">
            <button type="button" class="btn-primary" onclick="resetForm()">+ Tạo đơn nạp tiền khác</button>
        </div>
    </div>
</div>

<script>
let currentVerificationId = '';

function showAlert(message, type = 'error') {
    const box = document.getElementById('alertBox');
    box.className = 'alert alert-' + type;
    box.innerHTML = message;
    box.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function hideAlert() {
    document.getElementById('alertBox').style.display = 'none';
}

function setStep(step) {
    hideAlert();
    const bar = document.getElementById('progressBar');
    const c1 = document.getElementById('circle1');
    const c2 = document.getElementById('circle2');
    const c3 = document.getElementById('circle3');
    const item1 = document.getElementById('stepIndicator1');
    const item2 = document.getElementById('stepIndicator2');
    const item3 = document.getElementById('stepIndicator3');

    document.getElementById('step1Card').style.display = (step === 1) ? 'block' : 'none';
    document.getElementById('step2Card').style.display = (step === 2) ? 'block' : 'none';
    document.getElementById('step3Card').style.display = (step === 3) ? 'block' : 'none';

    if (step === 1) {
        bar.style.width = '0%';
        item1.className = 'step-item active';
        item2.className = 'step-item';
        item3.className = 'step-item';
        c1.innerHTML = '1';
        c2.innerHTML = '2';
        c3.innerHTML = '3';
    } else if (step === 2) {
        bar.style.width = '50%';
        item1.className = 'step-item completed';
        item2.className = 'step-item active';
        item3.className = 'step-item';
        c1.innerHTML = '✓';
        c2.innerHTML = '2';
        c3.innerHTML = '3';
    } else if (step === 3) {
        bar.style.width = '100%';
        item1.className = 'step-item completed';
        item2.className = 'step-item completed';
        item3.className = 'step-item completed';
        c1.innerHTML = '✓';
        c2.innerHTML = '✓';
        c3.innerHTML = '✓';
    }
}

// BƯỚC 1: Submit Form Preview
document.getElementById('step1Form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnStep1');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Đang gửi xác thực...';
    hideAlert();

    const formData = new FormData();
    formData.append('action', 'preview');
    formData.append('merchant_customer_id', document.getElementById('merchant_customer_id').value);
    formData.append('amount', document.getElementById('amount').value);
    formData.append('sender_account_name', document.getElementById('sender_account_name').value);
    formData.append('sender_name_kr', document.getElementById('sender_name_kr').value);
    formData.append('sender_bank_code', document.getElementById('sender_bank_code').value);
    formData.append('sender_account_number', document.getElementById('sender_account_number').value);
    formData.append('sender_dob', document.getElementById('sender_dob').value);

    try {
        const res = await fetch('payin_form.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (json.success) {
            currentVerificationId = json.data.verification_id;
            document.getElementById('displayVerificationId').value = currentVerificationId;
            document.getElementById('displayExpiresAt').value = json.data.expires_at || 'Không xác định';
            setStep(2);
            if (json.data.next_action === 'CREATE_ORDER') {
                showAlert('✓ Chủ tài khoản đã được phê duyệt sẵn (CREATE_ORDER)! Bạn có thể bấm tiếp tục để tạo đơn ngay hoặc giữ mã 6666.', 'success');
            } else {
                showAlert('✓ Khởi tạo xác thực thành công! Hãy nhập mã kiểm tra OTP (Sandbox: 6666) để tiếp tục.', 'info');
            }
        } else {
            let msg = 'Lỗi [' + (json.code || 'API') + ']: ' + json.message;
            if (json.meta && json.meta.details) {
                msg += '<br>' + json.meta.details.map(d => '• ' + (d.field ? d.field + ': ' : '') + d.message).join('<br>');
            }
            showAlert(msg, 'error');
        }
    } catch (err) {
        showAlert('Lỗi kết nối server: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Quay lại Bước 1
document.getElementById('btnBackTo1').addEventListener('click', function() {
    setStep(1);
});

// BƯỚC 2: Submit Form Xác nhận mã (Verify) & Tự động sang Bước 3 Tạo đơn
document.getElementById('step2Form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnStep2');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Đang xác nhận mã OTP...';
    hideAlert();

    const verifyCode = document.getElementById('verify_code').value;

    try {
        // Gọi xác nhận mã
        const confirmData = new FormData();
        confirmData.append('action', 'confirm');
        confirmData.append('verification_id', currentVerificationId);
        confirmData.append('verify_code', verifyCode);

        const confirmRes = await fetch('payin_form.php', { method: 'POST', body: confirmData });
        const confirmJson = await confirmRes.json();

        if (!confirmJson.success) {
            showAlert('Lỗi xác nhận mã: ' + confirmJson.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }

        // Tự động chuyển sang Bước 3: Tạo đơn hàng
        btn.innerHTML = '<span class="spinner"></span> Đang tạo đơn hàng nạp tiền...';

        const createData = new FormData();
        createData.append('action', 'create');
        createData.append('merchant_order_id', document.getElementById('merchant_order_id').value);
        createData.append('merchant_customer_id', document.getElementById('merchant_customer_id').value);
        createData.append('amount', document.getElementById('amount').value);
        createData.append('sender_account_name', document.getElementById('sender_account_name').value);
        createData.append('sender_name_kr', document.getElementById('sender_name_kr').value);
        createData.append('sender_bank_code', document.getElementById('sender_bank_code').value);
        createData.append('sender_account_number', document.getElementById('sender_account_number').value);
        createData.append('sender_dob', document.getElementById('sender_dob').value);
        createData.append('verification_id', currentVerificationId);

        const createRes = await fetch('payin_form.php', { method: 'POST', body: createData });
        const createJson = await createRes.json();

        if (createJson.success) {
            renderOrderSuccess(createJson.data);
            setStep(3);
        } else {
            let msg = 'Lỗi tạo đơn: ' + createJson.message;
            if (createJson.meta && createJson.meta.details) {
                msg += '<br>' + createJson.meta.details.map(d => '• ' + d.message).join('<br>');
            }
            showAlert(msg, 'error');
        }

    } catch (err) {
        showAlert('Lỗi kết nối server: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Chạy tự động cả 3 bước (1-click auto test)
document.getElementById('btnAutoRun').addEventListener('click', async function() {
    const btn = document.getElementById('btnAutoRun');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Đang chạy cả 3 bước...';
    hideAlert();

    try {
        // Bước 1: Preview
        const formData1 = new FormData();
        formData1.append('action', 'preview');
        formData1.append('merchant_customer_id', document.getElementById('merchant_customer_id').value);
        formData1.append('amount', document.getElementById('amount').value);
        formData1.append('sender_account_name', document.getElementById('sender_account_name').value);
        formData1.append('sender_name_kr', document.getElementById('sender_name_kr').value);
        formData1.append('sender_bank_code', document.getElementById('sender_bank_code').value);
        formData1.append('sender_account_number', document.getElementById('sender_account_number').value);
        formData1.append('sender_dob', document.getElementById('sender_dob').value);

        const res1 = await fetch('payin_form.php', { method: 'POST', body: formData1 });
        const json1 = await res1.json();
        if (!json1.success) throw new Error('Bước 1 thất bại: ' + json1.message);

        currentVerificationId = json1.data.verification_id;

        // Bước 2: Confirm mã 6666
        const formData2 = new FormData();
        formData2.append('action', 'confirm');
        formData2.append('verification_id', currentVerificationId);
        formData2.append('verify_code', '6666');

        const res2 = await fetch('payin_form.php', { method: 'POST', body: formData2 });
        const json2 = await res2.json();
        if (!json2.success) throw new Error('Bước 2 thất bại: ' + json2.message);

        // Bước 3: Create Order
        const formData3 = new FormData();
        formData3.append('action', 'create');
        formData3.append('merchant_order_id', document.getElementById('merchant_order_id').value);
        formData3.append('merchant_customer_id', document.getElementById('merchant_customer_id').value);
        formData3.append('amount', document.getElementById('amount').value);
        formData3.append('sender_account_name', document.getElementById('sender_account_name').value);
        formData3.append('sender_name_kr', document.getElementById('sender_name_kr').value);
        formData3.append('sender_bank_code', document.getElementById('sender_bank_code').value);
        formData3.append('sender_account_number', document.getElementById('sender_account_number').value);
        formData3.append('sender_dob', document.getElementById('sender_dob').value);
        formData3.append('verification_id', currentVerificationId);

        const res3 = await fetch('payin_form.php', { method: 'POST', body: formData3 });
        const json3 = await res3.json();
        if (!json3.success) throw new Error('Bước 3 thất bại: ' + json3.message);

        renderOrderSuccess(json3.data);
        setStep(3);

    } catch (err) {
        showAlert(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

function renderOrderSuccess(data) {
    document.getElementById('resStatus').innerText = data.status || 'AWAITING_PAYMENT';
    document.getElementById('resOrderRef').innerText = data.order_ref || '--';
    document.getElementById('resMerchantOrderId').innerText = data.merchant_order_id || '--';

    const amt = document.getElementById('amount').value;
    document.getElementById('resAmount').innerText = Number(amt).toLocaleString();
    document.getElementById('resAmountRaw').innerText = amt;

    if (data.instruction) {
        document.getElementById('resBank').innerText = data.instruction.bank || 'HSBC bank';
        document.getElementById('resKind').innerText = data.instruction.kind || 'VA';
        document.getElementById('resAccount').innerText = data.instruction.account || '--';
        document.getElementById('resHolder').innerText = data.instruction.holder || '--';
    }

    document.getElementById('rawJsonResponse').innerText = JSON.stringify(data, null, 2);
}

function copyText(elementId) {
    const text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('Đã copy: ' + text);
    }).catch(err => {
        console.error('Không thể copy', err);
    });
}

function resetForm() {
    currentVerificationId = '';
    document.getElementById('merchant_order_id').value = '';
    setStep(1);
}
</script>

</body>
</html>

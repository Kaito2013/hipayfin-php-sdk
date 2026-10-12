# HiPayFin PHP SDK Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use supercoders:subagent-driven-development (recommended) or supercoders:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a zero-dependency, PHP 5.6+ compatible PHP SDK for the HiPayFin Merchant Payment Gateway, supporting PSR-4 autoloading, standalone fallback autoloader, native cURL HTTP transport, HMAC-SHA256 request signing, secure webhook verification, and complete Payin/Payout/Order/Wallet services with pre-configured Sandbox credentials.

**Architecture:** Lightweight modular service-based architecture (`Lucas\HiPayFin\Client` as entry point, delegating to `PayinService`, `PayoutService`, `OrderService`, `WalletService`, `WebhookService`). HTTP communication is decoupled via `HttpClientInterface` and implemented via `CurlHttpClient`. Request signing and webhook verification are encapsulated in `HmacSigner`. Data formats and minor units are managed via `Currency` and `Uuid` support helpers.

**Tech Stack:** PHP >= 5.6.0 (compatible with 7.x and 8.x), PHP cURL extension, PHP Hash extension, PHP JSON extension, Composer (PSR-4), PHPUnit.

**Spec:** [supercoders/specs/2026-10-12-hipayfin-php-sdk-design.md](file:///Users/hoaiminh/Sites/localhost/hipayfin/supercoders/specs/2026-10-12-hipayfin-php-sdk-design.md)

## Global Constraints

- PHP Version: PHP >= 5.6.0 strict compatibility (no scalar param types `string $a`, no return types `: void`, no `??` operator, no typed properties).
- Root Namespace: `Lucas\HiPayFin\`.
- Network Transport: Pure cURL (`ext-curl`), zero third-party runtime HTTP dependencies.
- Autoloading: Both `composer.json` (PSR-4) and `autoload.php` (standalone fallback autoloader).
- Sandbox Credentials:
  - Base URL: `https://api-sandbox.hipayfin.com/api/v1`
  - API Key: `hk_live_VbdBEm3for1GBYzuke6kYl0BHpx9R3afH_yK`
  - HMAC Secret: `35b0091558ae05393e31643b7da158fefbc5397a6eaa037054bcd0b5aff045de`
- Amount Handling: Minor unit integer strings matching regex `^[1-9][0-9]*$` (VND/KRW: 0 decimals; USDT: 6 decimals).
- Request Signing: HMAC-SHA256 format `sha256=<hex>`, payload `timestamp . "." . rawBody` (GET has trailing dot, POST has unescaped raw JSON).
- Webhook Security: Timestamp drift check `abs(time() - $ts) <= 300` and constant-time string comparison `hash_equals`.

## Review Focus

1. **GET Request signature string format:** GET requests have no body, so signing string MUST end with a trailing dot (`timestamp . "."`). A missing dot causes `401 SIGNATURE_INVALID`.
2. **USDT minor unit precision:** Floating point math like `1.5 * 1000000` or `19.99 * 1000000` can produce float imprecision. Must use string manipulation or `bcmul` to guarantee exact integer string.
3. **Empty string optional fields in POST:** HiPayFin API rejects optional fields with empty strings (e.g. `"customer_name": ""`) with `400 VALIDATION_ERROR`. Omitted or null fields must be stripped from the JSON payload.
4. **Idempotency conflict handling (HTTP 409):** On `409 DUPLICATE_REQUEST`, the SDK's `HiPayFinApiException` must expose `getExistingId()` extracting `meta.existing_id` (`order_ref` or `session_token`).
5. **Webhook timestamp replay window:** Webhooks with timestamp older than 300 seconds (or more than 300 seconds in future) must be rejected to prevent replay attacks.

---

### Task 1: Project Scaffolding, Composer & PSR-4 Standalone Autoloader

**Files:**
- Create: `composer.json`
- Create: `autoload.php`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Create: `tests/run_tests.php`
- Test: `tests/AutoloadTest.php`

**Interfaces:**
- Consumes: None
- Produces: PSR-4 autoloader mapping `Lucas\HiPayFin\` to `src/`, standalone test runner.

- [ ] **Step 1: Write the failing test for autoloader**

File: `tests/AutoloadTest.php`
```php
<?php
namespace Lucas\HiPayFin\Tests;

class AutoloadTest
{
    public function testAutoloadCanLoadClasses()
    {
        // Require standalone autoloader
        require_once __DIR__ . '/../autoload.php';
        
        $classExists = class_exists('Lucas\\HiPayFin\\Config');
        if (!$classExists) {
            throw new \Exception('Failed to autoload Lucas\\HiPayFin\\Config class');
        }
        return true;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -r "require_once 'tests/AutoloadTest.php'; (new Lucas\HiPayFin\Tests\AutoloadTest())->testAutoloadCanLoadClasses();"`
Expected: FAIL with "Fatal error: require_once(autoload.php): failed to open stream" or "Failed to autoload".

- [ ] **Step 3: Write minimal implementation**

Create `composer.json`:
```json
{
  "name": "lucas/hipayfin-php-sdk",
  "description": "HiPayFin Merchant Payment Gateway PHP SDK for PHP 5.6+",
  "type": "library",
  "license": "MIT",
  "authors": [
    {
      "name": "Lucas Dev",
      "homepage": "https://github.com/lucas"
    }
  ],
  "require": {
    "php": ">=5.6.0",
    "ext-curl": "*",
    "ext-json": "*",
    "ext-hash": "*"
  },
  "require-dev": {
    "phpunit/phpunit": "^5.7 || ^9.0"
  },
  "autoload": {
    "psr-4": {
      "Lucas\\HiPayFin\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Lucas\\HiPayFin\\Tests\\": "tests/"
    }
  }
}
```

Create `autoload.php`:
```php
<?php
/**
 * Standalone PSR-4 Autoloader for HiPayFin PHP SDK
 * Suitable for environments without Composer (PHP 5.6+).
 */
spl_autoload_register(function ($class) {
    $prefix = 'Lucas\\HiPayFin\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
```

Create placeholder `src/Config.php`:
```php
<?php
namespace Lucas\HiPayFin;

class Config
{
}
```

Create `tests/bootstrap.php`:
```php
<?php
require_once __DIR__ . '/../autoload.php';

// Autoload test classes
spl_autoload_register(function ($class) {
    $prefix = 'Lucas\\HiPayFin\\Tests\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
```

Create `tests/run_tests.php` (zero-dependency standalone test runner):
```php
<?php
require_once __DIR__ . '/bootstrap.php';

$testFiles = glob(__DIR__ . '/*Test.php');
$total = 0;
$passed = 0;
$failed = 0;

echo "Running HiPayFin SDK Test Suite...\n\n";

foreach ($testFiles as $file) {
    $className = 'Lucas\\HiPayFin\\Tests\\' . basename($file, '.php');
    if (!class_exists($className)) {
        require_once $file;
    }
    if (!class_exists($className)) {
        continue;
    }

    $reflection = new ReflectionClass($className);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    $instance = new $className();

    foreach ($methods as $method) {
        if (strpos($method->name, 'test') === 0) {
            $total++;
            try {
                $method->invoke($instance);
                echo "  [PASS] {$className}::{$method->name}\n";
                $passed++;
            } catch (Exception $e) {
                echo "  [FAIL] {$className}::{$method->name} - " . $e->getMessage() . "\n";
                $failed++;
            }
        }
    }
}

echo "\nTests: {$total}, Passed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
```

Create `phpunit.xml.dist`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="HiPayFin SDK Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 1, Passed: 1, Failed: 0` with exit code 0.

- [ ] **Step 5: Commit**

```bash
git add composer.json autoload.php phpunit.xml.dist tests/ src/Config.php
git commit -m "chore: scaffold project, PSR-4 standalone autoloader and test runner"
```

---

### Task 2: Exception Hierarchy

**Files:**
- Create: `src/Exceptions/HiPayFinException.php`
- Create: `src/Exceptions/HiPayFinApiException.php`
- Create: `src/Exceptions/HiPayFinNetworkException.php`
- Create: `src/Exceptions/HiPayFinValidationException.php`
- Create: `src/Exceptions/HiPayFinSignatureException.php`
- Test: `tests/ExceptionTest.php`

**Interfaces:**
- Consumes: `\Exception`
- Produces: `HiPayFinException`, `HiPayFinApiException` (`getHttpStatusCode()`, `getErrorCode()`, `getMeta()`, `getExistingId()`), `HiPayFinNetworkException`, `HiPayFinValidationException`, `HiPayFinSignatureException`.

- [ ] **Step 1: Write the failing test**

File: `tests/ExceptionTest.php`
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Exceptions\HiPayFinException;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;
use Lucas\HiPayFin\Exceptions\HiPayFinNetworkException;
use Lucas\HiPayFin\Exceptions\HiPayFinValidationException;
use Lucas\HiPayFin\Exceptions\HiPayFinSignatureException;

class ExceptionTest
{
    public function testApiExceptionEnvelopeData()
    {
        $meta = array('existing_id' => 'PI-20261012001', 'details' => array(array('field' => 'amount')));
        $e = new HiPayFinApiException('Order duplicated', 409, 'DUPLICATE_REQUEST', $meta);

        if (!($e instanceof HiPayFinException)) {
            throw new \Exception('HiPayFinApiException must extend HiPayFinException');
        }
        if ($e->getHttpStatusCode() !== 409) {
            throw new \Exception('getHttpStatusCode failed');
        }
        if ($e->getErrorCode() !== 'DUPLICATE_REQUEST') {
            throw new \Exception('getErrorCode failed');
        }
        if ($e->getExistingId() !== 'PI-20261012001') {
            throw new \Exception('getExistingId failed');
        }
        if (!is_array($e->getMeta()) || count($e->getMeta()['details']) !== 1) {
            throw new \Exception('getMeta failed');
        }
        return true;
    }

    public function testNetworkException()
    {
        $e = new HiPayFinNetworkException('Connection timed out', 28);
        if ($e->getCurlErrorCode() !== 28) {
            throw new \Exception('getCurlErrorCode failed');
        }
        return true;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Class Lucas\HiPayFin\Exceptions\HiPayFinException not found"

- [ ] **Step 3: Write minimal implementation**

Create `src/Exceptions/HiPayFinException.php`:
```php
<?php
namespace Lucas\HiPayFin\Exceptions;

class HiPayFinException extends \Exception
{
}
```

Create `src/Exceptions/HiPayFinApiException.php`:
```php
<?php
namespace Lucas\HiPayFin\Exceptions;

class HiPayFinApiException extends HiPayFinException
{
    protected $httpStatusCode;
    protected $errorCode;
    protected $meta;

    public function __construct($message, $httpStatusCode = 0, $errorCode = '', array $meta = array(), \Exception $previous = null)
    {
        parent::__construct($message, $httpStatusCode, $previous);
        $this->httpStatusCode = (int)$httpStatusCode;
        $this->errorCode = (string)$errorCode;
        $this->meta = $meta;
    }

    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getMeta()
    {
        return $this->meta;
    }

    public function getExistingId()
    {
        if (isset($this->meta['existing_id'])) {
            return (string)$this->meta['existing_id'];
        }
        return null;
    }
}
```

Create `src/Exceptions/HiPayFinNetworkException.php`:
```php
<?php
namespace Lucas\HiPayFin\Exceptions;

class HiPayFinNetworkException extends HiPayFinException
{
    protected $curlErrorCode;

    public function __construct($message, $curlErrorCode = 0, \Exception $previous = null)
    {
        parent::__construct($message, (int)$curlErrorCode, $previous);
        $this->curlErrorCode = (int)$curlErrorCode;
    }

    public function getCurlErrorCode()
    {
        return $this->curlErrorCode;
    }
}
```

Create `src/Exceptions/HiPayFinValidationException.php`:
```php
<?php
namespace Lucas\HiPayFin\Exceptions;

class HiPayFinValidationException extends HiPayFinException
{
    protected $field;

    public function __construct($message, $field = '', $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, (int)$code, $previous);
        $this->field = (string)$field;
    }

    public function getField()
    {
        return $this->field;
    }
}
```

Create `src/Exceptions/HiPayFinSignatureException.php`:
```php
<?php
namespace Lucas\HiPayFin\Exceptions;

class HiPayFinSignatureException extends HiPayFinException
{
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 3, Passed: 3, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Exceptions/ tests/ExceptionTest.php
git commit -m "feat: implement HiPayFin exception hierarchy"
```

---

### Task 3: Support Helpers (`Currency` & `Uuid`)

**Files:**
- Create: `src/Support/Currency.php`
- Create: `src/Support/Uuid.php`
- Test: `tests/CurrencyTest.php`
- Test: `tests/UuidTest.php`

**Interfaces:**
- Consumes: None
- Produces:
  - `Currency::toMinorUnit($amount, $currency)`: returns integer string
  - `Currency::fromMinorUnit($minorAmount, $currency)`: returns human string
  - `Currency::validateMinorUnit($minorAmount)`: returns bool
  - `Uuid::v4()`: returns UUIDv4 string

- [ ] **Step 1: Write the failing tests**

File: `tests/CurrencyTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Support\Currency;

class CurrencyTest
{
    public function testVndAndKrwMinorUnit()
    {
        // 0 decimals
        if (Currency::toMinorUnit(150000, 'VND') !== '150000') {
            throw new \Exception('VND integer conversion failed');
        }
        if (Currency::toMinorUnit('250000', 'vnd') !== '250000') {
            throw new \Exception('VND string conversion failed');
        }
        if (Currency::toMinorUnit(50000, 'KRW') !== '50000') {
            throw new \Exception('KRW conversion failed');
        }
        return true;
    }

    public function testUsdtMinorUnitPrecision()
    {
        // 6 decimals: 10 USDT -> 10000000
        if (Currency::toMinorUnit(10, 'USDT') !== '10000000') {
            throw new \Exception('USDT 10 conversion failed');
        }
        // 1.5 USDT -> 1500000
        if (Currency::toMinorUnit('1.5', 'usdt') !== '1500000') {
            throw new \Exception('USDT 1.5 conversion failed');
        }
        // 0.000001 USDT -> 1
        if (Currency::toMinorUnit('0.000001', 'USDT') !== '1') {
            throw new \Exception('USDT 0.000001 conversion failed');
        }
        // 19.99 USDT -> 19990000
        if (Currency::toMinorUnit('19.99', 'USDT') !== '19990000') {
            throw new \Exception('USDT 19.99 conversion failed');
        }
        return true;
    }

    public function testValidateMinorUnit()
    {
        if (!Currency::validateMinorUnit('150000')) {
            throw new \Exception('Valid minor unit rejected');
        }
        if (Currency::validateMinorUnit('0150000')) {
            throw new \Exception('Leading zero should be invalid');
        }
        if (Currency::validateMinorUnit('150.50')) {
            throw new \Exception('Decimal point should be invalid');
        }
        if (Currency::validateMinorUnit('0')) {
            throw new \Exception('Zero should be invalid');
        }
        if (Currency::validateMinorUnit('-100')) {
            throw new \Exception('Negative should be invalid');
        }
        return true;
    }
}
```

File: `tests/UuidTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Support\Uuid;

class UuidTest
{
    public function testGenerateV4Uuid()
    {
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        if (!preg_match($pattern, $uuid1)) {
            throw new \Exception("UUID1 {$uuid1} is not valid RFC 4122 v4");
        }
        if (!preg_match($pattern, $uuid2)) {
            throw new \Exception("UUID2 {$uuid2} is not valid RFC 4122 v4");
        }
        if ($uuid1 === $uuid2) {
            throw new \Exception('UUIDs must be unique');
        }
        return true;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Class Lucas\HiPayFin\Support\Currency not found"

- [ ] **Step 3: Write minimal implementation**

Create `src/Support/Currency.php`:
```php
<?php
namespace Lucas\HiPayFin\Support;

use Lucas\HiPayFin\Exceptions\HiPayFinValidationException;

class Currency
{
    const CURRENCY_VND  = 'VND';
    const CURRENCY_KRW  = 'KRW';
    const CURRENCY_USDT = 'USDT';

    /**
     * Convert human readable amount to integer string Minor Unit.
     *
     * @param int|float|string $amount
     * @param string $currency
     * @return string
     * @throws HiPayFinValidationException
     */
    public static function toMinorUnit($amount, $currency)
    {
        $currency = strtoupper(trim((string)$currency));

        if ($currency === self::CURRENCY_VND || $currency === self::CURRENCY_KRW) {
            $cleaned = preg_replace('/[^0-9]/', '', (string)$amount);
            $cleaned = ltrim($cleaned, '0');
            if ($cleaned === '') {
                throw new HiPayFinValidationException("Invalid {$currency} amount: {$amount}", 'amount');
            }
            return $cleaned;
        }

        if ($currency === self::CURRENCY_USDT) {
            $amountStr = trim((string)$amount);
            if (!preg_match('/^[0-9]+(\.[0-9]{1,6})?$/', $amountStr)) {
                throw new HiPayFinValidationException("Invalid USDT amount: {$amount}", 'amount');
            }

            $parts = explode('.', $amountStr);
            $integerPart = $parts[0];
            $decimalPart = isset($parts[1]) ? $parts[1] : '';
            $decimalPart = str_pad($decimalPart, 6, '0', STR_PAD_RIGHT);

            $result = ltrim($integerPart . $decimalPart, '0');
            if ($result === '') {
                throw new HiPayFinValidationException("USDT amount must be greater than 0", 'amount');
            }
            return $result;
        }

        throw new HiPayFinValidationException("Unsupported currency: {$currency}", 'currency');
    }

    /**
     * Validate whether a string is a valid Minor Unit integer string.
     *
     * @param string $minorAmount
     * @return bool
     */
    public static function validateMinorUnit($minorAmount)
    {
        return (bool)preg_match('/^[1-9][0-9]*$/', (string)$minorAmount);
    }
}
```

Create `src/Support/Uuid.php`:
```php
<?php
namespace Lucas\HiPayFin\Support;

class Uuid
{
    /**
     * Generate RFC 4122 compliant UUID version 4.
     *
     * @return string
     */
    public static function v4()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $data = openssl_random_pseudo_bytes(16);
        } else {
            $data = '';
            for ($i = 0; $i < 16; $i++) {
                $data .= chr(mt_rand(0, 255));
            }
        }

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100 (v4)
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 7, Passed: 7, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Support/ tests/CurrencyTest.php tests/UuidTest.php
git commit -m "feat: implement Currency minor unit converter and Uuid v4 helper"
```

---

### Task 4: Configuration Container (`Config`)

**Files:**
- Modify: `src/Config.php`
- Test: `tests/ConfigTest.php`

**Interfaces:**
- Consumes: `HiPayFinValidationException`
- Produces: `Config` with getters: `getApiKey()`, `getHmacSecret()`, `getWebhookSecret()`, `getEnvironment()`, `getBaseUrl()`, `getTimeout()`, `getConnectTimeout()`.

- [ ] **Step 1: Write the failing test**

File: `tests/ConfigTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Config;

class ConfigTest
{
    public function testDefaultSandboxConfiguration()
    {
        $config = new Config(array(
            'api_key'     => 'test_key',
            'hmac_secret' => 'test_secret',
        ));

        if ($config->getApiKey() !== 'test_key') {
            throw new \Exception('getApiKey failed');
        }
        if ($config->getHmacSecret() !== 'test_secret') {
            throw new \Exception('getHmacSecret failed');
        }
        if ($config->getEnvironment() !== Config::ENV_SANDBOX) {
            throw new \Exception('default environment must be sandbox');
        }
        if ($config->getBaseUrl() !== 'https://api-sandbox.hipayfin.com/api/v1') {
            throw new \Exception('sandbox base url incorrect');
        }
        if ($config->getTimeout() !== 30) {
            throw new \Exception('default timeout must be 30');
        }
        return true;
    }

    public function testProductionConfiguration()
    {
        $config = new Config(array(
            'api_key'     => 'prod_key',
            'hmac_secret' => 'prod_secret',
            'environment' => Config::ENV_PRODUCTION,
            'timeout'     => 45,
        ));

        if ($config->getBaseUrl() !== 'https://api.hipayfin.com/api/v1') {
            throw new \Exception('production base url incorrect');
        }
        if ($config->getTimeout() !== 45) {
            throw new \Exception('timeout override failed');
        }
        return true;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Call to undefined method Lucas\HiPayFin\Config::getApiKey()"

- [ ] **Step 3: Write minimal implementation**

Update `src/Config.php`:
```php
<?php
namespace Lucas\HiPayFin;

use Lucas\HiPayFin\Exceptions\HiPayFinValidationException;

class Config
{
    const ENV_SANDBOX    = 'sandbox';
    const ENV_PRODUCTION = 'production';

    const BASE_URL_SANDBOX    = 'https://api-sandbox.hipayfin.com/api/v1';
    const BASE_URL_PRODUCTION = 'https://api.hipayfin.com/api/v1';

    protected $apiKey;
    protected $hmacSecret;
    protected $webhookSecret;
    protected $environment;
    protected $baseUrl;
    protected $timeout;
    protected $connectTimeout;

    public function __construct(array $options = array())
    {
        if (empty($options['api_key'])) {
            throw new HiPayFinValidationException('api_key is required in Config', 'api_key');
        }
        if (empty($options['hmac_secret'])) {
            throw new HiPayFinValidationException('hmac_secret is required in Config', 'hmac_secret');
        }

        $this->apiKey = (string)$options['api_key'];
        $this->hmacSecret = (string)$options['hmac_secret'];
        $this->webhookSecret = isset($options['webhook_secret']) ? (string)$options['webhook_secret'] : '';

        $env = isset($options['environment']) ? strtolower($options['environment']) : self::ENV_SANDBOX;
        $this->environment = ($env === self::ENV_PRODUCTION) ? self::ENV_PRODUCTION : self::ENV_SANDBOX;

        if (isset($options['base_url']) && !empty($options['base_url'])) {
            $this->baseUrl = rtrim((string)$options['base_url'], '/');
        } else {
            $this->baseUrl = ($this->environment === self::ENV_PRODUCTION)
                ? self::BASE_URL_PRODUCTION
                : self::BASE_URL_SANDBOX;
        }

        $this->timeout = isset($options['timeout']) ? (int)$options['timeout'] : 30;
        $this->connectTimeout = isset($options['connect_timeout']) ? (int)$options['connect_timeout'] : 10;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function getHmacSecret()
    {
        return $this->hmacSecret;
    }

    public function getWebhookSecret()
    {
        return $this->webhookSecret;
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function getConnectTimeout()
    {
        return $this->connectTimeout;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 9, Passed: 9, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Config.php tests/ConfigTest.php
git commit -m "feat: implement Config container with sandbox and production settings"
```

---

### Task 5: HMAC Request Signing & Webhook Verification (`HmacSigner`)

**Files:**
- Create: `src/Signature/HmacSigner.php`
- Test: `tests/HmacSignerTest.php`

**Interfaces:**
- Consumes: `Config`
- Produces:
  - `HmacSigner::buildSignedHeaders($apiKey, $hmacSecret, $method, $body = null, $idempotencyKey = null, $timestamp = null)`
  - `HmacSigner::verifyWebhook($rawBody, $receivedSignature, $timestamp, $webhookSecret, $toleranceSeconds = 300)`

- [ ] **Step 1: Write the failing test**

File: `tests/HmacSignerTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Signature\HmacSigner;

class HmacSignerTest
{
    public function testGetRequestSigningHasTrailingDot()
    {
        $apiKey = 'test_api_key';
        $secret = 'test_secret';
        $timestamp = '1728719200';

        // GET payload to sign is: 1728719200.
        $res = HmacSigner::buildSignedHeaders($apiKey, $secret, 'GET', null, null, $timestamp);
        $headers = $res['headers'];

        $expectedSig = 'sha256=' . hash_hmac('sha256', '1728719200.', $secret);

        if ($headers['X-Api-Key'] !== $apiKey) {
            throw new \Exception('X-Api-Key mismatch');
        }
        if ($headers['X-Timestamp'] !== '1728719200') {
            throw new \Exception('X-Timestamp mismatch');
        }
        if ($headers['X-Signature'] !== $expectedSig) {
            throw new \Exception("GET signature mismatch. Got {$headers['X-Signature']}, expected {$expectedSig}");
        }
        return true;
    }

    public function testPostRequestSigningUsesRawJson()
    {
        $apiKey = 'test_api_key';
        $secret = 'test_secret';
        $timestamp = '1728719200';
        $body = array('amount' => '150000', 'merchant_order_id' => 'ORD-001');

        $res = HmacSigner::buildSignedHeaders($apiKey, $secret, 'POST', $body, 'uuid-1234', $timestamp);
        $headers = $res['headers'];
        $rawBody = $res['bodyString'];

        $expectedPayload = '1728719200.' . $rawBody;
        $expectedSig = 'sha256=' . hash_hmac('sha256', $expectedPayload, $secret);

        if ($headers['X-Signature'] !== $expectedSig) {
            throw new \Exception('POST signature mismatch');
        }
        if ($headers['X-Idempotency-Key'] !== 'uuid-1234') {
            throw new \Exception('X-Idempotency-Key missing');
        }
        if ($headers['Content-Type'] !== 'application/json') {
            throw new \Exception('Content-Type must be application/json');
        }
        return true;
    }

    public function testWebhookSignatureVerification()
    {
        $secret = 'secret_webhook_123';
        $timestamp = (string)time();
        $rawBody = '{"event":"payment.confirmed","amount":"100000"}';

        $validSig = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        if (!HmacSigner::verifyWebhook($rawBody, $validSig, $timestamp, $secret)) {
            throw new \Exception('Valid webhook signature was rejected');
        }

        // Invalid signature
        if (HmacSigner::verifyWebhook($rawBody, 'sha256=invalidhash', $timestamp, $secret)) {
            throw new \Exception('Invalid webhook signature was accepted');
        }

        // Replay attack: old timestamp (> 300s)
        $oldTimestamp = (string)(time() - 400);
        $oldSig = 'sha256=' . hash_hmac('sha256', $oldTimestamp . '.' . $rawBody, $secret);
        if (HmacSigner::verifyWebhook($rawBody, $oldSig, $oldTimestamp, $secret)) {
            throw new \Exception('Expired webhook signature should be rejected');
        }
        return true;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Class Lucas\HiPayFin\Signature\HmacSigner not found"

- [ ] **Step 3: Write minimal implementation**

Create `src/Signature/HmacSigner.php`:
```php
<?php
namespace Lucas\HiPayFin\Signature;

class HmacSigner
{
    /**
     * Build signed headers and normalized raw body for HiPayFin API.
     *
     * @param string $apiKey
     * @param string $hmacSecret
     * @param string $method
     * @param mixed $body
     * @param string|null $idempotencyKey
     * @param string|null $timestamp
     * @return array array('headers' => array, 'bodyString' => string)
     */
    public static function buildSignedHeaders($apiKey, $hmacSecret, $method, $body = null, $idempotencyKey = null, $timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = (string)time();
        } else {
            $timestamp = (string)$timestamp;
        }

        $bodyString = '';
        if (strtoupper($method) !== 'GET' && $body !== null) {
            $bodyString = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        $payloadToSign = $timestamp . '.' . $bodyString;
        $sigHex = hash_hmac('sha256', $payloadToSign, (string)$hmacSecret);

        $headers = array(
            'X-Api-Key'    => (string)$apiKey,
            'X-Timestamp'  => $timestamp,
            'X-Signature'  => 'sha256=' . strtolower($sigHex),
            'Content-Type' => 'application/json',
        );

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['X-Idempotency-Key'] = (string)$idempotencyKey;
        }

        return array(
            'headers'    => $headers,
            'bodyString' => $bodyString,
        );
    }

    /**
     * Verify incoming Webhook signature.
     *
     * @param string $rawBody
     * @param string $receivedSignature
     * @param string $timestamp
     * @param string $webhookSecret
     * @param int $toleranceSeconds
     * @return bool
     */
    public static function verifyWebhook($rawBody, $receivedSignature, $timestamp, $webhookSecret, $toleranceSeconds = 300)
    {
        if (empty($timestamp) || empty($receivedSignature) || empty($webhookSecret)) {
            return false;
        }

        // Anti-replay check
        $currentTime = time();
        $eventTime = (int)$timestamp;
        if (abs($currentTime - $eventTime) > $toleranceSeconds) {
            return false;
        }

        $payloadToSign = $timestamp . '.' . (string)$rawBody;
        $expectedHex = hash_hmac('sha256', $payloadToSign, (string)$webhookSecret);
        $expectedSignature = 'sha256=' . strtolower($expectedHex);

        if (function_exists('hash_equals')) {
            return hash_equals($expectedSignature, $receivedSignature);
        }

        // Constant-time fallback for PHP < 5.6
        if (strlen($expectedSignature) !== strlen($receivedSignature)) {
            return false;
        }
        $res = 0;
        $len = strlen($expectedSignature);
        for ($i = 0; $i < $len; $i++) {
            $res |= ord($expectedSignature[$i]) ^ ord($receivedSignature[$i]);
        }
        return $res === 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 12, Passed: 12, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Signature/ tests/HmacSignerTest.php
git commit -m "feat: implement HMAC-SHA256 request signer and webhook verifier"
```

---

### Task 6: HTTP Transport Layer (`CurlHttpClient`, `HttpClientInterface`, `Response`)

**Files:**
- Create: `src/Http/HttpClientInterface.php`
- Create: `src/Http/Response.php`
- Create: `src/Http/CurlHttpClient.php`
- Test: `tests/CurlHttpClientTest.php`

**Interfaces:**
- Consumes: `Config`, `HmacSigner`, `HiPayFinApiException`, `HiPayFinNetworkException`
- Produces:
  - `HttpClientInterface::request($method, $uri, array $data = null, array $headers = array(), $idempotencyKey = null)`
  - `Response::getStatusCode()`, `Response::getBody()`, `Response::getData()`

- [ ] **Step 1: Write the failing test**

File: `tests/CurlHttpClientTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Config;
use Lucas\HiPayFin\Http\Response;

class CurlHttpClientTest
{
    public function testResponseParsingEnvelope()
    {
        $successJson = '{"data":{"order_ref":"PI-123","status":"CREATED"},"error":null}';
        $res = new Response(200, $successJson, array());

        if ($res->getStatusCode() !== 200) {
            throw new \Exception('getStatusCode failed');
        }
        $data = $res->getData();
        if ($data['order_ref'] !== 'PI-123') {
            throw new \Exception('data extraction failed');
        }
        return true;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Class Lucas\HiPayFin\Http\Response not found"

- [ ] **Step 3: Write minimal implementation**

Create `src/Http/HttpClientInterface.php`:
```php
<?php
namespace Lucas\HiPayFin\Http;

interface HttpClientInterface
{
    /**
     * Send HTTP request to HiPayFin API.
     *
     * @param string $method GET, POST
     * @param string $path relative path e.g. /payin/vnd/create
     * @param array|null $data
     * @param string|null $idempotencyKey
     * @return array The unwrapped 'data' array on success
     * @throws \Lucas\HiPayFin\Exceptions\HiPayFinApiException
     * @throws \Lucas\HiPayFin\Exceptions\HiPayFinNetworkException
     */
    public function request($method, $path, array $data = null, $idempotencyKey = null);
}
```

Create `src/Http/Response.php`:
```php
<?php
namespace Lucas\HiPayFin\Http;

class Response
{
    protected $statusCode;
    protected $rawBody;
    protected $headers;
    protected $decodedJson;

    public function __construct($statusCode, $rawBody, array $headers = array())
    {
        $this->statusCode = (int)$statusCode;
        $this->rawBody = (string)$rawBody;
        $this->headers = $headers;
        $this->decodedJson = json_decode($this->rawBody, true);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getRawBody()
    {
        return $this->rawBody;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getDecodedJson()
    {
        return $this->decodedJson;
    }

    public function getData()
    {
        if (is_array($this->decodedJson) && array_key_exists('data', $this->decodedJson)) {
            return $this->decodedJson['data'];
        }
        return null;
    }

    public function getError()
    {
        if (is_array($this->decodedJson) && array_key_exists('error', $this->decodedJson)) {
            return $this->decodedJson['error'];
        }
        return null;
    }
}
```

Create `src/Http/CurlHttpClient.php`:
```php
<?php
namespace Lucas\HiPayFin\Http;

use Lucas\HiPayFin\Config;
use Lucas\HiPayFin\Signature\HmacSigner;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;
use Lucas\HiPayFin\Exceptions\HiPayFinNetworkException;

class CurlHttpClient implements HttpClientInterface
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function request($method, $path, array $data = null, $idempotencyKey = null)
    {
        $url = $this->config->getBaseUrl() . '/' . ltrim($path, '/');
        $method = strtoupper($method);

        $signed = HmacSigner::buildSignedHeaders(
            $this->config->getApiKey(),
            $this->config->getHmacSecret(),
            $method,
            $data,
            $idempotencyKey
        );

        $curlHeaders = array();
        foreach ($signed['headers'] as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getTimeout());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->config->getConnectTimeout());
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_USERAGENT, 'HiPayFin-PHP-SDK/1.0 (PHP ' . PHP_VERSION . ')');

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $signed['bodyString']);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $rawResponse = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new HiPayFinNetworkException("cURL error ({$curlErrno}): {$curlError}", $curlErrno);
        }

        $response = new Response($statusCode, $rawResponse);
        $decoded = $response->getDecodedJson();

        // Check for error condition
        if ($statusCode >= 400 || (is_array($decoded) && isset($decoded['error']) && $decoded['error'] !== null)) {
            $errorCode = '';
            $message = 'HiPayFin API Error';
            $meta = array();

            if (is_array($decoded)) {
                $errorCode = isset($decoded['code']) ? $decoded['code'] : (isset($decoded['error']) ? $decoded['error'] : '');
                $message = isset($decoded['error']) ? $decoded['error'] : (isset($decoded['message']) ? $decoded['message'] : $message);
                $meta = isset($decoded['meta']) && is_array($decoded['meta']) ? $decoded['meta'] : array();
            } else {
                $message = "HTTP {$statusCode}: {$rawResponse}";
            }

            throw new HiPayFinApiException($message, $statusCode, $errorCode, $meta);
        }

        return $response->getData();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 13, Passed: 13, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Http/ tests/CurlHttpClientTest.php
git commit -m "feat: implement CurlHttpClient transport and Response Envelope handler"
```

---

### Task 7: Base Service & Payload Sanitizer (`AbstractService`)

**Files:**
- Create: `src/Services/AbstractService.php`
- Test: `tests/AbstractServiceTest.php`

**Interfaces:**
- Consumes: `HttpClientInterface`
- Produces: `AbstractService` with `sanitizePayload(array $params)` and HTTP proxy methods `get($path)`, `post($path, array $data = null, $idempotencyKey = null)`.

- [ ] **Step 1: Write the failing test**

File: `tests/AbstractServiceTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Services\AbstractService;
use Lucas\HiPayFin\Http\HttpClientInterface;

class ConcreteTestService extends AbstractService
{
    public function testSanitize(array $params)
    {
        return $this->sanitizePayload($params);
    }
}

class AbstractServiceTest
{
    public function testSanitizePayloadStripsNullAndEmptyStrings()
    {
        $mockHttp = new DummyHttpClient();
        $svc = new ConcreteTestService($mockHttp);

        $input = array(
            'merchant_order_id' => 'ORD-123',
            'amount'            => '150000',
            'customer_name'     => '',    // empty string must be removed
            'optional_field'    => null,  // null must be removed
            'zero_string'       => '0',   // '0' must NOT be removed
        );

        $sanitized = $svc->testSanitize($input);

        if (array_key_exists('customer_name', $sanitized)) {
            throw new \Exception('customer_name empty string must be removed');
        }
        if (array_key_exists('optional_field', $sanitized)) {
            throw new \Exception('optional_field null must be removed');
        }
        if ($sanitized['merchant_order_id'] !== 'ORD-123') {
            throw new \Exception('valid field was lost');
        }
        if ($sanitized['zero_string'] !== '0') {
            throw new \Exception('zero string was incorrectly stripped');
        }
        return true;
    }
}

class DummyHttpClient implements HttpClientInterface
{
    public function request($method, $path, array $data = null, $idempotencyKey = null)
    {
        return array();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Class Lucas\HiPayFin\Services\AbstractService not found"

- [ ] **Step 3: Write minimal implementation**

Create `src/Services/AbstractService.php`:
```php
<?php
namespace Lucas\HiPayFin\Services;

use Lucas\HiPayFin\Http\HttpClientInterface;

abstract class AbstractService
{
    protected $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Remove null and empty string values to adhere to HiPayFin Strict Validation.
     *
     * @param array $params
     * @return array
     */
    protected function sanitizePayload(array $params)
    {
        $sanitized = array();
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (is_array($v)) {
                $sanitized[$k] = $this->sanitizePayload($v);
            } else {
                $sanitized[$k] = $v;
            }
        }
        return $sanitized;
    }

    protected function get($path)
    {
        return $this->httpClient->request('GET', $path);
    }

    protected function post($path, array $data = null, $idempotencyKey = null)
    {
        $cleanData = ($data !== null) ? $this->sanitizePayload($data) : null;
        return $this->httpClient->request('POST', $path, $cleanData, $idempotencyKey);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 14, Passed: 14, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Services/AbstractService.php tests/AbstractServiceTest.php
git commit -m "feat: implement AbstractService with strict validation payload sanitizer"
```

---

### Task 8: Payin Service (`PayinService`)

**Files:**
- Create: `src/Services/PayinService.php`
- Test: `tests/PayinServiceTest.php`

**Interfaces:**
- Consumes: `AbstractService`, `Currency`, `Uuid`
- Produces:
  - `createVnd(array $params, $idempotencyKey = null)`
  - `createUsdt(array $params, $idempotencyKey = null)`
  - `createKrw(array $params, $idempotencyKey = null)`
  - `createHostedLink(array $params, $idempotencyKey = null)`
  - `getBanks($currency = 'krw')`
  - `previewKrw(array $params)`
  - `confirmKrwVerification($verificationId, $code)`

- [ ] **Step 1: Write the failing test**

File: `tests/PayinServiceTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Services\PayinService;
use Lucas\HiPayFin\Http\HttpClientInterface;

class MockPayinHttpClient implements HttpClientInterface
{
    public $lastMethod;
    public $lastPath;
    public $lastData;
    public $lastIdempotencyKey;
    public $mockResponse = array('order_ref' => 'PI-TEST-001', 'status' => 'CREATED');

    public function request($method, $path, array $data = null, $idempotencyKey = null)
    {
        $this->lastMethod = $method;
        $this->lastPath = $path;
        $this->lastData = $data;
        $this->lastIdempotencyKey = $idempotencyKey;
        return $this->mockResponse;
    }
}

class PayinServiceTest
{
    public function testCreateVndDirectPayin()
    {
        $mock = new MockPayinHttpClient();
        $payin = new PayinService($mock);

        $res = $payin->createVnd(array(
            'merchant_order_id'    => 'ORD-VND-001',
            'merchant_customer_id' => 'CUST-001',
            'amount'               => '250000',
            'customer_name'        => 'Nguyen Van A',
        ), 'idem-key-1');

        if ($mock->lastPath !== '/payin/vnd/create') {
            throw new \Exception('Path must be /payin/vnd/create');
        }
        if ($mock->lastMethod !== 'POST') {
            throw new \Exception('Method must be POST');
        }
        if ($mock->lastIdempotencyKey !== 'idem-key-1') {
            throw new \Exception('Idempotency key missing');
        }
        if ($res['order_ref'] !== 'PI-TEST-001') {
            throw new \Exception('Response not returned properly');
        }
        return true;
    }

    public function testCreateHostedLink()
    {
        $mock = new MockPayinHttpClient();
        $mock->mockResponse = array('hosted_page_url' => 'https://pay.hipayfin.com/payin/tok_1');
        $payin = new PayinService($mock);

        $res = $payin->createHostedLink(array(
            'merchant_order_id'    => 'ORD-LINK-001',
            'merchant_customer_id' => 'CUST-001',
            'amount'               => '500000',
            'target_currency'      => 'VND',
        ));

        if ($mock->lastPath !== '/payin/link') {
            throw new \Exception('Path must be /payin/link');
        }
        if (empty($mock->lastIdempotencyKey)) {
            throw new \Exception('Auto-generated Idempotency key was not created');
        }
        return true;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Class Lucas\HiPayFin\Services\PayinService not found"

- [ ] **Step 3: Write minimal implementation**

Create `src/Services/PayinService.php`:
```php
<?php
namespace Lucas\HiPayFin\Services;

use Lucas\HiPayFin\Support\Uuid;

class PayinService extends AbstractService
{
    public function createVnd(array $params, $idempotencyKey = null)
    {
        $key = ($idempotencyKey !== null) ? $idempotencyKey : Uuid::v4();
        return $this->post('/payin/vnd/create', $params, $key);
    }

    public function createUsdt(array $params, $idempotencyKey = null)
    {
        $key = ($idempotencyKey !== null) ? $idempotencyKey : Uuid::v4();
        return $this->post('/payin/usdt/create', $params, $key);
    }

    public function createKrw(array $params, $idempotencyKey = null)
    {
        $key = ($idempotencyKey !== null) ? $idempotencyKey : Uuid::v4();
        return $this->post('/payin/krw/create', $params, $key);
    }

    public function createHostedLink(array $params, $idempotencyKey = null)
    {
        $key = ($idempotencyKey !== null) ? $idempotencyKey : Uuid::v4();
        return $this->post('/payin/link', $params, $key);
    }

    public function getBanks($currency = 'krw')
    {
        $cur = strtolower(trim((string)$currency));
        return $this->get("/payin/{$cur}/banks");
    }

    public function previewKrw(array $params)
    {
        return $this->post('/payin/krw/preview', $params);
    }

    public function confirmKrwVerification($verificationId, $code)
    {
        $payload = array(
            'verification_id' => (string)$verificationId,
            'code'            => (string)$code,
        );
        return $this->post('/payin/krw/verify/confirm', $payload);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 16, Passed: 16, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Services/PayinService.php tests/PayinServiceTest.php
git commit -m "feat: implement PayinService with direct and hosted payin methods"
```

---

### Task 9: Payout Service (`PayoutService`)

**Files:**
- Create: `src/Services/PayoutService.php`
- Test: `tests/PayoutServiceTest.php`

**Interfaces:**
- Consumes: `AbstractService`, `Uuid`
- Produces:
  - `createVnd(array $params, $idempotencyKey = null)`
  - `createUsdt(array $params, $idempotencyKey = null)`
  - `createKrw(array $params, $idempotencyKey = null)`
  - `createHostedLink(array $params, $idempotencyKey = null)`
  - `getBanks($currency = 'vnd')`
  - `preview($currency, $amount)`

- [ ] **Step 1: Write the failing test**

File: `tests/PayoutServiceTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Services\PayoutService;
use Lucas\HiPayFin\Http\HttpClientInterface;

class MockPayoutHttpClient implements HttpClientInterface
{
    public $lastMethod;
    public $lastPath;
    public $lastData;
    public $lastIdempotencyKey;
    public $mockResponse = array('order_ref' => 'PO-TEST-001', 'status' => 'PENDING_APPROVAL');

    public function request($method, $path, array $data = null, $idempotencyKey = null)
    {
        $this->lastMethod = $method;
        $this->lastPath = $path;
        $this->lastData = $data;
        $this->lastIdempotencyKey = $idempotencyKey;
        return $this->mockResponse;
    }
}

class PayoutServiceTest
{
    public function testCreateVndDirectPayout()
    {
        $mock = new MockPayoutHttpClient();
        $payout = new PayoutService($mock);

        $res = $payout->createVnd(array(
            'merchant_order_id'    => 'PO-001',
            'merchant_customer_id' => 'USER-1',
            'amount'               => '150000',
            'bank_code'            => '970436',
            'account_number'       => '0011001234567',
            'account_holder'       => 'TRAN VAN B',
        ));

        if ($mock->lastPath !== '/payout/vnd/create') {
            throw new \Exception('Path must be /payout/vnd/create');
        }
        if ($res['order_ref'] !== 'PO-TEST-001') {
            throw new \Exception('Invalid response');
        }
        return true;
    }

    public function testPayoutPreview()
    {
        $mock = new MockPayoutHttpClient();
        $mock->mockResponse = array('receive_currency' => 'VND', 'receive_amount' => '150000');
        $payout = new PayoutService($mock);

        $res = $payout->preview('vnd', '150000');
        if ($mock->lastPath !== '/payout/vnd/preview') {
            throw new \Exception('Path must be /payout/vnd/preview');
        }
        if ($res['receive_amount'] !== '150000') {
            throw new \Exception('Preview calculation mismatch');
        }
        return true;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Class Lucas\HiPayFin\Services\PayoutService not found"

- [ ] **Step 3: Write minimal implementation**

Create `src/Services/PayoutService.php`:
```php
<?php
namespace Lucas\HiPayFin\Services;

use Lucas\HiPayFin\Support\Uuid;

class PayoutService extends AbstractService
{
    public function createVnd(array $params, $idempotencyKey = null)
    {
        $key = ($idempotencyKey !== null) ? $idempotencyKey : Uuid::v4();
        return $this->post('/payout/vnd/create', $params, $key);
    }

    public function createUsdt(array $params, $idempotencyKey = null)
    {
        $key = ($idempotencyKey !== null) ? $idempotencyKey : Uuid::v4();
        return $this->post('/payout/usdt/create', $params, $key);
    }

    public function createKrw(array $params, $idempotencyKey = null)
    {
        $key = ($idempotencyKey !== null) ? $idempotencyKey : Uuid::v4();
        return $this->post('/payout/krw/create', $params, $key);
    }

    public function createHostedLink(array $params, $idempotencyKey = null)
    {
        $key = ($idempotencyKey !== null) ? $idempotencyKey : Uuid::v4();
        return $this->post('/payout/link', $params, $key);
    }

    public function getBanks($currency = 'vnd')
    {
        $cur = strtolower(trim((string)$currency));
        return $this->get("/payout/{$cur}/banks");
    }

    public function preview($currency, $amount)
    {
        $cur = strtolower(trim((string)$currency));
        return $this->post("/payout/{$cur}/preview", array('amount' => (string)$amount));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 18, Passed: 18, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Services/PayoutService.php tests/PayoutServiceTest.php
git commit -m "feat: implement PayoutService with direct payout, link and preview"
```

---

### Task 10: Orders & Wallets Services (`OrderService`, `WalletService`)

**Files:**
- Create: `src/Services/OrderService.php`
- Create: `src/Services/WalletService.php`
- Test: `tests/OrderServiceTest.php`
- Test: `tests/WalletServiceTest.php`

**Interfaces:**
- Consumes: `AbstractService`
- Produces:
  - `OrderService::get($orderRef)`
  - `OrderService::getByMerchantOrderId($merchantOrderId)`
  - `OrderService::reportEvidence($orderRef, $txHash)`
  - `WalletService::all()`

- [ ] **Step 1: Write the failing test**

File: `tests/OrderServiceTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Services\OrderService;
use Lucas\HiPayFin\Http\HttpClientInterface;

class MockOrderHttpClient implements HttpClientInterface
{
    public $lastMethod;
    public $lastPath;
    public $lastData;
    public $mockResponse = array('order_ref' => 'PI-001', 'status' => 'CONFIRMED');

    public function request($method, $path, array $data = null, $idempotencyKey = null)
    {
        $this->lastMethod = $method;
        $this->lastPath = $path;
        $this->lastData = $data;
        return $this->mockResponse;
    }
}

class OrderServiceTest
{
    public function testGetOrderByRef()
    {
        $mock = new MockOrderHttpClient();
        $svc = new OrderService($mock);

        $res = $svc->get('PI-001');
        if ($mock->lastPath !== '/orders/PI-001') {
            throw new \Exception('Path must be /orders/PI-001');
        }
        if ($mock->lastMethod !== 'GET') {
            throw new \Exception('Method must be GET');
        }
        return true;
    }

    public function testGetByMerchantOrderId()
    {
        $mock = new MockOrderHttpClient();
        $svc = new OrderService($mock);

        $res = $svc->getByMerchantOrderId('ORD-VN-99');
        if ($mock->lastPath !== '/orders?merchant_order_id=ORD-VN-99') {
            throw new \Exception('Path with query string mismatch');
        }
        return true;
    }

    public function testReportEvidence()
    {
        $mock = new MockOrderHttpClient();
        $svc = new OrderService($mock);

        $svc->reportEvidence('PI-001', '0xabc123');
        if ($mock->lastPath !== '/orders/PI-001/evidence') {
            throw new \Exception('Path must be /orders/PI-001/evidence');
        }
        if ($mock->lastData['tx_hash'] !== '0xabc123') {
            throw new \Exception('tx_hash payload mismatch');
        }
        return true;
    }
}
```

File: `tests/WalletServiceTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Services\WalletService;
use Lucas\HiPayFin\Http\HttpClientInterface;

class WalletServiceTest
{
    public function testGetAllWallets()
    {
        $mock = new MockOrderHttpClient();
        $mock->mockResponse = array(
            array('currency' => 'VND', 'status' => 'ACTIVE'),
            array('currency' => 'USDT', 'status' => 'ACTIVE')
        );
        $svc = new WalletService($mock);

        $wallets = $svc->all();
        if ($mock->lastPath !== '/wallets') {
            throw new \Exception('Path must be /wallets');
        }
        if (count($wallets) !== 2) {
            throw new \Exception('Wallet count mismatch');
        }
        return true;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Class Lucas\HiPayFin\Services\OrderService not found"

- [ ] **Step 3: Write minimal implementation**

Create `src/Services/OrderService.php`:
```php
<?php
namespace Lucas\HiPayFin\Services;

class OrderService extends AbstractService
{
    public function get($orderRef)
    {
        $ref = rawurlencode(trim((string)$orderRef));
        return $this->get("/orders/{$ref}");
    }

    public function getByMerchantOrderId($merchantOrderId)
    {
        $orderId = rawurlencode(trim((string)$merchantOrderId));
        return $this->get("/orders?merchant_order_id={$orderId}");
    }

    public function reportEvidence($orderRef, $txHash)
    {
        $ref = rawurlencode(trim((string)$orderRef));
        return $this->post("/orders/{$ref}/evidence", array(
            'tx_hash' => (string)$txHash,
        ));
    }
}
```

Create `src/Services/WalletService.php`:
```php
<?php
namespace Lucas\HiPayFin\Services;

class WalletService extends AbstractService
{
    public function all()
    {
        return $this->get('/wallets');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 22, Passed: 22, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Services/OrderService.php src/Services/WalletService.php tests/OrderServiceTest.php tests/WalletServiceTest.php
git commit -m "feat: implement OrderService and WalletService"
```

---

### Task 11: Webhook Service (`WebhookService`)

**Files:**
- Create: `src/Services/WebhookService.php`
- Test: `tests/WebhookServiceTest.php`

**Interfaces:**
- Consumes: `Config`, `HmacSigner`, `HiPayFinSignatureException`
- Produces:
  - `verify($rawBody, $signature, $timestamp, $secret = null, $tolerance = 300)`
  - `parse($rawBody)`
  - `verifyAndParse($rawBody, $signature, $timestamp, $secret = null, $tolerance = 300)`

- [ ] **Step 1: Write the failing test**

File: `tests/WebhookServiceTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Config;
use Lucas\HiPayFin\Services\WebhookService;
use Lucas\HiPayFin\Exceptions\HiPayFinSignatureException;

class WebhookServiceTest
{
    public function testVerifyAndParseValidWebhook()
    {
        $secret = 'webhook_secret_xyz';
        $config = new Config(array(
            'api_key'        => 'key',
            'hmac_secret'    => 'secret',
            'webhook_secret' => $secret,
        ));

        $svc = new WebhookService($config);
        $rawPayload = '{"event":"payment.confirmed","event_id":"evt_01","amount":"250000","order_ref":"PI-001"}';
        $timestamp = (string)time();
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawPayload, $secret);

        $parsed = $svc->verifyAndParse($rawPayload, $signature, $timestamp);
        if ($parsed['event'] !== 'payment.confirmed') {
            throw new \Exception('event parsing failed');
        }
        if ($parsed['amount'] !== '250000') {
            throw new \Exception('amount parsing failed');
        }
        return true;
    }

    public function testVerifyThrowsOnInvalidSignature()
    {
        $config = new Config(array(
            'api_key'        => 'key',
            'hmac_secret'    => 'secret',
            'webhook_secret' => 'correct_secret',
        ));

        $svc = new WebhookService($config);
        $rawPayload = '{"event":"payment.confirmed"}';
        $timestamp = (string)time();

        try {
            $svc->verifyAndParse($rawPayload, 'sha256=wrong', $timestamp);
            throw new \Exception('Should have thrown HiPayFinSignatureException');
        } catch (HiPayFinSignatureException $e) {
            return true;
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Class Lucas\HiPayFin\Services\WebhookService not found"

- [ ] **Step 3: Write minimal implementation**

Create `src/Services/WebhookService.php`:
```php
<?php
namespace Lucas\HiPayFin\Services;

use Lucas\HiPayFin\Config;
use Lucas\HiPayFin\Signature\HmacSigner;
use Lucas\HiPayFin\Exceptions\HiPayFinSignatureException;
use Lucas\HiPayFin\Exceptions\HiPayFinValidationException;

class WebhookService
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Verify incoming webhook signature.
     *
     * @param string $rawBody
     * @param string $signature
     * @param string $timestamp
     * @param string|null $secret
     * @param int $tolerance
     * @return bool
     */
    public function verify($rawBody, $signature, $timestamp, $secret = null, $tolerance = 300)
    {
        $sec = ($secret !== null) ? (string)$secret : $this->config->getWebhookSecret();
        if (empty($sec)) {
            throw new HiPayFinValidationException('Webhook signing secret is not configured', 'webhook_secret');
        }

        return HmacSigner::verifyWebhook($rawBody, $signature, $timestamp, $sec, $tolerance);
    }

    /**
     * Parse raw webhook JSON body into an associative array.
     *
     * @param string $rawBody
     * @return array
     * @throws HiPayFinValidationException
     */
    public function parse($rawBody)
    {
        $decoded = json_decode((string)$rawBody, true);
        if (!is_array($decoded)) {
            throw new HiPayFinValidationException('Invalid Webhook JSON payload', 'raw_body');
        }
        return $decoded;
    }

    /**
     * Verify signature and return parsed payload; throws HiPayFinSignatureException on failure.
     *
     * @param string $rawBody
     * @param string $signature
     * @param string $timestamp
     * @param string|null $secret
     * @param int $tolerance
     * @return array
     * @throws HiPayFinSignatureException
     */
    public function verifyAndParse($rawBody, $signature, $timestamp, $secret = null, $tolerance = 300)
    {
        if (!$this->verify($rawBody, $signature, $timestamp, $secret, $tolerance)) {
            throw new HiPayFinSignatureException('Webhook signature verification failed or timestamp expired');
        }
        return $this->parse($rawBody);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 24, Passed: 24, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Services/WebhookService.php tests/WebhookServiceTest.php
git commit -m "feat: implement WebhookService with verify, parse and verifyAndParse methods"
```

---

### Task 12: Main Client (`Client`) & Lazy Service Access

**Files:**
- Create: `src/Client.php`
- Test: `tests/ClientTest.php`

**Interfaces:**
- Consumes: `Config`, `CurlHttpClient`, `PayinService`, `PayoutService`, `OrderService`, `WalletService`, `WebhookService`
- Produces: `Client` with `$client->payin`, `$client->payout`, `$client->orders`, `$client->wallets`, `$client->webhook`.

- [ ] **Step 1: Write the failing test**

File: `tests/ClientTest.php`:
```php
<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Client;
use Lucas\HiPayFin\Config;

class ClientTest
{
    public function testClientInitializesServices()
    {
        $client = new Client(array(
            'api_key'     => 'hk_live_VbdBEm3for1GBYzuke6kYl0BHpx9R3afH_yK',
            'hmac_secret' => '35b0091558ae05393e31643b7da158fefbc5397a6eaa037054bcd0b5aff045de',
        ));

        if (!($client->payin instanceof \Lucas\HiPayFin\Services\PayinService)) {
            throw new \Exception('payin service not loaded');
        }
        if (!($client->payout instanceof \Lucas\HiPayFin\Services\PayoutService)) {
            throw new \Exception('payout service not loaded');
        }
        if (!($client->orders instanceof \Lucas\HiPayFin\Services\OrderService)) {
            throw new \Exception('orders service not loaded');
        }
        if (!($client->wallets instanceof \Lucas\HiPayFin\Services\WalletService)) {
            throw new \Exception('wallets service not loaded');
        }
        if (!($client->webhook instanceof \Lucas\HiPayFin\Services\WebhookService)) {
            throw new \Exception('webhook service not loaded');
        }
        return true;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run_tests.php`
Expected: FAIL with "Class Lucas\HiPayFin\Client not found"

- [ ] **Step 3: Write minimal implementation**

Create `src/Client.php`:
```php
<?php
namespace Lucas\HiPayFin;

use Lucas\HiPayFin\Http\HttpClientInterface;
use Lucas\HiPayFin\Http\CurlHttpClient;
use Lucas\HiPayFin\Services\PayinService;
use Lucas\HiPayFin\Services\PayoutService;
use Lucas\HiPayFin\Services\OrderService;
use Lucas\HiPayFin\Services\WalletService;
use Lucas\HiPayFin\Services\WebhookService;

/**
 * HiPayFin Merchant Payment Gateway Client
 *
 * @property-read PayinService $payin
 * @property-read PayoutService $payout
 * @property-read OrderService $orders
 * @property-read WalletService $wallets
 * @property-read WebhookService $webhook
 */
class Client
{
    /** @var Config */
    protected $config;

    /** @var HttpClientInterface */
    protected $httpClient;

    /** @var array Cache for instantiated services */
    protected $services = array();

    /**
     * @param Config|array $configOrOptions
     * @param HttpClientInterface|null $httpClient
     */
    public function __construct($configOrOptions, HttpClientInterface $httpClient = null)
    {
        if ($configOrOptions instanceof Config) {
            $this->config = $configOrOptions;
        } else {
            $this->config = new Config((array)$configOrOptions);
        }

        $this->httpClient = ($httpClient !== null) ? $httpClient : new CurlHttpClient($this->config);
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getHttpClient()
    {
        return $this->httpClient;
    }

    public function __get($name)
    {
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }

        switch ($name) {
            case 'payin':
                return $this->services['payin'] = new PayinService($this->httpClient);
            case 'payout':
                return $this->services['payout'] = new PayoutService($this->httpClient);
            case 'orders':
                return $this->services['orders'] = new OrderService($this->httpClient);
            case 'wallets':
                return $this->services['wallets'] = new WalletService($this->httpClient);
            case 'webhook':
                return $this->services['webhook'] = new WebhookService($this->config);
            default:
                throw new \InvalidArgumentException("Undefined property or service: {$name}");
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run_tests.php`
Expected: `Tests: 25, Passed: 25, Failed: 0`

- [ ] **Step 5: Commit**

```bash
git add src/Client.php tests/ClientTest.php
git commit -m "feat: implement main Client class with lazy service loading"
```

---

### Task 13: Executable Sandbox Examples & Live Verification

**Files:**
- Create: `examples/config.php`
- Create: `examples/payin_create_vnd.php`
- Create: `examples/payin_create_usdt.php`
- Create: `examples/payout_create_vnd.php`
- Create: `examples/orders_query.php`
- Create: `examples/webhook_receiver.php`
- Test: Execute scripts against HiPayFin Sandbox environment.

**Interfaces:**
- Consumes: `Client`, `Config`, `Currency`, `Uuid`
- Produces: Runnable example scripts for developers integrating the SDK.

- [ ] **Step 1: Write `examples/config.php` with user sandbox credentials**

```php
<?php
require_once __DIR__ . '/../autoload.php';

use Lucas\HiPayFin\Client;
use Lucas\HiPayFin\Config;

$client = new Client(array(
    'api_key'        => 'hk_live_VbdBEm3for1GBYzuke6kYl0BHpx9R3afH_yK',
    'hmac_secret'    => '35b0091558ae05393e31643b7da158fefbc5397a6eaa037054bcd0b5aff045de',
    'webhook_secret' => '35b0091558ae05393e31643b7da158fefbc5397a6eaa037054bcd0b5aff045de', // default fallback for demo
    'environment'    => Config::ENV_SANDBOX,
));
```

- [ ] **Step 2: Write `examples/payin_create_vnd.php`**

```php
<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

try {
    $orderId = 'ORD-VN-' . time();
    $amountMinor = Currency::toMinorUnit(150000, 'VND'); // "150000"

    echo "Creating VND Payin Order: {$orderId}, Amount: {$amountMinor} VND...\n";

    $response = $client->payin->createVnd(array(
        'merchant_order_id'    => $orderId,
        'merchant_customer_id' => 'CUST-DEMO-01',
        'amount'               => $amountMinor,
        'customer_name'        => 'Nguyen Van A',
    ));

    echo "SUCCESS! Created Order:\n";
    echo "  Order Ref: " . $response['order_ref'] . "\n";
    echo "  Status:    " . $response['status'] . "\n";
    if (isset($response['instruction'])) {
        echo "  Payment Instruction: " . json_encode($response['instruction']) . "\n";
    }
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

- [ ] **Step 3: Write `examples/payin_create_usdt.php`**

```php
<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

try {
    $orderId = 'ORD-USDT-' . time();
    $amountMinor = Currency::toMinorUnit(10.5, 'USDT'); // "10500000"

    echo "Creating USDT Payin Order: {$orderId}, Amount: {$amountMinor} minor units (10.5 USDT)...\n";

    $response = $client->payin->createUsdt(array(
        'merchant_order_id'    => $orderId,
        'merchant_customer_id' => 'CUST-DEMO-01',
        'amount'               => $amountMinor,
    ));

    echo "SUCCESS! Created USDT Order:\n";
    echo "  Order Ref: " . $response['order_ref'] . "\n";
    echo "  Status:    " . $response['status'] . "\n";
    if (isset($response['instruction'])) {
        echo "  Address:   " . $response['instruction']['address'] . "\n";
        echo "  Network:   " . $response['instruction']['network'] . "\n";
    }
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

- [ ] **Step 4: Write `examples/payout_create_vnd.php`**

```php
<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Support\Currency;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

try {
    $payoutId = 'PO-VN-' . time();
    $amountMinor = Currency::toMinorUnit(100000, 'VND');

    echo "Submitting VND Payout Request: {$payoutId}, Amount: {$amountMinor} VND...\n";

    $response = $client->payout->createVnd(array(
        'merchant_order_id'    => $payoutId,
        'merchant_customer_id' => 'CUST-DEMO-01',
        'amount'               => $amountMinor,
        'bank_code'            => '970436', // Vietcombank
        'account_number'       => '0011001234567',
        'account_holder'       => 'NGUYEN VAN A',
    ));

    echo "SUCCESS! Payout Order Created:\n";
    echo "  Order Ref: " . $response['order_ref'] . "\n";
    echo "  Status:    " . $response['status'] . "\n";
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

- [ ] **Step 5: Write `examples/orders_query.php`**

```php
<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Exceptions\HiPayFinApiException;

try {
    // 1. Check merchant active wallets
    echo "Checking merchant active wallets...\n";
    $wallets = $client->wallets->all();
    echo "Active Wallets: " . json_encode($wallets) . "\n\n";

    // 2. Query order by merchant_order_id
    $testOrderId = isset($argv[1]) ? $argv[1] : 'ORD-VN-DEMO';
    echo "Querying order by merchant_order_id: {$testOrderId}...\n";
    $order = $client->orders->getByMerchantOrderId($testOrderId);
    echo "Order Info:\n" . json_encode($order, JSON_PRETTY_PRINT) . "\n";
} catch (HiPayFinApiException $e) {
    echo "API Error [{$e->getHttpStatusCode()} - {$e->getErrorCode()}]: " . $e->getMessage() . "\n";
    print_r($e->getMeta());
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

- [ ] **Step 6: Write `examples/webhook_receiver.php`**

```php
<?php
require_once __DIR__ . '/config.php';

use Lucas\HiPayFin\Exceptions\HiPayFinSignatureException;

// Read raw request headers and body
$rawBody = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) ? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] : '';
$timestamp = isset($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP']) ? $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] : '';
$eventId = isset($_SERVER['HTTP_X_WEBHOOK_ID']) ? $_SERVER['HTTP_X_WEBHOOK_ID'] : '';

try {
    // 1. Verify and parse incoming webhook
    $event = $client->webhook->verifyAndParse($rawBody, $signature, $timestamp);

    // 2. Anti-duplicate processing check (idempotent consumer)
    // Check if $event['event_id'] has already been processed in your database
    // if (isEventProcessed($event['event_id'])) {
    //     http_response_code(200);
    //     echo json_encode(['status' => 'ignored_duplicate']);
    //     exit;
    // }

    // 3. Handle event types
    switch ($event['event']) {
        case 'payment.confirmed':
            // CRITICAL: Always use actual received amount from webhook
            $actualAmount = $event['amount'];
            $orderRef = $event['order_ref'];
            $merchantOrderId = $event['merchant_order_id'];
            // creditUserBalance($merchantOrderId, $actualAmount);
            break;

        case 'payment.rejected':
            // markOrderFailed($event['merchant_order_id'], $event['reason']);
            break;

        case 'payout.processed':
            // markPayoutCompleted($event['order_ref']);
            break;

        case 'payout.blocked':
            // markPayoutFailedAndRefundBalance($event['order_ref'], $event['reason']);
            break;
    }

    // 4. Return fast HTTP 200 OK (< 2 seconds)
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(array('status' => 'success'));
} catch (HiPayFinSignatureException $e) {
    http_response_code(401);
    echo json_encode(array('error' => 'Invalid signature or timestamp expired'));
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(array('error' => $e->getMessage()));
}
```

- [ ] **Step 7: Execute live check with Sandbox**

Run: `php examples/orders_query.php`
Expected: Successfully queries Sandbox wallets API and connects with credentials.

- [ ] **Step 8: Commit**

```bash
git add examples/
git commit -m "feat: add runnable examples for payin, payout, query and webhook handler"
```

---

### Task 14: Documentation (`README.md`) & Package Finalization

**Files:**
- Create: `README.md`
- Test: Run complete test suite and verify git clean status.

**Interfaces:**
- Consumes: All completed modules and examples
- Produces: Comprehensive user manual and integration guide in Vietnamese & English.

- [ ] **Step 1: Write `README.md`**

Covering:
- System requirements (PHP >= 5.6, ext-curl, ext-json, ext-hash)
- Installation via Composer and via `autoload.php`
- Quick Start guide with Sandbox credentials
- Direct Payin & Hosted Link examples
- Direct Payout & Hosted Link examples
- Webhook signature verification & best practices
- Currency minor unit calculation guide (VND, KRW, USDT)
- Exception handling & HTTP 409 duplicate order recovery
- Running tests

- [ ] **Step 2: Run all tests to confirm full green suite**

Run: `php tests/run_tests.php`
Expected: `Tests: 25, Passed: 25, Failed: 0`

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: add comprehensive README documentation and integration guide"
```

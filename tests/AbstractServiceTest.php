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

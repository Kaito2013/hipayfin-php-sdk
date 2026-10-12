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

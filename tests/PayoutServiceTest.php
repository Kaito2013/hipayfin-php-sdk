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

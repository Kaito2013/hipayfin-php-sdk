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

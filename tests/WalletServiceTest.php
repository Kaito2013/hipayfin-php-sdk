<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Services\WalletService;
use Lucas\HiPayFin\Http\HttpClientInterface;

class MockWalletHttpClient implements HttpClientInterface
{
    public $lastMethod;
    public $lastPath;
    public $mockResponse = array(
        array('currency' => 'VND', 'status' => 'ACTIVE'),
        array('currency' => 'USDT', 'status' => 'ACTIVE')
    );

    public function request($method, $path, array $data = null, $idempotencyKey = null)
    {
        $this->lastMethod = $method;
        $this->lastPath = $path;
        return $this->mockResponse;
    }
}

class WalletServiceTest
{
    public function testGetAllWallets()
    {
        $mock = new MockWalletHttpClient();
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

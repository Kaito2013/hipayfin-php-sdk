<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Client;

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
        if ($client->getConfig()->getApiKey() !== 'hk_live_VbdBEm3for1GBYzuke6kYl0BHpx9R3afH_yK') {
            throw new \Exception('client config mismatch');
        }
        return true;
    }
}

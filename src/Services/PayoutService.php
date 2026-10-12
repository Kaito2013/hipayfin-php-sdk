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

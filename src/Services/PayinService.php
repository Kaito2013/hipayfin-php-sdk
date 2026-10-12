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

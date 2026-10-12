<?php
namespace Lucas\HiPayFin\Services;

class OrderService extends AbstractService
{
    public function get($orderRef)
    {
        $ref = rawurlencode(trim((string)$orderRef));
        return $this->httpGet("/orders/{$ref}");
    }

    public function getByMerchantOrderId($merchantOrderId)
    {
        $orderId = rawurlencode(trim((string)$merchantOrderId));
        return $this->httpGet("/orders?merchant_order_id={$orderId}");
    }

    public function reportEvidence($orderRef, $txHash)
    {
        $ref = rawurlencode(trim((string)$orderRef));
        return $this->httpPost("/orders/{$ref}/evidence", array(
            'tx_hash' => (string)$txHash,
        ));
    }
}

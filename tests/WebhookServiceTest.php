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

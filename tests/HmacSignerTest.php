<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Signature\HmacSigner;

class HmacSignerTest
{
    public function testGetRequestSigningHasTrailingDot()
    {
        $apiKey = 'test_api_key';
        $secret = 'test_secret';
        $timestamp = '1728719200';

        $res = HmacSigner::buildSignedHeaders($apiKey, $secret, 'GET', null, null, $timestamp);
        $headers = $res['headers'];

        $expectedSig = 'sha256=' . hash_hmac('sha256', '1728719200.', $secret);

        if ($headers['X-Api-Key'] !== $apiKey) {
            throw new \Exception('X-Api-Key mismatch');
        }
        if ($headers['X-Timestamp'] !== '1728719200') {
            throw new \Exception('X-Timestamp mismatch');
        }
        if ($headers['X-Signature'] !== $expectedSig) {
            throw new \Exception("GET signature mismatch. Got {$headers['X-Signature']}, expected {$expectedSig}");
        }
        return true;
    }

    public function testPostRequestSigningUsesRawJson()
    {
        $apiKey = 'test_api_key';
        $secret = 'test_secret';
        $timestamp = '1728719200';
        $body = array('amount' => '150000', 'merchant_order_id' => 'ORD-001');

        $res = HmacSigner::buildSignedHeaders($apiKey, $secret, 'POST', $body, 'uuid-1234', $timestamp);
        $headers = $res['headers'];
        $rawBody = $res['bodyString'];

        $expectedPayload = '1728719200.' . $rawBody;
        $expectedSig = 'sha256=' . hash_hmac('sha256', $expectedPayload, $secret);

        if ($headers['X-Signature'] !== $expectedSig) {
            throw new \Exception('POST signature mismatch');
        }
        if ($headers['X-Idempotency-Key'] !== 'uuid-1234') {
            throw new \Exception('X-Idempotency-Key missing');
        }
        if ($headers['Content-Type'] !== 'application/json') {
            throw new \Exception('Content-Type must be application/json');
        }
        return true;
    }

    public function testWebhookSignatureVerification()
    {
        $secret = 'secret_webhook_123';
        $timestamp = (string)time();
        $rawBody = '{"event":"payment.confirmed","amount":"100000"}';

        $validSig = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        if (!HmacSigner::verifyWebhook($rawBody, $validSig, $timestamp, $secret)) {
            throw new \Exception('Valid webhook signature was rejected');
        }

        if (HmacSigner::verifyWebhook($rawBody, 'sha256=invalidhash', $timestamp, $secret)) {
            throw new \Exception('Invalid webhook signature was accepted');
        }

        $oldTimestamp = (string)(time() - 400);
        $oldSig = 'sha256=' . hash_hmac('sha256', $oldTimestamp . '.' . $rawBody, $secret);
        if (HmacSigner::verifyWebhook($rawBody, $oldSig, $oldTimestamp, $secret)) {
            throw new \Exception('Expired webhook signature should be rejected');
        }
        return true;
    }
}

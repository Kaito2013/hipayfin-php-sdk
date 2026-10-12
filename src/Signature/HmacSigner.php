<?php
namespace Lucas\HiPayFin\Signature;

class HmacSigner
{
    /**
     * Build signed headers and normalized raw body for HiPayFin API.
     *
     * @param string $apiKey
     * @param string $hmacSecret
     * @param string $method
     * @param mixed $body
     * @param string|null $idempotencyKey
     * @param string|null $timestamp
     * @return array array('headers' => array, 'bodyString' => string)
     */
    public static function buildSignedHeaders($apiKey, $hmacSecret, $method, $body = null, $idempotencyKey = null, $timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = (string)time();
        } else {
            $timestamp = (string)$timestamp;
        }

        $bodyString = '';
        if (strtoupper($method) !== 'GET' && $body !== null) {
            $bodyString = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        $payloadToSign = $timestamp . '.' . $bodyString;
        $sigHex = hash_hmac('sha256', $payloadToSign, (string)$hmacSecret);

        $headers = array(
            'X-Api-Key'    => (string)$apiKey,
            'X-Timestamp'  => $timestamp,
            'X-Signature'  => 'sha256=' . strtolower($sigHex),
            'Content-Type' => 'application/json',
        );

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['X-Idempotency-Key'] = (string)$idempotencyKey;
        }

        return array(
            'headers'    => $headers,
            'bodyString' => $bodyString,
        );
    }

    /**
     * Verify incoming Webhook signature.
     *
     * @param string $rawBody
     * @param string $receivedSignature
     * @param string $timestamp
     * @param string $webhookSecret
     * @param int $toleranceSeconds
     * @return bool
     */
    public static function verifyWebhook($rawBody, $receivedSignature, $timestamp, $webhookSecret, $toleranceSeconds = 300)
    {
        if (empty($timestamp) || empty($receivedSignature) || empty($webhookSecret)) {
            return false;
        }

        // Anti-replay check
        $currentTime = time();
        $eventTime = (int)$timestamp;
        if (abs($currentTime - $eventTime) > $toleranceSeconds) {
            return false;
        }

        $payloadToSign = $timestamp . '.' . (string)$rawBody;
        $expectedHex = hash_hmac('sha256', $payloadToSign, (string)$webhookSecret);
        $expectedSignature = 'sha256=' . strtolower($expectedHex);

        $cleanReceived = strtolower(trim((string)$receivedSignature));
        if (strpos($cleanReceived, 'sha256=') !== 0) {
            $cleanReceived = 'sha256=' . $cleanReceived;
        }

        if (function_exists('hash_equals')) {
            return hash_equals($expectedSignature, $cleanReceived);
        }

        if (strlen($expectedSignature) !== strlen($cleanReceived)) {
            return false;
        }
        $res = 0;
        $len = strlen($expectedSignature);
        for ($i = 0; $i < $len; $i++) {
            $res |= ord($expectedSignature[$i]) ^ ord($cleanReceived[$i]);
        }
        return $res === 0;
    }
}

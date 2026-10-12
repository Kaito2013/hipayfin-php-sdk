<?php
namespace Lucas\HiPayFin\Services;

use Lucas\HiPayFin\Config;
use Lucas\HiPayFin\Signature\HmacSigner;
use Lucas\HiPayFin\Exceptions\HiPayFinSignatureException;
use Lucas\HiPayFin\Exceptions\HiPayFinValidationException;

class WebhookService
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Verify incoming webhook signature.
     *
     * @param string $rawBody
     * @param string $signature
     * @param string $timestamp
     * @param string|null $secret
     * @param int $tolerance
     * @return bool
     */
    public function verify($rawBody, $signature, $timestamp, $secret = null, $tolerance = 300)
    {
        $sec = ($secret !== null) ? (string)$secret : $this->config->getWebhookSecret();
        if (empty($sec)) {
            throw new HiPayFinValidationException('Webhook signing secret is not configured', 'webhook_secret');
        }

        return HmacSigner::verifyWebhook($rawBody, $signature, $timestamp, $sec, $tolerance);
    }

    /**
     * Parse raw webhook JSON body into an associative array.
     *
     * @param string $rawBody
     * @return array
     * @throws HiPayFinValidationException
     */
    public function parse($rawBody)
    {
        $decoded = json_decode((string)$rawBody, true);
        if (!is_array($decoded)) {
            throw new HiPayFinValidationException('Invalid Webhook JSON payload', 'raw_body');
        }
        return $decoded;
    }

    /**
     * Verify signature and return parsed payload; throws HiPayFinSignatureException on failure.
     *
     * @param string $rawBody
     * @param string $signature
     * @param string $timestamp
     * @param string|null $secret
     * @param int $tolerance
     * @return array
     * @throws HiPayFinSignatureException
     */
    public function verifyAndParse($rawBody, $signature, $timestamp, $secret = null, $tolerance = 300)
    {
        if (!$this->verify($rawBody, $signature, $timestamp, $secret, $tolerance)) {
            throw new HiPayFinSignatureException('Webhook signature verification failed or timestamp expired');
        }
        return $this->parse($rawBody);
    }
}

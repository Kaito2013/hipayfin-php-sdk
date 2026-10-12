<?php
namespace Lucas\HiPayFin;

use Lucas\HiPayFin\Exceptions\HiPayFinValidationException;

class Config
{
    const ENV_SANDBOX    = 'sandbox';
    const ENV_PRODUCTION = 'production';

    const BASE_URL_SANDBOX    = 'https://api-sandbox.hipayfin.com/api/v1';
    const BASE_URL_PRODUCTION = 'https://api.hipayfin.com/api/v1';

    protected $apiKey;
    protected $hmacSecret;
    protected $webhookSecret;
    protected $environment;
    protected $baseUrl;
    protected $timeout;
    protected $connectTimeout;

    public function __construct(array $options = array())
    {
        if (empty($options['api_key'])) {
            throw new HiPayFinValidationException('api_key is required in Config', 'api_key');
        }
        if (empty($options['hmac_secret'])) {
            throw new HiPayFinValidationException('hmac_secret is required in Config', 'hmac_secret');
        }

        $this->apiKey = (string)$options['api_key'];
        $this->hmacSecret = (string)$options['hmac_secret'];
        $this->webhookSecret = isset($options['webhook_secret']) ? (string)$options['webhook_secret'] : '';

        $env = isset($options['environment']) ? strtolower($options['environment']) : self::ENV_SANDBOX;
        $this->environment = ($env === self::ENV_PRODUCTION) ? self::ENV_PRODUCTION : self::ENV_SANDBOX;

        if (isset($options['base_url']) && !empty($options['base_url'])) {
            $this->baseUrl = rtrim((string)$options['base_url'], '/');
        } else {
            $this->baseUrl = ($this->environment === self::ENV_PRODUCTION)
                ? self::BASE_URL_PRODUCTION
                : self::BASE_URL_SANDBOX;
        }

        $this->timeout = isset($options['timeout']) ? (int)$options['timeout'] : 30;
        $this->connectTimeout = isset($options['connect_timeout']) ? (int)$options['connect_timeout'] : 10;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function getHmacSecret()
    {
        return $this->hmacSecret;
    }

    public function getWebhookSecret()
    {
        return !empty($this->webhookSecret) ? $this->webhookSecret : $this->hmacSecret;
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function getConnectTimeout()
    {
        return $this->connectTimeout;
    }
}

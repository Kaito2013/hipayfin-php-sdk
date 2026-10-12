<?php
namespace Lucas\HiPayFin\Http;

use Lucas\HiPayFin\Config;
use Lucas\HiPayFin\Signature\HmacSigner;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;
use Lucas\HiPayFin\Exceptions\HiPayFinNetworkException;

class CurlHttpClient implements HttpClientInterface
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function request($method, $path, array $data = null, $idempotencyKey = null)
    {
        $url = $this->config->getBaseUrl() . '/' . ltrim($path, '/');
        $method = strtoupper($method);

        $signed = HmacSigner::buildSignedHeaders(
            $this->config->getApiKey(),
            $this->config->getHmacSecret(),
            $method,
            $data,
            $idempotencyKey
        );

        $curlHeaders = array();
        foreach ($signed['headers'] as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getTimeout());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->config->getConnectTimeout());
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_USERAGENT, 'HiPayFin-PHP-SDK/1.0 (PHP ' . PHP_VERSION . ')');

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $signed['bodyString']);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $rawResponse = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new HiPayFinNetworkException("cURL error ({$curlErrno}): {$curlError}", $curlErrno);
        }

        $response = new Response($statusCode, $rawResponse);
        $decoded = $response->getDecodedJson();

        // Check for error condition
        if ($statusCode >= 400 || (is_array($decoded) && isset($decoded['error']) && $decoded['error'] !== null)) {
            $errorCode = '';
            $message = 'HiPayFin API Error';
            $meta = array();

            if (is_array($decoded)) {
                $errorCode = isset($decoded['code']) ? $decoded['code'] : (isset($decoded['error']) ? $decoded['error'] : '');
                $message = isset($decoded['error']) ? $decoded['error'] : (isset($decoded['message']) ? $decoded['message'] : $message);
                $meta = isset($decoded['meta']) && is_array($decoded['meta']) ? $decoded['meta'] : array();
            } else {
                $message = "HTTP {$statusCode}: {$rawResponse}";
            }

            throw new HiPayFinApiException($message, $statusCode, $errorCode, $meta);
        }

        return $response->getData();
    }
}

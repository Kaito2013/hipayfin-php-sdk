<?php
namespace Lucas\HiPayFin\Services;

use Lucas\HiPayFin\Http\HttpClientInterface;

abstract class AbstractService
{
    protected $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Remove null and empty string values to adhere to HiPayFin Strict Validation.
     *
     * @param array $params
     * @return array
     */
    protected function sanitizePayload(array $params)
    {
        $sanitized = array();
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (is_array($v)) {
                $sanitized[$k] = $this->sanitizePayload($v);
            } else {
                $sanitized[$k] = $v;
            }
        }
        return $sanitized;
    }

    protected function httpGet($path)
    {
        return $this->httpClient->request('GET', $path);
    }

    protected function httpPost($path, array $data = null, $idempotencyKey = null)
    {
        $cleanData = ($data !== null) ? $this->sanitizePayload($data) : null;
        return $this->httpClient->request('POST', $path, $cleanData, $idempotencyKey);
    }

    protected function get($path)
    {
        return $this->httpGet($path);
    }

    protected function post($path, array $data = null, $idempotencyKey = null)
    {
        return $this->httpPost($path, $data, $idempotencyKey);
    }
}

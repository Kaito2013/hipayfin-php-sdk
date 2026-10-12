<?php
namespace Lucas\HiPayFin\Http;

interface HttpClientInterface
{
    /**
     * Send HTTP request to HiPayFin API.
     *
     * @param string $method GET, POST
     * @param string $path relative path e.g. /payin/vnd/create
     * @param array|null $data
     * @param string|null $idempotencyKey
     * @return array The unwrapped 'data' array on success
     * @throws \Lucas\HiPayFin\Exceptions\HiPayFinApiException
     * @throws \Lucas\HiPayFin\Exceptions\HiPayFinNetworkException
     */
    public function request($method, $path, array $data = null, $idempotencyKey = null);
}

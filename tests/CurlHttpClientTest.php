<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Http\Response;

class CurlHttpClientTest
{
    public function testResponseParsingEnvelope()
    {
        $successJson = '{"data":{"order_ref":"PI-123","status":"CREATED"},"error":null}';
        $res = new Response(200, $successJson, array());

        if ($res->getStatusCode() !== 200) {
            throw new \Exception('getStatusCode failed');
        }
        $data = $res->getData();
        if ($data['order_ref'] !== 'PI-123') {
            throw new \Exception('data extraction failed');
        }
        if ($res->getError() !== null) {
            throw new \Exception('getError must be null on success');
        }
        return true;
    }
}

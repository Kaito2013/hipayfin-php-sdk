<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Exceptions\HiPayFinException;
use Lucas\HiPayFin\Exceptions\HiPayFinApiException;
use Lucas\HiPayFin\Exceptions\HiPayFinNetworkException;
use Lucas\HiPayFin\Exceptions\HiPayFinValidationException;
use Lucas\HiPayFin\Exceptions\HiPayFinSignatureException;

class ExceptionTest
{
    public function testApiExceptionEnvelopeData()
    {
        $details = array(array('field' => 'amount', 'message' => 'amount must match ^[1-9][0-9]*$'));
        $meta = array('existing_id' => 'PI-20261012001', 'details' => $details);
        $e = new HiPayFinApiException('Order duplicated', 409, 'DUPLICATE_REQUEST', $meta);

        if (!($e instanceof HiPayFinException)) {
            throw new \Exception('HiPayFinApiException must extend HiPayFinException');
        }
        if ($e->getHttpStatusCode() !== 409) {
            throw new \Exception('getHttpStatusCode failed');
        }
        if ($e->getErrorCode() !== 'DUPLICATE_REQUEST') {
            throw new \Exception('getErrorCode failed');
        }
        if ($e->getExistingId() !== 'PI-20261012001') {
            throw new \Exception('getExistingId failed');
        }
        $retMeta = $e->getMeta();
        if (!is_array($retMeta) || count($retMeta['details']) !== 1) {
            throw new \Exception('getMeta failed');
        }
        return true;
    }

    public function testNetworkException()
    {
        $e = new HiPayFinNetworkException('Connection timed out', 28);
        if ($e->getCurlErrorCode() !== 28) {
            throw new \Exception('getCurlErrorCode failed');
        }
        return true;
    }

    public function testValidationException()
    {
        $e = new HiPayFinValidationException('amount is invalid', 'amount');
        if ($e->getField() !== 'amount') {
            throw new \Exception('getField failed');
        }
        return true;
    }
}

<?php
namespace Lucas\HiPayFin\Exceptions;

class HiPayFinNetworkException extends HiPayFinException
{
    protected $curlErrorCode;

    public function __construct($message, $curlErrorCode = 0, \Exception $previous = null)
    {
        parent::__construct($message, (int)$curlErrorCode, $previous);
        $this->curlErrorCode = (int)$curlErrorCode;
    }

    public function getCurlErrorCode()
    {
        return $this->curlErrorCode;
    }
}

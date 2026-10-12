<?php
namespace Lucas\HiPayFin\Exceptions;

class HiPayFinApiException extends HiPayFinException
{
    protected $httpStatusCode;
    protected $errorCode;
    protected $meta;

    public function __construct($message, $httpStatusCode = 0, $errorCode = '', array $meta = array(), \Exception $previous = null)
    {
        parent::__construct($message, (int)$httpStatusCode, $previous);
        $this->httpStatusCode = (int)$httpStatusCode;
        $this->errorCode = (string)$errorCode;
        $this->meta = $meta;
    }

    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getMeta()
    {
        return $this->meta;
    }

    public function getExistingId()
    {
        if (isset($this->meta['existing_id'])) {
            return (string)$this->meta['existing_id'];
        }
        return null;
    }
}

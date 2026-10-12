<?php
namespace Lucas\HiPayFin\Http;

class Response
{
    protected $statusCode;
    protected $rawBody;
    protected $headers;
    protected $decodedJson;

    public function __construct($statusCode, $rawBody, array $headers = array())
    {
        $this->statusCode = (int)$statusCode;
        $this->rawBody = (string)$rawBody;
        $this->headers = $headers;
        $this->decodedJson = json_decode($this->rawBody, true);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getRawBody()
    {
        return $this->rawBody;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getDecodedJson()
    {
        return $this->decodedJson;
    }

    public function getData()
    {
        if (is_array($this->decodedJson) && array_key_exists('data', $this->decodedJson)) {
            return $this->decodedJson['data'];
        }
        return null;
    }

    public function getError()
    {
        if (is_array($this->decodedJson) && array_key_exists('error', $this->decodedJson)) {
            return $this->decodedJson['error'];
        }
        return null;
    }
}

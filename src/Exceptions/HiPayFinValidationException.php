<?php
namespace Lucas\HiPayFin\Exceptions;

class HiPayFinValidationException extends HiPayFinException
{
    protected $field;

    public function __construct($message, $field = '', $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, (int)$code, $previous);
        $this->field = (string)$field;
    }

    public function getField()
    {
        return $this->field;
    }
}

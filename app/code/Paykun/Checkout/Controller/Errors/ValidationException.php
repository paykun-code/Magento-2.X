<?php

namespace Paykun\Checkout\Controller\Errors;

class ValidationException extends Error
{
    protected $field = null;

    public function __construct($message, $code, $field = null)
    {
        parent::__construct($message, $code);

        $this->field = $field;
    }

    public function getField()
    {
        return $this->field;
    }
}
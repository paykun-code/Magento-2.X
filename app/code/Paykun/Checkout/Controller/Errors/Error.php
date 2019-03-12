<?php

namespace Paykun\Checkout\Controller\Errors;

use Exception;

class Error extends Exception
{
    public function __construct($message, $code)
    {
        $this->code = $code;

        $this->message = $message;
    }
}

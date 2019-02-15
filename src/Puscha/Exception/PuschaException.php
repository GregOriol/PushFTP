<?php

namespace Puscha\Exception;

use Throwable;

class PuschaException extends \Exception
{
    public function __construct($message = "", $code = 1, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

<?php

namespace Lomi\Payments\Gateway\Exception;

class ApiException extends \Exception
{
    /** @var int */
    protected $statusCode = 0;

    public function __construct($message = '', $statusCode = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = (int) $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

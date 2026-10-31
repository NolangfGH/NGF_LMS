<?php

namespace App\Exceptions;

class AiReviewUnavailable extends \RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=503)
    {
        parent::__construct($message);
    }
}

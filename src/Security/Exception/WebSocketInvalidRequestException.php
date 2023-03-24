<?php

namespace App\Security\Exception;

class WebSocketInvalidRequestException extends \Exception
{
    public const FATAL_ERROR = true;
    public const NOT_FATAL_ERROR = false;

    public function __construct(string $message = 'Requête invalide', private bool $isFatal = self::NOT_FATAL_ERROR)
    {
        parent::__construct($message);
    }

    public function isFatal()
    {
        return $this->isFatal;
    }
}
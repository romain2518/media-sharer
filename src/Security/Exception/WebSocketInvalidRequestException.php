<?php

namespace App\Security\Exception;

class WebSocketInvalidRequestException extends \Exception
{
    public function __construct(string $message = 'Requête invalide')
    {
        parent::__construct($message);
    }
}
<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Throwable;

class WebTestCaseMessageBeautifier extends WebTestCase
{
    /**
    * Override PHPUnit fail method
    * to catch "assertResponse" exceptions
    * 
    * @link https://devdocs.io/phpunit~9/fixtures
    */
    protected function onNotSuccessfulTest(Throwable $t): void
    {
        // If "assertResponse" is found in the trace, custom message
        if (strpos($t->getTraceAsString(), 'assertResponse') > 0) {
            $arrayMessage = explode("\n", $t->getMessage());
            $message = $arrayMessage[0] . "\n" . $arrayMessage[1];
            $this->fail($message);
        }

        // Other Exceptions
        throw $t;
    }
}
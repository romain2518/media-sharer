<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MainTest extends WebTestCase
{
    public function testHome(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Media sharer');
    }

    public function testLegalMentions(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/mentions-legales');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('section:last-child h2', 'Mentions légales');
    }

    public function testPrivacyPolicy(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/politique-de-confidentialite');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('section:last-child h2', 'Politique de confidentialité');
    }

    public function testTerms(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/cgu');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('section:last-child h2', 'Conditions Générales d\'Utilisation');
    }
}

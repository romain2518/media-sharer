<?php

namespace App\Tests;

use App\Repository\UserRepository;

class MainTest extends WebTestCaseMessageBeautifier
{
    public function testHomeAnonymously(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseRedirects('/connexion');
    }

    public function testHomeLoggedIn(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findBy([], null, 1)[0];
        $client->loginUser($user);

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Media sharer');
    }

    public function testLegalMentions(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mentions-legales');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('section:last-child h2', 'Mentions légales');
    }

    public function testPrivacyPolicy(): void
    {
        $client = static::createClient();
        $client->request('GET', '/politique-de-confidentialite');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('section:last-child h2', 'Politique de confidentialité');
    }

    public function testTerms(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cgu');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('section:last-child h2', 'Conditions Générales d\'Utilisation');
    }
}

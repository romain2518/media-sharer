<?php

namespace App\Tests;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class LoginTest extends WebTestCaseMessageBeautifier
{
    public function testLoginWithValidData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connexion');

        $this->assertResponseIsSuccessful();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findBy([], null, 1)[0];

        $client->submitForm('Se connecter', [
            'email' => $user->getEmail(),
            'password' => 'J\ai 19 ans.',
        ]);
        
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
    }

    public function testLoginWithInvalidData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connexion');

        $this->assertResponseIsSuccessful();

        $client->submitForm('Se connecter', [
            'email' => 'unused@mail.com',
            'password' => 'Bad password',
        ]);
        
        $this->assertResponseRedirects('/connexion');
    }

    public function testLogout(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findBy([], null, 1)[0];
        $client->loginUser($user);

        $client->request('GET', '/deconnexion');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
    }

    public function testLoginWithNonVerifiedUser()
    {
        $client = static::createClient();
        $client->request('GET', '/connexion');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findBy([], null, 1)[0];

        $user->setIsVerified(false);
        $entityManager->flush();

        $client->submitForm('Se connecter', [
            'email' => $user->getEmail(),
            'password' => 'J\'ai 19 ans.',
        ]);

        $this->assertResponseRedirects('/verification/renvoi/' . $user->getId());
    }

    public function testLoginThrottling()
    {
        $client = static::createClient();
        
        for ($i=0; $i < 6; $i++) { 
            $client->request('GET', '/connexion');
            
            $client->submitForm('Se connecter', [
                'email' => 'unused@gmail.com',
                'password' => 'Bad password',
            ]);

            $client->followRedirect(true);
        }

        $this->assertSelectorTextSame('p', 'Plusieurs tentatives de connexion ont échoué, veuillez réessayer dans 1 minute.');        
    }
}

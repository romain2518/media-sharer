<?php

namespace App\Tests;

use App\Entity\User;
use App\Repository\BanRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;

class BanTest extends WebTestCaseMessageBeautifier
{
    public function testList(): void
    {
        $client = static::createClient();

        $client->request('GET', '/gestion/ban');
        $this->assertResponseRedirects('/connexion');

        $userRepository = static::getContainer()->get(UserRepository::class);

        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]
        
        $client->loginUser($users[2]);
        $client->request('GET', '/gestion/ban');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->loginUser($users[1]);
        $client->request('GET', '/gestion/ban');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Nouveau bannissement');

        $client->loginUser($users[0]);
        $client->request('GET', '/gestion/ban');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Nouveau bannissement');
    }

    /**
     * @dataProvider getAddDatas
     */
    public function testBan(array $data, bool $testShouldBeSuccessful): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]
        
        $client->loginUser($users[1]);
        $client->request('GET', '/gestion/ban');
        $this->assertResponseIsSuccessful();
        
        $client->submitForm('Bannir', $data);
        
        if ($testShouldBeSuccessful) {
            $this->assertResponseRedirects('/gestion/ban');
            $client->followRedirect();
            $this->assertResponseIsSuccessful();
        } else {
            $this->assertResponseIsUnprocessable();
        }
    }

    public function testBanUsedEmail()
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]

        $targetedUser = new User();
        $targetedUser
            ->setPseudo('test')
            ->setEmail(uniqid().'mail@mail.com')
            ->setRoles(['ROLE_USER'])
            ->setPassword('aaaa')
        ;
        $userRepository->save($targetedUser, true);
        
        $client->loginUser($users[1]);
        $client->request('GET', '/gestion/ban');
        $this->assertResponseIsSuccessful();
        
        $client->submitForm('Bannir', [
            'ban[email]' => $targetedUser->getEmail(),
            'ban[comment]' => '',
        ]);
        
        $this->assertResponseRedirects('/gestion/ban');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        // $userRepository must be reset to access updated datas
        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $targetedUserPostBan = $userRepository->find($targetedUser->getId());
        $this->assertNull($targetedUserPostBan);
    }

    public function testBanSuperiorRole(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]
        
        $client->loginUser($users[1]);
        $client->request('GET', '/gestion/ban');
        $this->assertResponseIsSuccessful();
        
        $client->submitForm('Bannir', [
            'ban[email]' => $users[0]->getEmail(),
            'ban[comment]' => '',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDelete(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $banRepository = static::getContainer()->get(BanRepository::class);

        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]
        
        $client->loginUser($users[1]);
        $crawler = $client->request('GET', '/gestion/ban/date');
        $this->assertResponseIsSuccessful();

        $deleteURL = $crawler->filter('article:first-of-type form')->extract(['action'])[0];
        $targetedBanId = explode('/', $deleteURL)[3];
        $deleteToken = $crawler->filter('article:first-of-type form input[name=_token]')->extract(['value'])[0];
        
        //? Bad token test
        $client->request('POST', $deleteURL, ['_token' => 'Not a valid token']);

        $this->assertResponseRedirects('/gestion/ban');

        // $banRepository must be reset to access updated datas
        $banRepository = static::getContainer()->get(BanRepository::class);

        $targetedBan = $banRepository->find($targetedBanId);
        $this->assertNotNull($targetedBan);

        //? Delete test
        $client->request('POST', $deleteURL, ['_token' => $deleteToken]);

        $this->assertResponseRedirects('/gestion/ban');
        
        // $banRepository must be reset to access updated datas
        $banRepository = static::getContainer()->get(BanRepository::class);
        
        $targetedBan = $banRepository->find($targetedBanId);
        $this->assertNull($targetedBan);
    }

    public function getAddDatas(): array
    {
        return [
            'Valid datas' => [
                [
                    'ban[email]' => uniqid().'mail@mail.com',
                    'ban[comment]' => 'A good comment',
                ],
                true,
            ],
            'Email invalid' => [
                [
                    'ban[email]' => 'Not a valid email',
                    'ban[comment]' => 'A good comment',
                ],
                false,
            ],
            'Email min length' => [
                [
                    'ban[email]' => 'a@',
                    'ban[comment]' => 'A good comment',
                ],
                false,
            ],
            'Email max length' => [
                [
                    'ban[email]' => str_repeat('a', 180) . '@mail.com',
                    'ban[comment]' => 'A good comment',
                ],
                false,
            ],
            'Email blank' => [
                [
                    'ban[email]' => '',
                    'ban[comment]' => 'A good comment',
                ],
                false,
            ],
            'Email null' => [
                [
                    'ban[email]' => null,
                    'ban[comment]' => 'A good comment',
                ],
                false,
            ],
            'Comment max length' => [
                [
                    'ban[email]' => uniqid().'mail@mail.com',
                    'ban[comment]' => str_repeat('a', 256),
                ],
                false,
            ],
        ];
    }

}

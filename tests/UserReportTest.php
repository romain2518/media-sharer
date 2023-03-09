<?php

namespace App\Tests;

use App\Repository\UserReportRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;

class UserReportTest extends WebTestCaseMessageBeautifier
{
    public function testList(): void
    {
        $client = static::createClient();

        $client->request('GET', '/gestion/signalement/utilisateur');
        $this->assertResponseRedirects('/connexion');

        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findBy([], ['id' => 'DESC'], 1)[0];
        $adminUser = $userRepository->findBy([], null, 1)[0];
        
        
        $client->loginUser($user);
        $client->request('GET', '/gestion/signalement/utilisateur');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->loginUser($adminUser);
        $client->request('GET', '/gestion/signalement/utilisateur');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Liste des signalements d\'utilisateurs');
    }

    /**
     * @dataProvider getAddDatas
     */
    public function testAdd(?string $comment, ?string $reportedUser, bool $testShouldBeSuccessful, bool $expectInvalidArgExc = false): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findBy([], null, 1)[0];
        $client->loginUser($user);

        $client->request('GET', '/signalement/utilisateur');
        $this->assertResponseIsSuccessful();

        if ($expectInvalidArgExc) {
            $this->expectException('InvalidArgumentException');
        }

        if (null === $reportedUser) {
            $client->submitForm('Soumettre le signalement', [
                'user_report[comment]' => $comment,
            ]);
        } else {
            $client->submitForm('Soumettre le signalement', [
                'user_report[comment]' => $comment,
                'user_report[reportedUser]' => $reportedUser === 'default' ? $user->getId() + "1" : $reportedUser,
            ]);
        }
        
        if ($testShouldBeSuccessful) {
            $this->assertResponseRedirects('/');
            $client->followRedirect();
            $this->assertResponseIsSuccessful();
        } else {
            $this->assertResponseIsUnprocessable();
        }
    }

    public function testMarkAs()
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findBy([], null, 1)[0];
        
        $client->loginUser($user);
        $crawler = $client->request('GET', '/gestion/signalement/utilisateur');
        $this->assertResponseIsSuccessful();

        $targetedUserReportId = $crawler->filter('article:first-of-type input[type=checkbox]:first-child')->extract(['data-id'])[0];
        $processedToken = $crawler->filter("input#checkbox_processed$targetedUserReportId")->extract(['data-token'])[0];
        $importantToken = $crawler->filter("input#checkbox_important$targetedUserReportId")->extract(['data-token'])[0];
        
        //? Bad token test
        $client->request('POST', "/gestion/signalement/utilisateur/$targetedUserReportId/marquer-comme-traite", ['_token' => 'Not a valid token']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        //? Proccessed test
        $client->request('POST', "/gestion/signalement/utilisateur/$targetedUserReportId/marquer-comme-traite", ['_token' => $processedToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        
        $processedRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"isProcessed":true', $processedRequestContent);

        //? Unproccessed test
        $client->request('POST', "/gestion/signalement/utilisateur/$targetedUserReportId/marquer-comme-non-traite", ['_token' => $processedToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        
        $unprocessedRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"isProcessed":false', $unprocessedRequestContent);

        //? Important test
        $client->request('POST', "/gestion/signalement/utilisateur/$targetedUserReportId/marquer-comme-important", ['_token' => $importantToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        
        $importantRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"isImportant":true', $importantRequestContent);

        //? Not important test
        $client->request('POST', "/gestion/signalement/utilisateur/$targetedUserReportId/marquer-comme-non-important", ['_token' => $importantToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        
        $notImportantRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"isImportant":false', $notImportantRequestContent);
    }

    public function testDelete(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $userReportRepository = static::getContainer()->get(UserReportRepository::class);
        $user = $userRepository->findBy([], null, 1)[0];
        
        $client->loginUser($user);
        $crawler = $client->request('GET', '/gestion/signalement/utilisateur');
        $this->assertResponseIsSuccessful();

        $targetedUserReportId = $crawler->filter('article:first-of-type input[type=checkbox]:first-child')->extract(['data-id'])[0];
        $deleteToken = $crawler->filter('article:first-of-type .actions>form input[name=_token]')->extract(['value'])[0];
        
        //? Bad token test
        $client->request('POST', "/gestion/signalement/utilisateur/$targetedUserReportId/suppression", ['_token' => 'Not a valid token']);

        $this->assertResponseRedirects('/gestion/signalement/utilisateur');

        $userReport = $userReportRepository->find($targetedUserReportId);
        $this->assertNotNull($userReport);

        //? Delete test
        $client->request('POST', "/gestion/signalement/utilisateur/$targetedUserReportId/suppression", ['_token' => $deleteToken]);

        $this->assertResponseRedirects('/gestion/signalement/utilisateur');
        
        // $userReportRepository must be reset to access updated datas
        $userReportRepository = static::getContainer()->get(UserReportRepository::class);
        
        $userReport = $userReportRepository->find($targetedUserReportId);
        $this->assertNull($userReport);
    }

    public function getAddDatas(): array
    {
        return [
            'Valid datas' => [
                'comment' => 'A good report comment',
                'reportedUser' => 'default',
                true,
            ],
            'Comment min length' => [
                'comment' => 'a',
                'reportedUser' => 'default',
                false,
            ],
            'Comment max length' => [
                'comment' => str_repeat('a', 256),
                'reportedUser' => 'default',
                false,
            ],
            'Comment blank' => [
                'comment' => '',
                'reportedUser' => 'default',
                false,
            ],
            'Comment null' => [
                'comment' => null,
                'reportedUser' => 'default',
                false,
            ],
            'User not existing' => [
                'comment' => 'A good report comment',
                'reportedUser' => '1',
                false,
                true,
            ],
            'User blank' => [
                'comment' => 'A good report comment',
                'reportedUser' => '',
                false,
            ],
            'User null' => [
                'comment' => 'A good report comment',
                'reportedUser' => null,
                false,
            ],
        ];
    }

}

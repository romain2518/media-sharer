<?php

namespace App\Tests;

use App\Repository\BugReportRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;

class BugReportTest extends WebTestCaseMessageBeautifier
{
    public function testList(): void
    {
        $client = static::createClient();

        $client->request('GET', '/gestion/signalement/bug');
        $this->assertResponseRedirects('/connexion');

        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findBy([], ['id' => 'DESC'], 1)[0];
        $adminUser = $userRepository->findBy([], null, 1)[0];
        
        
        $client->loginUser($user);
        $client->request('GET', '/gestion/signalement/bug');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->loginUser($adminUser);
        $client->request('GET', '/gestion/signalement/bug');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Liste des signalements de bug');
    }

    /**
     * @dataProvider getAddDatas
     */
    public function testAdd(array $data, bool $testShouldBeSuccessful): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findBy([], null, 1)[0];
        $client->loginUser($user);

        $client->request('GET', '/signalement/bug');
        $this->assertResponseIsSuccessful();


        $client->submitForm('Soumettre le signalement', $data);
        
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
        $crawler = $client->request('GET', '/gestion/signalement/bug');
        $this->assertResponseIsSuccessful();

        $targetedBugReportId = $crawler->filter('article:first-of-type input[type=checkbox]:first-child')->extract(['data-id'])[0];
        $processedToken = $crawler->filter("input#checkbox_processed$targetedBugReportId")->extract(['data-token'])[0];
        $importantToken = $crawler->filter("input#checkbox_important$targetedBugReportId")->extract(['data-token'])[0];
        
        //? Bad token test
        $client->request('POST', "/gestion/signalement/bug/$targetedBugReportId/marquer-comme-traite", ['_token' => 'Not a valid token']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        //? Proccessed test
        $client->request('POST', "/gestion/signalement/bug/$targetedBugReportId/marquer-comme-traite", ['_token' => $processedToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        
        $processedRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"isProcessed":true', $processedRequestContent);

        //? Unproccessed test
        $client->request('POST', "/gestion/signalement/bug/$targetedBugReportId/marquer-comme-non-traite", ['_token' => $processedToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        
        $unprocessedRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"isProcessed":false', $unprocessedRequestContent);

        //? Important test
        $client->request('POST', "/gestion/signalement/bug/$targetedBugReportId/marquer-comme-important", ['_token' => $importantToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        
        $importantRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"isImportant":true', $importantRequestContent);

        //? Not important test
        $client->request('POST', "/gestion/signalement/bug/$targetedBugReportId/marquer-comme-non-important", ['_token' => $importantToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        
        $notImportantRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"isImportant":false', $notImportantRequestContent);
    }

    public function testDelete(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bugReportRepository = static::getContainer()->get(BugReportRepository::class);
        $user = $userRepository->findBy([], null, 1)[0];
        
        $client->loginUser($user);
        $crawler = $client->request('GET', '/gestion/signalement/bug');
        $this->assertResponseIsSuccessful();

        $targetedBugReportId = $crawler->filter('article:first-of-type input[type=checkbox]:first-child')->extract(['data-id'])[0];
        $deleteToken = $crawler->filter('article:first-of-type .actions>form input[name=_token]')->extract(['value'])[0];
        
        //? Bad token test
        $client->request('POST', "/gestion/signalement/bug/$targetedBugReportId/suppression", ['_token' => 'Not a valid token']);

        $this->assertResponseRedirects('/gestion/signalement/bug');

        $bugReport = $bugReportRepository->find($targetedBugReportId);
        $this->assertNotNull($bugReport);

        //? Delete test
        $client->request('POST', "/gestion/signalement/bug/$targetedBugReportId/suppression", ['_token' => $deleteToken]);

        $this->assertResponseRedirects('/gestion/signalement/bug');
        
        // $bugReportRepository must be reset to access updated datas
        $bugReportRepository = static::getContainer()->get(BugReportRepository::class);
        
        $bugReport = $bugReportRepository->find($targetedBugReportId);
        $this->assertNull($bugReport);
    }

    public function getAddDatas(): array
    {
        return [
            'Valid datas' => [
                [
                    'bug_report[url]' => 'http://valid-url.com',
                    'bug_report[comment]' => 'A good report comment',
                ],
                true,
            ],
            'URL invalid' => [
                [
                    'bug_report[url]' => 'not a valid url',
                    'bug_report[comment]' => 'A good report comment',
                ],
                false,
            ],
            'URL min length' => [
                [
                    'bug_report[url]' => 'a',
                    'bug_report[comment]' => 'A good report comment',
                ],
                false,
            ],
            'URL max length' => [
                [
                    'bug_report[url]' => str_repeat('a', 256).'.com',
                    'bug_report[comment]' => 'A good report comment',
                ],
                false,
            ],
            'URL blank' => [
                [
                    'bug_report[url]' => '',
                    'bug_report[comment]' => 'A good report comment',
                ],
                false,
            ],
            'URL null' => [
                [
                    'bug_report[url]' => null,
                    'bug_report[comment]' => 'A good report comment',
                ],
                false,
            ],
            'Comment min length' => [
                [
                    'bug_report[url]' => 'http://valid-url.com',
                    'bug_report[comment]' => 'a',
                ],
                false,
            ],
            'Comment max length' => [
                [
                    'bug_report[url]' => 'http://valid-url.com',
                    'bug_report[comment]' => str_repeat('a', 256),
                ],
                false,
            ],
            'Comment blank' => [
                [
                    'bug_report[url]' => 'http://valid-url.com',
                    'bug_report[comment]' => '',
                ],
                false,
            ],
            'Comment null' => [
                [
                    'bug_report[url]' => 'http://valid-url.com',
                    'bug_report[comment]' => null,
                ],
                false,
            ],
        ];
    }

}

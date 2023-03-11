<?php

namespace App\Tests;

use App\Repository\PatchNoteRepository;
use App\Repository\UserRepository;

class PatchNoteTest extends WebTestCaseMessageBeautifier
{
    public function testList(): void
    {
        $client = static::createClient();

        $client->request('GET', '/notes-de-maj');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('section:last-child h2', 'Notes de mise à jour');
    }

    /**
     * @dataProvider getAddDatas
     */
    public function testAdd(array $data, bool $testShouldBeSuccessful): void
    {
        $client = static::createClient();
        
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findBy([], null, 1)[0];

        $client->request('GET', '/gestion/note-de-maj/ajout');
        $this->assertResponseRedirects('/connexion');

        $client->loginUser($user);
        $client->request('GET', '/gestion/note-de-maj/ajout');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Enregistrer', $data);
        
        if ($testShouldBeSuccessful) {
            $this->assertResponseRedirects('/notes-de-maj');
            $client->followRedirect();
            $this->assertResponseIsSuccessful();
        } else {
            $this->assertResponseIsUnprocessable();
        }
    }

    /**
     * @dataProvider getEditDatas
     */
    public function testEdit(array $data, bool $testShouldBeSuccessful): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $patchNoteRepository = static::getContainer()->get(PatchNoteRepository::class);

        $user = $userRepository->findBy([], null, 1)[0];
        $patchNote = $patchNoteRepository->findBy([], ['id' => 'DESC'], 1)[0];

        $client->request('GET', '/gestion/note-de-maj/' . $patchNote->getId() . '/modification');
        $this->assertResponseRedirects('/connexion');

        $client->loginUser($user);
        $client->request('GET', '/gestion/note-de-maj/' . $patchNote->getId() . '/modification');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Modifier', $data);
        
        if ($testShouldBeSuccessful) {
            $this->assertResponseRedirects('/notes-de-maj');
            $client->followRedirect();
            $this->assertResponseIsSuccessful();
        } else {
            $this->assertResponseIsUnprocessable();
        }
    }

    public function testDelete(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $patchNoteRepository = static::getContainer()->get(PatchNoteRepository::class);

        $user = $userRepository->findBy([], null, 1)[0];
        $patchNote = $patchNoteRepository->findBy([], ['id' => 'DESC'], 1)[0];

        $client->loginUser($user);
        $crawler = $client->request('GET', '/notes-de-maj');
        $this->assertResponseIsSuccessful();

        $deleteToken = $crawler->filter('section:last-child li:first-child article .dropdown-content form input[name=_token]')->extract(['value'])[0];
        
        //? Bad token test
        $client->request('POST', '/gestion/note-de-maj/' . $patchNote->getId() . '/suppression', ['_token' => 'Not a valid token']);

        $this->assertResponseRedirects('/notes-de-maj');

        // $patchNoteRepository must be reset to access updated datas
        $patchNoteRepository = static::getContainer()->get(PatchNoteRepository::class);
        
        $postTestPatchNote = $patchNoteRepository->find($patchNote->getId());
        $this->assertNotNull($postTestPatchNote);

        //? Delete test
        $client->request('POST', '/gestion/note-de-maj/' . $patchNote->getId() . '/suppression', ['_token' => $deleteToken]);

        $this->assertResponseRedirects('/notes-de-maj');
        
        // $patchNoteRepository must be reset to access updated datas
        $patchNoteRepository = static::getContainer()->get(PatchNoteRepository::class);
        
        $postTestPatchNote = $patchNoteRepository->find($patchNote->getId());
        $this->assertNull($postTestPatchNote);
    }

    public function getAddDatas(): array
    {
        return [
            'Valid datas' => [
                [
                    'patch_note[title]' => 'A nice title',
                    'patch_note[note]' => 'A pretty nice patch note',
                ],
                true,
            ],
            'Title min length' => [
                [
                    'patch_note[title]' => 'a',
                    'patch_note[note]' => 'A pretty nice patch note',
                ],
                false,
            ],
            'Title max length' => [
                [
                    'patch_note[title]' => str_repeat('a', 256),
                    'patch_note[note]' => 'A pretty nice patch note',
                ],
                false,
            ],
            'Title blank' => [
                [
                    'patch_note[title]' => '',
                    'patch_note[note]' => 'A pretty nice patch note',
                ],
                false,
            ],
            'Title null' => [
                [
                    'patch_note[title]' => null,
                    'patch_note[note]' => 'A pretty nice patch note',
                ],
                false,
            ],
            'Note min length' => [
                [
                    'patch_note[title]' => 'A nice title',
                    'patch_note[note]' => 'a',
                ],
                false,
            ],
            'Note max length' => [
                [
                    'patch_note[title]' => 'A nice title',
                    'patch_note[note]' => str_repeat('a', 2001),
                ],
                false,
            ],
            'Note blank' => [
                [
                    'patch_note[title]' => 'A nice title',
                    'patch_note[note]' => '',
                ],
                false,
            ],
            'Note null' => [
                [
                    'patch_note[title]' => 'A nice title',
                    'patch_note[note]' => null,
                ],
                false,
            ],
        ];
    }

    public function getEditDatas(): array
    {
        return [
            'Valid datas' => [
                [
                    'patch_note[title]' => 'A nice title',
                    'patch_note[note]' => 'A pretty nice patch note',
                ],
                true,
            ],
            'Title min length' => [
                [
                    'patch_note[title]' => 'a',
                    'patch_note[note]' => 'A pretty nice patch note',
                ],
                false,
            ],
            'Title max length' => [
                [
                    'patch_note[title]' => str_repeat('a', 256),
                    'patch_note[note]' => 'A pretty nice patch note',
                ],
                false,
            ],
            'Note min length' => [
                [
                    'patch_note[title]' => 'A nice title',
                    'patch_note[note]' => 'a',
                ],
                false,
            ],
            'Note max length' => [
                [
                    'patch_note[title]' => 'A nice title',
                    'patch_note[note]' => str_repeat('a', 2001),
                ],
                false,
            ],
        ];
    }
}

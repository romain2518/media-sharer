<?php

namespace App\Tests;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserTest extends WebTestCaseMessageBeautifier
{
    /**
     * @dataProvider getLoginsDatas
     */
    public function testEditLogins(array $data, bool $testShouldBeSuccessful): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = $userRepository->findBy([], null, 1)[0];

        // User password must be J'ai 19 ans.
        $user->setPassword(
            $passwordHasher->hashPassword(
                $user,
                'J\'ai 19 ans.'
            )
        );

        $entityManager->flush();

        $client->loginUser($user);

        $client->request('GET', '/modification-des-identifiants');

        $this->assertResponseIsSuccessful();
        
        $client->submitForm('Modifier les identifiants', $data);
        
        if ($testShouldBeSuccessful) {
            $this->assertResponseRedirects('/');
            
            $mailerEvent = $this->getMailerEvent();
            $this->assertEmailIsQueued($mailerEvent);
        } else {
            $this->assertResponseIsUnprocessable();
        }
    }

    /**
     * @dataProvider getProfileDatas
     */
    public function testEditProfile(array $data, bool $testShouldBeSuccessful): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findBy([], null, 1)[0];

        $client->loginUser($user);

        $client->request('GET', '/modification-du-profil');

        $this->assertResponseIsSuccessful();
        
        $client->submitForm('Modifier le profil', $data);
        
        if ($testShouldBeSuccessful) {
            $this->assertResponseRedirects('/');
            $client->followRedirect();
            $this->assertResponseIsSuccessful();
        } else {
            $this->assertResponseIsUnprocessable();
        }
    }

    public function testDeleteAccount(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Searching for the last user instead of the first
        // The first 2 users may be used by other tests
        $user = $userRepository->findBy([], ['id' => 'DESC'], 1)[0];

        // User password must be J'ai 19 ans.
        $user->setPassword(
            $passwordHasher->hashPassword(
                $user,
                'J\'ai 19 ans.'
            )
        );

        $entityManager->flush();

        $client->loginUser($user);

        $client->request('GET', '/suppression-du-compte');

        $this->assertResponseIsSuccessful();
        
        $client->submitForm('Supprimer le compte', [
            'delete_account[password]' => 'Wrong password'
        ]);

        $this->assertResponseIsUnprocessable();

        $client->submitForm('Supprimer le compte', [
            'delete_account[password]' => 'J\'ai 19 ans.'
        ]);

        $this->assertResponseRedirects('/inscription');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testGetBlockedUsers(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findBy([], null, 1)[0];

        $client->loginUser($user);

        $client->request('GET', '/utilisateurs-bloques');

        $this->assertResponseIsSuccessful();
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testBlockSelf(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $user = $userRepository->findBy([], null, 1)[0];
        $client->loginUser($user);
        
        //? Getting CSRF Tokens
        $crawler = $client->request('GET', '/');
        $blockToken = $crawler->filter('input[name=block_user_token]')->extract(['value'])[0];
        $unblockToken = $crawler->filter('input[name=unblock_user_token]')->extract(['value'])[0];

        //? Blocking self test
        $client->request('POST', '/utilisateurs-bloques/' . $user->getId() . '/ajout', ['token' => $blockToken]);
        
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        
        //? Unblocking self test
        $client->request('POST', '/utilisateurs-bloques/' . $user->getId() . '/suppression', ['token' => $unblockToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testBlockUser(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findBy([], null, 1)[0];
        $client->loginUser($user);
        
        //? Getting CSRF Token
        $crawler = $client->request('GET', '/');
        $blockToken = $crawler->filter('input[name=block_user_token]')->extract(['value'])[0];

        //? Bad token test
        $client->request('POST', '/utilisateurs-bloques/' . $user->getId()+1 . '/ajout', ['token' => 'Not a valid token']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        //? Block test
        $client->request('POST', '/utilisateurs-bloques/' . $user->getId()+1 . '/ajout', ['token' => $blockToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        $blockRequestContent = $client->getResponse()->getContent();

        $client->request('GET', '/utilisateurs-bloques');

        $this->assertStringContainsString($blockRequestContent, $client->getResponse()->getContent());
    }

    public function testUnblockUser(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findBy([], null, 1)[0];        
        $client->loginUser($user);
        
        //? Getting CSRF Token
        $crawler = $client->request('GET', '/');
        $unblockToken = $crawler->filter('input[name=unblock_user_token]')->extract(['value'])[0];

        //? Bad token test
        $client->request('POST', '/utilisateurs-bloques/' . $user->getId()+1 . '/suppression', ['token' => 'Not a valid token']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        //? Unblock test
        $client->request('POST', '/utilisateurs-bloques/' . $user->getId()+1 . '/suppression', ['token' => $unblockToken]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        $unblockRequestContent = $client->getResponse()->getContent();

        $client->request('POST', '/utilisateurs-bloques');

        $this->assertStringNotContainsString($unblockRequestContent, $client->getResponse()->getContent());
    }

    public function testManageList(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]
        
        $client->request('GET', '/gestion/utilisateur');
        $this->assertResponseRedirects('/connexion');

        $client->loginUser($users[2]);
        $client->request('GET', '/gestion/utilisateur');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->loginUser($users[1]);
        $client->request('GET', '/gestion/utilisateur');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Liste des utilisateurs');
        $this->assertSelectorNotExists('select.POST_CTA');

        $client->loginUser($users[0]);
        $client->request('GET', '/gestion/utilisateur');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Liste des utilisateurs');
        $this->assertSelectorExists('select.POST_CTA');
    }

    public function testResetPseudo(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]
        
        $client->loginUser($users[1]);
        
        //? Getting CSRF Token
        $crawler = $client->request('GET', '/gestion/utilisateur');
        $extracts = $crawler->filter('section>article:first-of-type')->extract(['data-id', 'data-token'])[0];
        
        //? Making sure that targeted user only has role ROLE_USER and has a valid pseudo
        $targetedUser = $userRepository->find($extracts[0]);
        $targetedUser->setPseudo('This is a pseudo');
        $targetedUser->setRoles(['ROLE_USER']);
        $entityManager->flush();

        //? Bad token test
        $client->request('POST', '/gestion/utilisateur/' . $extracts[0] . '/reinitialisation-pseudo', ['_token' => 'Not a valid token']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        //? Reset test on a user with superior role (admin trying to manage superadmin)
        $client->request('POST', '/gestion/utilisateur/' . $users[0]->getId() . '/reinitialisation-pseudo', ['_token' => $extracts[1]]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        //? Reset test
        $client->request('POST', '/gestion/utilisateur/' . $extracts[0] . '/reinitialisation-pseudo', ['_token' => $extracts[1]]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        $resetRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"pseudo":"Membre #'.$extracts[0].'"', $resetRequestContent);
    }

    public function testResetPicture(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]
        
        $client->loginUser($users[1]);
        
        //? Getting CSRF Token
        $crawler = $client->request('GET', '/gestion/utilisateur');
        $extracts = $crawler->filter('section>article:first-of-type')->extract(['data-id', 'data-token'])[0];
        
        //? Making sure that targeted user only has role ROLE_USER and has a valid picture
        $targetedUser = $userRepository->find($extracts[0]);
        $targetedUser->setPicturePath('valid-pic.png');
        $targetedUser->setRoles(['ROLE_USER']);
        $entityManager->flush();

        //? Bad token test
        $client->request('POST', '/gestion/utilisateur/' . $extracts[0] . '/reinitialisation-photo-de-profil', ['_token' => 'Not a valid token']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        //? Reset test on a user with superior role (admin trying to manage superadmin)
        $client->request('POST', '/gestion/utilisateur/' . $users[0]->getId() . '/reinitialisation-photo-de-profil', ['_token' => $extracts[1]]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        //? Reset test
        $client->request('POST', '/gestion/utilisateur/' . $extracts[0] . '/reinitialisation-photo-de-profil', ['_token' => $extracts[1]]);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        $resetRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"picturePath":null', $resetRequestContent);
    }

    public function testEditRole(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]
        
        //! Testing with admin
        $client->loginUser($users[1]);

        //? Getting CSRF Token
        $crawler = $client->request('GET', '/gestion/utilisateur');
        $extracts = $crawler->filter('section>article:first-of-type')->extract(['data-id', 'data-token'])[0];
        
        $client->request('POST', '/gestion/utilisateur/' . $extracts[0] . '/modification-role', ['_token' => $extracts[1], 'role' => 'ROLE_USER']);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        //! Testing with Super admin
        $client->loginUser($users[0]);
        
        //? Getting CSRF Token
        $crawler = $client->request('GET', '/gestion/utilisateur');
        $extracts = $crawler->filter('section>article:first-of-type')->extract(['data-id', 'data-token'])[0];
        
        //? Making sure that targeted user only has role ROLE_USER
        $targetedUser = $userRepository->find($extracts[0]);
        $targetedUser->setRoles(['ROLE_USER']);
        $entityManager->flush();

        //? Bad token test
        $client->request('POST', '/gestion/utilisateur/' . $extracts[0] . '/modification-role', ['_token' => 'Not a valid token', 'role' => 'ROLE_ADMIN']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        //? Edit role test on a SUPERADMIN
        $client->request('POST', '/gestion/utilisateur/' . $users[0]->getId() . '/modification-role', ['_token' => $extracts[1], 'role' => 'ROLE_ADMIN']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));

        //? Edit role test set undefined role
        $client->request('POST', '/gestion/utilisateur/' . $extracts[0] . '/modification-role', ['_token' => $extracts[1], 'role' => 'ROLE_UNDEFINED']);
                
        $this->assertResponseIsUnprocessable();
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        
        //? Edit role test set admin
        $client->request('POST', '/gestion/utilisateur/' . $extracts[0] . '/modification-role', ['_token' => $extracts[1], 'role' => 'ROLE_ADMIN']);
        
        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        $resetRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"roles":["ROLE_ADMIN","ROLE_USER"]', $resetRequestContent);
        
        //? Edit role test set user
        $client->request('POST', '/gestion/utilisateur/' . $extracts[0] . '/modification-role', ['_token' => $extracts[1], 'role' => 'ROLE_USER']);

        $this->assertResponseStatusCodeSame(Response::HTTP_PARTIAL_CONTENT);
        $this->assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        $resetRequestContent = $client->getResponse()->getContent();
        $this->assertStringContainsString('"roles":["ROLE_USER"]', $resetRequestContent);
    }

    public function getLoginsDatas(): array
    {
        return [
            'Edit email only' => [
                [
                    'edit_logins[email]' => uniqid().'mail@mail.com',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    // 'edit_logins[newPassword][first]' => '',
                    // 'edit_logins[newPassword][second]' => '',
                ],
                true,
            ],
            'Edit email & password' => [
                [
                    'edit_logins[email]' => uniqid().'mail@mail.com',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    'edit_logins[newPassword][first]' => 'J\'ai 20 ans.',
                    'edit_logins[newPassword][second]' => 'J\'ai 20 ans.',
                ],
                true,
            ],
            'Email invalid' => [
                [
                    'edit_logins[email]' => 'Not a valid email',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    // 'edit_logins[newPassword][first]' => '',
                    // 'edit_logins[newPassword][second]' => '',
                ],
                false,
            ],
            'Email min length' => [
                [
                    'edit_logins[email]' => 'a@',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    // 'edit_logins[newPassword][first]' => '',
                    // 'edit_logins[newPassword][second]' => '',
                ],
                false,
            ],
            'Email max length' => [
                [
                    'edit_logins[email]' => str_repeat('a', 180) . '@mail.com',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    // 'edit_logins[newPassword][first]' => '',
                    // 'edit_logins[newPassword][second]' => '',
                ],
                false,
            ],
            'Email blank' => [
                [
                    'edit_logins[email]' => '',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    // 'edit_logins[newPassword][first]' => '',
                    // 'edit_logins[newPassword][second]' => '',
                ],
                false,
            ],
            'Email null' => [
                [
                    'edit_logins[email]' => null,
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    // 'edit_logins[newPassword][first]' => '',
                    // 'edit_logins[newPassword][second]' => '',
                ],
                false,
            ],
            'Email already used' => [
                [
                    'edit_logins[email]' => 'used@mail.com',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    // 'edit_logins[newPassword][first]' => '',
                    // 'edit_logins[newPassword][second]' => '',
                ],
                false,
            ],
            'Bad current password' => [
                [
                    'edit_logins[email]' => uniqid().'mail@mail.com',
                    'edit_logins[currentPassword]' => 'J\'ai 20 ans.',
                    // 'edit_logins[newPassword][first]' => '',
                    // 'edit_logins[newPassword][second]' => '',
                ],
                false,
            ],
            'Password blank' => [
                [
                    'edit_logins[email]' => uniqid().'mail@mail.com',
                    'edit_logins[currentPassword]' => '',
                    // 'edit_logins[newPassword][first]' => '',
                    // 'edit_logins[newPassword][second]' => '',
                ],
                false,
            ],
            'Password null' => [
                [
                    'edit_logins[email]' => uniqid().'mail@mail.com',
                    'edit_logins[currentPassword]' => null,
                    // 'edit_logins[newPassword][first]' => '',
                    // 'edit_logins[newPassword][second]' => '',
                ],
                false,
            ],
            'Password not the same' => [
                [
                    'edit_logins[email]' => uniqid().'mail@mail.com',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    'edit_logins[newPassword][first]' => 'J\ai 19 ans.',
                    'edit_logins[newPassword][second]' => 'Je n\'ai pas 19 ans.',
                ],
                false,
            ],
            'Password min length' => [
                [
                    'edit_logins[email]' => uniqid().'mail@mail.com',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    'edit_logins[newPassword][first]' => 'D8!j',
                    'edit_logins[newPassword][second]' => 'D8!j',
                ],
                false,
            ],
            'Password max length' => [
                [
                    'edit_logins[email]' => uniqid().'mail@mail.com',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    'edit_logins[newPassword][first]' => str_repeat('D8!j', 1025),
                    'edit_logins[newPassword][second]' => str_repeat('D8!j', 1025),
                ],
                false,
            ],
            'Password strength' => [
                [
                    'edit_logins[email]' => uniqid().'mail@mail.com',
                    'edit_logins[currentPassword]' => 'J\'ai 19 ans.',
                    'edit_logins[newPassword][first]' => 'apqmzosleidkrufjtygh',
                    'edit_logins[newPassword][second]' => 'apqmzosleidkrufjtygh',
                ],
                false,
            ],
        ];
    }

    public function getProfileDatas(): array
    {
        return [
            'Edit pseudo only' => [
                [
                    'edit_profile[pseudo]' => 'New pseudo',
                    // 'edit_profile[pictureFile][file]' => new UploadedFile(__DIR__.'/fixtures/1.jfif', '1.jfif'),
                ],
                true,
            ],
            'Edit pseudo & picture' => [
                [
                    'edit_profile[pseudo]' => 'New pseudo',
                    'edit_profile[pictureFile][file]' => new UploadedFile(__DIR__.'/fixtures/1.jfif', '1.jfif'),
                ],
                true,
            ],
            'Pseudo min length' => [
                [
                    'edit_profile[pseudo]' => 'A',
                    // 'edit_profile[pictureFile][file]' => new UploadedFile(__DIR__.'/fixtures/1.jfif', '1.jfif'),
                ],
                false,
            ],
            'Pseudo max length' => [
                [
                    'edit_profile[pseudo]' => 'This string has more than 30 characters',
                    // 'edit_profile[pictureFile][file]' => new UploadedFile(__DIR__.'/fixtures/1.jfif', '1.jfif'),
                ],
                false,
            ],
            'Pseudo blank' => [
                [
                    'edit_profile[pseudo]' => '',
                    // 'edit_profile[pictureFile][file]' => new UploadedFile(__DIR__.'/fixtures/1.jfif', '1.jfif'),
                ],
                false,
            ],
            'Pseudo null' => [
                [
                    'edit_profile[pseudo]' => null,
                    // 'edit_profile[pictureFile][file]' => new UploadedFile(__DIR__.'/fixtures/1.jfif', '1.jfif'),
                ],
                false,
            ],
            'Picture mime type' => [
                [
                    'edit_profile[pseudo]' => 'New pseudo',
                    'edit_profile[pictureFile][file]' => new UploadedFile(__DIR__.'/fixtures/0.svg', '0.svg'),
                ],
                false,
            ],
            'Picture max size' => [
                [
                    'edit_profile[pseudo]' => 'New pseudo',
                    'edit_profile[pictureFile][file]' => new UploadedFile(__DIR__.'/fixtures/potato.jpg', 'potato.jpg'),
                ],
                false,
            ],
        ];
    }
}

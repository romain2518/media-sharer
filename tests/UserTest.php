<?php

namespace App\Tests;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

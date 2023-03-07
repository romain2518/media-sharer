<?php

namespace App\Tests;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;

class RegistrationTest extends WebTestCaseMessageBeautifier
{
    private static $confirmationLink = null;

    public function testRegisterWithValidData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/inscription');

        $this->assertResponseIsSuccessful();

        // Email pseudo@email.com must be free for this test
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findBy(['email' => 'pseudo@email.com'], null, 1);
        if (!empty($user[0])) {
            $userRepository->remove($user[0], true);
        }

        $client->submitForm('S\'inscrire', [
            'registration_form[pseudo]' => 'Pseudo',
            'registration_form[email]' => 'pseudo@email.com',
            'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
            'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
            'registration_form[agreeTerms]' => '1',
        ]);
        
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $mailerEvent = $this->getMailerEvent();
        $this->assertEmailIsQueued($mailerEvent);
    }

    /**
     * @depends testRegisterWithValidData
     * Email pseudo@email.com must not be free for this test
     * 
     * @dataProvider getInvalidDatas
     */
    public function testRegisterWithInvalidData(array $data): void
    {
        $client = static::createClient();
        $client->request('GET', '/inscription');

        $client->submitForm('S\'inscrire', $data);
        
        $this->assertResponseIsUnprocessable();
    }

    /**
     * @depends testRegisterWithValidData
     * This test is the continuation of the registration
     */
    public function testResendConfirmationMail(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'pseudo@email.com']);

        $client->request('GET', '/verification/renvoi/' . $user->getId());

        $this->assertResponseIsSuccessful(); 

        $client->submitForm('Renvoyer l\'email');
        
        $this->assertResponseIsSuccessful(); 

        $mailerEvent = $this->getMailerEvent();
        $this->assertEmailIsQueued($mailerEvent);

        $mail = $this->getMailerMessage()->toString();
        $mail = preg_replace('#=\r\n#', '', $mail);
        $mail = preg_replace('#=3D#', '=', $mail);
        preg_match('#(/verification/email.*)"#s', $mail, $matches);

        self::$confirmationLink = $matches[1];
    }

    /**
     * @depends testResendConfirmationMail
     * This test is the end of the registration
     */
    public function testConfirmEmail(): void
    {
        $client = static::createClient();

        $client->request('GET', self::$confirmationLink);

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'pseudo@email.com']);

        $this->assertTrue($user->isVerified());
    }

    public function getInvalidDatas(): array
    {
        return [
            'Pseudo min length' => [[
                'registration_form[pseudo]' => 'A',
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Pseudo max length' => [[
                'registration_form[pseudo]' => 'This string has more than 30 characters',
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Pseudo blank' => [[
                'registration_form[pseudo]' => '',
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Pseudo null' => [[
                'registration_form[pseudo]' => null,
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Email invalid' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => 'Not a valid email',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Email min length' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => 'a@',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Email max length' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => str_repeat('a', 180) . '@mail.com',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Email blank' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => '',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Email null' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => null,
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Email already used' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => 'pseudo@email.com',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Password not the same' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'Je n\'ai pas 19 ans.',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Password blank' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => '',
                'registration_form[plainPassword][second]' => '',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Password null' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => null,
                'registration_form[plainPassword][second]' => null,
                'registration_form[agreeTerms]' => '1',
            ]],
            'Password min length' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => 'D8!j',
                'registration_form[plainPassword][second]' => 'D8!j',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Password max length' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => str_repeat('D8!j', 1025),
                'registration_form[plainPassword][second]' => str_repeat('D8!j', 1025),
                'registration_form[agreeTerms]' => '1',
            ]],
            'Password strength' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => 'apqmzosleidkrufjtygh',
                'registration_form[plainPassword][second]' => 'apqmzosleidkrufjtygh',
                'registration_form[agreeTerms]' => '1',
            ]],
            'Terms not agreed' => [[
                'registration_form[pseudo]' => 'Pseudo',
                'registration_form[email]' => 'unused@email.com',
                'registration_form[plainPassword][first]' => 'J\ai 19 ans.',
                'registration_form[plainPassword][second]' => 'J\ai 19 ans.',
                // 'registration_form[agreeTerms]' => '1',
            ]],
        ];
    }
}
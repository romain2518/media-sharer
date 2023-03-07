<?php

namespace App\Tests;

use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;

class ResetPasswordTest extends WebTestCaseMessageBeautifier
{
    private static $confirmationLink = null;

    public function testRequestWithValidData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reinitialisation-mdp');

        $this->assertResponseIsSuccessful();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $resetPasswordRepository = static::getContainer()->get(ResetPasswordRequestRepository::class);

        $user = $userRepository->findBy([], null, 1)[0];
        $request = $resetPasswordRepository->findOneBy(['user' => $user]);

        // Remove the request if one has already been sent
        if (null !== $request) {
            $resetPasswordRepository->remove($request, true);
        }

        $client->submitForm('Envoyer l\'email de réinitialisation', [
            'reset_password_request_form[email]' => $user->getEmail(),
        ]);
        
        $this->assertResponseRedirects('/reinitialisation-mdp/check-email');

        $mailerEvent = $this->getMailerEvent();
        $this->assertEmailIsQueued($mailerEvent);

        $mail = $this->getMailerMessage()->toString();
        $mail = preg_replace('#=\r\n#', '', $mail);
        $mail = preg_replace('#=3D#', '=', $mail);
        preg_match('#(/reinitialisation-mdp/reset.*)"#s', $mail, $matches);

        self::$confirmationLink = $matches[1];
    }

    public function testRequestWithUnusedEmail(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reinitialisation-mdp');

        $this->assertResponseIsSuccessful();

        $client->submitForm('Envoyer l\'email de réinitialisation', [
            'reset_password_request_form[email]' => 'unused@mail.com',
        ]);
        
        $this->assertResponseRedirects('/reinitialisation-mdp/check-email');

        $mailerEvent = $this->getMailerEvent();
        $this->assertNull($mailerEvent);
    }

    /**
     * @dataProvider getInvalidRequestDatas
     */
    public function testRequestWithInvalidData(array $data): void
    {
        $client = static::createClient();
        $client->request('GET', '/reinitialisation-mdp');

        $this->assertResponseIsSuccessful();

        $client->submitForm('Envoyer l\'email de réinitialisation', $data);
        
        $this->assertResponseIsUnprocessable();
    }

    /**
     * @depends testResetPasswordWithInvalidData
     * Both test use the same password reset request,
     * Test with invalid datas must be done before the one with valid datas
     */
    public function testResetPasswordWithValidData(): void
    {
        $client = static::createClient();

        $client->request('GET', self::$confirmationLink);

        $this->assertResponseRedirects('/reinitialisation-mdp/reset');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $client->submitForm('Réinitialiser le mot de passe', [
            'change_password_form[plainPassword][first]' => 'J\'ai 19 ans.',
            'change_password_form[plainPassword][second]' => 'J\'ai 19 ans.',
        ]);

        $this->assertResponseRedirects('/connexion');
    }

    /**
     * @dataProvider getInvalidPasswordDatas
     *
     * @param array $data
     * @return void
     */
    public function testResetPasswordWithInvalidData(array $data): void
    {
        $client = static::createClient();

        $client->request('GET', self::$confirmationLink);

        $this->assertResponseRedirects('/reinitialisation-mdp/reset');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $client->submitForm('Réinitialiser le mot de passe', $data);

        $this->assertResponseIsUnprocessable();
    }

    public function getInvalidRequestDatas(): array
    {
        return [
            'Email blank' => [[
                'reset_password_request_form[email]' => '',
            ]],
            'Email null' => [[
                'reset_password_request_form[email]' => null,
            ]],
        ];
    }

    public function getInvalidPasswordDatas(): array
    {
        return [
            'Password not the same' => [[
                'change_password_form[plainPassword][first]' => 'J\'ai 19 ans.',
                'change_password_form[plainPassword][second]' => 'Je n\'ai pas 19 ans.',
            ]],
            'Password blank' => [[
                'change_password_form[plainPassword][first]' => '',
                'change_password_form[plainPassword][second]' => '',
            ]],
            'Password null' => [[
                'change_password_form[plainPassword][first]' => null,
                'change_password_form[plainPassword][second]' => null,
            ]],
            'Password min length' => [[
                'change_password_form[plainPassword][first]' => 'D8!j',
                'change_password_form[plainPassword][second]' => 'D8!j',
            ]],
            'Password max length' => [[
                'change_password_form[plainPassword][first]' => str_repeat('D8!j', 1025),
                'change_password_form[plainPassword][second]' => str_repeat('D8!j', 1025),
            ]],
            'Password strength' => [[
                'change_password_form[plainPassword][first]' => 'apqmzosleidkrufjtygh',
                'change_password_form[plainPassword][second]' => 'apqmzosleidkrufjtygh',
            ]],
        ];
    }
}

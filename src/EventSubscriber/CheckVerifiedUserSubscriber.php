<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\AccountNotVerifiedAuthenticationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class CheckVerifiedUserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router
    ) {
    }
    
    public function onCheckPassportEvent(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();

        $user = $passport->getUser();
        if (!$user instanceof User) {
            throw new \Exception('Unexpected user type');
        }
        if (!$user->isVerified()) {
            throw new AccountNotVerifiedAuthenticationException();
        }
    }

    public function onLoginFailureEvent(LoginFailureEvent $event): void
    {
        if (!$event->getException() instanceof AccountNotVerifiedAuthenticationException) {
            return;
        }

        $passport = $event->getPassport();

        $user = $passport->getUser();
        if (!$user instanceof User) {
            throw new \Exception('Unexpected user type');
        }

        $response = new RedirectResponse(
            $this->router->generate('app_verify_resend-email', ['id' => $user->getId()])
        );
        $event->setResponse($response);        
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassportEvent', -10],
            LoginFailureEvent::class => 'onLoginFailureEvent'
        ];
    }
}
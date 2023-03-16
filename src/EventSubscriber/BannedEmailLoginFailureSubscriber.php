<?php

namespace App\EventSubscriber;

use App\Repository\BanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class BannedEmailLoginFailureSubscriber extends AbstractController implements EventSubscriberInterface
{
    public function __construct(
        private BanRepository $banRepository,
    ) {
    }
    
    public function onLoginFailureEvent(LoginFailureEvent $event): void
    {
        $passport = $event->getPassport();
        /** @var UserBadge $userBadge */
        $userBadge = $passport->getBadge(UserBadge::class);
        $email = $userBadge->getUserIdentifier();

        $targetedBan = $this->banRepository->findOneBy(['email' => $email]);
        if (null !== $targetedBan) {
            $this->addFlash('login_error', 'Cette adresse mail a été bannie.');
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailureEvent',
        ];
    }
}

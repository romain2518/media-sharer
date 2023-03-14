<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

class UserVoter extends Voter
{
    public const MANAGE = 'USER_MANAGE';

    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager,
        private Security $security
        ) {
    }
    
    protected function supports(string $attribute, mixed $subject): bool
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        return $attribute === self::MANAGE && $subject instanceof \App\Entity\User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }

        // ... (check conditions and return true to grant permission) ...
        switch ($attribute) {
            case self::MANAGE:
                /** @var User $subject */

                //? Return true if the targeted user has a lower role than the logged in user
                //* Using access decision manager because isGranted() can not be used on another user than the logged in user
                
                $loggedInUserMaxRole = $this->security->isGranted('ROLE_SUPERADMIN') ? 'ROLE_SUPERADMIN' : 'ROLE_ADMIN';

                $token = new UsernamePasswordToken($subject, 'none', $subject->getRoles());

                return !$this->accessDecisionManager->decide($token, [$loggedInUserMaxRole], $subject);

                break;
        }

        return false;
    }
}

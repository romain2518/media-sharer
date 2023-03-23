<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Status;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Security\Exception\WebSocketInvalidRequestException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\TimeBundle\DateTimeFormatter;
use Symfony\Component\Security\Core\User\UserInterface;

class ConversationController extends WebSocketCoreController
{
    public static function block(string $action, User $targetedUser, UserInterface $user, EntityManagerInterface $entityManager, DateTimeFormatter $dateTimeFormatter): string
    {
        /** @var User $user */

        if ($action === 'block') {
            $user->addBlockedUser($targetedUser);
        } else {
            $user->removeBlockedUser($targetedUser);
        }

        $entityManager->flush();

        //? Normalizing data and returning JSON
        if ($action === 'unblock') {
            /** @var ConversationRepository $conversationRepository */
            $conversationRepository = $entityManager->getRepository(Conversation::class);
            
            $conversation = $conversationRepository->findOneByUsersLight($user, $targetedUser);
            if (null !== $conversation) {
                return self::serialize($conversation, 'api_conversation_light', $dateTimeFormatter);
            }            
        }

        return self::serialize($targetedUser, 'api_user_light', $dateTimeFormatter);
    }

    public static function markConversation(string $action, User $targetedUser, UserInterface $user, EntityManagerInterface $entityManager, DateTimeFormatter $dateTimeFormatter): string
    {
        /** @var User $user */
        /** @var ConversationRepository $conversationRepository */
        $conversationRepository = $entityManager->getRepository(Conversation::class);

        $conversation = $conversationRepository->findOneByUsersLight($user, $targetedUser);

        if (null === $conversation) {
            throw new WebSocketInvalidRequestException;
        }
        
        $statusUserIndex = $conversation->getStatuses()[0]->getUser() === $user ? 0 : 1;
        $conversation->getStatuses()[$statusUserIndex]->setIsRead($action === 'setRead' ? true : false);

        $entityManager->flush();
        
        return self::serialize($conversation, 'api_conversation_light', $dateTimeFormatter);
    }
}

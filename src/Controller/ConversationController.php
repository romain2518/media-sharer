<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\Status;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Security\Exception\WebSocketInvalidRequestException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\TimeBundle\DateTimeFormatter;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    public static function show(User $targetedUser, UserInterface $user, EntityManagerInterface $entityManager, DateTimeFormatter $dateTimeFormatter): string
    {
        /** @var User $user */
        /** @var ConversationRepository $conversationRepository */
        $conversationRepository = $entityManager->getRepository(Conversation::class);

        // Throws error if targeted user is blocked by logged user
        if ($user->getBlockedUsers()->contains($targetedUser)) {
            throw new WebSocketInvalidRequestException('Vous ne pouvez pas afficher la discussion d\'un utilisateur bloqué.');
        }

        $conversation = $conversationRepository->findOneByUsersDetailed($user, $targetedUser);
        if (null === $conversation) {
            $conversation = new Conversation();
            $conversation
                ->addUser($user)
                ->addUser($targetedUser)
                ->addStatus((new Status())->setUser($user))
                ->addStatus((new Status())->setUser($targetedUser))
            ;

            $conversationRepository->save($conversation);
        }

        // Mark conversation has read for logged in user
        $statusUserIndex = $conversation->getStatuses()[0]->getUser() === $user ? 0 : 1;
        $conversation->getStatuses()[$statusUserIndex]->setIsRead(true);

        $entityManager->flush();
        
        return self::serialize($conversation, 'api_conversation_detailed', $dateTimeFormatter);
    }

    /**
     * @return array [string $jsonResponse, bool $sendToTarget]
     */
    public static function new(User $targetedUser, UserInterface $user, string $message, EntityManagerInterface $entityManager, ValidatorInterface $validator, DateTimeFormatter $dateTimeFormatter): array
    {
        /** @var User $user */
        /** @var ConversationRepository $conversationRepository */
        $conversationRepository = $entityManager->getRepository(Conversation::class);

        // Throws error if targeted user is blocked by logged user
        if ($user->getBlockedUsers()->contains($targetedUser)) {
            throw new WebSocketInvalidRequestException('Vous ne pouvez pas envoyer de message à un utilisateur bloqué.');
        }

        $conversation = $conversationRepository->findOneByUsersDetailed($user, $targetedUser);
        if (null === $conversation) {
            throw new WebSocketInvalidRequestException;
        }

        // Creating object
        $newMessage = new Message();
        $newMessage
            ->setMessage(trim($message))
            ->setUser($user)
            ->setConversation($conversation)
        ;

        $errors = $validator->validate($newMessage);
        if (count($errors) > 0) {
            return [self::serializeErrors($errors), false];
        }
        
        $entityManager->persist($newMessage);
        $conversation->setUpdatedAt(new \DateTime('now'));
        
        // Mark conversation has unread for targeted user
        $statusUserIndex = $conversation->getStatuses()[0]->getUser() === $targetedUser ? 0 : 1;
        $conversation->getStatuses()[$statusUserIndex]->setIsRead(false);

        $entityManager->flush();

        $sendToTarget = !$targetedUser->getBlockedUsers()->contains($user);

        // Entity manager cache needs be cleared to reorder & update $conversation 
        $entityManager->clear();
        $reorderedConversation = $conversationRepository->findOneByUsersDetailed($user, $targetedUser);

        
        return [self::serialize($reorderedConversation, 'api_conversation_detailed', $dateTimeFormatter), $sendToTarget];
    }

    /**
     * @return array [string $jsonResponse, bool $sendToTarget]
     */
    public static function delete(User $targetedUser, UserInterface $user, int $messageId, EntityManagerInterface $entityManager, DateTimeFormatter $dateTimeFormatter): array
    {
        /** @var User $user */
        /** @var ConversationRepository $conversationRepository */
        $conversationRepository = $entityManager->getRepository(Conversation::class);
        
        /** @var MessageRepository $messageRepository */
        $messageRepository = $entityManager->getRepository(Message::class);

        // Throws error if targeted user is blocked by logged user
        if ($user->getBlockedUsers()->contains($targetedUser)) {
            throw new WebSocketInvalidRequestException('Vous ne pouvez pas accéder à une conversation avec un utilisateur bloqué.');
        }

        $conversation = $conversationRepository->findOneByUsersLight($user, $targetedUser);
        $message = $messageRepository->find($messageId);

        // Throws error If conversation or message is null, 
        //              If message is not from conversation,
        //              If user is not the sender of the message
        if (
            null === $conversation || null === $message 
            || $conversation !== $message->getConversation()
            || $user !== $message->getUser()
            ) {
            throw new WebSocketInvalidRequestException;
        }

        $messageRepository->remove($message, true);
        
        $sendToTarget = !$targetedUser->getBlockedUsers()->contains($user);

        return [self::serialize($message, 'api_message', $dateTimeFormatter), $sendToTarget];
    }
}

<?php

namespace App\WebSocket;

use App\Controller\ConversationController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Exception\WebSocketInvalidRequestException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\TimeBundle\DateTimeFormatter;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Notification implements MessageComponentInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $JWTManager,
        private ValidatorInterface $validator,
        private DateTimeFormatter $dateTimeFormatter,
        private $clients = new \SplObjectStorage,
    ) {
    }

    public function onOpen(ConnectionInterface $conn) {
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $query);

        $token = new JWTUserToken();
        $token->setRawToken($query['token'] ?? null);

        try {
            $payload = $this->JWTManager->decode($token);
        } catch (\Exception $e) {
            throw new WebSocketInvalidRequestException(isFatal:  WebSocketInvalidRequestException::FATAL_ERROR);            
        }

        $user = $this->getUserByEmail($payload['username']);

        if (null === $user) {
            throw new WebSocketInvalidRequestException(isFatal:  WebSocketInvalidRequestException::FATAL_ERROR);
        }

        $conn->userId = $user->getId();

        $this->clients->attach($conn);
        echo "New connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        //? Checking datas
        $messageData = json_decode(trim($msg));

        if (!isset($messageData->action) || !isset($messageData->data) || !isset($messageData->targetedUserId)) {
            throw new WebSocketInvalidRequestException(isFatal:  WebSocketInvalidRequestException::FATAL_ERROR);
        }

        //? Checking user & target user (both must be not null and different from each other)
        $user = $this->getUser($from->userId);
        $targetedUser = $this->getUser($messageData->targetedUserId);

        if (null === $user) {
            // Throw fatal error if current user has been deleted
            throw new WebSocketInvalidRequestException(isFatal:  WebSocketInvalidRequestException::FATAL_ERROR);
        }

        if (null === $targetedUser || $user === $targetedUser) {
            throw new WebSocketInvalidRequestException;
        }

        if (in_array($messageData->action, ['new', 'delete'])) {
            $messageData->data = json_decode($messageData->data);
            if (null === $messageData) {throw new WebSocketInvalidRequestException;}
        }

        //? Dispatching action
        $data = null;
        $receiversId = [$user->getId()];

        switch ($messageData->action) {
            case 'block':
            case 'unblock':
                $data = ConversationController::block($messageData->action, $targetedUser, $user, $this->entityManager, $this->dateTimeFormatter);
                break;
            case 'setRead':
            case 'setNotRead':
                $data = ConversationController::markConversation($messageData->action, $targetedUser, $user, $this->entityManager, $this->dateTimeFormatter);
                break;
            case 'show':
                $data = ConversationController::show($targetedUser, $user, $this->entityManager, $this->dateTimeFormatter);
                break;
            case 'new':
                if (empty($messageData->data->message)) {
                    throw new WebSocketInvalidRequestException;
                }

                [$data, $sendToTarget] = ConversationController::new($targetedUser, $user, $messageData->data->message, $this->entityManager, $this->validator, $this->dateTimeFormatter);

                if ($sendToTarget) {
                    $receiversId[] = $targetedUser->getId();
                }
                
                break;
            case 'delete':
                if (empty($messageData->data->id)) {
                    throw new WebSocketInvalidRequestException;
                }

                [$data, $sendToTarget] = ConversationController::delete($targetedUser, $user, $messageData->data->id, $this->entityManager, $this->dateTimeFormatter);

                if ($sendToTarget) {
                    $receiversId[] = $targetedUser->getId();
                }
                
                break;
            default:
                throw new WebSocketInvalidRequestException;
                break;
        }

        //? Sending response
        foreach ($this->clients as $client) {
            if (in_array($client->userId, $receiversId)) {
                $this->send($client, $messageData->action, $data);
            }
        }

        // Clear cache for next message
        $this->entityManager->clear();

        echo "Message sent by connection {$from->resourceId}\n";        
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        if ($e instanceof WebSocketInvalidRequestException && !$e->isFatal()) {
            $this->send($conn, 'error', "{\"message\":\"{$e->getMessage()}\"}");
            return;
        }
        
        $this->send($conn, 'fatalError', "{\"message\":\"{$e->getMessage()}\"}");
        $conn->close();
        echo "An error has occurred: {$e->getMessage()}\n";
    }

    /**
     * @param string $email
     * @return User|null
     */
    private function getUserByEmail(string $email)
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        return $userRepository->findOneBy(['email' => $email]);
    }

    /**
     * @param int $id
     * @return User|null
     */
    private function getUser(int $id)
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        return $userRepository->find($id);
    }
    
    private function send(ConnectionInterface $conn, string $action, string $data)
    {
        $conn->send(json_encode([
            'action' => $action,
            'data' => $data,
        ]));
    }
}

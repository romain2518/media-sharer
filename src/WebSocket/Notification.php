<?php

namespace App\WebSocket;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Exception\WebSocketInvalidRequestException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class Notification implements MessageComponentInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $JWTManager,
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
            throw new WebSocketInvalidRequestException();            
        }

        $user = $this->getUserByEmail($payload['username']);

        if (null === $user) {
            throw new WebSocketInvalidRequestException();
        }

        $conn->userId = $user->getId();

        $this->clients->attach($conn);
        echo "New connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        //? Checking datas
        $messageData = json_decode(trim($msg));

        if (!isset($messageData->action) || !isset($messageData->data) || !isset($messageData->targetedUserId)) {
            throw new WebSocketInvalidRequestException();
        }

        //? Checking user & target user (both must be not null and different from each other)
        $user = $this->getUser($from->userId);
        $targetedUser = $this->getUser($messageData->targetedUserId);

        if (null === $user || null === $targetedUser || $user === $targetedUser) {
            throw new WebSocketInvalidRequestException();
        }

        //? Dispatching action
        $data = null;

        switch ($messageData->action) {
            case 'test':
                $data = 'Test OK !';
                break;
        }

        if (null === $data) {
            throw new WebSocketInvalidRequestException();
        }

        //? Sending response
        foreach ($this->clients as $client) {
            if ($client->userId === $targetedUser->getId()) {
                $this->send($client, $messageData->action, $data);
            }
        }

        echo "Message sent by connection {$from->resourceId}\n";        
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $this->send($conn, 'error', $e->getMessage());
        $conn->close();
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

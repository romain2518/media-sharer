<?php

namespace App\Tests;

use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use WebSocket\Client;

class ConversationTest extends WebTestCaseMessageBeautifier
{
    // !
    // ! THESES TESTS NEED following command to work
    // ! bin/console --env=test app:wsserver:start
    // !
    public function testInvalidConnexion(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]
        
        $user = new User();
        $user
        ->setPseudo('test')
        ->setEmail(uniqid().'mail@mail.com')
        ->setRoles(['ROLE_USER'])
        ->setPassword('aaaa')
        ;
        $userRepository->save($user, true);

        [$baseUrl, $JWTToken] = $this->getWSConst($client, $user);

        //? Invalid JWT token
        $WSClientInvalidJWT = new Client("ws://$baseUrl:8080?token=invalid_jwt_token");

        $WSClientInvalidJWT->send('test');
        $response = $WSClientInvalidJWT->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('fatalError', $action);

        //? Missing action
        $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        $WSClient->send(json_encode(
            [
                'data' => 'test',
                'targetedUserId' => $users[0]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('fatalError', $action);

        //? Missing data
        $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        $WSClient->send(json_encode(
            [
                'action' => 'test',
                'targetedUserId' => $users[0]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('fatalError', $action);

        //? Missing targetedUserId
        $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        $WSClient->send(json_encode(
            [
                'action' => 'test',
                'data' => 'test',
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('fatalError', $action);

        //? Target is null
        $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");
        
        $WSClient->send(json_encode(
            [
                'action' => 'test',
                'data' => 'test',
                // $user being the last registered user, $user->getId()+1 is not attributed yet
                'targetedUserId' => $user->getId()+1,
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $this->assertSame('error', $action);

        //? User is same as target
        //* We don't need a new WS client since last one is still working
        // $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        $WSClient->send(json_encode(
            [
                'action' => 'test',
                'data' => 'test',
                'targetedUserId' => $user->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //? User is null (User got deleted since last message)
        //* We don't need a new WS client since last one is still working
        // $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        $userRepository->remove($user, true);

        $WSClient->send(json_encode(
            [
                'action' => 'test',
                'data' => 'test',
                'targetedUserId' => $users[0]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('fatalError', $action);
    }

    public function testShow(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $conversationRepository = static::getContainer()->get(ConversationRepository::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]

        // Making sure that users[0] has blocked users[1] but not users[2]
        $users[0]->addBlockedUser($users[1]);
        $users[0]->removeBlockedUser($users[2]);
        $userRepository->save($users[0], true);

        // Making sure that there is currently no conversation between users[0] & users[2]
        $conversation = $conversationRepository->findOneByUsersLight($users[0], $users[2]);
        if (null !== $conversation) {
            $conversationRepository->remove($conversation, true);
        }

        [$baseUrl, $JWTToken] = $this->getWSConst($client, $users[0]);

        $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        //? Trying to show a blocked user
        $WSClient->send(json_encode(
            [
                'action' => 'show',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //? Case where conversation does not yet exist
        $WSClient->send(json_encode(
            [
                'action' => 'show',
                'data' => '',
                'targetedUserId' => $users[2]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('show', $action);
        $this->assertSame($users[0]->getId(), $data->users[0]->id);
        $this->assertSame($users[2]->getId(), $data->users[1]->id);
        $this->assertTrue($data->statuses[0]->isRead);

        //? Case where conversation already exists
        $WSClient->send(json_encode(
            [
                'action' => 'show',
                'data' => '',
                'targetedUserId' => $users[2]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('show', $action);
        $this->assertSame($users[0]->getId(), $data->users[0]->id);
        $this->assertSame($users[2]->getId(), $data->users[1]->id);
    }

    /**
     * This method uses WS message with action : show
     * @depends testShow
     */
    public function testBlock(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $conversationRepository = static::getContainer()->get(ConversationRepository::class);

        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]

        // Making sure that users[1] is not blocked by users[0]
        $users[0]->removeBlockedUser($users[1]);
        $userRepository->save($users[0], true);

        // Making sure that there is currently no conversation between users[0] & users[1]
        $conversation = $conversationRepository->findOneByUsersLight($users[0], $users[1]);
        if (null !== $conversation) {
            $conversationRepository->remove($conversation, true);
        }

        [$baseUrl, $JWTToken] = $this->getWSConst($client, $users[0]);

        $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        //? Test block
        $WSClient->send(json_encode(
            [
                'action' => 'block',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('block', $action);
        $this->assertSame($users[1]->getId(), $data->id);

        //? Test unblock with no conversation existing (returns user)
        $WSClient->send(json_encode(
            [
                'action' => 'unblock',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('unblock', $action);
        $this->assertSame($users[1]->getId(), $data->id);

        //? Test unblock with conversation existing (returns conversation)
        $WSClient->send(json_encode(
            [
                'action' => 'show',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));
        $WSClient->receive();
        
        $WSClient->send(json_encode(
            [
                'action' => 'unblock',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('unblock', $action);
        $this->assertSame($users[0]->getId(), $data->users[0]->id);
        $this->assertSame($users[1]->getId(), $data->users[1]->id);
    }

    /**
     * This method uses WS message with action : show
     * @depends testShow
     */
    public function testMarkAs(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $conversationRepository = static::getContainer()->get(ConversationRepository::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]

        // Making sure that users[1] is not blocked by users[0]
        $users[0]->removeBlockedUser($users[1]);
        $userRepository->save($users[0], true);

        // Making sure that there is currently no conversation between users[0] & users[1]
        $conversation = $conversationRepository->findOneByUsersLight($users[0], $users[1]);
        if (null !== $conversation) {
            $conversationRepository->remove($conversation, true);
        }

        [$baseUrl, $JWTToken] = $this->getWSConst($client, $users[0]);

        $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        //? Test mark read conversation that does not yet exist
        $WSClient->send(json_encode(
            [
                'action' => 'setRead',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //? Test mark unread conversation that does not yet exist
        $WSClient->send(json_encode(
            [
                'action' => 'setNotRead',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //* Creating conversation
        $WSClient->send(json_encode(
            [
                'action' => 'show',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));
        $WSClient->receive();

        //? Test mark read
        $WSClient->send(json_encode(
            [
                'action' => 'setRead',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('setRead', $action);
        $this->assertTrue($data->statuses[0]->isRead);

        //? Test mark unread
        $WSClient->send(json_encode(
            [
                'action' => 'setNotRead',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('setNotRead', $action);
        $this->assertNotTrue($data->statuses[0]->isRead);
    }

    /**
     * This method uses WS message with action : unblock
     * @depends testBlock
     * This method also uses WS message with action : show
     * @depends testShow
     */
    public function testNew(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $conversationRepository = static::getContainer()->get(ConversationRepository::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]

        // Making sure that users[1] is blocked by users[0]
        $users[0]->addBlockedUser($users[1]);
        $userRepository->save($users[0], false);

        // Making sure that users[0] is not blocked by users[1]
        $users[1]->removeBlockedUser($users[0]);
        $userRepository->save($users[1], true);

        // Making sure that there is currently no conversation between users[0] & users[1]
        $conversation = $conversationRepository->findOneByUsersLight($users[0], $users[1]);
        if (null !== $conversation) {
            $conversationRepository->remove($conversation, true);
        }
        
        [$baseUrl, $JWTToken] = $this->getWSConst($client, $users[0]);
        $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        //? Test send message to a blocked user
        $WSClient->send(json_encode(
            [
                'action' => 'new',
                'data' => json_encode(['message' => 'A valid message']),
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //* Unblocking $users[1]
        $WSClient->send(json_encode(
            [
                'action' => 'unblock',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));
        $WSClient->receive();

        //? Test send message to a non-existent conversation
        $WSClient->send(json_encode(
            [
                'action' => 'new',
                'data' => json_encode(['message' => 'A valid message']),
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //* Creating conversation
        $WSClient->send(json_encode(
            [
                'action' => 'show',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));
        $WSClient->receive();

        //? Test send empty message
        $WSClient->send(json_encode(
            [
                'action' => 'new',
                'data' => json_encode(['message' => '']),
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('new', $action);
        $this->assertTrue(empty($data->id));
        $this->assertNotTrue(empty($data[0]));

        //? Test send invalid message (over 255 characters)
        $WSClient->send(json_encode(
            [
                'action' => 'new',
                'data' => json_encode(['message' => str_repeat('a', 256)]),
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('new', $action);
        $this->assertTrue(empty($data->id));
        $this->assertNotTrue(empty($data[0]));

        //? Test send valid
        //* Creating second WS client
        [$targetBaseUrl, $targetJWTToken] = $this->getWSConst($client, $users[1]);
        $targetWSClient = new Client("ws://$targetBaseUrl:8080?token=$targetJWTToken");

        // For some reasons, second client needs to be used at least one time to receive messages from server
        $targetWSClient->send(json_encode(
            [
                'action' => 'test',
                'data' => '',
                'targetedUserId' => $users[0]->getId(),
            ]
        ));
        $targetWSClient->receive();

        //* Sending message
        $uniqMessage = 'A valid message'.uniqid();
        $WSClient->send(json_encode(
            [
                'action' => 'new',
                'data' => json_encode(['message' => $uniqMessage]),
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        //* Sender receiving message
        $response = $WSClient->receive();
        
        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('new', $action);
        $this->assertNotTrue($data->statuses[1]->isRead);
        $this->assertSame($uniqMessage, $data->messages[0]->message);
        
        //* Target receiving message
        $response = $targetWSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('new', $action);
        $this->assertNotTrue($data->statuses[1]->isRead);
        $this->assertSame($uniqMessage, $data->messages[0]->message);
    }

    /**
     * @depends testNew
     */
    public function testDelete(): void
    {
        $client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $conversationRepository = static::getContainer()->get(ConversationRepository::class);
        
        $users = $userRepository->findBy([], null, 3);
        // Super admin : $users[0]
        // Admin : $users[1]
        // User : $users[2]

        // Making sure that users[1] is blocked by users[0]
        $users[0]->addBlockedUser($users[1]);
        $userRepository->save($users[0], false);

        // Making sure that users[0] is not blocked by users[1]
        $users[1]->removeBlockedUser($users[0]);
        $userRepository->save($users[1], true);

        $conversation = $conversationRepository->findOneByUsersDetailed($users[0], $users[1]);

        // Making sure that there is currently no conversation between users[0] & users[2]
        $conversation2 = $conversationRepository->findOneByUsersLight($users[0], $users[2]);
        if (null !== $conversation2) {
            $conversationRepository->remove($conversation2, true);
        }
        
        //* $users[1] WS client
        [$baseUrl, $JWTToken] = $this->getWSConst($client, $users[0]);
        $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        //* $users[2] WS client
        [$targetBaseUrl, $targetJWTToken] = $this->getWSConst($client, $users[1]);
        $targetWSClient = new Client("ws://$targetBaseUrl:8080?token=$targetJWTToken");

        //? Test delete message to a blocked user
        $WSClient->send(json_encode(
            [
                'action' => 'delete',
                'data' => json_encode(['id' => $conversation->getMessages()[0]->getId()]),
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //* Unblocking $users[1]
        $WSClient->send(json_encode(
            [
                'action' => 'unblock',
                'data' => '',
                'targetedUserId' => $users[1]->getId(),
            ]
        ));
        $WSClient->receive();

        //? Test delete message to a non-existent conversation
        $WSClient->send(json_encode(
            [
                'action' => 'delete',
                'data' => json_encode(['id' => 0]),
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //* Creating conversation
        $WSClient->send(json_encode(
            [
                'action' => 'show',
                'data' => '',
                'targetedUserId' => $users[2]->getId(),
            ]
        ));
        $WSClient->receive();

        //? Test delete a non-existent message
        $WSClient->send(json_encode(
            [
                'action' => 'delete',
                'data' => json_encode(['id' => 0]),
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //? Test delete a message from another conversation
        $WSClient->send(json_encode(
            [
                'action' => 'delete',
                'data' => json_encode(['id' => $conversation->getMessages()[0]->getId()]),
                'targetedUserId' => $users[2]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //? Test delete a message when not being sender of that message
        //* Creating message from $users[1]
        $targetWSClient->send(json_encode(
            [
                'action' => 'new',
                'data' => json_encode(['message' => 'A good message']),
                'targetedUserId' => $users[0]->getId(),
            ]
        ));

        $response = $targetWSClient->receive();
        // $conversation is not a Conversation object anymore but a JSON object
        $conversation = json_decode(json_decode($response)->data);

        // Receive message from owning side
        $WSClient->receive();

        //* Trying to delete message
        $WSClient->send(json_encode(
            [
                'action' => 'delete',
                'data' => json_encode(['id' => $conversation->messages[0]->id]),
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        $response = $WSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        
        $this->assertSame('error', $action);

        //? Test delete valid
        //* Deleting message
        $WSClient->send(json_encode(
            [
                'action' => 'delete',
                'data' => json_encode(['id' => $conversation->messages[1]->id]),
                'targetedUserId' => $users[1]->getId(),
            ]
        ));

        //* Sender receiving message
        $response = $WSClient->receive();
        
        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('delete', $action);
        $this->assertSame($conversation->messages[1]->id, $data->id);
        
        //* Target receiving message
        $response = $targetWSClient->receive();

        $this->assertJson($response);
        $action = json_decode($response)->action;
        $data = json_decode(json_decode($response)->data);
        
        $this->assertSame('delete', $action);
        $this->assertSame($conversation->messages[1]->id, $data->id);
    }

    /**
     * This method may use WS message with action : show
     * @depends testShow
     */
    public function testConversationMessageAutoDeletion()
    {
        $client = static::createClient();

        $conversationRepository = static::getContainer()->get(ConversationRepository::class);
        $conversation = $conversationRepository->findOneBy([]);

        $sender = $conversation->getUsers()[0];
        $target = $conversation->getUsers()[1];

        [$baseUrl, $JWTToken] = $this->getWSConst($client, $sender);
        $WSClient = new Client("ws://$baseUrl:8080?token=$JWTToken");

        //* Conversation must have at least one message
        $WSClient->send(json_encode(
            [
                'action' => 'new',
                'data' => json_encode(['message' => 'A valid message']),
                'targetedUserId' => $target->getId(),
            ]
        ));
        $WSClient->receive();

        $this->assertLessThanOrEqual(50, count($conversation->getMessages()));

        for ($i=0; $i < 50; $i++) {
            $WSClient->send(json_encode(
                [
                    'action' => 'new',
                    'data' => json_encode(['message' => 'A valid message']),
                    'targetedUserId' => $target->getId(),
                ]
            ));
            $response = $WSClient->receive();
        }
        
        $conversationPostAdd = json_decode(json_decode($response)->data);

        //? Conversation still have less than 50 messages after adding 50 messages
        $this->assertCount(50, $conversationPostAdd->messages);

        //? Oldest message pre & post message adding are different
        $this->assertNotSame(
            $conversation->getMessages()[count($conversation->getMessages()) - 1],
            $conversationPostAdd->messages[count($conversationPostAdd->messages) - 1]
        );
    }

    /**
     * Return Web socket constants for given user
     *
     * @param User $user
     * @return array[string $baseUrl, string $JWTToken]
     */
    private function getWSConst(KernelBrowser $client, User $user): array
    {        
        $client->loginUser($user);
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $javaScriptConst = $crawler->filter('script#JS-CONST')->extract(['_text'])[0];
        $javaScriptConstArray = explode("\n", trim($javaScriptConst));

        preg_match('/const BASE_URL = "(.*)";/', $javaScriptConstArray[0], $baseUrlResults);
        preg_match('/const JWTToken = "(.*)";/', $javaScriptConstArray[1], $JWTTokenResults);

        $baseUrl = $baseUrlResults[1];
        $JWTToken = $JWTTokenResults[1];

        return [$baseUrl, $JWTToken];
    }

}

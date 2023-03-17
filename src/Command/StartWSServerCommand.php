<?php

namespace App\Command;

use App\WebSocket\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:wsserver:start',
    description: 'Start the notification web socket server',
)]
class StartWSServerCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $JWTManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $port = 8080;
        $output->writeln("Starting server on port " . $port);

        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    new Notification($this->entityManager, $this->JWTManager)
                )
            ),
            $port
        );

        $server->run();
    }
}

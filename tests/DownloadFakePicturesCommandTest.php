<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DownloadFakePicturesCommandTest extends KernelTestCase
{
    public function testSomething(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        
        $command = $application->find('app:download-fake-pictures');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $outpout = $commandTester->getDisplay();

        $this->assertStringContainsString('Success, the following pictures were downloaded :', $outpout);
    }
}

<?php

namespace Pug\Tests\PugSymfonyBundle\Command;

use Pug\PugSymfonyBundle\Command\AssetsPublishCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AssetsPublishCommandTest extends KernelTestCase
{
    public function setUp()
    {
        self::bootKernel();
    }

    public function testCommand()
    {
        $application = new Application(self::$kernel);
        $application->add(new AssetsPublishCommand());

        $command = $application->find('assets:publish');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command'  => $command->getName(),
            '--env'    => 'prod',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('6 templates cached', $output, 'All templates can be cached except filter.pug as the upper filter does not exists.');
        $this->assertContains('1 templates failed to be cached', $output, 'filter.pug fails as the upper filter does not exists.');
    }
}

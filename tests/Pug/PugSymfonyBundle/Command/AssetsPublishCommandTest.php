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

        // Convert PHP style files to JS style
        $customHelperFile = __DIR__ . '/../../../project/app/Resources/views/custom-helper.pug';
        $customHelper = file_get_contents($customHelperFile);
        file_put_contents($customHelperFile, 'if view.custom
    u=view.custom.foo()
else
    s Noop
');
        $stylePhpFile = __DIR__ . '/../../../project/app/Resources/views/style-php.pug';
        $stylePhp = file_get_contents($stylePhpFile);
        file_put_contents($stylePhpFile, '.foo(style=\'background-position: 50% -402px; background-image: \' + css_url(\'assets/img/patterns/5.png\') + \';\')
.foo(style={\'background-position\': "50% -402px", \'background-image\': css_url(\'assets/img/patterns/5.png\')})
');
        $command = $application->find('assets:publish');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command'  => $command->getName(),
            '--env'    => 'prod',
        ]);

        $output = $commandTester->getDisplay();
        file_put_contents($customHelperFile, $customHelper);
        file_put_contents($stylePhpFile, $stylePhp);

        $this->assertContains('9 templates cached', $output, 'All templates can be cached except filter.pug as the upper filter does not exists.');
        $this->assertContains('1 templates failed to be cached', $output, 'filter.pug fails as the upper filter does not exists.');
        $this->assertRegExp('/(Unknown\sfilter\supper|upper:\sFilter\sdoes\s?n[\'o]t\sexists)/', $output, 'filter.pug fails as the upper filter does not exists.');
        $this->assertContains('filter.pug', $output, 'filter.pug fails as the upper filter does not exists.');
    }
}

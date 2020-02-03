<?php

namespace Pug\Tests\PugSymfonyBundle\Command;

use Pug\PugSymfonyBundle\Command\AssetsPublishCommand;
use Pug\PugSymfonyEngine;
use Pug\Tests\AbstractTestCase;
use Pug\Tests\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AssetsPublishCommandTest extends AbstractTestCase
{
    public function testCommand()
    {
        require_once __DIR__.'/../../TestKernel.php';

        self::$kernel = new TestKernel(function () {
        });
        self::$kernel->boot();

        $application = new Application(self::$kernel);
        $application->add(new AssetsPublishCommand(new PugSymfonyEngine(self::$kernel)));

        // Convert PHP style files to JS style
        $customHelperFile = __DIR__.'/../../../project-s5/templates/custom-helper.pug';
        $customHelper = file_get_contents($customHelperFile);
        file_put_contents($customHelperFile, 'if view.custom
    u=view.custom.foo()
else
    s Noop
');
        $stylePhpFile = __DIR__.'/../../../project-s5/templates/style-php.pug';
        $stylePhp = file_get_contents($stylePhpFile);
        file_put_contents($stylePhpFile, '.foo(style=\'background-position: 50% -402px; background-image: \' + css_url(\'assets/img/patterns/5.png\') + \';\')
.foo(style={\'background-position\': "50% -402px", \'background-image\': css_url(\'assets/img/patterns/5.png\')})
');
        $command = $application->find('assets:publish');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--env'   => 'prod',
        ]);

        $output = $commandTester->getDisplay();
        file_put_contents($customHelperFile, $customHelper);
        file_put_contents($stylePhpFile, $stylePhp);

        $this->assertStringContainsString('13 templates cached', $output, 'All templates can be cached except filter.pug as the upper filter does not exists.');
        $this->assertStringContainsString('1 templates failed to be cached', $output, 'filter.pug fails as the upper filter does not exists.');
        $this->assertRegExp('/(Unknown\sfilter\supper|upper:\sFilter\sdoes\s?n[\'o]t\sexists)/', $output, 'filter.pug fails as the upper filter does not exists.');
        $this->assertStringContainsString('filter.pug', $output, 'filter.pug fails as the upper filter does not exists.');
    }
}

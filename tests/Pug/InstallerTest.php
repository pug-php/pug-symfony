<?php

namespace Pug\Tests;

use Composer\Composer;
use Composer\Script\Event;
use Pug\PugSymfonyEngine;

require_once __DIR__.'/Composer/Composer.php';
require_once __DIR__.'/Composer/IOInterface.php';
require_once __DIR__.'/Composer/BaseIO.php';
require_once __DIR__.'/Composer/NullIO.php';
require_once __DIR__.'/Composer/CaptureIO.php';
require_once __DIR__.'/Composer/EventDispatcher/Event.php';
require_once __DIR__.'/Composer/Event.php';

class InstallerTest extends AbstractTestCase
{
    public function testTestInstall()
    {
        $io = new CaptureIO();
        $io->setInteractive(false);
        PugSymfonyEngine::install(new Event('update', new Composer(), $io));

        self::assertTrue(PugSymfonyEngine::install(new Event('update', new Composer(), $io)));

        self::assertTrue(PugSymfonyEngine::install(new Event('update', new Composer(), $io), (object) []));
    }
}

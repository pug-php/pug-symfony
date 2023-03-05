<?php

namespace Pug\Tests;

use Composer\Composer;
use Composer\Script\Event;
use Pug\PugSymfonyEngine;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__.'/Composer/Composer.php';
require_once __DIR__.'/Composer/IOInterface.php';
require_once __DIR__.'/Composer/BaseIO.php';
require_once __DIR__.'/Composer/NullIO.php';
require_once __DIR__.'/Composer/CaptureIO.php';
require_once __DIR__.'/Composer/EventDispatcher/Event.php';
require_once __DIR__.'/Composer/Event.php';

class InstallerTest extends AbstractTestCase
{
    public function testTestInstallQuickExit()
    {
        $io = new CaptureIO();
        $io->setInteractive(false);
        touch(__DIR__.'/../../installed');

        self::assertTrue(PugSymfonyEngine::install(new Event('update', new Composer(), $io)));
        self::assertSame([], $io->getLastOutput());

        self::assertTrue(PugSymfonyEngine::install(new Event('update', new Composer(), $io), (object) []));
        self::assertSame([], $io->getLastOutput());

        unlink(__DIR__.'/../../installed');

        self::assertTrue(PugSymfonyEngine::install(new Event('update', new Composer(), $io), __DIR__.'/Exceptions'));
        self::assertSame(['Not inside a composer vendor directory, setup skipped.'], $io->getLastOutput());
    }

    public function testTestInstall()
    {
        $projectDir = sys_get_temp_dir().'/pug-symfony-'.mt_rand(0, 9999999);
        $fs = new Filesystem();
        $fs->mkdir("$projectDir/config");
        $fs->copy(__DIR__.'/../project-s5/config/bundles-before.php', "$projectDir/config/bundles.php");
        $fs->copy(__DIR__.'/../../composer.json', "$projectDir/composer.json");
        $io = new CaptureIO();
        $io->setInteractive(false);

        self::assertTrue(PugSymfonyEngine::install(new Event('update', new Composer(), $io), $projectDir));
        self::assertSame(['Bundle added to config/bundles.php'], $io->getLastOutput());
        self::assertFileExists(__DIR__.'/../../installed');
        $getContent = static function (string $file): string {
            return preg_replace(
                '/(\S)\s+=>/',
                '$1 =>',
                str_replace("\r", '', file_get_contents($file))
            );
        };
        $expected = $getContent(__DIR__.'/../project-s5/config/bundles.php');
        $actual = $getContent("$projectDir/config/bundles.php");
        self::assertSame($expected, $actual);

        unlink(__DIR__.'/../../installed');
        $io->reset();

        self::assertTrue(PugSymfonyEngine::install(new Event('update', new Composer(), $io), $projectDir));
        self::assertSame(['The bundle already exists in config/bundles.php'], $io->getLastOutput());
        self::assertFileExists(__DIR__.'/../../installed');

        unlink(__DIR__.'/../../installed');
        file_put_contents("$projectDir/config/bundles.php", 'No longer an array.');
        $io->reset();

        self::assertTrue(PugSymfonyEngine::install(new Event('update', new Composer(), $io), $projectDir));
        self::assertSame(['Sorry, config/bundles.php has a format we can\'t handle automatically.'], $io->getLastOutput());
        self::assertFileDoesNotExist(__DIR__.'/../../installed');

        file_put_contents("$projectDir/config/bundles.php", file_get_contents(__DIR__.'/../project-s5/config/bundles-before.php'));
        $io->reset();
        $io->setInteractive(true);
        $io->setPermissive(false);

        self::assertTrue(PugSymfonyEngine::install(new Event('update', new Composer(), $io), $projectDir));
        self::assertSame([], $io->getLastOutput());
        self::assertFileEquals(__DIR__.'/../project-s5/config/bundles-before.php', "$projectDir/config/bundles.php");
        self::assertFileExists(__DIR__.'/../../installed');

        unlink(__DIR__.'/../../installed');
        $fs->remove($projectDir);
    }
}

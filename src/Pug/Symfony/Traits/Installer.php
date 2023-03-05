<?php

declare(strict_types=1);

namespace Pug\Symfony\Traits;

use Composer\IO\IOInterface;
use Composer\Script\Event;
use Pug\Symfony\Contracts\InstallerInterface;

/**
 * Trait Installer.
 */
trait Installer
{
    protected static function askConfirmation(IOInterface $io, string $message): bool
    {
        return !$io->isInteractive() || $io->askConfirmation($message);
    }

    /**
     * @SuppressWarnings(PHPMD.ErrorControlOperator)
     */
    protected static function installSymfonyBundle(
        IOInterface $io,
        string $dir,
        string $bundle,
        string $bundleClass,
        callable $proceedTask,
        int &$flags,
    ): void {
        $appFile = $dir.'/config/bundles.php';
        $contents = @file_get_contents($appFile) ?: '';

        if (!preg_match('/\[\s*\n/', $contents)) {
            $io->write('Sorry, config/bundles.php has a format we can\'t handle automatically.');

            return;
        }

        if (str_contains($contents, $bundleClass)) {
            $flags |= InstallerInterface::KERNEL_OK;
            $io->write('The bundle already exists in config/bundles.php');

            return;
        }

        $contents = preg_replace_callback('/\[\s*\n/', function ($match) use ($bundle) {
            return $match[0]."    $bundle\n";
        }, $contents);

        $proceedTask(
            file_put_contents($appFile, $contents),
            InstallerInterface::KERNEL_OK,
            'Bundle added to config/bundles.php',
            'Unable to add the bundle engine in config/bundles.php',
        );
    }

    /**
     * @param Event  $event
     * @param string $dir
     *
     * @return bool
     */
    protected static function installInSymfony5($event, $dir): bool
    {
        $io = $event->getIO();
        $baseDirectory = __DIR__.'/../../../..';

        $flags = 0;

        $bundleClass = 'Pug\PugSymfonyBundle\PugSymfonyBundle';
        $bundle = "$bundleClass::class => ['all' => true],";
        $addBundle = static::askConfirmation($io, 'Would you like us to add automatically the pug bundle in your config/bundles.php? [Y/N] ');

        $proceedTask = function ($taskResult, $flag, $successMessage, $errorMessage) use (&$flags, $io) {
            static::proceedTask($flags, $io, $taskResult, $flag, $successMessage, $errorMessage);
        };

        if ($addBundle) {
            static::installSymfonyBundle($io, $dir, $bundle, $bundleClass, $proceedTask, $flags);
        } else {
            $flags |= InstallerInterface::KERNEL_OK;
        }

        if (($flags & InstallerInterface::KERNEL_OK)) {
            touch($baseDirectory.'/installed');
        }

        return true;
    }

    public static function proceedTask(&$flags, $io, $taskResult, $flag, $successMessage, $message)
    {
        if ($taskResult) {
            $flags |= $flag;
            $message = $successMessage;
        }

        if ($io instanceof IOInterface) {
            $io->write($message);
        }
    }

    /**
     * @param Event  $event
     * @param string $dir
     *
     * @return bool
     */
    public static function install($event, $dir = null)
    {
        if (!is_string($dir)) {
            $dir = null;
        }

        $baseDirectory = __DIR__.'/../../../..';

        if (file_exists($baseDirectory.'/installed')) {
            return true;
        }

        $io = $event->getIO();
        $dir = is_string($dir) && is_dir($dir) ? $dir : $baseDirectory.'/../../..';

        if (!file_exists($dir.'/composer.json')) {
            $io->write('Not inside a composer vendor directory, setup skipped.');

            return true;
        }

        return static::installInSymfony5($event, $dir);
    }
}

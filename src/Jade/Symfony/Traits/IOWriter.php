<?php

namespace Jade\Symfony\Traits;

use Composer\IO\IOInterface;

/**
 * @internal
 *
 * Trait IOWriter.
 */
trait IOWriter
{
    protected static $flags = 0;

    protected static function askConfirmation(IOInterface $io, $message)
    {
        return !$io->isInteractive() || $io->askConfirmation($message);
    }

    protected function addFlag($flag)
    {
        static::$flags |= $flag;
    }

    protected function writeWithFlag(IOInterface $io, $message, $flag)
    {
        static::addFlag($flag);
        $io->write($message);
    }
}

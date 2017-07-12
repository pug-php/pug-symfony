<?php

namespace Jade;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump' => [
                ['onAutoloadDump', 0],
            ],
        ];
    }

    public function onAutoloadDump()
    {
        JadeSymfonyEngine::install();
    }
}

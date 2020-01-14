<?php

namespace Pug\PugSymfonyBundle\Command;

use Pug\PugSymfonyEngine;
use Symfony\Component\Console\Command\Command;

abstract class PugAwareCommand extends Command
{
    protected $pugSymfonyEngine;

    public function __construct(PugSymfonyEngine $pugSymfonyEngine)
    {
        $this->pugSymfonyEngine = $pugSymfonyEngine;
        parent::__construct(null);
    }
}

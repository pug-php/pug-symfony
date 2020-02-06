<?php

namespace Pug\Symfony;

use Pug\PugSymfonyEngine;
use Symfony\Contracts\EventDispatcher\Event;

class RenderEvent extends Event
{
    public const NAME = 'pug.render';

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $locals;

    /**
     * @var PugSymfonyEngine
     */
    protected $engine;

    public function __construct(string $name, array $locals, PugSymfonyEngine $engine)
    {
        $this->name = $name;
        $this->locals = $locals;
        $this->engine = $engine;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return array
     */
    public function getLocals(): array
    {
        return $this->locals;
    }

    /**
     * @param array $locals
     */
    public function setLocals(array $locals): void
    {
        $this->locals = $locals;
    }

    /**
     * @return PugSymfonyEngine
     */
    public function getEngine(): PugSymfonyEngine
    {
        return $this->engine;
    }
}

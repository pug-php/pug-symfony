<?php

declare(strict_types=1);

namespace Pug\Symfony\Contracts;

use Symfony\Contracts\EventDispatcher\Event;

interface InterceptorInterface
{
    public function intercept(Event $event);
}

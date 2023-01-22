<?php

namespace App\Service;

use Pug\PugSymfonyEngine;
use Pug\Symfony\Contracts\InterceptorInterface;
use Pug\Symfony\RenderEvent;
use Symfony\Contracts\EventDispatcher\Event;

class PugInterceptor implements InterceptorInterface
{
    public function __construct(private PugSymfonyEngine $pug)
    {
    }

    public function intercept(Event $event)
    {
        if ($event instanceof RenderEvent) {
            $locals = $event->getLocals();
            $locals['newVar'] = get_class($this->pug->getTwig());
            $event->setLocals($locals);

            if ($event->getEngine()->getOptionDefault('special-thing', false)) {
                $event->setName('p.pug');
            }
        }
    }
}

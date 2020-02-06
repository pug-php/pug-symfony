<?php

namespace App\Service;

use Pug\Symfony\Contracts\InterceptorInterface;
use Pug\Symfony\RenderEvent;
use Symfony\Contracts\EventDispatcher\Event;
use Twig\Environment;

class PugInterceptor implements InterceptorInterface
{
    /**
     * @var Environment
     */
    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function intercept(Event $event)
    {
        if ($event instanceof RenderEvent) {
            $locals = $event->getLocals();
            $locals['newVar'] = get_class($this->twig);
            $event->setLocals($locals);

            if ($event->getEngine()->getOptionDefault('special-thing', false)) {
                $event->setName('p.pug');
            }
        }
    }
}

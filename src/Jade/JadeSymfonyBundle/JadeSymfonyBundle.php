<?php

namespace Jade\JadeSymfonyBundle;

use Jade\Symfony\Traits\PrivatePropertyAccessor;
use Pug\PugSymfonyEngine;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class JadeSymfonyBundle extends Bundle
{
    use PrivatePropertyAccessor;

    protected $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;

        if ($container) {
            $engine = new PugSymfonyEngine($container->get('kernel'));
            $services = static::getPrivateProperty($container, 'services', $propertyAccessor);
            $services['Pug\\PugSymfonyEngine'] = $engine;
            $propertyAccessor->setValue($container, $services);
        }
    }
}

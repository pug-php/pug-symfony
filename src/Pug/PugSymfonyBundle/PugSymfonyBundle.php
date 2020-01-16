<?php

namespace Pug\PugSymfonyBundle;

use Pug\PugSymfonyBundle\Command\AssetsPublishCommand;
use Pug\PugSymfonyEngine;
use Pug\Symfony\Traits\PrivatePropertyAccessor;
use ReflectionMethod;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PugSymfonyBundle extends Bundle
{
    use PrivatePropertyAccessor;

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

    public function registerCommands(Application $application)
    {
        $method = new ReflectionMethod('Pug\\PugSymfonyBundle\\Command\\AssetsPublishCommand', '__construct');
        $class = $method->getNumberOfParameters() === 1 ? $method->getParameters()[0]->getClass() : null;

        if ($class && $class->getName() === 'Pug\\PugSymfonyEngine') {
            $application->addCommands([
                new AssetsPublishCommand($this->container->get('Pug\\PugSymfonyEngine')),
            ]);
        }
    }
}

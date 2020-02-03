<?php

namespace Pug\PugSymfonyBundle;

use Pug\PugSymfonyBundle\Command\AssetsPublishCommand;
use Pug\PugSymfonyBundle\DependencyInjection\PugSymfonyExtension;
use Pug\PugSymfonyEngine;
use Pug\Symfony\Traits\PrivatePropertyAccessor;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\KernelInterface;

class PugSymfonyBundle extends Bundle
{
    use PrivatePropertyAccessor;

    /**
     * @param ContainerInterface|null $container
     *
     * @throws ReflectionException
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;

        if ($container) {
            /** @var KernelInterface $kernel */
            $kernel = $container->get('kernel');
            $engine = new PugSymfonyEngine($kernel);
            /** @var ReflectionProperty $propertyAccessor */
            $services = static::getPrivateProperty($container, 'services', $propertyAccessor);
            $services[PugSymfonyEngine::class] = $engine;
            $propertyAccessor->setValue($container, $services);
        }
    }

    public function registerCommands(Application $application)
    {
        $method = new ReflectionMethod(AssetsPublishCommand::class, '__construct');
        $class = $method->getNumberOfParameters() === 1 ? $method->getParameters()[0]->getClass() : null;

        if ($class && $class->getName() === PugSymfonyEngine::class) {
            /** @var PugSymfonyEngine $engine */
            $engine = $this->container->get(PugSymfonyEngine::class);

            $application->addCommands([
                new AssetsPublishCommand($engine),
            ]);
        }
    }

    public function getContainerExtension()
    {
        return new PugSymfonyExtension();
    }
}

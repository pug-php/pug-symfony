<?php

declare(strict_types=1);

namespace Pug\PugSymfonyBundle;

use Pug\PugSymfonyBundle\Command\AssetsPublishCommand;
use Pug\PugSymfonyEngine;
use Pug\Symfony\Traits\PrivatePropertyAccessor;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PugSymfonyBundle extends Bundle
{
    use PrivatePropertyAccessor;

    public function build(ContainerBuilder $containerBuilder): void
    {
        $extension = new PugExtension();
        $containerBuilder->registerExtension($extension);
        $containerBuilder->loadFromExtension($extension->getAlias());
    }
    public function registerCommands(Application $application)
    {
        $application->addCommands([
            $this->container->get(AssetsPublishCommand::class),
        ]);
    }
}

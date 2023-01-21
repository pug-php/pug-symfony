<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Pug\\', __DIR__ . '/../../*')
        ->exclude([
            __DIR__ . '/../../Exceptions',
            __DIR__ . '/../../PugSymfonyBundle',
            __DIR__ . '/../../Symfony',
            __DIR__ . '/../../Twig',
        ]);

    $services->load('Pug\\PugSymfonyBundle\\Command\\', __DIR__ . '/../../PugSymfonyBundle/Command/*')
        ->public();
};

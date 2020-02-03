<?php

namespace Pug\PugSymfonyBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\KernelInterface;

class PugSymfonyExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        /** @var KernelInterface $kernel */
        $kernel = $container->get('kernel');
        $loader = new YamlFileLoader(
            $container,
            new FileLocator($kernel->getProjectDir().'/config')
        );
        $loader->load('pug.yaml');
    }
}

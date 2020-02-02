<?php

namespace Pug\Tests;

use App\Kernel;
use Closure;
use Exception;
use Symfony\Component\Config\Loader\LoaderInterface;

class TestKernel extends Kernel
{
    /**
     * @var Closure
     */
    private $containerConfigurator;

    public function __construct(Closure $containerConfigurator, $environment = 'test', $debug = false)
    {
        $this->containerConfigurator = $containerConfigurator;

        parent::__construct($environment, $debug);

        $this->rootDir = $this->getRootDir();
    }

    public function getLogDir()
    {
        return sys_get_temp_dir().'/pug-symfony-log';
    }

    public function getRootDir()
    {
        return realpath(__DIR__.'/../project-s5');
    }

    /**
     * @param LoaderInterface $loader
     *
     * @throws Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        parent::registerContainerConfiguration($loader);
        $loader->load(__DIR__.'/../project-s5/config/packages/framework.yaml');
        $loader->load($this->containerConfigurator);
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir().'/pug-symfony-cache';
    }

    /**
     * Override the parent method to force recompiling the container.
     * For performance reasons the container is also not dumped to disk.
     */
    protected function initializeContainer()
    {
        $this->container = $this->buildContainer();
        $this->container->compile();
        $this->container->set('kernel', $this);
    }
}

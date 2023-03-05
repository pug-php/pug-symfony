<?php

namespace Pug\Tests;

use App\Kernel;
use Closure;
use Exception;
use Symfony\Component\Config\Loader\LoaderInterface;

class TestKernel extends Kernel
{
    private Closure $containerConfigurator;

    private ?string $projectDirectory = null;

    private string $rootDir;

    public function __construct(Closure $containerConfigurator = null, $environment = 'test', $debug = false)
    {
        $this->containerConfigurator = $containerConfigurator ?? static function () {
        };

        parent::__construct($environment, $debug);

        $this->rootDir = $this->getRootDir();
    }

    public function getProjectDir(): string
    {
        return $this->projectDirectory ?? parent::getProjectDir();
    }

    /**
     * @param string $projectDirectory
     */
    public function setProjectDirectory(string $projectDirectory): void
    {
        $this->projectDirectory = $projectDirectory;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/pug-symfony-log';
    }

    public function getRootDir(): string
    {
        return realpath(__DIR__.'/../project-s5');
    }

    /**
     * @param LoaderInterface $loader
     *
     * @throws Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);
        $loader->load(__DIR__.'/../project-s5/config/packages/framework.yaml');
        $loader->load($this->containerConfigurator);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/pug-symfony-cache';
    }

    /**
     * Override the parent method to force recompiling the container.
     * For performance reasons the container is also not dumped to disk.
     */
    protected function initializeContainer(): void
    {
        $this->container = $this->buildContainer();
        $this->container->compile();
        $this->container->set('kernel', $this);
    }
}

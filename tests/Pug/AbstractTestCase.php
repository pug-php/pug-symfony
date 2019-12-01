<?php

namespace Pug\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

abstract class AbstractTestCase extends KernelTestCase
{
    private static function getConfigFiles()
    {
        return [
            __DIR__ . '/../project-s4/config/packages/framework.yaml',
            __DIR__ . '/../project/app/config.yml',
            __DIR__ . '/../project/app/config/config.yml',
        ];
    }

    protected static function isAtLeastSymfony5()
    {
        return defined('Symfony\Component\HttpKernel\Kernel::VERSION') &&
            version_compare(Kernel::VERSION, '5.0.0-dev', '>=');
    }

    protected static function handleKernelRootDir($configFiles)
    {
        foreach ((array) $configFiles as $configFile) {
            if (defined('Symfony\Component\HttpKernel\Kernel::VERSION') &&
                version_compare(Kernel::VERSION, '5.0.0-dev', '>=')
            ) {
                file_put_contents($configFile, str_replace('%kernel.root_dir%', '%kernel.project_dir%', file_get_contents($configFile)));
            }
        }
    }

    protected static function clearCache()
    {
        foreach (['app', 'var'] as $directory) {
            try {
                (new Filesystem())->remove(__DIR__ . "/../project/$directory/cache");
            } catch (\Exception $e) {
                // noop
            }
        }
    }

    public static function setUpBeforeClass()
    {
        self::handleKernelRootDir(self::getConfigFiles());
        self::clearCache();
    }

    public static function tearDownAfterClass()
    {
        self::clearCache();
        foreach (self::getConfigFiles() as $file) {
            file_put_contents($file, str_replace('%kernel.project_dir%', '%kernel.root_dir%', file_get_contents($file)));
        }
    }

    public function setUp()
    {
        self::bootKernel();
    }
}

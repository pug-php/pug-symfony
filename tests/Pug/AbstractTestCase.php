<?php

namespace Pug\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

abstract class AbstractTestCase extends KernelTestCase
{
    protected static $originalFiles = [];

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
        return defined('Symfony\\Component\\HttpKernel\\Kernel::VERSION') &&
            version_compare(Kernel::VERSION, '5.0.0-dev', '>=');
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
        if (static::isAtLeastSymfony5()) {
            foreach (self::getConfigFiles() as $file) {
                $contents = file_get_contents($file);

                if (!isset(static::$originalFiles[$file])) {
                    static::$originalFiles[$file] = $contents;
                }

                file_put_contents($file, strtr($contents, [
                    '%kernel.root_dir%'                            => '%kernel.project_dir%',
                    "templating: { engines: ['pug', 'php'] }"      => '',
                    "templating:\n        engines: ['pug', 'php']" => '',
                ]));
            }
        }
        self::clearCache();
    }

    public static function tearDownAfterClass()
    {
        self::clearCache();
        foreach (self::getConfigFiles() as $file) {
            if (isset(static::$originalFiles[$file])) {
                file_put_contents($file, static::$originalFiles[$file]);
            }
        }
    }

    public function setUp()
    {
        self::bootKernel();
    }
}

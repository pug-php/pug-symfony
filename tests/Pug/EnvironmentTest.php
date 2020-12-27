<?php

namespace Pug\Tests;

use Pug\Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

class EnvironmentTest extends AbstractTestCase
{
    /**
     * @throws RuntimeError
     */
    public function testWithoutRoot()
    {
        self::expectException(RuntimeError::class);
        self::expectExceptionMessage('Unable to load the "I-surely-does-not-exist" runtime.');

        $env = new Environment(new ArrayLoader());
        $env->getRuntime('I-surely-does-not-exist');
    }

    /**
     * @throws RuntimeError
     */
    public function testWithRoot()
    {
        self::expectException(RuntimeError::class);
        self::expectExceptionMessage('Unable to load the "I-surely-does-not-exist" runtime.');

        $env = new Environment(new ArrayLoader());
        $env->rootEnv = new Environment(new ArrayLoader());
        $env->getRuntime('I-surely-does-not-exist');
    }
}

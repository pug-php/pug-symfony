<?php

namespace Pug\Twig;

use Twig\Environment as TwigEnvironment;

// @codeCoverageIgnoreStart
$version = version_compare(TwigEnvironment::VERSION, '3.0.0-dev', '>=') ? 3 : 2;
require_once __DIR__ . '/../../../polyfill/Pug/Twig/EnvironmentTwig' . $version . '.php';
$version === 2
    ? class_alias('Pug\\Twig\\EnvironmentTwig2', 'Pug\\Twig\\EnvironmentTwigPolyfill')
    : class_alias('Pug\\Twig\\EnvironmentTwig3', 'Pug\\Twig\\EnvironmentTwigPolyfill');

class Environment extends EnvironmentTwigPolyfill
{
}

// @codeCoverageIgnoreEnd

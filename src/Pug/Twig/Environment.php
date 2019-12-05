<?php

namespace Pug\Twig;

use Twig\Environment as TwigEnvironment;

// @codeCoverageIgnoreStart
require_once __DIR__ . '/../../../polyfill/Pug/Twig/EnvironmentTwig' . TwigEnvironment::MAJOR_VERSION . '.php';
class_alias('Pug\\Twig\\EnvironmentTwig' . TwigEnvironment::MAJOR_VERSION, 'Pug\\Twig\\EnvironmentTwigPolyfill');

class Environment extends EnvironmentTwigPolyfill
{
}

// @codeCoverageIgnoreEnd

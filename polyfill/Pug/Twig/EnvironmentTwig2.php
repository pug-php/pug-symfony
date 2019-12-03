<?php

namespace Pug\Twig;

use Twig\Source;

/**
 * @codeCoverageIgnore
 */
class EnvironmentTwig2 extends EnvironmentBase
{
    public function compileSource(Source $source)
    {
        return $this->compileSourceBase($source);
    }

    public function render($name, array $context = [])
    {
        return $this->renderBase($name, array_merge($this->pugSymfonyEngine->getSharedVariables(), $context));
    }
}

<?php

namespace Pug\Twig;

/**
 * @codeCoverageIgnore
 */
class EnvironmentTwig1 extends EnvironmentBase
{
    public function compileSource($source, $name = null)
    {
        return $this->compileSourceBase($source);
    }

    public function render($name, array $context = [])
    {
        return $this->renderBase($name, array_merge($this->pugSymfonyEngine->getSharedVariables(), $context));
    }
}

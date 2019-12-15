<?php

namespace Pug\Twig;

use Twig\Source;
use Twig\Template;

/**
 * @codeCoverageIgnore
 */
class EnvironmentTwig3 extends EnvironmentBase
{
    /**
     * @var string[]
     */
    protected $classNames = [];

    public function compileSource(Source $source): string
    {
        return $this->compileSourceBase($source);
    }

    public function loadTemplate(string $cls, string $name, int $index = null): Template
    {
        if ($index !== null) {
            $cls .= '___' . $index;
        }

        $this->classNames[$name] = $cls;

        return parent::loadTemplate($cls, $name, $index);
    }

    public function render($name, array $context = []): string
    {
        return $this->renderBase($name, array_merge($this->pugSymfonyEngine->getSharedVariables(), $context));
    }

    public function getFunctionAsCallable($name)
    {
        $function = $this->getFunction($name);

        if (!$function) {
            throw new BadMethodCallException("twig.$name function not fount.");
        }

        $callable = $function->getCallable();

        if ($callable && is_string($callable[0])) {
            $callable[0] = $this->getRuntime($callable[0]);
        }

        if (!is_callable($callable)) {
            throw new BadMethodCallException("twig.$name seems not to be callable.");
        }

        return $callable;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array($this->getFunctionAsCallable($name), $arguments);
    }
}

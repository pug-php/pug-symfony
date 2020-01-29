<?php

namespace Pug\Twig;

use Twig\Error\RuntimeError;
use Twig\Source;
use Twig\Template;
use Twig\TwigFunction;

/**
 * @codeCoverageIgnore
 */
class Environment extends EnvironmentBase
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
            $cls .= '___'.$index;
        }

        $this->classNames[$name] = $cls;

        return parent::loadTemplate($cls, $name, $index);
    }

    public function render($name, array $context = []): string
    {
        return $this->renderBase($name, array_merge($this->pugSymfonyEngine->getSharedVariables(), $context));
    }

    /**
     * Execute at runtime a Twig function.
     *
     * @param TwigFunction $function  Twig function origin definition object.
     * @param array        $arguments Runtime function arguments passed in the template.
     *
     * @throws RuntimeError
     *
     * @return mixed
     */
    public function runFunction(TwigFunction $function, array $arguments)
    {
        $callable = $function->getCallable();
        $service = $this->getRuntime($callable[0]);

        return $service->{$callable[1]}(...$arguments);
    }
}

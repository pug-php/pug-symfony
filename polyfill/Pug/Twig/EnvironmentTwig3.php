<?php

namespace Pug\Twig;

use Jade\Exceptions\ReservedVariable;
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
        if ($this->pugSymfonyEngine->supports($name)) {
            foreach (['context', 'blocks', 'macros'] as $name) {
                if (array_key_exists($name, $context)) {
                    throw new ReservedVariable($name);
                }
            }
        }

        return $this->renderBase($name, array_merge($this->pugSymfonyEngine->getSharedVariables(), $context));
    }
}

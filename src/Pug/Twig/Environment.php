<?php

namespace Pug\Twig;

use RuntimeException;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\ExpressionParser;
use Twig\Loader\LoaderInterface;
use Twig\Node\Node;
use Twig\Parser;
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

    public function getTemplateClass(string $name, int $index = null): string
    {
        if (substr($name, 0, 16) === '__twig_function_') {
            return 'TwigFunctionTemplate_'.sha1($name);
        }

        return parent::getTemplateClass($name, $index);
    }

    public function compileCode(TwigFunction $function, string $code)
    {
        $name = $function->getName();
        $arguments[] = $name;
        $parser = new Parser($this);
        $path = '__twig_function_'.$name.'_'.sha1($code).'.html.twig';
        $stream = $this->tokenize(new Source($code, $path, $path));

        if (!preg_match('/^\s*echo\s(.*);\s*$/m', $this->compile($parser->parse($stream)), $match)) {
            throw new RuntimeException('Unable to compile '.$name.' function.');
        }

        return trim($match[1]);
    }

    /**
     * Execute at runtime a Twig function.
     *
     * @param TwigFunction $function  Twig function origin definition object.
     * @param array        $arguments Runtime function arguments passed in the template.
     *
     * @throws SyntaxError
     * @throws RuntimeError
     *
     * @return mixed
     */
    public function runFunction(TwigFunction $function, array $arguments)
    {
        $callable = $function->getCallable();

        if (!$callable) {
            $name = $function->getName();
            $arguments[] = $name;
            $parser = new Parser($this);
            $variables = [];
            foreach ($arguments as $index => $argument) {
                $variables['arg'.$index] = $argument;
            }
            $path = '__twig_function_'.$name.'_'.count($variables).'.html.twig';
            $stream = $this->tokenize(new Source('{{ '.$name.'('.implode(', ', array_keys($variables)).') }}', $path, $path));

            if (!preg_match('/^\s*echo\s(.*);\s*$/m', $this->compile($parser->parse($stream)), $match)) {
                throw new RuntimeException('Unable to compile '.$name.' function.');
            }
            $code = trim($match[1]);
            $callable = function ($__php_code, $__variables) {
                extract($__variables);
                eval($__php_code);
            };
            $callable->bindTo($this);

            return $callable($code, $variables);
        }

        $service = $this->getRuntime($callable[0]);

        return $service->{$callable[1]}(...$arguments);
    }
}

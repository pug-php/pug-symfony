<?php

declare(strict_types=1);

namespace Pug\Twig;

use Psr\Container\ContainerInterface;
use Pug\PugSymfonyEngine;
use Pug\Symfony\Traits\PrivatePropertyAccessor;
use RuntimeException;
use Twig\Environment as TwigEnvironment;
use Twig\Error\RuntimeError;
use Twig\Extension\EscaperExtension;
use Twig\Extension\OptimizerExtension;
use Twig\Loader\LoaderInterface;
use Twig\Parser;
use Twig\Source;
use Twig\Template;
use Twig\TwigFunction;

class Environment extends TwigEnvironment
{
    use PrivatePropertyAccessor;

    protected PugSymfonyEngine $pugSymfonyEngine;

    protected ContainerInterface $container;

    /**
     * @var string[]
     */
    protected array $classNames = [];

    public array $extensions = [];

    public TwigEnvironment $rootEnv;

    public TwigEnvironment $env;

    public function __construct(LoaderInterface $loader, $options = [])
    {
        parent::__construct($loader, $options);

        $this->extensions = $this->getExtensions();
    }

    public function getEngine()
    {
        return $this->pugSymfonyEngine;
    }

    public function getRenderer()
    {
        return $this->pugSymfonyEngine->getRenderer();
    }

    public function getRuntime(string $class)
    {
        try {
            return parent::getRuntime($class);
        } catch (RuntimeError $error) {
            if (!($this->rootEnv ?? null)) {
                throw $error;
            }

            try {
                return $this->rootEnv->getRuntime($class);
            } catch (RuntimeError) {
                throw $error;
            }
        }
    }

    public static function fromTwigEnvironment(
        TwigEnvironment $baseTwig,
        PugSymfonyEngine $pugSymfonyEngine,
        ContainerInterface $container,
    ): static {
        $twig = new static($baseTwig->getLoader(), [
            'debug'            => $baseTwig->isDebug(),
            'charset'          => $baseTwig->getCharset(),
            'strict_variables' => $baseTwig->isStrictVariables(),
            'autoescape'       => static::getPrivateExtensionProperty($baseTwig, EscaperExtension::class, 'defaultStrategy'),
            'cache'            => $baseTwig->getCache(true),
            'auto_reload'      => $baseTwig->isAutoReload(),
            'optimizations'    => static::getPrivateExtensionProperty($baseTwig, OptimizerExtension::class, 'optimizers'),
        ]);
        $twig->rootEnv = $baseTwig;

        $twig->setPugSymfonyEngine($pugSymfonyEngine);
        $twig->setContainer($container);

        $extensions = $baseTwig->getExtensions();

        foreach (array_keys($twig->getExtensions()) as $key) {
            unset($extensions[$key]);
        }

        foreach ($baseTwig->getGlobals() as $key => $value) {
            $twig->addGlobal($key, $value);
        }

        foreach ($baseTwig->getFilters() as $filter) {
            $twig->addFilter($filter);
        }

        foreach ($baseTwig->getFunctions() as $function) {
            $twig->addFunction($function);
        }

        $twig->setExtensions($extensions);

        return $twig;
    }

    public function setPugSymfonyEngine(PugSymfonyEngine $pugSymfonyEngine): void
    {
        $this->pugSymfonyEngine = $pugSymfonyEngine;
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @SuppressWarnings(PHPMD.DuplicatedArrayKey)
     */
    public function compileSource(Source $source): string
    {
        $path = $source->getPath();

        if ($this->pugSymfonyEngine->supports($path)) {
            $pug = $this->getRenderer();
            $code = $source->getCode();
            $php = $pug->compile($code, $path);
            $codeFirstLine = $this->isDebug() ? 39 : 28;
            $templateLine = 1;
            $debugInfo = [$codeFirstLine => $templateLine];
            $lines = explode("\n", $php);

            if ($this->isDebug()) {
                $formatter = $pug->getCompiler()->getFormatter();

                foreach ($lines as $index => $line) {
                    if (preg_match('/^\/\/ PUG_DEBUG:(\d+)$/m', $line, $match)) {
                        $node = $formatter->getNodeFromDebugId((int) $match[1]);
                        $location = $node->getSourceLocation();

                        if ($location) {
                            $newLine = $location->getLine();

                            if ($newLine > $templateLine) {
                                $templateLine = $newLine;
                                $debugInfo[$codeFirstLine + $index] = $newLine;
                            }
                        }
                    }
                }
            }

            $fileName = $this->isDebug() ? 'PugDebugTemplateTemplate' : 'PugTemplateTemplate';
            $templateFile = __DIR__."/../../../cache-templates/$fileName.php";
            $name = $source->getName();
            $className = $this->classNames[$name] ?? '__Template_'.sha1($path);
            $pathExport = var_export($path, true);
            $replacements = [
                $fileName               => $className,
                "'{{filename}}'"        => var_export($name, true),
                '{{filename}}'          => $name,
                "'{{path}}'"            => $pathExport,
                '// {{code}}'           => "?>$php<?php",
                '[/* {{debugInfo}} */]' => var_export(array_reverse($debugInfo, true), true),
            ];

            if ($this->isDebug()) {
                $sourceExport = var_export($code, true);
                $replacements["'{{source}}'"] = $sourceExport;
                $replacements['__internal_1'] = '__internal_'.sha1('1'.$path);
                $replacements['__internal_2'] = '__internal_'.sha1('2'.$path);
            }

            return strtr(file_get_contents($templateFile), $replacements);
        }

        return parent::compileSource($source);
    }

    public function loadTemplate(string $cls, string $name, int $index = null): Template
    {
        $this->classNames[$name] = $cls.($index === null ? '' : '___'.$index);

        return parent::loadTemplate($cls, $name, $index);
    }

    public function render($name, array $context = []): string
    {
        if (is_string($name) && $this->pugSymfonyEngine->supports($name)) {
            [$name, $context] = $this->pugSymfonyEngine->getRenderArguments($name, $context);
        }

        return parent::render($name, $context);
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
        $parser = new Parser($this);
        $path = '__twig_function_'.$name.'_'.sha1($code).'.html.twig';
        $stream = $this->tokenize(new Source($code, $path, $path));
        $output = $this->compile($parser->parse($stream));

        if (!preg_match('/^\s*echo\s(.*);\s*$/m', $output, $match)) {
            throw new RuntimeException('Unable to compile '.$name.' function.');
        }

        return '('.trim($match[1]).')'."\n";
    }

    protected static function getPrivateExtensionProperty(TwigEnvironment $twig, string $extension, string $property)
    {
        return static::getPrivateProperty($twig->getExtension($extension), $property);
    }
}

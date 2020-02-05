<?php

namespace Pug\Twig;

use Pug\PugSymfonyEngine;
use Pug\Symfony\Traits\PrivatePropertyAccessor;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment as TwigEnvironment;
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

    /**
     * @var PugSymfonyEngine
     */
    protected $pugSymfonyEngine;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var string[]
     */
    protected $classNames = [];

    /**
     * @var array
     */
    public $extensions = [];

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

    public static function fromTwigEnvironment(TwigEnvironment $baseTwig, PugSymfonyEngine $pugSymfonyEngine, ContainerInterface $container)
    {
        $twig = new static($baseTwig->getLoader(), [
            'debug'            => $baseTwig->isDebug(),
            'charset'          => $baseTwig->getCharset(),
            'strict_variables' => $baseTwig->isStrictVariables(),
            'autoescape'       => static::getPrivateExtensionProperty($baseTwig, EscaperExtension::class, 'defaultStrategy'),
            'cache'            => $baseTwig->getCache(true),
            'auto_reload'      => $baseTwig->isAutoReload(),
            'optimizations'    => static::getPrivateExtensionProperty($baseTwig, OptimizerExtension::class, 'optimizers'),
        ]);

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

    /**
     * @param PugSymfonyEngine $pugSymfonyEngine
     */
    public function setPugSymfonyEngine(PugSymfonyEngine $pugSymfonyEngine)
    {
        $this->pugSymfonyEngine = $pugSymfonyEngine;
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function compileSourceBase(Source $source)
    {
        $path = $source->getPath();

        if ($this->pugSymfonyEngine->supports($path)) {
            $pug = $this->pugSymfonyEngine->getRenderer();
            $code = $source->getCode();
            $php = $pug->compile($code, $path);
            $codeFirstLine = $this->isDebug() ? 31 : 25;
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
            $className = isset($this->classNames[$name]) ? $this->classNames[$name] : '__Template_'.sha1($path);
            $replacements = [
                $fileName               => $className,
                '"{{filename}}"'        => var_export($name, true),
                '{{filename}}'          => $name,
                '"{{path}}"'            => var_export($path, true),
                '// {{code}}'           => "?>$php<?php",
                '[/* {{debugInfo}} */]' => var_export($debugInfo, true),
            ];

            if ($this->isDebug()) {
                $replacements['"{{source}}"'] = var_export($code, true);
                $replacements['__internal_1'] = '__internal_'.sha1('1'.$path);
                $replacements['__internal_2'] = '__internal_'.sha1('2'.$path);
            }

            return strtr(file_get_contents($templateFile), $replacements);
        }

        $html = parent::compileSource($source);

        return $html;
    }

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
        $arguments[] = $name;
        $parser = new Parser($this);
        $path = '__twig_function_'.$name.'_'.sha1($code).'.html.twig';
        $stream = $this->tokenize(new Source($code, $path, $path));

        if (!preg_match('/^\s*echo\s(.*);\s*$/m', $this->compile($parser->parse($stream)), $match)) {
            throw new RuntimeException('Unable to compile '.$name.' function.');
        }

        return trim($match[1]);
    }

    protected static function getPrivateExtensionProperty(TwigEnvironment $twig, string $extension, string $property)
    {
        return static::getPrivateProperty($twig->getExtension($extension), $property);
    }
}

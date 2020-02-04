<?php

namespace Pug\Twig;

use Pug\Exceptions\ReservedVariable;
use Pug\PugSymfonyEngine;
use Pug\Symfony\Traits\PrivatePropertyAccessor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\LoaderInterface;
use Twig\Source;

abstract class EnvironmentBase extends TwigEnvironment
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
            'autoescape'       => static::getPrivateProperty(
                $baseTwig->getExtension('Twig\\Extension\\EscaperExtension'),
                'defaultStrategy'
            ),
            'cache'            => $baseTwig->getCache(true),
            'auto_reload'      => $baseTwig->isAutoReload(),
            'optimizations'    => static::getPrivateProperty(
                $baseTwig->getExtension('Twig\\Extension\\OptimizerExtension'),
                'optimizers'
            ),
        ]);

        $twig->setPugSymfonyEngine($pugSymfonyEngine);
        $twig->setContainer($container);

        $extensions = $baseTwig->getExtensions();

        foreach (array_keys($twig->getExtensions()) as $key) {
            unset($extensions[$key]);
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

    public function loadTemplateBase(string $cls, string $name, int $index = null)
    {
        if ($index !== null) {
            $cls .= '___'.$index;
        }

        $this->classNames[$name] = $cls;

        return parent::loadTemplate($cls, $name, $index);
    }

    public function renderBase($name, array $context = [])
    {
        if (is_string($name) && $this->pugSymfonyEngine->supports($name)) {
            foreach (['context', 'blocks', 'macros'] as $variable) {
                if (array_key_exists($variable, $context)) {
                    throw new ReservedVariable($variable);
                }
            }
        }

        return parent::render($name, $context);
    }
}

<?php

namespace Pug\Symfony\Traits;

use Closure;
use Phug\Component\ComponentExtension;
use Pug\Assets;
use Pug\Pug;
use Pug\Symfony\CssExtension;
use Pug\Symfony\MixedLoader;
use Pug\Twig\Environment;
use RuntimeException;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RequestContext;
use Twig\Environment as TwigEnvironment;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Trait HelpersHandler.
 */
trait HelpersHandler
{
    use PrivatePropertyAccessor;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var Kernel|KernelInterface
     */
    protected $kernel;

    /**
     * @var Pug|null
     */
    protected $pug;

    /**
     * @var array
     */
    protected $helpers;

    /**
     * @var array
     */
    protected $twigHelpers;

    /**
     * @var array
     */
    protected $templatingHelpers = [
        'actions',
        'assets',
        'code',
        'form',
        'request',
        'router',
        'security',
        'session',
        'slots',
        'stopwatch',
        'translator',
    ];

    protected static $globalHelpers = [];

    /**
     * Get a global helper by name.
     *
     * @param string $name
     *
     * @return callable
     */
    public static function getGlobalHelper($name)
    {
        return is_callable(static::$globalHelpers[$name])
            ? static::$globalHelpers[$name]
            : function ($input = null) {
                return $input;
            };
    }

    /**
     * Get an helper by name.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function offsetGet($name)
    {
        return $this->helpers[$name];
    }

    /**
     * Check if an helper exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public function offsetExists($name)
    {
        return isset($this->helpers[$name]);
    }

    /**
     * Set an helper.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function offsetSet($name, $value)
    {
        $this->helpers[$name] = $value;
    }

    /**
     * Remove an helper.
     *
     * @param string $name
     */
    public function offsetUnset($name)
    {
        unset($this->helpers[$name]);
    }

    /**
     * Get the Pug engine.
     *
     * @return Pug
     */
    public function getRenderer(): Pug
    {
        if ($this->pug === null) {
            $cache = $this->getCacheDir();
            (new Filesystem())->mkdir($cache);
            $userOptions = ($this->container->hasParameter('pug') ? $this->container->getParameter('pug') : null) ?: [];

            $this->pug = $this->createEngine($this->getRendererOptions($cache, $userOptions));
            $this->registerHelpers(array_slice(func_get_args(), 1));
            $this->initializePugPlugins($userOptions);
            $this->copyTwigGlobals();
            $this->setOption('paths', array_unique($this->getOptionDefault('paths', [])));
        }

        return $this->pug;
    }

    protected function getRendererOptions(string $cache, array $userOptions): array
    {
        $environment = $this->kernel->getEnvironment();
        $projectDirectory = $this->kernel->getProjectDir();
        $assetsDirectories = [$projectDirectory.'/Resources/assets'];
        $viewDirectories = [$projectDirectory.'/templates'];

        if (($loader = $this->getTwig()->getLoader()) instanceof FilesystemLoader &&
            is_array($paths = $loader->getPaths()) &&
            isset($paths[0])
        ) {
            $viewDirectories[] = $paths[0];
        }

        $srcDir = $projectDirectory.'/src';
        $webDir = $projectDirectory.'/public';
        $baseDir = isset($userOptions['baseDir'])
            ? $userOptions['baseDir']
            : $this->crawlDirectories($srcDir, $assetsDirectories, $viewDirectories);
        $baseDir = $baseDir && file_exists($baseDir) ? realpath($baseDir) : $baseDir;
        $this->defaultTemplateDirectory = $baseDir;

        if (isset($userOptions['paths'])) {
            $viewDirectories = array_merge($viewDirectories, $userOptions['paths'] ?: []);
        }

        $debug = $this->kernel->isDebug();
        $options = array_merge([
            'debug'           => $debug,
            'assetDirectory'  => static::extractUniquePaths($assetsDirectories),
            'viewDirectories' => static::extractUniquePaths($viewDirectories),
            'baseDir'         => $baseDir,
            'cache'           => $debug ? false : $cache,
            'environment'     => $environment,
            'extension'       => ['.pug', '.jade'],
            'outputDirectory' => $webDir,
            'prettyprint'     => $debug,
            'on_node'         => [$this, 'handleTwigInclude'],
        ], $userOptions);

        $options['paths'] = array_unique(array_filter($options['viewDirectories'], function ($path) use ($baseDir) {
            return $path !== $baseDir;
        }));

        return $options;
    }

    protected function createEngine(array $options): Pug
    {
        $pug = new Pug($options);
        /** @var Closure|null $transformation */
        $transformation = $pug->hasOption('patterns')
            ? ($pug->getOption('patterns')['transform_expression'] ?? null)
            : null;
        $pug->setOptionsRecursive([
            'patterns' => [
                'transform_expression' => function ($code) use ($transformation) {
                    if ($transformation) {
                        $code = $transformation($code);
                    }

                    return $this->interpolateTwigFunctions($code);
                },
            ],
        ]);

        return $pug;
    }

    protected function copyTwigGlobals(): void
    {
        foreach ($this->getTwig()->getGlobals() as $globalKey => $globalValue) {
            if ($globalValue instanceof AppVariable) {
                $globalValue->setDebug($this->kernel->isDebug());
                $globalValue->setEnvironment($this->kernel->getEnvironment());
                $globalValue->setRequestStack($this->container->get('request_stack'));
                // @codeCoverageIgnoreStart
                if ($this->container->has('security.token_storage')) {
                    $globalValue->setTokenStorage($this->container->get('security.token_storage'));
                }
                // @codeCoverageIgnoreEnd
            }

            $this->share($globalKey, $globalValue);
        }
    }

    protected function initializePugPlugins(array $userOptions): void
    {
        $pug = $this->getRenderer();

        if ($userOptions['assets'] ?? true) {
            $this->assets = new Assets($pug);
        }

        if ($userOptions['component'] ?? true) {
            ComponentExtension::enable($pug);

            $this->componentExtension = $pug->getModule(ComponentExtension::class);

            $pug->getCompiler()->setOption('mixin_keyword', $pug->getOption('mixin_keyword'));
        }
    }

    protected function interpolateTwigFunctions(string $code): string
    {
        $tokens = array_slice(token_get_all('<?php '.$code), 1);
        $output = '';
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_STRING && $tokens[$index + 1] === '(') {
                if ($token[1] === 'function_exists') {
                    if ($tokens[$index + 3] === ')' && is_array($tokens[$index + 2]) && $tokens[$index + 2][0] === T_CONSTANT_ENCAPSED_STRING && isset($this->twigHelpers[substr($tokens[$index + 2][1], 1, -1)])) {
                        $output .= 'true';
                        $index += 3;
                        continue;
                    }
                } elseif (isset($this->twigHelpers[$token[1]])) {
                    $index += 2;
                    $arguments = [];
                    $argumentNeedInterpolation = false;
                    $argument = '';

                    for ($opening = 1; $opening !== 0; $index++) {
                        switch ($tokens[$index]) {
                            case '(':
                                $opening++;
                                $argumentNeedInterpolation = true;
                                $argument .= '(';

                                break;

                            case ')':
                                if ((--$opening) !== 0) {
                                    $argument .= ')';
                                }

                                break;

                            case ',':
                                if ($opening > 1) {
                                    $argument .= ',';

                                    break;
                                }

                                $this->pushArgument($arguments, $argument, $argumentNeedInterpolation);

                                break;

                            default:
                                $argument .= $this->getTokenImage($tokens[$index]);
                        }
                    }

                    $this->pushArgument($arguments, $argument, $argumentNeedInterpolation);
                    $placeholders = [];

                    foreach ($arguments as $number => $argument) {
                        $placeholders["\"__argument_placeholder_$number\""] = $argument;
                    }

                    $output .= strtr($this->getTwig()->compileCode(
                        $this->twigHelpers[$token[1]],
                        '{{ '.$token[1].'('.implode(', ', array_keys($placeholders)).') | raw }}'
                    ), $placeholders);

                    continue;
                }
            }

            $output .= $this->getTokenImage($token);
        }

        return $output;
    }

    protected function getTokenImage($token): string
    {
        return is_array($token) ? $token[1] : $token;
    }

    protected function pushArgument(array &$arguments, string &$argument, bool &$argumentNeedInterpolation)
    {
        $argument = trim($argument);

        if ($argument !== '') {
            if ($argumentNeedInterpolation) {
                $argument = $this->interpolateTwigFunctions($argument);
                $argumentNeedInterpolation = false;
            }

            $arguments[] = $argument;
        }

        $argument = '';
    }

    protected function getTemplatingHelper(string $name)
    {
        return isset($this->helpers[$name]) ? $this->helpers[$name] : null;
    }

    protected function copyTwigFunction(Environment $twig, TwigFunction $function): void
    {
        $name = $function->getName();

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            // Methods like render_* not yet supported
            return;
        }

        $this->twigHelpers[$name] = $function;
    }

    protected function enhanceTwig(): void
    {
        $this->twig = $this->container->has('twig') ? $this->container->get('twig') : null;

        if (!($this->twig instanceof TwigEnvironment)) {
            throw new RuntimeException('Twig service not configured.');
        }

        $this->twig = Environment::fromTwigEnvironment($this->twig, $this, $this->container);

        $services = static::getPrivateProperty($this->container, 'services', $propertyAccessor);
        $services['twig'] = $this->twig;
        $propertyAccessor->setValue($this->container, $services);
    }

    protected function getTwig(): Environment
    {
        return $this->twig;
    }

    protected function copyTwigFunctions(): void
    {
        $this->twigHelpers = [];
        $twig = $this->getTwig();
        $twig->env = $twig;
        $loader = new MixedLoader($twig->getLoader());
        $twig->setLoader($loader);
        $this->share('twig', $twig);
        $twig->extensions = $twig->getExtensions();

        if (!isset($twig->extensions[AssetExtension::class])) {
            $assetExtension = new AssetExtension(new Packages(new Package(new EmptyVersionStrategy())));
            $twig->extensions[AssetExtension::class] = $assetExtension;
            $twig->addExtension($assetExtension);
        }

        foreach ($this->helpers as $helper) {
            $class = get_class($helper);

            if (!isset($twig->extensions[$class])) {
                $twig->extensions[$class] = $helper;
                $twig->addExtension($helper);
            }
        }

        foreach ($twig->extensions as $extension) {
            /* @var ExtensionInterface $extension */
            foreach ($extension->getFunctions() as $function) {
                $this->copyTwigFunction($twig, $function);
            }
        }
    }

    protected function copyStandardHelpers(): void
    {
        foreach ($this->templatingHelpers as $helper) {
            if (
                $this->container->has('templating.helper.'.$helper) &&
                ($instance = $this->container->get('templating.helper.'.$helper, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ) {
                $this->helpers[$helper] = $instance;
            }
        }
    }

    protected function getHttpFoundationExtension(): HttpFoundationExtension
    {
        /* @var RequestStack $stack */
        $stack = $this->container->get('request_stack');

        /* @var RequestContext $context */
        $context = $this->container->has('router.request_context')
            ? $this->container->get('router.request_context')
            : $this->container->get('router')->getContext();

        return new HttpFoundationExtension(new UrlHelper($stack, $context));
    }

    protected function copySpecialHelpers(): void
    {
        $this->helpers['css'] = new CssExtension($this->getTemplatingHelper('assets'));
        $this->helpers['http'] = $this->getHttpFoundationExtension();
    }

    protected function copyUserHelpers(array $helpers): void
    {
        foreach ($helpers as $helper) {
            $name = preg_replace('`^(?:.+\\\\)([^\\\\]+?)(?:Helper)?$`', '$1', get_class($helper));
            $name = strtolower(substr($name, 0, 1)).substr($name, 1);
            $this->helpers[$name] = $helper;
        }
    }

    protected function globalizeHelpers(): void
    {
        foreach ($this->twigHelpers as $name => $callable) {
            if (is_array($callable) && !is_callable($callable) && isset($this->helpers[$callable[0]])) {
                $subCallable = $callable;
                $subCallable[0] = $this->helpers[$subCallable[0]];

                $callable = function () use ($subCallable) {
                    return call_user_func_array($subCallable, func_get_args());
                };
            }

            static::$globalHelpers[$name] = $callable;
        }
    }

    protected function registerHelpers(array $helpers): void
    {
        $this->helpers = [];
        $this->copySpecialHelpers();
        $this->copyTwigFunctions();
        $this->copyStandardHelpers();
        $this->copyUserHelpers($helpers);
        $this->globalizeHelpers();
    }
}

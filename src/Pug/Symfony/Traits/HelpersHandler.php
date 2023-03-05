<?php

declare(strict_types=1);

namespace Pug\Symfony\Traits;

use Closure;
use Phug\Component\ComponentExtension;
use Psr\Container\ContainerInterface;
use Pug\Assets;
use Pug\Pug;
use Pug\Symfony\CssExtension;
use Pug\Symfony\MixedLoader;
use Pug\Twig\Environment;
use RuntimeException;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
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

    protected ContainerInterface $container;

    protected Environment $twig;

    protected ?Pug $pug = null;

    protected array $userOptions = [];

    protected array $twigHelpers;

    /**
     * @var callable
     */
    protected $nodeHandler;

    /**
     * Get the Pug engine.
     *
     * @return Pug
     */
    public function getRenderer(): Pug
    {
        if ($this->pug === null) {
            $this->pug = $this->createEngine($this->getRendererOptions());
            $this->copyTwigFunctions();
            $this->initializePugPlugins();
            $this->share($this->getTwig()->getGlobals());
            $this->setOption('paths', array_unique($this->getOptionDefault('paths', [])));
        }

        return $this->pug;
    }

    public function onNode(callable $nodeHandler): void
    {
        $this->nodeHandler = $nodeHandler;
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }

    protected function getRendererOptions(): array
    {
        if ($this->options === null) {
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
            $assetsDirectories[] = $srcDir.'/Resources/assets';
            $webDir = $projectDirectory.'/public';
            $baseDir = $this->userOptions['baseDir']
                ?? $this->crawlDirectories($srcDir, $assetsDirectories, $viewDirectories);
            $baseDir = $baseDir && file_exists($baseDir) ? realpath($baseDir) : $baseDir;
            $this->defaultTemplateDirectory = $baseDir;

            if (isset($this->userOptions['paths'])) {
                $viewDirectories = array_merge($viewDirectories, $this->userOptions['paths'] ?: []);
            }

            $debug = $this->kernel->isDebug();
            $options = array_merge([
                'debug'           => $debug,
                'assetDirectory'  => static::extractUniquePaths($assetsDirectories),
                'viewDirectories' => static::extractUniquePaths($viewDirectories),
                'baseDir'         => $baseDir,
                'cache'           => $debug ? false : $this->getCacheDir(),
                'environment'     => $environment,
                'extension'       => ['.pug', '.jade'],
                'outputDirectory' => $webDir,
                'prettyprint'     => $debug,
                'on_node'         => $this->nodeHandler,
            ], $this->userOptions);
            $cache = $options['cache'] ?? $options['cache_dir'] ?? null;

            if ($cache) {
                (new Filesystem())->mkdir($cache);
            }

            $options['paths'] = array_unique(array_filter(
                $options['viewDirectories'],
                static fn ($path) => $path !== $baseDir,
            ));

            $this->options = $options;
        }

        return $this->options;
    }

    protected function createEngine(array $options): Pug
    {
        $pug = new Pug($options);
        /** @var Closure|null $transformation */
        $transformation = $pug->hasOption('patterns')
            ? ($pug->getOption('patterns')['transform_expression'] ?? null)
            : null; // @codeCoverageIgnore
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

    protected function initializePugPlugins(): void
    {
        $pug = $this->getRenderer();

        if ($this->userOptions['assets'] ?? true) {
            $this->assets = new Assets($pug);
        }

        if ($this->userOptions['component'] ?? true) {
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

            if (is_array($token) && $token[0] === T_STRING && ($tokens[$index + 1] ?? null) === '(') {
                if ($token[1] === 'function_exists') {
                    if ($tokens[$index + 3] === ')' &&
                        is_array($tokens[$index + 2]) &&
                        $tokens[$index + 2][0] === T_CONSTANT_ENCAPSED_STRING &&
                        isset($this->twigHelpers[substr($tokens[$index + 2][1], 1, -1)])
                    ) {
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

                                    break;
                                }

                                break 2;

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

    protected function getTokenImage(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }

    protected function pushArgument(array &$arguments, string &$argument, bool &$argumentNeedInterpolation): void
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

    protected function copyTwigFunction(TwigFunction $function): void
    {
        $name = $function->getName();

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            // Methods like render_* not yet supported
            return;
        }

        $this->twigHelpers[$name] = $function;
    }

    protected function enhanceTwig($twig): void
    {
        $twig ??= $this->container->has('twig') ? $this->container->get('twig') : null;

        if (!($twig instanceof TwigEnvironment)) {
            throw new RuntimeException('Twig needs to be configured.');
        }

        $this->twig = Environment::fromTwigEnvironment($twig, $this, $this->container);
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

        /** @var AssetExtension $assetExtension */
        $assetExtension = $twig->extensions[AssetExtension::class] ?? null;

        if (!$assetExtension) {
            $assetExtension = new AssetExtension(new Packages(new Package(new EmptyVersionStrategy())));
            $twig->extensions[AssetExtension::class] = $assetExtension;

            if (!$twig->hasExtension(AssetExtension::class)) {
                $twig->addExtension($assetExtension);
            }
        }

        $helpers = [
            'css'  => new CssExtension($assetExtension),
            'http' => $this->getHttpFoundationExtension(),
        ];

        foreach ($helpers as $helper) {
            $class = get_class($helper);

            if (!isset($twig->extensions[$class])) {
                $twig->extensions[$class] = $helper;

                if (!$twig->hasExtension($class)) {
                    $twig->addExtension($helper);
                }
            }
        }

        foreach ($twig->extensions as $extension) {
            /* @var ExtensionInterface $extension */
            foreach ($extension->getFunctions() as $function) {
                $this->copyTwigFunction($function);
            }
        }
    }

    protected function getHttpFoundationExtension(): HttpFoundationExtension
    {
        /* @var RequestStack $stack */
        $stack = $this->stack ?? $this->container->get('request_stack');

        /* @var RequestContext $context */
        $context = $this->context ?? (
            $this->container->has('router.request_context')
                ? $this->container->get('router.request_context')
                : $this->container->get('router')->getContext()
        );

        return new HttpFoundationExtension(new UrlHelper($stack, $context));
    }
}

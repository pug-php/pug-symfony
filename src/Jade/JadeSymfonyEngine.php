<?php

namespace Jade;

use Composer\IO\IOInterface;
use Jade\Symfony\Css;
use Jade\Symfony\Logout;
use Jade\Symfony\MixedLoader;
use Pug\Assets;
use Pug\Pug;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Templating\EngineInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

class JadeSymfonyEngine implements EngineInterface, \ArrayAccess
{
    const GLOBAL_HELPER_PREFIX = '__pug_symfony_helper_';
    const CONFIG_OK = 1;
    const ENGINE_OK = 2;
    const KERNEL_OK = 4;

    /**
     * @var ContainerInterface|null
     */
    protected $container;

    /**
     * @var Pug
     */
    protected $jade;

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
    protected $replacements;

    /**
     * @var Assets
     */
    protected $assets;

    /**
     * @var Kernel|KernelInterface
     */
    protected $kernel;

    /**
     * @var string
     */
    protected $defaultTemplateDirectory;

    public function __construct($kernel)
    {
        if (empty($kernel) || !($kernel instanceof KernelInterface || $kernel instanceof Kernel)) {
            throw new \InvalidArgumentException("It seems you did not set the new settings in services.yml, please add \"@kernel\" to templating.engine.pug service arguments, see https://github.com/pug-php/pug-symfony#readme", 1);
        }

        $this->kernel = $kernel;
        $cache = $this->getCacheDir();
        if (!file_exists($cache)) {
            mkdir($cache, 0777, true);
        }
        $container = $kernel->getContainer();
        $this->container = $container;
        $environment = $kernel->getEnvironment();
        $appDir = $kernel->getRootDir();
        $rootDir = dirname($appDir);
        $assetsDirectories = [$appDir . '/Resources/assets'];
        $viewDirectories = [$appDir . '/Resources/views'];
        if ($container->has('twig') &&
            $container->initialized('twig') &&
            ($twig = $container->get('twig')) instanceof \Twig_Environment &&
            ($loader = $twig->getLoader()) instanceof FilesystemLoader &&
            is_array($paths = $loader->getPaths()) &&
            isset($paths[0])
        ) {
            $viewDirectories[] = $paths[0];
        }
        $this->defaultTemplateDirectory = end($viewDirectories);
        $srcDir = $rootDir . '/src';
        $webDir = $rootDir . '/web';
        $baseDir = $this->crawlDirectories($srcDir, $appDir, $assetsDirectories, $viewDirectories);
        $pugClassName = $this->getEngineClassName();
        $debug = substr($environment, 0, 3) === 'dev';
        $this->jade = new $pugClassName(array_merge([
            'debug'           => $debug,
            'assetDirectory'  => static::extractUniquePaths($assetsDirectories),
            'viewDirectories' => static::extractUniquePaths($viewDirectories),
            'baseDir'         => $baseDir,
            'cache'           => $debug ? false : $cache,
            'environment'     => $environment,
            'extension'       => ['.pug', '.jade'],
            'outputDirectory' => $webDir,
            'preRender'       => [$this, 'preRender'],
            'prettyprint'     => $kernel->isDebug(),
        ], ($container->hasParameter('pug') ? $container->getParameter('pug') : null) ?: []));
        $this->registerHelpers($container, array_slice(func_get_args(), 1));
        $this->assets = new Assets($this->jade);
        $app = new AppVariable();
        $app->setDebug($kernel->isDebug());
        $app->setEnvironment($environment);
        $app->setRequestStack($container->get('request_stack'));
        if ($container->has('security.token_storage')) {
            $app->setTokenStorage($container->get('security.token_storage'));
        }
        $this->share('app', $app);
    }

    protected function getEngineClassName()
    {
        $engineName = class_exists('\\Pug\\Pug') ? 'Pug' : 'Jade';
        include_once __DIR__ . '/Symfony/' . $engineName . 'Engine.php';

        return '\\Jade\\Symfony\\' . $engineName . 'Engine';
    }

    protected function crawlDirectories($srcDir, $appDir, &$assetsDirectories, &$viewDirectories)
    {
        $baseDir = null;
        if (file_exists($srcDir)) {
            foreach (scandir($srcDir) as $directory) {
                if ($directory === '.' || $directory === '..' || is_file($srcDir . '/' . $directory)) {
                    continue;
                }
                if (is_dir($viewDirectory = $srcDir . '/' . $directory . '/Resources/views')) {
                    if (is_null($baseDir)) {
                        $baseDir = $viewDirectory;
                    }
                    $viewDirectories[] = $srcDir . '/' . $directory . '/Resources/views';
                }
                $assetsDirectories[] = $srcDir . '/' . $directory . '/Resources/assets';
            }
        }

        return $baseDir ?: $this->defaultTemplateDirectory;
    }

    protected function getTemplatingHelper($name)
    {
        return isset($this->helpers[$name]) ? $this->helpers[$name] : null;
    }

    protected function copyTwigFunctions(ContainerInterface $services)
    {
        $this->twigHelpers = [];
        if ($services->has('twig') &&
            $services->initialized('twig') &&
            ($twig = $services->get('twig')) instanceof \Twig_Environment
        ) {
            /* @var \Twig_Environment $twig */
            $twig = clone $twig;
            $loader = new MixedLoader($twig->getLoader());
            $twig->setLoader($loader);
            $formLayout = $twig->load('form_div_layout.html.twig')->getSourceContext()->getCode();
            $this->share('twig', $twig);
            foreach ($twig->getExtensions() as $extension) {
                /* @var \Twig_Extension $extension */
                foreach ($extension->getFunctions() as $function) {
                    /* @var \Twig_Function $function */
                    $name = $function->getName();
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                        // Methods like render_* not yet supported
                        continue;
                    }
                    $callable = $function->getCallable();
                    if ($callable && (is_callable($callable)) || $callable instanceof \Closure) {
                        if (!is_string($callable)) {
                            if (is_array($callable)) {
                                $this->twigHelpers[$name] = $callable;
                            }
                        }
                    }
                    if (!$callable && ($nodeClass = $function->getNodeClass())) {
                        $twig->env = $twig;
                        $callable = function () use ($twig, $name, $nodeClass, $loader) {
                            $variables = [];
                            foreach (func_get_args() as $index => $argument) {
                                $variables['arg' . $index] = $argument;
                            }

                            $template = $loader->uniqueTemplate('{{' . $name . '(' . implode(', ', array_keys($variables)) . ') }}');

                            try {
                                return $twig->render($template, $variables);
                            } catch (\Throwable $e) {
                                return $e->getMessage()."\n";
                            }
                        };
                        $this->twigHelpers[$name] = $callable->bindTo($twig);
                    }
                }
            }
        }
    }

    protected function copyStandardHelpers(ContainerInterface $services)
    {
        foreach ([
            'actions',
            'assets',
            'code',
            'form',
            'logout_url',
            'request',
            'router',
            'security',
            'session',
            'slots',
            'stopwatch',
            'translator',
        ] as $helper) {
            if (
                $services->has('templating.helper.' . $helper) &&
                ($instance = $services->get('templating.helper.' . $helper, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ) {
                $this->helpers[$helper] = $instance;
            }
        }
    }

    protected function copySpecialHelpers(ContainerInterface $services)
    {
        if ($helper = $this->getTemplatingHelper('logout_url')) {
            $this->helpers['logout'] = new Logout($helper);
        }
        $this->helpers['css'] = new Css($this->getTemplatingHelper('assets'));
        /* @var \Symfony\Component\HttpFoundation\RequestStack $stack */
        $stack = $services->get('request_stack');
        /* @var \Symfony\Component\Routing\RequestContext $context */
        $context = $services->has('router.request_context')
            ? $services->get('router.request_context')
            : $services->get('router')->getContext();
        $this->helpers['http'] = new HttpFoundationExtension($stack, $context);
    }

    protected function copyUserHelpers(array $helpers)
    {
        foreach ($helpers as $helper) {
            $name = preg_replace('`^(?:.+\\\\)([^\\\\]+?)(?:Helper)?$`', '$1', get_class($helper));
            $name = strtolower(substr($name, 0, 1)) . substr($name, 1);
            $this->helpers[$name] = $helper;
        }
    }

    protected function storeReplacements()
    {
        $this->replacements = array_merge([
            'random'        => 'mt_rand',
            'asset'         => ['assets', 'getUrl'],
            'asset_version' => ['assets', 'getVersion'],
            'css_url'       => ['css', 'getUrl'],
            'csrf_token'    => ['form', 'csrfToken'],
            'url'           => ['router', 'url'],
            'path'          => ['router', 'path'],
            'logout_url'    => ['logout', 'url'],
            'logout_path'   => ['logout', 'path'],
            'absolute_url'  => ['http', 'generateAbsoluteUrl'],
            'relative_path' => ['http', 'generateRelativePath'],
            'is_granted'    => ['security', 'isGranted'],
        ], $this->twigHelpers);
    }

    protected function globalizeHelpers()
    {
        foreach ($this->replacements as $name => $callable) {
            if (is_array($callable) && !is_callable($callable)) {
                $subCallable = $callable;
                if (!isset($this->helpers[$subCallable[0]])) {
                    continue;
                }
                $subCallable[0] = $this->helpers[$subCallable[0]];

                $callable = function () use ($subCallable) {
                    return call_user_func_array($subCallable, func_get_args());
                };
            }

            $GLOBALS[static::GLOBAL_HELPER_PREFIX . $name] = $callable;
        }
    }

    protected function registerHelpers(ContainerInterface $services, $helpers)
    {
        $this->helpers = [];
        $this->copyTwigFunctions($services);
        $this->copyStandardHelpers($services);
        $this->copySpecialHelpers($services);
        $this->copyUserHelpers($helpers);
        $this->storeReplacements();
        $this->globalizeHelpers();
    }

    protected function getFileFromName($name)
    {
        $parts = explode(':', strval($name));
        $directory = $this->defaultTemplateDirectory;
        if (count($parts) > 1) {
            $name = $parts[2];
            if (!empty($parts[1])) {
                $name = $parts[1] . DIRECTORY_SEPARATOR . $name;
            }
            if ($bundle = $this->kernel->getBundle($parts[0])) {
                $directory = $bundle->getPath() .
                    DIRECTORY_SEPARATOR . 'Resources' .
                    DIRECTORY_SEPARATOR . 'views';
            }
        }

        return $directory . DIRECTORY_SEPARATOR . $name;
    }

    public function share($variables, $value = null)
    {
        $this->jade->share($variables, $value);
    }

    /**
     * Pug code transformation to do before Pug render.
     *
     * @param string $pugCode code input
     *
     * @return string
     */
    public function preRender($pugCode)
    {
        $preCode = '';
        foreach ($this->replacements as $name => $callable) {
            $preCode .= ":php\n" .
                "    if (!function_exists('$name')) {\n" .
                "        function $name() {\n" .
                "            return call_user_func_array(\$GLOBALS['" . static::GLOBAL_HELPER_PREFIX . "$name'], func_get_args());\n" .
                "        }\n" .
                "    }\n";
        }

        return $preCode . $pugCode;
    }

    /**
     * Get a Pug engine option or the default value passed as second parameter (null if omitted).
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getOptionDefault($name, $default = null)
    {
        try {
            return $this->getOption($name, $default);
        } catch (\InvalidArgumentException $exception) {
            return $default;
        }
    }

    /**
     * Get a Pug engine option or the default value passed as second parameter (null if omitted).
     *
     * @deprecated This method has inconsistent behavior depending on which major version of the Pug-php engine you
     *             use, so prefer using getOptionDefault instead that has consistent output no matter the Pug-php
     *             version.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @throws \InvalidArgumentException when using Pug-php 2 engine and getting an option not set
     *
     * @return mixed
     */
    public function getOption($name, $default = null)
    {
        return method_exists($this->jade, 'hasOption') && !$this->jade->hasOption($name)
            ? $default
            : $this->jade->getOption($name);
    }

    /**
     * Set a Pug engine option.
     *
     * @param string|array $name
     * @param mixed        $value
     *
     * @return Pug
     */
    public function setOption($name, $value)
    {
        return $this->jade->setOption($name, $value);
    }

    /**
     * Set multiple options of the Pug engine.
     *
     * @param array $options
     *
     * @return Pug
     */
    public function setOptions(array $options)
    {
        return $this->jade->setOptions($options);
    }

    /**
     * Set custom options of the Pug engine.
     *
     * @deprecated Method only used with Pug-php 2, if you're using Pug-php 2, please consider using the
     *             last major release.
     *
     * @param array $options
     *
     * @return Pug
     */
    public function setCustomOptions(array $options)
    {
        return $this->jade->setCustomOptions($options);
    }

    /**
     * Get the Pug engine.
     *
     * @return Pug
     */
    public function getEngine()
    {
        return $this->jade;
    }

    /**
     * Get the Pug cache directory path.
     *
     * @return string
     */
    public function getCacheDir()
    {
        return $this->kernel->getCacheDir() . DIRECTORY_SEPARATOR . 'pug';
    }

    /**
     * Set a Pug filter.
     *
     * @param string   $name
     * @param callable $filter
     *
     * @return Pug
     */
    public function filter($name, $filter)
    {
        return $this->jade->filter($name, $filter);
    }

    /**
     * Check if the Pug engine has a given filter by name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasFilter($name)
    {
        return $this->jade->hasFilter($name);
    }

    /**
     * Get a filter by name from the Pug engine.
     *
     * @param string $name
     *
     * @return callable
     */
    public function getFilter($name)
    {
        return $this->jade->getFilter($name);
    }

    /**
     * Prepare and group input and global parameters.
     *
     * @param array $parameters
     *
     * @throws \ErrorException when a forbidden parameter key is used
     *
     * @return array input parameters with global parameters
     */
    public function getParameters(array $parameters = [])
    {
        foreach (['view', 'this'] as $forbiddenKey) {
            if (array_key_exists($forbiddenKey, $parameters)) {
                throw new \ErrorException('The "' . $forbiddenKey . '" key is forbidden.');
            }
        }
        $sharedVariables = $this->getOptionDefault('shared_variables');
        if ($sharedVariables) {
            $parameters = array_merge($sharedVariables, $parameters);
        }
        $parameters['view'] = $this;

        return $parameters;
    }

    /**
     * Render a template by name.
     *
     * @param string|\Symfony\Component\Templating\TemplateReferenceInterface $name
     * @param array                                                           $parameters
     *
     * @throws \ErrorException when a forbidden parameter key is used
     *
     * @return string
     */
    public function render($name, array $parameters = [])
    {
        $parameters = $this->getParameters($parameters);
        $method = method_exists($this->jade, 'renderFile')
            ? [$this->jade, 'renderFile']
            : [$this->jade, 'render'];

        return call_user_func($method, $this->getFileFromName($name), $parameters);
    }

    /**
     * Render a template string.
     *
     * @param string|\Symfony\Component\Templating\TemplateReferenceInterface $name
     * @param array                                                           $parameters
     *
     * @throws \ErrorException when a forbidden parameter key is used
     *
     * @return string
     */
    public function renderString($code, array $parameters = [])
    {
        $parameters = $this->getParameters($parameters);
        $method = method_exists($this->jade, 'renderString')
            ? [$this->jade, 'renderString']
            : [$this->jade, 'render'];

        return call_user_func($method, $code, $parameters);
    }

    /**
     * Check if a template exists.
     *
     * @param string|\Symfony\Component\Templating\TemplateReferenceInterface $name
     *
     * @return bool
     */
    public function exists($name)
    {
        return file_exists($this->getFileFromName($name));
    }

    /**
     * Check if a file extension is supported by Pug.
     *
     * @param string|\Symfony\Component\Templating\TemplateReferenceInterface $name
     *
     * @return bool
     */
    public function supports($name)
    {
        $extensions = method_exists($this->jade, 'getExtensions')
            ? $this->jade->getExtensions()
            : $this->jade->getOption('extensions');
        foreach ($extensions as $extension) {
            if (substr($name, -strlen($extension)) === $extension) {
                return true;
            }
        }

        return false;
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

    protected static function extractUniquePaths($paths)
    {
        $result = [];
        foreach ($paths as $path) {
            $realPath = realpath($path) ?: $path;

            if (!in_array($realPath, $result)) {
                $result[] = $path;
            }
        }

        return $result;
    }

    protected static function askConfirmation(IOInterface $io, $message)
    {
        return !$io->isInteractive() || $io->askConfirmation($message);
    }

    protected static function installInSymfony4($event, $dir)
    {
        /** @var \Composer\Script\Event $event */
        $io = $event->getIO();
        $baseDirectory = __DIR__ . '/../..';

        $flags = 0;

        $templateService = "\n    templating:\n" .
            "        engines: ['twig', 'pug']\n";
        $pugService = "\n    templating.engine.pug:\n" .
            "        public: true\n" .
            "        autowire: false\n" .
            "        class: Pug\PugSymfonyEngine\n" .
            "        arguments:\n" .
            "            - '@kernel'\n";
        $bundleClass = 'Pug\PugSymfonyBundle\PugSymfonyBundle';
        $bundle = "$bundleClass::class => ['all' => true],";
        $addServicesConfig = static::askConfirmation($io, 'Would you like us to add automatically needed settings in your config/services.yaml? [Y/N] ');
        $addConfig = static::askConfirmation($io, 'Would you like us to add automatically needed settings in your config/packages/framework.yaml? [Y/N] ');
        $addBundle = static::askConfirmation($io, 'Would you like us to add automatically the pug bundle in your config/bundles.php? [Y/N] ');

        $proceedTask = function ($taskResult, $flag, $successMessage, $errorMessage) use (&$flags, $io) {
            static::proceedTask($flags, $io, $taskResult, $flag, $successMessage, $errorMessage);
        };

        if ($addConfig) {
            $configFile = $dir . '/config/packages/framework.yaml';
            $contents = @file_get_contents($configFile) ?: '';

            if (!preg_match('/[^a-zA-Z]pug[^a-zA-Z]/', $contents)) {
                $newContents = null;
                if (preg_match('/^\s*-\s*([\'"]?)twig[\'"]?/m', $contents)) {
                    $newContents = preg_replace('/^(\s*-\s*)([\'"]?)twig[\'"]?(\n)/m', '$0$1$2pug$2$3', $contents);
                } elseif (preg_match('/[[,]\s*([\'"]?)twig[\'"]?/', $contents)) {
                    $newContents = preg_replace('/[[,]\s*([\'"]?)twig[\'"]?/', '$0, $2pug$2', $contents);
                } elseif (preg_match('/^framework\s*:\s*\n/m', $contents)) {
                    $newContents = preg_replace_callback('/^framework\s*:\s*\n/', function ($match) use ($templateService) {
                        return $match[0] . $templateService;
                    }, $contents);
                }
                if ($newContents) {
                    $proceedTask(
                        file_put_contents($configFile, $newContents),
                        static::ENGINE_OK,
                        'Engine service added in config/packages/framework.yaml',
                        'Unable to add the engine service in config/packages/framework.yaml'
                    );
                } else {
                    $io->write('framework entry not found in config/packages/framework.yaml.');
                }
            } else {
                $flags |= static::ENGINE_OK;
                $io->write('templating.engine.pug setting in config/packages/framework.yaml already exists.');
            }
        } else {
            $flags |= static::ENGINE_OK;
        }

        if ($addServicesConfig) {
            $configFile = $dir . '/config/services.yaml';
            $contents = @file_get_contents($configFile) ?: '';

            if (strpos($contents, 'templating.engine.pug') === false) {
                if (preg_match('/^services\s*:\s*\n/m', $contents)) {
                    $contents = preg_replace_callback('/^services\s*:\s*\n/m', function ($match) use ($pugService) {
                        return $match[0] . $pugService;
                    }, $contents);
                    $proceedTask(
                        file_put_contents($configFile, $contents),
                        static::CONFIG_OK,
                        'Engine service added in config/services.yaml',
                        'Unable to add the engine service in config/services.yaml'
                    );
                } else {
                    $io->write('services entry not found in config/services.yaml.');
                }
            } else {
                $flags |= static::CONFIG_OK;
                $io->write('templating.engine.pug setting in config/services.yaml already exists.');
            }
        } else {
            $flags |= static::CONFIG_OK;
        }

        if ($addBundle) {
            $appFile = $dir . '/config/bundles.php';
            $contents = @file_get_contents($appFile) ?: '';

            if (preg_match('/\[\s*\n/', $contents)) {
                if (strpos($contents, $bundleClass) === false) {
                    $contents = preg_replace_callback('/\[\s*\n/', function ($match) use ($bundle) {
                        return $match[0] . "$bundle\n";
                    }, $contents);
                    $proceedTask(
                        file_put_contents($appFile, $contents),
                        static::KERNEL_OK,
                        'Bundle added to config/bundles.php',
                        'Unable to add the bundle engine in config/bundles.php'
                    );
                } else {
                    $flags |= static::KERNEL_OK;
                    $io->write('The bundle already exists in config/bundles.php');
                }
            } else {
                $io->write('Sorry, config/bundles.php has a format we can\'t handle automatically.');
            }
        } else {
            $flags |= static::KERNEL_OK;
        }

        if (($flags & static::KERNEL_OK) && ($flags & static::CONFIG_OK) && ($flags & static::ENGINE_OK)) {
            touch($baseDirectory . '/installed');
        }

        return true;
    }

    public static function proceedTask(&$flags, $io, $taskResult, $flag, $successMessage, $message)
    {
        if ($taskResult) {
            $flags |= $flag;
            $message = $successMessage;
        }

        $io->write($message);
    }

    public static function install($event, $dir = null)
    {
        /** @var \Composer\Script\Event $event */
        $io = $event->getIO();
        $baseDirectory = __DIR__ . '/../..';

        if (!$io->isInteractive() || file_exists($baseDirectory . '/installed')) {
            return true;
        }

        $dir = is_string($dir) && is_dir($dir)
            ? $dir
            : $baseDirectory . '/../../..';

        if (!file_exists($dir . '/composer.json')) {
            $io->write('Not inside a composer vendor directory, setup skipped.');

            return true;
        }

        if (file_exists($dir . '/config/packages/framework.yaml')) {
            return static::installInSymfony4($event, $dir);
        }

        $service = "\n    templating.engine.pug:\n" .
            "        public: true\n" .
            "        class: Pug\PugSymfonyEngine\n" .
            "        arguments: [\"@kernel\"]\n";

        $bundle = 'new Pug\PugSymfonyBundle\PugSymfonyBundle()';

        $flags = 0;
        $addConfig = static::askConfirmation($io, 'Would you like us to add automatically needed settings in your config.yml? [Y/N] ');
        $addBundle = static::askConfirmation($io, 'Would you like us to add automatically the pug bundle in your AppKernel.php? [Y/N] ');

        $proceedTask = function ($taskResult, $flag, $successMessage, $errorMessage) use (&$flags, $io) {
            static::proceedTask($flags, $io, $taskResult, $flag, $successMessage, $errorMessage);
        };

        if ($addConfig) {
            $configFile = $dir . '/app/config/config.yml';
            $contents = @file_get_contents($configFile) ?: '';

            if (preg_match('/^framework\s*:/m', $contents)) {
                if (strpos($contents, 'templating.engine.pug') === false) {
                    if (!preg_match('/^services\s*:/m', $contents)) {
                        $contents = preg_replace('/^framework\s*:/m', "services:\n\$0", $contents);
                    }
                    $contents = preg_replace('/^services\s*:/m', "\$0$service", $contents);
                    $proceedTask(
                        file_put_contents($configFile, $contents),
                        static::CONFIG_OK,
                        'Engine service added in config.yml',
                        'Unable to add the engine service in config.yml'
                    );
                } else {
                    $flags |= static::CONFIG_OK;
                    $io->write('templating.engine.pug setting in config.yml already exists.');
                }
                $lines = explode("\n", $contents);
                $proceeded = false;
                $inFramework = false;
                $inTemplating = false;
                $templatingIndent = 0;
                foreach ($lines as &$line) {
                    $trimmedLine = ltrim($line);
                    $indent = mb_strlen($line) - mb_strlen($trimmedLine);
                    if (preg_match('/^framework\s*:/', $line)) {
                        $inFramework = true;
                        continue;
                    }
                    if ($inFramework && preg_match('/^templating\s*:/', $trimmedLine)) {
                        $templatingIndent = $indent;
                        $inTemplating = true;
                        continue;
                    }
                    if ($indent < $templatingIndent) {
                        $inTemplating = false;
                    }
                    if ($indent === 0) {
                        $inFramework = false;
                    }
                    if ($inTemplating && preg_match('/^engines\s*:(.*)$/', $trimmedLine, $match)) {
                        $engines = @json_decode(str_replace("'", '"', trim($match[1])));
                        if (!is_array($engines)) {
                            $io->write('Automatic engine adding is only possible if framework.templating.engines is a ' .
                                'one-line setting in config.yml.');

                            break;
                        }
                        if (in_array('pug', $engines)) {
                            $flags |= static::ENGINE_OK;
                            $io->write('Pug engine already exist in framework.templating.engines in config.yml.');

                            break;
                        }
                        array_unshift($engines, 'pug');
                        $line = preg_replace('/^(\s+engines\s*:)(.*)$/', '$1 ' . json_encode($engines), $line);
                        $proceeded = true;
                        break;
                    }
                }
                if ($proceeded) {
                    $contents = implode("\n", $lines);
                    $proceedTask(
                        file_put_contents($configFile, $contents),
                        static::ENGINE_OK,
                        'Engine added to framework.templating.engines in config.yml',
                        'Unable to add the templating engine in framework.templating.engines in config.yml'
                    );
                }
            } else {
                $io->write('framework entry not found in config.yml.');
            }
        } else {
            $flags |= static::CONFIG_OK | static::ENGINE_OK;
        }

        if ($addBundle) {
            $appFile = $dir . '/app/AppKernel.php';
            $contents = @file_get_contents($appFile) ?: '';

            if (preg_match('/^[ \\t]*new\\s+Symfony\\\\Bundle\\\\FrameworkBundle\\\\FrameworkBundle\\(\\)/m', $contents)) {
                if (strpos($contents, $bundle) === false) {
                    $contents = preg_replace('/^([ \\t]*)new\\s+Symfony\\\\Bundle\\\\FrameworkBundle\\\\FrameworkBundle\\(\\)/m', "\$0,\n\$1$bundle", $contents);
                    $proceedTask(
                        file_put_contents($appFile, $contents),
                        static::KERNEL_OK,
                        'Bundle added to AppKernel.php',
                        'Unable to add the bundle engine in AppKernel.php'
                    );
                } else {
                    $flags |= static::KERNEL_OK;
                    $io->write('The bundle already exists in AppKernel.php');
                }
            } else {
                $io->write('Sorry, AppKernel.php has a format we can\'t handle automatically.');
            }
        } else {
            $flags |= static::KERNEL_OK;
        }

        if (($flags & static::KERNEL_OK) && ($flags & static::CONFIG_OK) && ($flags & static::ENGINE_OK)) {
            touch($baseDirectory . '/installed');
        }

        return true;
    }
}

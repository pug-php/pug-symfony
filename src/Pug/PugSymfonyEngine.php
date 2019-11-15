<?php

namespace Pug;

use Pug\Symfony\Contracts\HelpersHandlerInterface;
use Pug\Symfony\Contracts\InstallerInterface;
use Pug\Symfony\Traits\Filters;
use Pug\Symfony\Traits\HelpersHandler;
use Pug\Symfony\Traits\Installer;
use Pug\Symfony\Traits\Options;
use Pug\Assets;
use Pug\Pug;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Templating\EngineInterface;
use Twig\Loader\FilesystemLoader;

class PugSymfonyEngine implements EngineInterface, InstallerInterface, HelpersHandlerInterface
{
    use Installer, HelpersHandler, Filters, Options;

    /**
     * @var ContainerInterface|null
     */
    protected $container;

    /**
     * @var Pug
     */
    protected $jade;

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

    /**
     * @var Filesystem
     */
    protected $fs;

    public function __construct($kernel)
    {
        if (empty($kernel) || !($kernel instanceof KernelInterface || $kernel instanceof Kernel)) {
            throw new \InvalidArgumentException("It seems you did not set the new settings in services.yml, please add \"@kernel\" to templating.engine.pug service arguments, see https://github.com/pug-php/pug-symfony#readme", 1);
        }

        $this->kernel = $kernel;
        $cache = $this->getCacheDir();
        $this->fs = new Filesystem();
        $this->fs->mkdir($cache);
        $container = $kernel->getContainer();
        $this->container = $container;
        $environment = $kernel->getEnvironment();
        $appDir = $this->getAppDirectory($kernel);
        $rootDir = dirname($appDir);
        $assetsDirectories = [$appDir . '/Resources/assets'];
        $viewDirectories = [$appDir . '/Resources/views'];
        if ($container->has('twig') &&
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
        $baseDir = $this->crawlDirectories($srcDir, $assetsDirectories, $viewDirectories);
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

        foreach ($container->get('twig')->getGlobals() as $globalKey => $globalValue) {
            if ($globalValue instanceof AppVariable) {
                $globalValue->setDebug($kernel->isDebug());
                $globalValue->setEnvironment($environment);
                $globalValue->setRequestStack($container->get('request_stack'));
                // @codeCoverageIgnoreStart
                if ($container->has('security.token_storage')) {
                    $globalValue->setTokenStorage($container->get('security.token_storage'));
                }
                // @codeCoverageIgnoreEnd
            }

            $this->share($globalKey, $globalValue);
        }
    }

    protected function getAppDirectory($kernel)
    {
        /* @var KernelInterface $kernel */
        if (method_exists($kernel, 'getProjectDir') &&
            ($directory = $kernel->getProjectDir()) &&
            file_exists($directory = "$directory/app")
        ) {
            return realpath($directory);
        }

        /* @var Kernel $kernel */
        return $kernel->getRootDir(false); // @codeCoverageIgnore
    }

    protected function getEngineClassName()
    {
        $engineName = class_exists('\\Pug\\Pug') ? 'Pug' : 'Jade';
        include_once __DIR__ . '/Symfony/' . $engineName . 'Engine.php';

        return '\\Jade\\Symfony\\' . $engineName . 'Engine';
    }

    protected function crawlDirectories($srcDir, &$assetsDirectories, &$viewDirectories)
    {
        $baseDir = null;
        if ($this->fs->exists($srcDir)) {
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

    protected function getPugCodeLayoutStructure($pugCode)
    {
        $parts = preg_split('/^extend(?=s?\s)/m', $pugCode, 2);

        if (count($parts) === 1) {
            return ['', $parts[0]];
        }

        $parts[1] .= "\n";
        $parts[1] = explode("\n", $parts[1], 2);
        $parts[0] .= 'extend' . $parts[1][0] . "\n";
        $parts[1] = substr($parts[1][1], 0, -1);

        return $parts;
    }

    /**
     * Share variables (local templates parameters) with all future templates rendered.
     *
     * @example $pug->share('lang', 'fr')
     * @example $pug->share(['title' => 'My blog', 'today' => new DateTime()])
     *
     * @param array|string $variables a variables name-value pairs or a single variable name
     * @param mixed        $value     the variable value if the first argument given is a string
     *
     * @return $this
     */
    public function share($variables, $value = null)
    {
        $this->jade->share($variables, $value);

        return $this;
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
        $parts = $this->getPugCodeLayoutStructure($pugCode);
        $className = get_class($this);
        foreach ($this->replacements as $name => $callable) {
            $parts[0] .= ":php\n" .
                "    if (!function_exists('$name')) {\n" .
                "        function $name() {\n" .
                "            return call_user_func_array($className::getGlobalHelper('$name'), func_get_args());\n" .
                "        }\n" .
                "    }\n";
        }

        return implode('', $parts);
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
        return $this->fs->exists($this->getFileFromName($name));
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
        // @codeCoverageIgnoreStart
        $extensions = method_exists($this->jade, 'getExtensions')
            ? $this->jade->getExtensions()
            : $this->jade->getOption('extensions');
        // @codeCoverageIgnoreEnd
        foreach ($extensions as $extension) {
            if (substr($name, -strlen($extension)) === $extension) {
                return true;
            }
        }

        return false;
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
}

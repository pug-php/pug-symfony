<?php

namespace Jade;

use Jade\Symfony\Css;
use Jade\Symfony\Logout;
use Pug\Assets;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Templating\EngineInterface;

class JadeSymfonyEngine implements EngineInterface, \ArrayAccess
{
    protected $container;
    protected $jade;
    protected $helpers;
    protected $assets;
    protected $kernel;

    const CONFIG_OK = 1;
    const ENGINE_OK = 2;
    const KERNEL_OK = 4;

    public function __construct($kernel)
    {
        if (empty($kernel) || !($kernel instanceof Kernel)) {
            throw new \InvalidArgumentException("It seems you did not set the new settings in services.yml, please add \"@kernel\" to templating.engine.pug service arguments, see https://github.com/pug-php/pug-symfony#readme", 1);
        }

        $this->kernel = $kernel;
        $cache = $this->getCacheDir();
        if (!file_exists($cache)) {
            mkdir($cache);
        }
        $container = $kernel->getContainer();
        $this->container = $container;
        $environment = $kernel->getEnvironment();
        $appDir = $kernel->getRootDir();
        $rootDir = dirname($appDir);
        $assetsDirectories = [$appDir . '/Resources/assets'];
        $viewDirectories = [$appDir . '/Resources/views'];
        $srcDir = $rootDir . '/src';
        $webDir = $rootDir . '/web';
        $baseDir = $this->crawlDirectories($srcDir, $appDir, $assetsDirectories, $viewDirectories);
        $pugClassName = $this->getEngineClassName();
        $debug = substr($environment, 0, 3) === 'dev';
        $pug3 = is_a($pugClassName, '\\Phug\\Renderer', true);
        $this->jade = new $pugClassName([
            'debug'           => $debug,
            'assetDirectory'  => $assetsDirectories,
            'viewDirectories' => $viewDirectories,
            'baseDir'         => $baseDir,
            'cache'           => $debug ? false : $cache,
            'environment'     => $environment,
            'extension'       => ['.pug', '.jade'],
            'outputDirectory' => $webDir,
            'preRender'       => $pug3 ? null : [$this, 'preRender'],
            'prettyprint'     => $kernel->isDebug(),
        ]);
        if ($pug3) {
            $format = $this->jade->getCompiler()->getFormatter()->getFormatInstance();
            $transform = $format->getOption('patterns.transform_expression');
            $format->setPattern('transform_expression', function ($code) use ($format, $transform) {
                return call_user_func($format->getOption('pattern'), $transform, $this->replaceCode($code));
            });
        }
        $this->registerHelpers($container, array_slice(func_get_args(), 1));
        $this->assets = new Assets($this->jade);
        $app = new AppVariable();
        $app->setDebug($kernel->isDebug());
        $app->setEnvironment($environment);
        $app->setRequestStack($container->get('request_stack'));
        if ($container->has('security.token_storage')) {
            $app->setTokenStorage($container->get('security.token_storage'));
        }
        $this->jade->share('app', $app);
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
        foreach (scandir($srcDir) as $directory) {
            if ($directory === '.' || $directory === '..' || is_file($srcDir . '/' . $directory)) {
                continue;
            }
            $viewDirectory = $srcDir . '/' . $directory . '/Resources/views';
            if (is_dir($viewDirectory)) {
                if (is_null($baseDir)) {
                    $baseDir = $viewDirectory;
                }
                $viewDirectories[] = $srcDir . '/' . $directory . '/Resources/views';
            }
            $assetsDirectories[] = $srcDir . '/' . $directory . '/Resources/assets';
        }

        return $baseDir ?: $appDir . '/Resources/views';
    }

    protected function replaceFunction($name, $function, $code)
    {
        return mb_substr(preg_replace(
            '/(?<=\=\>|[=\.\+,:\?\(])\s*' . preg_quote($name, '/') . '\s*\(/',
            $function . '(',
            '=' . $code
        ), 1);
    }

    protected function replaceCode($pugCode)
    {
        $helperPattern = $this->getOption('expressionLanguage') === 'js'
            ? 'view.%s.%s'
            : '$view[\'%s\']->%s';
        foreach ([
            'random' => 'mt_rand',
            'asset' => ['assets', 'getUrl'],
            'asset_version' => ['assets', 'getVersion'],
            'css_url' => ['css', 'getUrl'],
            'csrf_token' => ['form', 'csrfToken'],
            'logout_url' => ['logout', 'url'],
            'logout_path' => ['logout', 'path'],
            'url' => ['router', 'url'],
            'path' => ['router', 'path'],
            'absolute_url' => ['http', 'generateAbsoluteUrl'],
            'relative_path' => ['http', 'generateRelativePath'],
            'is_granted' => ['security', 'isGranted'],
        ] as $name => $function) {
            if (is_array($function)) {
                $function = sprintf($helperPattern, $function[0], $function[1]);
            }
            $output = '';
            $input = $pugCode;
            while (preg_match('/^([^\'"]*)("(?:\\\\[\\S\\s]|[^"\\\\])*"|\'(?:\\\\[\\S\\s]|[^\'\\\\])*\')/', $input, $match)) {
                $input = mb_substr($input, mb_strlen($match[0]));
                $output .= $this->replaceFunction($name, $function, $match[1]) . $match[2];
            }
            $pugCode = $output . $this->replaceFunction($name, $function, $input);
        }

        return $pugCode;
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
        $newCode = '';
        while (mb_strlen($pugCode)) {
            if (!preg_match('/^(.*)("(?:\\\\[\\s\\S]|[^"\\\\])*"|\'(?:\\\\[\\s\\S]|[^\'\\\\])*\')/U', $pugCode, $match)) {
                $newCode .= $this->replaceCode($pugCode);

                break;
            }

            $newCode .= $this->replaceCode($match[1]) . $match[2];
            $pugCode = mb_substr($pugCode, mb_strlen($match[0]));
        }

        return $newCode;
    }

    protected function getTemplatingHelper($name)
    {
        return isset($this->helpers[$name]) ? $this->helpers[$name] : null;
    }

    protected function registerHelpers(ContainerInterface $services, $helpers)
    {
        $this->helpers = [];
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
                ($instance = $services->get('templating.helper.' . $helper))
            ) {
                $this->helpers[$helper] = $instance;
            }
        }
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
        foreach ($helpers as $helper) {
            $name = preg_replace('`^(?:.+\\\\)([^\\\\]+?)(?:Helper)?$`', '$1', get_class($helper));
            $name = strtolower(substr($name, 0, 1)) . substr($name, 1);
            $this->helpers[$name] = $helper;
        }
    }

    public function getOption($name)
    {
        return method_exists($this->jade, 'hasOption') && !$this->jade->hasOption($name)
            ? null
            : $this->jade->getOption($name);
    }

    public function setOption($name, $value)
    {
        return $this->jade->setOption($name, $value);
    }

    public function setOptions(array $options)
    {
        return $this->jade->setOptions($options);
    }

    public function setCustomOptions(array $options)
    {
        return $this->jade->setCustomOptions($options);
    }

    public function getEngine()
    {
        return $this->jade;
    }

    public function getCacheDir()
    {
        return $this->kernel->getCacheDir() . DIRECTORY_SEPARATOR . 'pug';
    }

    public function filter($name, $filter)
    {
        return $this->jade->filter($name, $filter);
    }

    public function hasFilter($name)
    {
        return $this->jade->hasFilter($name);
    }

    public function getFilter($name)
    {
        return $this->jade->getFilter($name);
    }

    protected function getFileFromName($name)
    {
        $parts = explode(':', $name);
        $directory = $this->kernel->getRootDir();
        if (count($parts) > 1) {
            $name = $parts[2];
            if (!empty($parts[1])) {
                $name = $parts[1] . DIRECTORY_SEPARATOR . $name;
            }
            if ($bundle = $this->kernel->getBundle($parts[0])) {
                $directory = $bundle->getPath();
            }
        }

        return $directory .
            DIRECTORY_SEPARATOR . 'Resources' .
            DIRECTORY_SEPARATOR . 'views' .
            DIRECTORY_SEPARATOR . $name;
    }

    public function render($name, array $parameters = [])
    {
        foreach (['view', 'this'] as $forbiddenKey) {
            if (array_key_exists($forbiddenKey, $parameters)) {
                throw new \ErrorException('The "' . $forbiddenKey . '" key is forbidden.');
            }
        }
        $parameters['view'] = $this;

        return $this->jade->render($this->getFileFromName($name), $parameters);
    }

    public function exists($name)
    {
        return file_exists($this->getFileFromName($name));
    }

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

    public function offsetGet($name)
    {
        return $this->helpers[$name];
    }

    public function offsetExists($name)
    {
        return isset($this->helpers[$name]);
    }

    public function offsetSet($name, $value)
    {
        $this->helpers[$name] = $value;
    }

    public function offsetUnset($name)
    {
        unset($this->helpers[$name]);
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

        $service = "\n    templating.engine.pug:\n" .
            "        public: true\n" .
            "        class: Pug\PugSymfonyEngine\n" .
            "        arguments: [\"@kernel\"]\n";

        $bundle = 'new Pug\PugSymfonyBundle\PugSymfonyBundle()';

        $flags = 0;
        $addConfig = $io->askConfirmation('Would you like us to add automatically needed settings in your config.yml? [Y/N] ');
        $addBundle = $io->askConfirmation('Would you like us to add automatically the pug bundle in your AppKernel.php? [Y/N] ');

        if ($addConfig) {
            $configFile = $dir . '/app/config/config.yml';
            $contents = @file_get_contents($configFile) ?: '';

            if (preg_match('/^framework\s*:/m', $contents)) {
                if (strpos($contents, 'templating.engine.pug') === false) {
                    if (!preg_match('/^services\s*:/m', $contents)) {
                        $contents = preg_replace('/^framework\s*:/m', "services:\n\$0", $contents);
                    }
                    $contents = preg_replace('/^services\s*:/m', "\$0$service", $contents);
                    if (file_put_contents($configFile, $contents)) {
                        $flags |= static::CONFIG_OK;
                        $io->write('Engine service added in config.yml');
                    } else {
                        $io->write('Unable to add the engine service in config.yml');
                    }
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
                    if (file_put_contents($configFile, $contents)) {
                        $flags |= static::ENGINE_OK;
                        $io->write('Engine added to framework.templating.engines in config.yml');
                    } else {
                        $io->write('Unable to add the templating engine in framework.templating.engines in config.yml');
                    }
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
                    if (file_put_contents($appFile, $contents)) {
                        $flags |= static::KERNEL_OK;
                        $io->write('Bundle added to AppKernel.php');
                    } else {
                        $io->write('Unable to add the bundle engine in AppKernel.php');
                    }
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

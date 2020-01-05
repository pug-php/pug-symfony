<?php

namespace Jade\Symfony\Traits;

use Closure;
use Jade\Symfony\Css;
use Jade\Symfony\MixedLoader;
use Pug\Pug;
use Pug\Twig\Environment;
use Pug\Twig\EnvironmentTwig3;
use ReflectionException;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RequestContext;
use Twig\Environment as TwigEnvironment;
use Twig\TwigFunction;
use Twig_Environment;
use Twig_Extension;
use Twig_Function;

/**
 * Trait HelpersHandler.
 */
trait HelpersHandler
{
    use PrivatePropertyAccessor;

    /**
     * @var Pug
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
    protected $replacements;

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
     * Version of Symfony to force pug compatibility.
     *
     * Default to Symfony\Component\HttpKernel\Kernel::VERSION
     *
     * @var string|int
     */
    protected $symfonyLevel;

    /**
     * Get a global helper by name.
     *
     * @param string $name
     *
     * @return callable
     */
    public static function getGlobalHelper($name, $twig = null)
    {
        if ($twig && $twig instanceof EnvironmentTwig3) {
            return $twig->getFunctionAsCallable($name);
        }

        return static::$globalHelpers[$name];
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

    protected function getTemplatingHelper($name)
    {
        return isset($this->helpers[$name]) ? $this->helpers[$name] : null;
    }

    /**
     * @param Twig_Environment $twig
     * @param string           $name
     *
     * @return Closure
     */
    protected function compileTwigCallable($twig, $name)
    {
        $callable = function () use ($twig, $name) {
            $variables = [];
            foreach (func_get_args() as $index => $argument) {
                $variables['arg' . $index] = $argument;
            }

            /* @var MixedLoader $loader */
            $loader = $twig->getLoader();

            $template = $loader->uniqueTemplate(
                '{{' . $name . '(' . implode(', ', array_keys($variables)) . ')}}'
            );

//            if ($twig::MAJOR_VERSION >= 3) {
//                file_put_contents('temp.php', $twig->compileSource(new Source($code, $name), $variables));
//                exit;
//                return $twig->render($twig->createTemplate($template, $name), $variables);
//            }

            return $twig->render($template, $variables);
        };

        return $callable->bindTo($twig);
    }

    /**
     * @param Twig_Environment $twig
     * @param callable         $function
     * @param string           $name
     *
     * @throws ReflectionException
     *
     * @return Closure
     */
    protected function getTwigCallable($twig, $function, $name)
    {
        /* @var Twig_Function|TwigFunction $function */
        $callable = $function->getCallable();

        if (!$callable ||
            is_callable($callable) &&
            is_array($callable) &&
            is_string($callable[0]) && is_string($callable[1]) &&
            !(new \ReflectionMethod($callable[0], $callable[1]))->isStatic()
        ) {
            $callable = $this->compileTwigCallable($twig, $name);
        }

        return $callable;
    }

    /**
     * @param Twig_Environment $twig
     * @param callable         $function
     */
    protected function copyTwigFunction($twig, $function)
    {
        /* @var Twig_Function $function */
        $name = $function->getName();

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            // Methods like render_* not yet supported
            return;
        }

        $callable = $this->getTwigCallable($twig, $function, $name);

        if (is_callable($callable) && !is_string($callable)) {
            $this->twigHelpers[$name] = $callable;
        }
    }

    protected function getTwig(ContainerInterface $container)
    {
        $twig = $container->has('twig') ? $container->get('twig') : null;

        $twig = ($twig instanceof Twig_Environment || $twig instanceof TwigEnvironment) ? $twig : null;

        if ($twig && $this->isAtLeastSymfony5()) {
            $twig = Environment::fromTwigEnvironment($twig, $this);

            $services = static::getPrivateProperty($container, 'services', $propertyAccessor);
            $services['twig'] = $twig;
            $propertyAccessor->setValue($container, $services);
        }

        return $twig;
    }

    protected function copyTwigFunctions(ContainerInterface $services)
    {
        $this->twigHelpers = [];
        $twig = $this->getTwig($services);

        if ($twig) {
            /* @var Twig_Environment $twig */
            $twig = clone $twig;
            $twig->env = $twig;
            $loader = new MixedLoader($twig->getLoader());
            $twig->setLoader($loader);
            $this->share('twig', $twig);
            $extensions = $twig->getExtensions();

            if ($twig::MAJOR_VERSION >= 3 &&
                !isset($extensions['Symfony\\Bridge\\Twig\\Extension\\AssetExtension'])) {
                $assetExtension = new AssetExtension(new Packages(new Package(new EmptyVersionStrategy())));
                $extensions['Symfony\\Bridge\\Twig\\Extension\\AssetExtension'] = $assetExtension;
                $twig->addExtension($assetExtension);
            }

            foreach ($twig->getExtensions() as $extension) {
                /* @var Twig_Extension $extension */
                foreach ($extension->getFunctions() as $function) {
                    $this->copyTwigFunction($twig, $function);
                }
            }
        }
    }

    protected function copyStandardHelpers(ContainerInterface $services)
    {
        foreach ($this->templatingHelpers as $helper) {
            if (
                $services->has('templating.helper.' . $helper) &&
                ($instance = $services->get('templating.helper.' . $helper, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ) {
                $this->helpers[$helper] = $instance;
            }
        }
    }

    protected function getHttpFoundationExtension(ContainerInterface $services)
    {
        /* @var RequestStack $stack */
        $stack = $services->get('request_stack');

        /* @var RequestContext $context */
        $context = $services->has('router.request_context')
            ? $services->get('router.request_context')
            : $services->get('router')->getContext();

        // @codeCoverageIgnoreStart

        if ($this->isAtLeastSymfony5()) {
            return new HttpFoundationExtension(new UrlHelper($stack, $context));
        }

        return new HttpFoundationExtension($stack, $context);

        // @codeCoverageIgnoreEnd
    }

    protected function copySpecialHelpers(ContainerInterface $services)
    {
        $this->helpers['css'] = new Css($this->getTemplatingHelper('assets'));
        $this->helpers['http'] = $this->getHttpFoundationExtension($services);
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
            //'csrf_token'    => ['form', 'csrfToken'],
            'url'           => ['router', 'url'],
            'path'          => ['router', 'path'],
            'absolute_url'  => ['http', 'generateAbsoluteUrl'],
            'relative_path' => ['http', 'generateRelativePath'],
            'is_granted'    => ['security', 'isGranted'],
        ], $this->twigHelpers ?: []);
    }

    protected function globalizeHelpers()
    {
        foreach ($this->replacements as $name => $callable) {
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

    protected function registerHelpers(ContainerInterface $services, $helpers)
    {
        $this->helpers = [];
        // $this->copyTwigFunctions($services);
        $this->copyStandardHelpers($services);
        $this->copySpecialHelpers($services);
        $this->copyUserHelpers($helpers);
        $this->storeReplacements();
        $this->globalizeHelpers();
    }

    protected function getSymfonyVersion()
    {
        return $this->symfonyLevel ?: (defined('Symfony\Component\HttpKernel\Kernel::VERSION') ? Kernel::VERSION : 0);
    }

    protected function isAtLeastSymfony5()
    {
        return version_compare($this->getSymfonyVersion(), '5.0.0-dev', '>=');
    }

    /**
     * Set version of Symfony to force pug compatibility.
     *
     * Use Symfony\Component\HttpKernel\Kernel::VERSION if null.
     *
     * @param int|string|null $symfonyLevel
     */
    public function setSymfonyLevel($symfonyLevel)
    {
        $this->symfonyLevel = $symfonyLevel;
    }
}

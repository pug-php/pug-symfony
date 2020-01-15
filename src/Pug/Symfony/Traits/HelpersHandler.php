<?php

namespace Pug\Symfony\Traits;

use Closure;
use Phug\Renderer;
use Pug\Symfony\Css;
use Pug\Symfony\MixedLoader;
use Pug\Twig\Environment;
use ReflectionException;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\RequestContext;
use Twig\Extension\ExtensionInterface;
use Twig\TwigFunction;

/**
 * Trait HelpersHandler.
 */
trait HelpersHandler
{
    use PrivatePropertyAccessor;

    /**
     * @var Renderer
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
     * @return Renderer
     */
    public function getEngine(): Renderer
    {
        return $this->pug;
    }

    protected function getTemplatingHelper($name)
    {
        return isset($this->helpers[$name]) ? $this->helpers[$name] : null;
    }

    /**
     * @param \Twig\Environment $twig
     * @param string            $name
     *
     * @return Closure
     */
    protected function compileTwigCallable($twig, $name)
    {
        $callable = function () use ($twig, $name) {
            $variables = [];
            foreach (func_get_args() as $index => $argument) {
                $variables['arg'.$index] = $argument;
            }

            /* @var MixedLoader $loader */
            $loader = $twig->getLoader();

            $template = $loader->uniqueTemplate(
                '{{'.$name.'('.implode(', ', array_keys($variables)).')}}'
            );

            if ($twig::MAJOR_VERSION >= 3) {
                return $twig->render($twig->createTemplate($template, $name), $variables);
            }

            return $twig->render($template, $variables);
        };

        return $callable->bindTo($twig);
    }

    /**
     * @param \Twig\Environment $twig
     * @param callable          $function
     * @param string            $name
     *
     * @throws ReflectionException
     *
     * @return Closure
     */
    protected function getTwigCallable(\Twig\Environment $twig, TwigFunction $function, string $name)
    {
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

    protected function copyTwigFunction(\Twig\Environment $twig, TwigFunction $function): void
    {
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

        $twig = ($twig instanceof \Twig\Environment) ? $twig : null;

        if ($twig) {
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
            /* @var \Twig\Environment $twig */
            $twig = clone $twig;
            $twig->env = $twig;
            $loader = new MixedLoader($twig->getLoader());
            $twig->setLoader($loader);
            $this->share('twig', $twig);
            $extensions = $twig->getExtensions();

            if (version_compare(Environment::VERSION, '3.0.0-dev', '>=') &&
                !isset($extensions['Symfony\\Bridge\\Twig\\Extension\\AssetExtension'])) {
                $assetExtension = new AssetExtension(new Packages(new Package(new EmptyVersionStrategy())));
                $extensions['Symfony\\Bridge\\Twig\\Extension\\AssetExtension'] = $assetExtension;
                $twig->addExtension($assetExtension);
            }

            foreach ($twig->getExtensions() as $extension) {
                /* @var ExtensionInterface $extension */
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
                $services->has('templating.helper.'.$helper) &&
                ($instance = $services->get('templating.helper.'.$helper, ContainerInterface::NULL_ON_INVALID_REFERENCE))
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

        return new HttpFoundationExtension(new UrlHelper($stack, $context));
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
            $name = strtolower(substr($name, 0, 1)).substr($name, 1);
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
            'absolute_url'  => ['http', 'generateAbsoluteUrl'],
            'relative_path' => ['http', 'generateRelativePath'],
            'is_granted'    => ['security', 'isGranted'],
        ], $this->twigHelpers);
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
        $this->copyTwigFunctions($services);
        $this->copyStandardHelpers($services);
        $this->copySpecialHelpers($services);
        $this->copyUserHelpers($helpers);
        $this->storeReplacements();
        $this->globalizeHelpers();
    }
}

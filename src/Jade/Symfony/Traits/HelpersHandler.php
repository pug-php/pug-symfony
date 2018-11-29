<?php

namespace Jade\Symfony\Traits;

use Jade\Symfony\Css;
use Jade\Symfony\Logout;
use Jade\Symfony\MixedLoader;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *
 * Trait HelpersHandler.
 */
trait HelpersHandler
{
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
        'logout_url',
        'request',
        'router',
        'security',
        'session',
        'slots',
        'stopwatch',
        'translator',
    ];

    protected static $globalHelpers = [];

    protected function getTemplatingHelper($name)
    {
        return isset($this->helpers[$name]) ? $this->helpers[$name] : null;
    }

    protected function copyTwigFunction(\Twig_Environment $twig, $function)
    {
        /* @var \Twig_Function $function */
        $name = $function->getName();

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            // Methods like render_* not yet supported
            return;
        }

        $callable = $function->getCallable();

        if (!$callable) {
            $callable = function () use ($twig, $name) {
                $variables = [];
                foreach (func_get_args() as $index => $argument) {
                    $variables['arg' . $index] = $argument;
                }

                $template = $twig->getLoader()->uniqueTemplate('{{' . $name . '(' . implode(', ', array_keys($variables)) . ') }}');

                return $twig->render($template, $variables);
            };
            $callable = $callable->bindTo($twig);
        }

        if (is_callable($callable) && !is_string($callable)) {
            $this->twigHelpers[$name] = $callable;
        }
    }

    protected function copyTwigFunctions(ContainerInterface $services)
    {
        $this->twigHelpers = [];
        if ($services->has('twig') &&
            ($twig = $services->get('twig')) instanceof \Twig_Environment
        ) {
            /* @var \Twig_Environment $twig */
            $twig = clone $twig;
            $twig->env = $twig;
            $loader = new MixedLoader($twig->getLoader());
            $twig->setLoader($loader);
            $this->share('twig', $twig);
            foreach ($twig->getExtensions() as $extension) {
                /* @var \Twig_Extension $extension */
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

    /**
     * Get a global helper by name.
     *
     * @param string $name
     *
     * @return callable
     */
    public static function getGlobalHelper($name)
    {
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
}

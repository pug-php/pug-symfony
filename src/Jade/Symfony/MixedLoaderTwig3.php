<?php

namespace Jade\Symfony;

use Twig\Source;
use Twig_Error_Loader;
use Twig_LoaderInterface;

class_alias('Twig\\Environment', 'Twig_Environment,');
class_alias('Twig\\Error\\LoaderError', 'Twig_Error_Loader');
class_alias('Twig\\Loader\\LoaderInterface', 'Twig_LoaderInterface');

class MixedLoader implements \Twig_LoaderInterface
{
    /**
     * @var Twig_LoaderInterface
     */
    protected $base;

    /**
     * @var array
     */
    protected $extraTemplates = [];

    public function __construct(Twig_LoaderInterface $base)
    {
        $this->base = $base;
    }

    public function uniqueTemplate($template)
    {
        $name = uniqid();
        $this->setTemplateSource($name, $template);

        return $name;
    }

    public function setTemplateSource($name, $template)
    {
        $this->extraTemplates[$name] = $template;
    }

    public function getSourceContext(string $name): Source
    {
        if (isset($this->extraTemplates[$name])) {
            return new Source($this->extraTemplates[$name], $name);
        }

        return $this->base->getSourceContext($name);
    }

    // @codeCoverageIgnoreStart
    public function getSource($name)
    {
        return $this->getSourceContext($name)->getCode();
    }

    // @codeCoverageIgnoreEnd

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->base, $name], $arguments);
    }

    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param string $name The name of the template to load
     *
     * @throws Twig_Error_Loader When $name is not found
     *
     * @return string The cache key
     */
    public function getCacheKey(string $name): string
    {
        if (isset($this->extraTemplates[$name])) {
            return $name;
        }

        return $this->base->getCacheKey($name);
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param string $name The template name
     * @param int    $time Timestamp of the last modification time of the
     *                     cached template
     *
     * @throws Twig_Error_Loader When $name is not found
     *
     * @return bool true if the template is fresh, false otherwise
     */
    public function isFresh(string $name, $time): bool
    {
        if (isset($this->extraTemplates[$name])) {
            return true;
        }

        return $this->base->isFresh($name, $time);
    }

    /**
     * Check if we have the source code of a template, given its name.
     *
     * @param string $name The name of the template to check if we can load
     *
     * @return bool If the template source code is handled by this loader or not
     */
    public function exists(string $name)
    {
        if (isset($this->extraTemplates[$name])) {
            return true;
        }

        return $this->base->exists($name);
    }
}

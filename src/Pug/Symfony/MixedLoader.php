<?php

declare(strict_types=1);

namespace Pug\Symfony;

use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

class MixedLoader implements LoaderInterface
{
    /**
     * @var LoaderInterface
     */
    protected $base;

    /**
     * @var array
     */
    protected $extraTemplates = [];

    public function __construct(LoaderInterface $base)
    {
        $this->base = $base;
    }

    public function setTemplateSource($name, $template): void
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

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->base, $name], $arguments);
    }

    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param string $name The name of the template to load
     *
     * @throws LoaderError When $name is not found
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
     * @throws LoaderError When $name is not found
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
    public function exists(string $name): bool
    {
        if (isset($this->extraTemplates[$name])) {
            return true;
        }

        return $this->base->exists($name);
    }
}

<?php

namespace Pug\Symfony\Traits;

use Pug\Pug;

/**
 * Trait Filters.
 *
 * @method Pug getRenderer()
 */
trait Filters
{
    /**
     * Set a Pug filter.
     *
     * @param string          $name
     * @param callable|string $filter
     *
     * @return $this
     */
    public function filter(string $name, $filter): self
    {
        $this->getRenderer()->filter($name, $filter);

        return $this;
    }

    /**
     * Check if the Pug engine has a given filter by name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasFilter(string $name): bool
    {
        return $this->getRenderer()->hasFilter($name);
    }

    /**
     * Get a filter by name from the Pug engine.
     *
     * @param string $name
     *
     * @return callable|string
     */
    public function getFilter(string $name)
    {
        return $this->getRenderer()->getFilter($name);
    }
}

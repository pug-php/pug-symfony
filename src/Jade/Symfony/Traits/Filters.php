<?php

namespace Jade\Symfony\Traits;

trait Filters
{
    /**
     * Set a Pug filter.
     *
     * @param string   $name
     * @param callable $filter
     *
     * @return $this
     */
    public function filter($name, $filter)
    {
        $this->jade->filter($name, $filter);

        return $this;
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
}

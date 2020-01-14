<?php

namespace Pug\Symfony\Traits;

use Pug\Pug;

/**
 * Trait Options.
 *
 * @property-read Pug $pug
 */
trait Options
{
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
        return method_exists($this->pug, 'hasOption') && !$this->pug->hasOption($name)
            ? $default
            : $this->pug->getOption($name);
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
        return $this->pug->setOption($name, $value);
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
        /** @var Pug $pug */
        $pug = $this->pug->setOptions($options);

        return $pug;
    }

    /**
     * Get pug variables shared across views.
     *
     * @return array
     */
    public function getSharedVariables()
    {
        return $this->getOptionDefault('shared_variables', []);
    }
}

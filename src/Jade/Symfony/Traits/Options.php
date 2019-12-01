<?php

namespace Jade\Symfony\Traits;

use Pug\Pug;

/**
 * Trait Options.
 *
 * @property-read \Pug\Pug $pug
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
        // @codeCoverageIgnoreStart
        try {
            return $this->getOption($name, $default);
        } catch (\InvalidArgumentException $exception) {
            return $default;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get a Pug engine option or the default value passed as second parameter (null if omitted).
     *
     * @deprecated This method has inconsistent behavior depending on which major version of the Pug-php engine you
     *             use, so prefer using getOptionDefault instead that has consistent output no matter the Pug-php
     *             version.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @throws \InvalidArgumentException when using Pug-php 2 engine and getting an option not set
     *
     * @return mixed
     */
    public function getOption($name, $default = null)
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
        return $this->pug->setOptions($options);
    }

    /**
     * Set custom options of the Pug engine.
     *
     * @deprecated Method only used with Pug-php 2, if you're using Pug-php 2, please consider using the
     *             last major release.
     *
     * @param array $options
     *
     * @return Pug
     */
    public function setCustomOptions(array $options)
    {
        return $this->pug->setCustomOptions($options);
    }
}

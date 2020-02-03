<?php

namespace Pug\Symfony\Traits;

use Pug\Pug;

/**
 * Trait Options.
 *
 * @method Pug getRenderer()
 */
trait Options
{
    /**
     * Get a Pug engine option or the default value passed as second parameter (null if omitted).
     *
     * @param string|string[] $name    option path (string) or deep path (array of strings).
     * @param mixed           $default value to return if the option is not set (null by default).
     *
     * @return mixed
     */
    public function getOptionDefault($name, $default = null)
    {
        $pug = $this->getRenderer();

        return method_exists($pug, 'hasOption') && !$pug->hasOption($name)
            ? $default
            : $pug->getOption($name);
    }

    /**
     * Set a Pug engine option.
     *
     * @param string|string[] $name  option path (string) or deep path (array of strings).
     * @param mixed           $value new value.
     *
     * @return Pug
     */
    public function setOption($name, $value)
    {
        return $this->getRenderer()->setOption($name, $value);
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
        $pug = $this->getRenderer()->setOptions($options);

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

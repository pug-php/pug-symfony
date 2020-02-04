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
     */
    public function setOption($name, $value): void
    {
        $this->getRenderer()->setOption($name, $value);
    }

    /**
     * Set multiple options of the Pug engine.
     *
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $this->getRenderer()->setOptions($options);
    }

    /**
     * Get pug variables shared across views.
     *
     * @return array
     */
    public function getSharedVariables(): array
    {
        return $this->getOptionDefault('shared_variables', []);
    }
}

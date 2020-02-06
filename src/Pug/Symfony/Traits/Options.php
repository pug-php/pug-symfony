<?php

namespace Pug\Symfony\Traits;

use Pug\Pug;

/**
 * Trait Options.
 *
 * @method Pug   getRenderer()
 * @method array getRendererOptions()
 */
trait Options
{
    /**
     * @var array
     */
    protected $options;

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
        if ($this->pug === null) {
            $options = $this->getRendererOptions();

            return array_key_exists($name, $options) ? $options[$name] : $default;
        }

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
        if ($this->pug === null) {
            $this->getRendererOptions();
            $this->options[$name] = $value;

            return;
        }

        $this->getRenderer()->setOption($name, $value);
    }

    /**
     * Set multiple options of the Pug engine.
     *
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        if ($this->pug === null) {
            $this->options = array_merge($this->getRendererOptions(), $options);

            return;
        }

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

<?php

namespace Pug\Symfony;

use Pug\Pug;

class PugEngine extends Pug
{
    // Pug engine customization come here.

    /**
     * Return the current major version of twig/twig.
     *
     * @codeCoverageIgnore
     *
     * @return int
     */
    public static function getTwigVersion()
    {
        if (class_exists('Twig_Autoloader')) {
            return 1;
        }

        if (method_exists('Twig\\Parser', 'isReservedMacroName')) {
            return 2;
        }

        return 3;
    }
}

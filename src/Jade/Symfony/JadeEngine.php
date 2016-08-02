<?php

namespace Jade\Symfony;

use Jade\Jade;

class JadeEngine extends Jade
{
    /**
     * Compile PHP code from a Pug input or a Pug file.
     *
     * @param string input
     *
     * @throws \Exception
     *
     * @return string
     */
    public function compile($input)
    {
        $php = parent::compile($input);
        $php = preg_replace('/(\\Jade\\Compiler::getPropertyFromAnything\((?:[^()]++|(?R))*+\))\(((?:[^()]++|(?R))*+)\)/', 'call_user_func($1, $2)', $php);

        return $php;
    }
}

<?php

namespace Pug\Exceptions;

use RuntimeException;
use Throwable;

class ReservedVariable extends RuntimeException
{
    public function __construct($variableName, $code = 0, Throwable $previous = null)
    {
        parent::__construct("\"$variableName\" is a reserved variable name, you can't overwrite it.", $code, $previous);
    }
}

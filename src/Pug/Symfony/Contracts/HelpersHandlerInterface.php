<?php

namespace Pug\Symfony\Contracts;

use ArrayAccess;

interface HelpersHandlerInterface extends ArrayAccess
{
    public static function getGlobalHelper($name);
}

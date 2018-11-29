<?php

namespace Jade\Symfony\Contracts;

use ArrayAccess;

interface HelpersHandlerInterface extends ArrayAccess
{
    public static function getGlobalHelper($name);
}

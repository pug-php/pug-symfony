<?php

declare(strict_types=1);

namespace Pug\Symfony\Contracts;

interface InstallerInterface
{
    const CONFIG_OK = 1;
    const ENGINE_OK = 2;
    const KERNEL_OK = 4;

    public static function install($event, $dir = null);
}

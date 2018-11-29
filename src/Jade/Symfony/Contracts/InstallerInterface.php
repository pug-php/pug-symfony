<?php

namespace Jade\Symfony\Contracts;

interface InstallerInterface
{
    const CONFIG_OK = 1;
    const ENGINE_OK = 2;
    const KERNEL_OK = 4;

    public static function install($event, $dir = null);
}

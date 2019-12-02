<?php

use Symfony\Component\HttpKernel\Kernel;

require __DIR__ . '/vendor/autoload.php';

var_dump(defined('Symfony\\Component\\HttpKernel\\Kernel::VERSION'));
var_dump(Kernel::VERSION);
var_dump(version_compare(Kernel::VERSION, '5.0.0-dev', '>='));

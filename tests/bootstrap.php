<?php

include_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/project/src/TestBundle/TestBundle.php';
include_once __DIR__ . '/project/app/AppKernel.php';

if (!class_exists('PHPUnit_Framework_TestCase')) {
    class PHPUnit_Framework_TestCase extends \PHPUnit\Framework\TestCase
    {
        // Symfony 3.0 and 3.1 compatibility
    }
}

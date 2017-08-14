<?php

include_once __DIR__ . '/../vendor/autoload.php';

if (!class_exists('PHPUnit_Framework_TestCase')) {
    class PHPUnit_Framework_TestCase extends \PHPUnit\Framework\TestCase {
        // PHPUnit compatibility
    }
}

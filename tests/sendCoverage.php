#!/usr/bin/env php
<?php

if (version_compare(PHP_VERSION, '5.6') >= 0 && version_compare(PHP_VERSION, '7.0') < 0) {
    echo 'Send coverage report for ' . PHP_VERSION;
    chdir(__DIR__ . '/..');
    exec('vendor/bin/test-reporter --coverage-report coverage.xml', $output, $status);
    echo implode("\n", $output) . "\n";
    exit($status);
}

echo 'Coverage report ignored for ' . PHP_VERSION;
exit(0);

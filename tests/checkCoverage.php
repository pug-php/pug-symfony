<?php

if (defined('HHVM_VERSION')) {
    echo 'Code coverage check disabled on HHVM.';

    exit(0);
}

$xmlFile = isset($argv[2]) ? $argv[2] : __DIR__ . '/../coverage.xml';
$requiredCoverage = isset($argv[1]) ? intval($argv[1]) : 90;

if (!file_exists($xmlFile)) {
    echo 'Error: Code coverage files not found. Please run `unit-tests:run`.';

    exit(1);
}

echo 'Validating code coverage...';

$xml = new SimpleXMLElement(file_get_contents($xmlFile));
$metrics = $xml->xpath('//metrics');
$totalElements = 0;
$checkedElements = 0;
foreach ($metrics as $metric) {
    $totalElements += (int) $metric['elements'];
    $checkedElements += (int) $metric['coveredelements'];
}
$coverage = ($checkedElements / $totalElements) * 100;

if ($coverage < $requiredCoverage) {
    echo "Fail: Code coverage is {$coverage}%. You need to reach {$requiredCoverage}% to validate this build.";

    exit(1);
}

echo "Pass: Code Coverage {$coverage}%!";

exit(0);

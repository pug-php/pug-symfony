<?php

$travisData = array(
    'language'      => 'php',
    'matrix'        => array(
        'include' => array(),
    ),
    'before_script' => array(
        'travis_retry composer self-update',
        implode(' ', array(
            'if [ "$SYMFONY_VERSION" != "" ];',
            'then travis_retry composer require "symfony/symfony:${SYMFONY_VERSION}" --no-update;',
            'fi;',
        )),
        'travis_retry composer update --no-interaction',
        'chmod -R 0777 tests/project',
    ),
    'script' => array(
        'vendor/bin/phpunit --verbose --coverage-text --coverage-clover=coverage.xml',
    ),
    'after_script' => array(
        'vendor/bin/test-reporter --coverage-report coverage.xml',
    ),
    'after_success' => array(
        'bash <(curl -s https://codecov.io/bash)'
    ),
    'addons' => array(
        'code_climate' => array(
            'repo_token' => 'ae72d604c76bbd8cd0d0b8318930e3a28afd007ab8381d2a8c49ce296ca2b292',
        ),
    ),
);

$matrix = array(
    '5.3'  => array('2.7', '2.8'),
    '5.4'  => array('2.7', '2.8'),
    '5.5'  => array('2.7', '2.8', '3.0', '3.1', '3.2', '3.3'),
    '5.6'  => array('2.7', '2.8', '3.0', '3.1', '3.2', '3.3'),
    '7.0'  => array('2.7', '2.8', '3.0', '3.1', '3.2', '3.3', '4.0'),
    '7.1'  => array('2.7', '2.8', '3.0', '3.1', '3.2', '3.3', '4.0'),
    '7.2'  => array('2.7', '2.8', '3.0', '3.1', '3.2', '3.3', '4.0'),
    'hhvm' => array('2.7', '2.8', '3.0', '3.1', '3.2', '3.3', '4.0'),
);

foreach ($matrix as $phpVersion => $symfonyVersions) {
    foreach ($symfonyVersions as $symphonyVersion) {
        $environment = array(
            'php' => $phpVersion,
            'env' => 'SYMFONY_VERSION=' . $symphonyVersion . '.*',
        );
        if ($phpVersion === 'hhvm') {
            $environment['dist'] = 'trusty';
            $environment['sudo'] = 'required';
        }
        $travisData['matrix']['include'][] = $environment;
    }
}

function compileYaml($data, $indent = 0) {
    $contents = '';
    foreach ($data as $key => $value) {
        $isAssoc = is_string($key);
        $contents .= str_repeat(' ', $indent * 2) . ($isAssoc ? $key . ':' : '- ');
        if (is_array($value)) {
            $value = compileYaml($value, $indent + 1);
            $contents .= $isAssoc
                ? "\n$value"
                : '' . ltrim($value);

            continue;
        }

        $contents .= ' ' . $value . "\n";
    }

    return $contents;
}

file_put_contents('.travis.yml', compileYaml($travisData));

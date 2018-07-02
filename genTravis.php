<?php

$travisData = [
    'language'      => 'php',
    'cache'         => [
        'apt' => 'true',
        'directories' => [
            '$HOME/.composer/cache',
        ],
    ],
    'matrix'        => [
        'include' => [],
    ],
    'before_script' => [
        'php -r "copy(\'https://getcomposer.org/installer\', \'composer-setup.php\');"',
        'php -r "if (hash_file(\'SHA384\', \'composer-setup.php\') === \'544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061\') { echo \'Installer verified\'; } else { echo \'Installer corrupt\'; unlink(\'composer-setup.php\'); } echo PHP_EOL;"',
        'php composer-setup.php',
        'php -r "unlink(\'composer-setup.php\');"',
        'if [ "$SYMFONY_VERSION" != "" ]; then travis_retry composer require --no-update -n symfony/symfony=$SYMFONY_VERSION; fi;',
        'if [ "$PUG_VERSION" != "" ]; then travis_retry composer require --no-update -n pug-php/pug=$PUG_VERSION; fi;',
        'if [ -f /home/travis/.phpenv/versions/$(phpenv version-name)/etc/conf.d/xdebug.ini ]; then mv /home/travis/.phpenv/versions/$(phpenv version-name)/etc/conf.d/xdebug.ini ~/xdebug.ini; fi;',
        'travis_retry php -d memory_limit=-1 composer.phar update -o --no-interaction --prefer-stable',
        'if [ -f ~/xdebug.ini ]; then mv ~/xdebug.ini /home/travis/.phpenv/versions/$(phpenv version-name)/etc/conf.d/xdebug.ini; fi;',
        'chmod -R 0777 tests/project',
    ],
    'script'        => [
        'vendor/bin/phpunit --verbose --coverage-text --coverage-clover=coverage.xml',
        'php tests/checkCoverage.php',
    ],
    'after_script'  => [
        implode(' ', [
            'if [ $(phpenv version-name) = \'5.6\' ];',
            'then vendor/bin/test-reporter --coverage-report coverage.xml;',
            'fi;',
        ]),
        'bash <(curl -s https://codecov.io/bash)',
    ],
    'addons'        => [
        'code_climate' => [
            'repo_token' => 'ae72d604c76bbd8cd0d0b8318930e3a28afd007ab8381d2a8c49ce296ca2b292',
        ],
    ],
];

$matrix = [
    '5.4'  => [
        '^2.7.1' => ['2.7.*', '2.8.*'],
    ],
    '5.5'  => [
        '^3.0.0' => ['2.7.*', '2.8.*', '3.0.*', '3.1.*', '3.2.*', '3.3.*', '3.4.*'],
    ],
    '5.6'  => [
        '^3.0.0' => ['2.7.*', '2.8.*', '3.0.*', '3.1.*', '3.2.*', '3.3.*', '3.4.*'],
    ],
    '7.0'  => [
        '^3.0.0' => ['2.7.*', '2.8.*', '3.0.*', '3.1.*', '3.2.*', '3.3.*', '3.4.*'],
    ],
    '7.1'  => [
        '^3.0.0' => ['2.7.*', '2.8.*', '3.0.*', '3.1.*', '3.2.*', '3.3.*', '3.4.*', '4.0.*', '4.1.*', '4.2.x-dev'],
    ],
    '7.2'  => [
        '^2.7.1' => ['2.7.*', '2.8.*', '3.0.*', '3.1.*', '3.2.*', '3.3.*', '3.4.*', '4.0.*', '4.1.*', '4.2.x-dev'],
        '^3.0.0' => ['2.7.*', '2.8.*', '3.0.*', '3.1.*', '3.2.*', '3.3.*', '3.4.*', '4.0.*', '4.1.*', '4.2.x-dev'],
    ],
    'hhvm' => [
        '^2.7.1' => ['2.7.*', '2.8.*', '3.0.*', '3.1.*', '3.2.*', '3.3.*', '3.4.*'],
    ],
];

foreach ($matrix as $phpVersion => $pugVersions) {
    foreach ($pugVersions as $pugVersion => $symfonyVersions) {
        foreach ($symfonyVersions as $symphonyVersion) {
            $environment = [
                'php' => $phpVersion,
                'env' => [
                    "SYMFONY_VERSION='$symphonyVersion'",
                    "PUG_VERSION='$pugVersion'",
                ],
            ];
            if ($phpVersion === 'hhvm') {
                $environment['dist'] = 'trusty';
                $environment['sudo'] = 'required';
            }
            $travisData['matrix']['include'][] = $environment;
        }
    }
}

function compileYaml($data, $indent = 0)
{
    $contents = '';
    foreach ($data as $key => $value) {
        $isAssoc = is_string($key);
        $contents .= str_repeat(' ', $indent * 2) . ($isAssoc ? $key . ':' : '-');
        if (is_array($value)) {
            $value = compileYaml($value, $indent + 1);
            $contents .= $isAssoc
                ? "\n$value"
                : ' ' . ltrim($value);

            continue;
        }

        $contents .= ' ' . $value . "\n";
    }

    return $contents;
}

file_put_contents('.travis.yml', compileYaml($travisData));

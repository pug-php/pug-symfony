<?php

list($symfonyVersion, $pugVersion) = array_slice($argv, 1);
$composerFile = __DIR__ . '/../composer.json';
$composer = file_get_contents($composerFile);
$newContent = preg_replace('/"symfony\/symfony"\s*:\s*"[^"]+"/', '"symfony/symfony": "' . $symfonyVersion . '"', $composer);
$newContent = preg_replace('/"pug-php\/pug"\s*:\s*"[^"]+"/', '"pug-php/pug": "' . $pugVersion . '"', $composer);

if (version_compare(PHP_VERSION, '7.2') >= 0) {
    // https://github.com/symfony/symfony/pull/23952
    $newContent = preg_replace('/"symfony\/phpunit-bridge"\s*:\s*"[^"]+",/', '', $composer);
}
if ($newContent === $composer) {
    echo 'symfony/symfony not found in ./composer.json';

    exit(1);
}

if (empty($newContent) || !file_put_contents($composerFile, $newContent)) {
    echo './composer.json cannot be updated';

    exit(1);
}

echo './composer.json has been updated';

exit(0);

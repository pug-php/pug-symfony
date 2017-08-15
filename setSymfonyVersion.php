<?php

$version = implode(' ', array_slice($argv, 1));

$composer = file_get_contents(__DIR__ . '/composer.json');
$newContent = preg_replace('/"symfony\/symfony"\s*:\s*"[^"]+"/', '"symfony/symfony": "' . $version . '"', $composer);
if ($newContent === $composer) {
    echo 'symfony/symfony not found in ./composer.json';

    exit(1);
}
if (empty($newContent) || !file_put_contents(__DIR__ . '/composer.json', $newContent)) {
    echo './composer.json cannot be updated';

    exit(1);
}

echo './composer.json has been updated';

exit(0);

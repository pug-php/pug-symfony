<?php

list($pugVersion, $symfonyVersion) = explode(' ', implode(' ', array_slice($argv, 1)), 2);

echo shell_exec('composer require --no-update -n symfony/symfony='.$symfonyVersion);
echo shell_exec('composer require --no-update -n pug-php/pug='.$pugVersion);

exit(0);

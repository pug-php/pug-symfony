<?php

if (file_exists(__DIR__ . '/installed')) {
    exit(0);
}

function ask()
{
    return PHP_OS == 'WINNT'
        ? stream_get_line(STDIN, 1024, PHP_EOL)
        : readline('$ ');
}

function confirm()
{
    while (!in_array($answer = mb_strtoupper(mb_substr(ask(), 0, 1)), ['Y', 'N'])) {
        echo "Please enter Y for yes or N for no.\n";
    }

    return $answer === 'Y';
}

$dir = __DIR__ . '/../../..';

$service = '
    templating.engine.pug:
        class: Pug\PugSymfonyEngine
        arguments: ["@kernel"]
';

$bundle = 'new Pug\PugSymfonyBundle\PugSymfonyBundle()';

define('CONFIG_OK', 1);
define('ENGINE_OK', 2);
define('KERNEL_OK', 4);

$flags = 0;

echo 'Would you like us to add automatically needed settings in your config.yml? [Y/N] ';

if (confirm()) {
    $configFile = $dir . '/app/config/config.yml';
    $contents = @file_get_contents($configFile) ?: '';

    if (preg_match('/^framework\s*:/m', $contents)) {
        if (strpos($contents, 'templating.engine.pug') === false) {
            if (!preg_match('/^services\s*:/m', $contents)) {
                $contents = preg_replace('/^framework\s*:/m', "services:\n\$0", $contents);
            }
            $contents = preg_replace('/^services\s*:/m', "\$0$service", $contents);
            if (file_put_contents($configFile, $contents)) {
                $flags |= CONFIG_OK;
                echo "Engine service added in config.yml\n";
            } else {
                echo "Unable to add the engine service in config.yml\n";
            }
        } else {
            $flags |= CONFIG_OK;
            echo "templating.engine.pug setting in config.yml already exists.\n";
        }
        $lines = explode("\n", $contents);
        $proceeded = false;
        $inFramework = false;
        $inTemplating = false;
        $templatingIndent = 0;
        foreach ($lines as &$line) {
            $trimedLine = ltrim($line);
            $indent = mb_strlen($line) - mb_strlen($trimedLine);
            if (preg_match('/^framework\s*:/', $line)) {
                $inFramework = true;
                continue;
            }
            if ($inFramework && preg_match('/^templating\s*:/', $trimedLine)) {
                $templatingIndent = $indent;
                $inTemplating = true;
                continue;
            }
            if ($indent < $templatingIndent) {
                $inTemplating = false;
            }
            if ($indent === 0) {
                $inFramework = false;
            }
            if ($inTemplating && preg_match('/^engines\s*:(.*)$/', $trimedLine, $match)) {
                $engines = @json_decode(str_replace("'", '"', trim($match[1])));
                if (!is_array($engines)) {
                    echo "Automatic engine adding is only possible if framework.templating.engines is a " .
                        "one-line setting in config.yml.\n.\n";

                    break;
                }
                if (in_array('pug', $engines)) {
                    $flags |= ENGINE_OK;
                    echo "Pug engine already exist in framework.templating.engines in config.yml.\n";

                    break;
                }
                array_unshift($engines, 'pug');
                $line = preg_replace('/^(\s+engines\s*:)(.*)$/', '$1 ' . json_encode($engines), $line);
                $proceeded = true;
                break;
            }
        }
        if ($proceeded) {
            $contents = implode("\n", $lines);
            if (file_put_contents($configFile, $contents)) {
                $flags |= ENGINE_OK;
                echo "Engine added to framework.templating.engines in config.yml\n";
            } else {
                echo "Unable to add the templating engine in framework.templating.engines in config.yml\n";
            }
        }
    } else {
        echo "framework entry not found in config.yml.\n";
    }
} else {
    $flags |= CONFIG_OK | ENGINE_OK;
}

echo 'Would you like us to add automatically the pug bundle in your AppKernel.php? [Y/N] ';

if (confirm()) {
    $appFile = $dir . '/app/AppKernel.php';
    $contents = @file_get_contents($appFile) ?: '';

    if (preg_match('/^[ \\t]*new\\s+Symfony\\\\Bundle\\\\FrameworkBundle\\\\FrameworkBundle\\(\\)/m', $contents)) {
        if (strpos($contents, $bundle) === false) {
            $contents = preg_replace('/^([ \\t]*)new\\s+Symfony\\\\Bundle\\\\FrameworkBundle\\\\FrameworkBundle\\(\\)/m', "\$0,\n\$1$bundle", $contents);
            if (file_put_contents($appFile, $contents)) {
                $flags |= KERNEL_OK;
                echo "Bundle added to AppKernel.php\n";
            } else {
                echo "Unable to add the bundle engine in AppKernel.php\n";
            }
        } else {
            $flags |= KERNEL_OK;
            echo "The bundle already exists in AppKernel.php\n";
        }
    } else {
        echo "Sorry, AppKernel.php has a format we can't handle automatically.\n";
    }
} else {
    $flags |= KERNEL_OK;
}

if (($flags & KERNEL_OK) && ($flags & CONFIG_OK) && ($flags & ENGINE_OK)) {
    touch(__DIR__ . '/installed');
}

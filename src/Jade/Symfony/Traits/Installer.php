<?php

namespace Jade\Symfony\Traits;

use Composer\IO\IOInterface;
use Jade\Symfony\Contracts\InstallerInterface;

/**
 * @internal
 *
 * Trait Installer.
 */
trait Installer
{
    protected static function askConfirmation(IOInterface $io, $message)
    {
        return !$io->isInteractive() || $io->askConfirmation($message);
    }

    protected static function installSymfony4Config(IOInterface $io, $dir, $templateService, $proceedTask, &$flags)
    {
        $configFile = $dir . '/config/packages/framework.yaml';
        $contents = @file_get_contents($configFile) ?: '';

        if (!preg_match('/[^a-zA-Z]pug[^a-zA-Z]/', $contents)) {
            $newContents = null;
            if (preg_match('/^\s*-\s*([\'"]?)twig[\'"]?/m', $contents)) {
                $newContents = preg_replace('/^(\s*-\s*)([\'"]?)twig[\'"]?(\n)/m', '$0$1$2pug$2$3', $contents);
            } elseif (preg_match('/[[,]\s*([\'"]?)twig[\'"]?/', $contents)) {
                $newContents = preg_replace('/[[,]\s*([\'"]?)twig[\'"]?/', '$0, $2pug$2', $contents);
            } elseif (preg_match('/^framework\s*:\s*\n/m', $contents)) {
                $newContents = preg_replace_callback('/^framework\s*:\s*\n/', function ($match) use ($templateService) {
                    return $match[0] . $templateService;
                }, $contents);
            }
            if ($newContents) {
                $proceedTask(
                    file_put_contents($configFile, $newContents),
                    InstallerInterface::ENGINE_OK,
                    'Engine service added in config/packages/framework.yaml',
                    'Unable to add the engine service in config/packages/framework.yaml'
                );
            } else {
                $io->write('framework entry not found in config/packages/framework.yaml.');
            }
        } else {
            $flags |= InstallerInterface::ENGINE_OK;
            $io->write('templating.engine.pug setting in config/packages/framework.yaml already exists.');
        }
    }

    protected static function installSymfony4ServiceConfig(IOInterface $io, $dir, $pugService, $proceedTask, &$flags)
    {
        $configFile = $dir . '/config/services.yaml';
        $contents = @file_get_contents($configFile) ?: '';

        if (strpos($contents, 'templating.engine.pug') === false) {
            if (preg_match('/^services\s*:\s*\n/m', $contents)) {
                $contents = preg_replace_callback('/^services\s*:\s*\n/m', function ($match) use ($pugService) {
                    return $match[0] . $pugService;
                }, $contents);
                $proceedTask(
                    file_put_contents($configFile, $contents),
                    InstallerInterface::CONFIG_OK,
                    'Engine service added in config/services.yaml',
                    'Unable to add the engine service in config/services.yaml'
                );
            } else {
                $io->write('services entry not found in config/services.yaml.');
            }
        } else {
            $flags |= InstallerInterface::CONFIG_OK;
            $io->write('templating.engine.pug setting in config/services.yaml already exists.');
        }
    }

    protected static function installSymfony4Bundle(IOInterface $io, $dir, $bundle, $bundleClass, $proceedTask, &$flags)
    {
        $appFile = $dir . '/config/bundles.php';
        $contents = @file_get_contents($appFile) ?: '';

        if (preg_match('/\[\s*\n/', $contents)) {
            if (strpos($contents, $bundleClass) === false) {
                $contents = preg_replace_callback('/\[\s*\n/', function ($match) use ($bundle) {
                    return $match[0] . "$bundle\n";
                }, $contents);
                $proceedTask(
                    file_put_contents($appFile, $contents),
                    InstallerInterface::KERNEL_OK,
                    'Bundle added to config/bundles.php',
                    'Unable to add the bundle engine in config/bundles.php'
                );
            } else {
                $flags |= InstallerInterface::KERNEL_OK;
                $io->write('The bundle already exists in config/bundles.php');
            }
        } else {
            $io->write('Sorry, config/bundles.php has a format we can\'t handle automatically.');
        }
    }

    protected static function installInSymfony4($event, $dir)
    {
        /** @var \Composer\Script\Event $event */
        $io = $event->getIO();
        $baseDirectory = __DIR__ . '/../../../..';

        $flags = 0;

        $templateService = "\n    templating:\n" .
            "        engines: ['twig', 'pug']\n";
        $pugService = "\n    templating.engine.pug:\n" .
            "        public: true\n" .
            "        autowire: false\n" .
            "        class: Pug\PugSymfonyEngine\n" .
            "        arguments:\n" .
            "            - '@kernel'\n";
        $bundleClass = 'Pug\PugSymfonyBundle\PugSymfonyBundle';
        $bundle = "$bundleClass::class => ['all' => true],";
        $addServicesConfig = static::askConfirmation($io, 'Would you like us to add automatically needed settings in your config/services.yaml? [Y/N] ');
        $addConfig = static::askConfirmation($io, 'Would you like us to add automatically needed settings in your config/packages/framework.yaml? [Y/N] ');
        $addBundle = static::askConfirmation($io, 'Would you like us to add automatically the pug bundle in your config/bundles.php? [Y/N] ');

        $proceedTask = function ($taskResult, $flag, $successMessage, $errorMessage) use (&$flags, $io) {
            static::proceedTask($flags, $io, $taskResult, $flag, $successMessage, $errorMessage);
        };

        if ($addConfig) {
            static::installSymfony4Config($io, $dir, $templateService, $proceedTask, $flags);
        } else {
            $flags |= InstallerInterface::ENGINE_OK;
        }

        if ($addServicesConfig) {
            static::installSymfony4ServiceConfig($io, $dir, $pugService, $proceedTask, $flags);
        } else {
            $flags |= InstallerInterface::CONFIG_OK;
        }

        if ($addBundle) {
            static::installSymfony4Bundle($io, $dir, $bundle, $bundleClass, $proceedTask, $flags);
        } else {
            $flags |= InstallerInterface::KERNEL_OK;
        }

        if (($flags & InstallerInterface::KERNEL_OK) && ($flags & InstallerInterface::CONFIG_OK) && ($flags & InstallerInterface::ENGINE_OK)) {
            touch($baseDirectory . '/installed');
        }

        return true;
    }

    protected static function installSymfony3Config(IOInterface $io, $dir, $service, $proceedTask, &$flags)
    {
        $configFile = $dir . '/app/config/config.yml';
        $contents = @file_get_contents($configFile) ?: '';

        if (preg_match('/^framework\s*:/m', $contents)) {
            if (strpos($contents, 'templating.engine.pug') === false) {
                if (!preg_match('/^services\s*:/m', $contents)) {
                    $contents = preg_replace('/^framework\s*:/m', "services:\n\$0", $contents);
                }
                $contents = preg_replace('/^services\s*:/m', "\$0$service", $contents);
                $proceedTask(
                    file_put_contents($configFile, $contents),
                    InstallerInterface::CONFIG_OK,
                    'Engine service added in config.yml',
                    'Unable to add the engine service in config.yml'
                );
            } else {
                $flags |= InstallerInterface::CONFIG_OK;
                $io->write('templating.engine.pug setting in config.yml already exists.');
            }
            $lines = explode("\n", $contents);
            $proceeded = false;
            $inFramework = false;
            $inTemplating = false;
            $templatingIndent = 0;
            foreach ($lines as &$line) {
                $trimmedLine = ltrim($line);
                $indent = mb_strlen($line) - mb_strlen($trimmedLine);
                if (preg_match('/^framework\s*:/', $line)) {
                    $inFramework = true;
                    continue;
                }
                if ($inFramework && preg_match('/^templating\s*:/', $trimmedLine)) {
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
                if ($inTemplating && preg_match('/^engines\s*:(.*)$/', $trimmedLine, $match)) {
                    $engines = @json_decode(str_replace("'", '"', trim($match[1])));
                    // @codeCoverageIgnoreStart
                    if (!is_array($engines)) {
                        $io->write('Automatic engine adding is only possible if framework.templating.engines is a ' .
                            'one-line setting in config.yml.');

                        break;
                    }
                    // @codeCoverageIgnoreEnd
                    if (in_array('pug', $engines)) {
                        $flags |= InstallerInterface::ENGINE_OK;
                        $io->write('Pug engine already exist in framework.templating.engines in config.yml.');

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
                $proceedTask(
                    file_put_contents($configFile, $contents),
                    InstallerInterface::ENGINE_OK,
                    'Engine added to framework.templating.engines in config.yml',
                    'Unable to add the templating engine in framework.templating.engines in config.yml'
                );
            }
        } else {
            $io->write('framework entry not found in config.yml.');
        }
    }

    protected static function installSymfony3Bundle(IOInterface $io, $dir, $bundle, $proceedTask, &$flags)
    {
        $appFile = $dir . '/app/AppKernel.php';
        $contents = @file_get_contents($appFile) ?: '';

        if (preg_match('/^[ \\t]*new\\s+Symfony\\\\Bundle\\\\FrameworkBundle\\\\FrameworkBundle\\(\\)/m', $contents)) {
            if (strpos($contents, $bundle) === false) {
                $contents = preg_replace('/^([ \\t]*)new\\s+Symfony\\\\Bundle\\\\FrameworkBundle\\\\FrameworkBundle\\(\\)/m', "\$0,\n\$1$bundle", $contents);
                $proceedTask(
                    file_put_contents($appFile, $contents),
                    InstallerInterface::KERNEL_OK,
                    'Bundle added to AppKernel.php',
                    'Unable to add the bundle engine in AppKernel.php'
                );
            } else {
                $flags |= InstallerInterface::KERNEL_OK;
                $io->write('The bundle already exists in AppKernel.php');
            }
        } else {
            $io->write('Sorry, AppKernel.php has a format we can\'t handle automatically.');
        }
    }

    protected static function installInSymfony3(IOInterface $io, $baseDirectory, $dir)
    {
        $service = "\n    templating.engine.pug:\n" .
            "        public: true\n" .
            "        class: Pug\PugSymfonyEngine\n" .
            "        arguments: [\"@kernel\"]\n";

        $bundle = 'new Pug\PugSymfonyBundle\PugSymfonyBundle()';

        $flags = 0;
        $addConfig = static::askConfirmation($io, 'Would you like us to add automatically needed settings in your config.yml? [Y/N] ');
        $addBundle = static::askConfirmation($io, 'Would you like us to add automatically the pug bundle in your AppKernel.php? [Y/N] ');

        $proceedTask = function ($taskResult, $flag, $successMessage, $errorMessage) use (&$flags, $io) {
            static::proceedTask($flags, $io, $taskResult, $flag, $successMessage, $errorMessage);
        };

        if ($addConfig) {
            static::installSymfony3Config($io, $dir, $service, $proceedTask, $flags);
        } else {
            $flags |= InstallerInterface::CONFIG_OK | InstallerInterface::ENGINE_OK;
        }

        if ($addBundle) {
            static::installSymfony3Bundle($io, $dir, $bundle, $proceedTask, $flags);
        } else {
            $flags |= InstallerInterface::KERNEL_OK;
        }

        if (($flags & InstallerInterface::KERNEL_OK) && ($flags & InstallerInterface::CONFIG_OK) && ($flags & InstallerInterface::ENGINE_OK)) {
            touch($baseDirectory . '/installed');
        }

        return true;
    }

    public static function proceedTask(&$flags, $io, $taskResult, $flag, $successMessage, $message)
    {
        if ($taskResult) {
            $flags |= $flag;
            $message = $successMessage;
        }

        if ($io instanceof IOInterface) {
            $io->write($message);
        }
    }

    public static function install($event, $dir = null)
    {
        /** @var \Composer\Script\Event $event */
        $io = $event->getIO();
        $baseDirectory = __DIR__ . '/../../../..';

        if (!$io->isInteractive() || file_exists($baseDirectory . '/installed')) {
            return true;
        }

        $dir = is_string($dir) && is_dir($dir)
            ? $dir
            : $baseDirectory . '/../../..';

        if (!file_exists($dir . '/composer.json')) {
            $io->write('Not inside a composer vendor directory, setup skipped.');

            return true;
        }

        return file_exists($dir . '/config/packages/framework.yaml')
            ? static::installInSymfony4($event, $dir)
            : static::installInSymfony3($io, $baseDirectory, $dir);
    }
}

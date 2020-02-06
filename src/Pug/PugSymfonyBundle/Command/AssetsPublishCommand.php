<?php

namespace Pug\PugSymfonyBundle\Command;

use Phug\Renderer;
use Pug\PugSymfonyEngine;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class AssetsPublishCommand extends Command
{
    protected $pugSymfonyEngine;

    public function __construct(PugSymfonyEngine $pugSymfonyEngine)
    {
        $this->pugSymfonyEngine = $pugSymfonyEngine;
        parent::__construct(null);
    }

    protected function configure()
    {
        $this
            ->setName('assets:publish')
            ->setDescription('Export your assets in the web directory.');
    }

    protected function cacheTemplates(Renderer $pug)
    {
        $success = 0;
        $errors = 0;
        $errorDetails = [];
        $directories = [];

        foreach ($pug->getOption('viewDirectories') as $viewDirectory) {
            if (is_dir($viewDirectory) && !in_array($viewDirectory, $directories)) {
                $directories[] = $viewDirectory;
                $data = $pug->cacheDirectory($viewDirectory);
                $success += $data[0];
                $errors += $data[1];
                $errorDetails = array_merge($errorDetails, isset($data[2]) && $data[2] ? $data[2] : []);
            }
        }

        return [$directories, $success, $errors, $errorDetails];
    }

    protected function outputError(OutputInterface $output, Throwable $error): void
    {
        $output->writeln($error->getMessage());
        $output->writeln($error->getTraceAsString());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$directories, $success, $errors, $errorDetails] = $this->cacheTemplates($this->pugSymfonyEngine->getRenderer());
        $count = count($directories);
        $output->writeln($count.' '.($count === 1 ? 'directory' : 'directories').' scanned: '.implode(', ', $directories).'.');
        $output->writeln($success.' templates cached.');
        $output->writeln($errors.' templates failed to be cached.');

        foreach ($errorDetails as $index => $detail) {
            $output->writeln("\n".($index + 1).') '.$detail['inputFile']);
            $this->outputError($output, $detail['error']);
        }

        return 0;
    }
}

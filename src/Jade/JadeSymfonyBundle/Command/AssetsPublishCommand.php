<?php

namespace Jade\JadeSymfonyBundle\Command;

use Jade\Jade;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AssetsPublishCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('assets:publish')
            ->setDescription('Export your assets in the web directory.');
    }

    protected function cacheTemplates($pug)
    {
        if (!($pug instanceof \Jade\Jade) && !($pug instanceof \Pug\Pug) && !($pug instanceof \Phug\Renderer)) {
            throw new \InvalidArgumentException(
                'Allowed pug engine are Jade\\Jade, Pug\\Pug or Phug\\Renderer, ' . get_class($pug) . ' given.'
            );
        }

        $success = 0;
        $errors = 0;
        $errorDetails = [];
        $directories = [];
        foreach ($pug->getOption('viewDirectories') as $viewDirectory) {
            if (is_dir($viewDirectory)) {
                $directories[] = $viewDirectory;
                $data = $pug->cacheDirectory($viewDirectory);
                $success += $data[0];
                $errors += $data[1];
                $errorDetails = array_merge($errorDetails, isset($data[2]) && $data[2] ? $data[2] : []);
            }
        }

        return [$directories, $success, $errors, $errorDetails];
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($directories, $success, $errors, $errorDetails) = $this->cacheTemplates(
            $this->getContainer()->get('templating.engine.pug')->getEngine()
        );
        $count = count($directories);
        $output->writeln($count . ' ' . ($count === 1 ? 'directory' : 'directories') . ' scanned: ' . implode(', ', $directories) . '.');
        $output->writeln($success . ' templates cached.');
        $output->writeln($errors . ' templates failed to be cached.');
        foreach ($errorDetails as $index => $detail) {
            $output->writeln("\n" . ($index + 1) . ') ' . $detail['inputFile']);
            $output->writeln($detail['error']->getMessage());
            $output->writeln($detail['error']->getTraceAsString());
        }
    }
}

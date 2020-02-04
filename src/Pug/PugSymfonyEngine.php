<?php

namespace Pug;

use ArrayAccess;
use ErrorException;
use Exception;
use Phug\Compiler\Event\NodeEvent;
use Phug\Component\ComponentExtension;
use Phug\Parser\Node\FilterNode;
use Phug\Parser\Node\ImportNode;
use Phug\Parser\Node\TextNode;
use Pug\Symfony\Contracts\InstallerInterface;
use Pug\Symfony\Traits\Filters;
use Pug\Symfony\Traits\HelpersHandler;
use Pug\Symfony\Traits\Installer;
use Pug\Symfony\Traits\Options;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Templating\EngineInterface;

class PugSymfonyEngine implements EngineInterface, InstallerInterface, ArrayAccess
{
    use Installer;
    use HelpersHandler;
    use Filters;
    use Options;

    /**
     * @var Assets
     */
    protected $assets;

    /**
     * @var ComponentExtension
     */
    protected $componentExtension;

    /**
     * @var string
     */
    protected $defaultTemplateDirectory;

    /**
     * @var string
     */
    protected $fallbackTemplateDirectory;

    public function __construct(KernelInterface $kernel)
    {
        $container = $kernel->getContainer();

        if (!$container->has('twig')) {
            throw new RuntimeException('Twig needs to be configured.');
        }

        $this->kernel = $kernel;
        $this->container = $container;
        $this->userOptions = ($this->container->hasParameter('pug') ? $this->container->getParameter('pug') : null) ?: [];
        $this->shareServices();
        $this->enhanceTwig();
        $this->onNode([$this, 'handleTwigInclude']);
    }

    public function handleTwigInclude(NodeEvent $nodeEvent): void
    {
        $node = $nodeEvent->getNode();

        if ($node instanceof ImportNode && $node->getName() === 'include') {
            $code = new TextNode($node->getToken());
            $path = var_export($node->getPath(), true);
            $location = $node->getSourceLocation();
            $line = $location->getLine();
            $template = var_export($location->getPath(), true);
            $code->setValue('$this->loadTemplate('.$path.', '.$template.', '.$line.')->display($context);');
            $filter = new FilterNode($node->getToken());
            $filter->setName('php');
            $filter->appendChild($code);
            $nodeEvent->setNode($filter);
        }
    }

    protected function crawlDirectories(string $srcDir, array &$assetsDirectories, array &$viewDirectories): ?string
    {
        $baseDir = isset($viewDirectories[0]) && file_exists($viewDirectories[0])
            ? $viewDirectories[0]
            : $this->fallbackTemplateDirectory;

        if (file_exists($srcDir)) {
            foreach (scandir($srcDir) as $directory) {
                if ($directory === '.' || $directory === '..' || is_file($srcDir.'/'.$directory)) {
                    continue;
                }

                if (is_dir($viewDirectory = $srcDir.'/'.$directory.'/Resources/views')) {
                    if (is_null($baseDir)) {
                        $baseDir = $viewDirectory;
                    }

                    $viewDirectories[] = $srcDir.'/'.$directory.'/Resources/views';
                }

                $assetsDirectories[] = $srcDir.'/'.$directory.'/Resources/assets';
            }
        }

        return $baseDir ?: $this->defaultTemplateDirectory;
    }

    protected function getFileFromName(string $name, string $directory = null): string
    {
        $parts = explode(':', strval($name));

        if (count($parts) > 1) {
            $name = $parts[2];

            if (!empty($parts[1])) {
                $name = $parts[1].DIRECTORY_SEPARATOR.$name;
            }

            if ($bundle = $this->kernel->getBundle($parts[0])) {
                return $bundle->getPath().
                    DIRECTORY_SEPARATOR.'Resources'.
                    DIRECTORY_SEPARATOR.'views'.
                    DIRECTORY_SEPARATOR.$name;
            }
        }

        return ($directory ? $directory.DIRECTORY_SEPARATOR : '').$name;
    }

    /**
     * Share variables (local templates parameters) with all future templates rendered.
     *
     * @example $pug->share('lang', 'fr')
     * @example $pug->share(['title' => 'My blog', 'today' => new DateTime()])
     *
     * @param array|string $variables a variables name-value pairs or a single variable name
     * @param mixed        $value     the variable value if the first argument given is a string
     *
     * @return $this
     */
    public function share($variables, $value = null): self
    {
        $this->getRenderer()->share($variables, $value);

        return $this;
    }

    /**
     * Get the Pug cache directory path.
     */
    public function getCacheDir(): string
    {
        return $this->kernel->getCacheDir().DIRECTORY_SEPARATOR.'pug';
    }

    /**
     * Return an array of the global variables all views receive.
     *
     * @return array
     */
    public function getGlobalVariables(): array
    {
        return [
            $this->getOptionDefault('shared_variables'),
            [
                'view' => $this,
                'app'  => $this->kernel,
            ],
        ];
    }

    /**
     * Prepare and group input and global parameters.
     *
     * @param array $parameters
     *
     * @throws ErrorException when a forbidden parameter key is used
     *
     * @return array input parameters with global parameters
     */
    public function getParameters(array $parameters = []): array
    {
        foreach (['view', 'this', 'app'] as $forbiddenKey) {
            if (array_key_exists($forbiddenKey, $parameters)) {
                throw new ErrorException('The "'.$forbiddenKey.'" key is forbidden.');
            }
        }

        foreach ($this->getGlobalVariables() as $sharedVariables) {
            if ($sharedVariables) {
                $parameters = array_merge($sharedVariables, $parameters);
            }
        }

        $parameters['this'] = $this->getTwig();

        return $parameters;
    }

    /**
     * Render a template by name.
     *
     * @param string|\Symfony\Component\Templating\TemplateReferenceInterface $name
     * @param array                                                           $parameters
     *
     * @throws ErrorException when a forbidden parameter key is used
     * @throws Exception      when the PHP code generated from the pug code throw an exception
     *
     * @return string
     */
    public function render($name, array $parameters = []): string
    {
        return $this->getRenderer()->renderFile(
            $this->getFileFromName($name),
            $this->getParameters($parameters)
        );
    }

    /**
     * Render a template string.
     *
     * @param string|\Symfony\Component\Templating\TemplateReferenceInterface $name
     * @param array                                                           $parameters
     *
     * @throws ErrorException when a forbidden parameter key is used
     *
     * @return string
     */
    public function renderString($code, array $parameters = []): string
    {
        $pug = $this->getRenderer();
        $method = method_exists($pug, 'renderString') ? 'renderString' : 'render';

        return $pug->$method(
            $code,
            $this->getParameters($parameters)
        );
    }

    /**
     * Check if a template exists.
     *
     * @param string|\Symfony\Component\Templating\TemplateReferenceInterface $name
     *
     * @return bool
     */
    public function exists($name): bool
    {
        foreach ($this->getOptionDefault('paths', []) as $directory) {
            if (file_exists($directory.DIRECTORY_SEPARATOR.$name)) {
                return true;
            }
        }

        return file_exists($this->getFileFromName($name, $this->defaultTemplateDirectory));
    }

    /**
     * Check if a file extension is supported by Pug.
     *
     * @param string|\Symfony\Component\Templating\TemplateReferenceInterface $name
     *
     * @return bool
     */
    public function supports($name): bool
    {
        foreach ($this->getOptionDefault('extensions', []) as $extension) {
            if (substr($name, -strlen($extension)) === $extension) {
                return true;
            }
        }

        return false;
    }

    protected static function extractUniquePaths(array $paths): array
    {
        return array_unique(array_map(function ($path) {
            return realpath($path) ?: $path;
        }, $paths));
    }
}

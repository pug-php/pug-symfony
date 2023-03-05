<?php

declare(strict_types=1);

namespace Pug;

use ErrorException;
use Exception;
use Phug\Compiler\Event\NodeEvent;
use Phug\Component\ComponentExtension;
use Phug\Parser\Node\FilterNode;
use Phug\Parser\Node\ImportNode;
use Phug\Parser\Node\TextNode;
use Pug\Exceptions\ReservedVariable;
use Pug\Symfony\Contracts\InstallerInterface;
use Pug\Symfony\Contracts\InterceptorInterface;
use Pug\Symfony\RenderEvent;
use Pug\Symfony\Traits\Filters;
use Pug\Symfony\Traits\HelpersHandler;
use Pug\Symfony\Traits\Installer;
use Pug\Symfony\Traits\Options;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;
use Twig\Environment as TwigEnvironment;

class PugSymfonyEngine implements EngineInterface, InstallerInterface
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

    public function __construct(
        protected readonly KernelInterface $kernel,
        TwigEnvironment $twig,
        private readonly ?RequestStack $stack = null,
        private readonly ?RequestContext $context = null,
    ) {
        $container = $kernel->getContainer();
        $this->container = $container;
        $this->userOptions = ($this->container->hasParameter('pug') ? $this->container->getParameter('pug') : null) ?: [];
        $this->enhanceTwig($twig);
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
        $baseDir = file_exists($viewDirectories[0]) ? $viewDirectories[0] : null;

        if (file_exists($srcDir)) {
            foreach (scandir($srcDir) as $directory) {
                if ($directory === '.' || $directory === '..' || is_file($srcDir.'/'.$directory)) {
                    continue;
                }

                $viewDirectory = $srcDir.'/'.$directory.'/Resources/views';

                if (is_dir($viewDirectory)) {
                    $baseDir ??= $viewDirectory;

                    $viewDirectories[] = $srcDir.'/'.$directory.'/Resources/views';
                }

                $assetsDirectories[] = $srcDir.'/'.$directory.'/Resources/assets';
            }
        }

        return $baseDir ?: $this->defaultTemplateDirectory;
    }

    protected function getFileFromName(string $name, string $directory = null): string
    {
        $parts = explode(':', $name);

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
        if (func_num_args() === 2) {
            $variables = [
                $variables => $value,
            ];
        }

        $variables = array_merge($this->getOptionDefault('shared_variables', []), $variables);

        $this->setOption('shared_variables', $variables);

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
     * Prepare and group input and global parameters.
     *
     * @param array $locals
     *
     * @throws ErrorException when a forbidden parameter key is used
     *
     * @return array input parameters with global parameters
     */
    public function getParameters(array $locals = []): array
    {
        $locals = array_merge(
            $this->getOptionDefault('globals', []),
            $this->getOptionDefault('shared_variables', []),
            $locals,
        );

        foreach (['context', 'blocks', 'macros', 'this'] as $forbiddenKey) {
            if (array_key_exists($forbiddenKey, $locals)) {
                throw new ReservedVariable($forbiddenKey);
            }
        }

        $locals['this'] = $this->getTwig();

        return $locals;
    }

    /**
     * Render a template by name.
     *
     * @param string|TemplateReferenceInterface $name
     * @param array                             $parameters
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
            $this->getParameters($parameters),
        );
    }

    /**
     * Render a template string.
     *
     * @param string|TemplateReferenceInterface $name
     * @param array                             $locals
     *
     * @throws ErrorException when a forbidden parameter key is used
     *
     * @return string
     */
    public function renderString($code, array $locals = []): string
    {
        return $this->getRenderer()->renderString(
            $code,
            $this->getParameters($locals),
        );
    }

    /**
     * Check if a template exists.
     *
     * @param string|TemplateReferenceInterface $name
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
     * @param string|TemplateReferenceInterface $name
     *
     * @return bool
     */
    public function supports($name): bool
    {
        foreach ($this->getOptionDefault('extensions', ['.pug', '.jade']) as $extension) {
            if ($extension && str_ends_with($name, $extension)) {
                return true;
            }
        }

        return false;
    }

    public function getRenderArguments(string $name, array $locals): array
    {
        $event = new RenderEvent($name, $locals, $this);
        $container = $this->container;

        if ($container->has('event_dispatcher')) {
            /** @var EventDispatcher $dispatcher */
            $dispatcher = $container->get('event_dispatcher');
            $dispatcher->dispatch($event, RenderEvent::NAME);

            $interceptors = array_map(
                static fn (string $interceptorClass) => $container->get($interceptorClass),
                $this->userOptions['interceptors'] ?? [],
            );

            array_walk($interceptors, static function (InterceptorInterface $interceptor) use ($event) {
                $interceptor->intercept($event);
            });
        }

        return [$event->getName(), $this->getParameters($event->getLocals())];
    }

    public function renderResponse(
        string|TemplateReferenceInterface $view,
        array $parameters = [],
        ?Response $response = null,
    ): Response {
        $content = $this->render($view, $parameters);
        $response ??= new Response();

        if ($response->getStatusCode() === 200) {
            foreach ($parameters as $v) {
                if ($v instanceof FormInterface && $v->isSubmitted() && !$v->isValid()) {
                    $response->setStatusCode(422);

                    break;
                }
            }
        }

        $response->setContent($content);

        return $response;
    }

    protected static function extractUniquePaths(array $paths): array
    {
        return array_unique(array_map(static fn ($path) => realpath($path) ?: $path, $paths));
    }
}

<?php

namespace Pug\Tests;

use Pug\PugSymfonyEngine;
use Pug\Twig\Environment;
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bridge\Twig\Extension\CsrfRuntime;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\LogoutUrlExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RequestContext;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

abstract class AbstractTestCase extends KernelTestCase
{
    protected static array $originalFiles = [];

    protected static string $cachePath = __DIR__.'/../project-s5/var/cache/test';

    protected ?Environment $twig = null;

    protected ?PugSymfonyEngine $pugSymfony = null;

    protected function getTwigEnvironment(): Environment
    {
        if (!isset($this->twig)) {
            $this->twig = new Environment(new FilesystemLoader(
                __DIR__.'/../project-s5/templates/',
            ));
            $this->twig->addExtension(new CsrfExtension());
            $this->twig->addExtension(new FormExtension());
            $this->twig->addExtension(new LogoutUrlExtension(new LogoutUrlGenerator()));
            $this->twig->addExtension(new TranslationExtension());
            $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([
                CsrfRuntime::class => static fn () => new CsrfRuntime(new TestCsrfTokenManager()),
            ]));
        }

        return $this->twig;
    }

    protected function getPugSymfonyEngine(?KernelInterface $kernel = null): PugSymfonyEngine
    {
        $twig = $this->getTwigEnvironment();
        $this->pugSymfony ??= new PugSymfonyEngine(
            $kernel ?? self::$kernel,
            $twig,
            new RequestStack(),
            new RequestContext(),
        );
        $twig->setPugSymfonyEngine($this->pugSymfony);

        return $this->pugSymfony;
    }

    private static function getConfigFiles(): array
    {
        return [
            __DIR__.'/../project-s5/config/packages/framework.yaml',
        ];
    }

    protected static function clearCache(): void
    {
        try {
            (new Filesystem())->remove(static::$cachePath);
        } catch (\Exception $e) {
            // noop
        }
    }

    public static function setUpBeforeClass(): void
    {
        foreach (self::getConfigFiles() as $file) {
            $contents = file_get_contents($file);

            if (!isset(static::$originalFiles[$file])) {
                static::$originalFiles[$file] = $contents;
            }

            file_put_contents($file, strtr($contents, [
                '%kernel.root_dir%'                            => '%kernel.project_dir%',
                "templating: { engines: ['pug', 'php'] }"      => '',
                "templating:\n        engines: ['pug', 'php']" => '',
            ]));
        }

        self::clearCache();
    }

    public static function tearDownAfterClass(): void
    {
        self::clearCache();

        foreach (self::getConfigFiles() as $file) {
            if (isset(static::$originalFiles[$file])) {
                file_put_contents($file, static::$originalFiles[$file]);
            }
        }
    }

    public function setUp(): void
    {
        try {
            (new Filesystem())->mkdir(static::$cachePath);
        } catch (\Exception $e) {
            // noop
        }

        chdir(__DIR__.'/../..');

        self::bootKernel();

        $this->addFormRenderer();
    }

    protected function addFormRenderer()
    {
        require_once __DIR__.'/TestCsrfTokenManager.php';

        $twig = $this->getTwigEnvironment();
        $csrfManager = new TestCsrfTokenManager();
        $formEngine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);

        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn () => new FormRenderer($formEngine, $csrfManager),
        ]));
    }
}

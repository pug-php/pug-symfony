<?php

namespace Pug\Tests;

use AppKernel;
use Closure;
use Composer\Composer;
use Composer\Script\Event;
use DateTime;
use InvalidArgumentException;
use Jade\Symfony\Css;
use Jade\Symfony\MixedLoader;
use Pug\Filter\AbstractFilter;
use Pug\Pug;
use Pug\PugSymfonyEngine;
use ReflectionProperty;
use Symfony\Bridge\Twig\Extension\LogoutUrlExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\FakeAssetsHelper;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage as BaseTokenStorage;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator as BaseLogoutUrlGenerator;
use Twig\Loader\ArrayLoader;

class TokenStorage extends BaseTokenStorage
{
    public function __construct()
    {
    }

    public function getToken()
    {
        return 'the token';
    }
}

class CustomHelper
{
    public function foo()
    {
        return 'bar';
    }
}

class Upper extends AbstractFilter
{
    public function parse($code)
    {
        return strtoupper($code);
    }
}

class LogoutUrlGenerator extends BaseLogoutUrlGenerator
{
    public function getLogoutUrl($key = null)
    {
        return 'logout-url';
    }

    public function getLogoutPath($key = null)
    {
        return 'logout-path';
    }
}

class TestKernel extends AppKernel
{
    /**
     * @var Closure
     */
    private $containerConfigurator;

    public function __construct(Closure $containerConfigurator, $environment = 'test', $debug = false)
    {
        $this->containerConfigurator = $containerConfigurator;

        parent::__construct($environment, $debug);

        $this->rootDir = $this->getRootDir();
    }

    public function getProjectDir()
    {
    }

    public function getLogDir()
    {
        return sys_get_temp_dir() . '/pug-symfony-log';
    }

    public function getRootDir()
    {
        return realpath(__DIR__ . '/../project/app');
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        parent::registerContainerConfiguration($loader);
        $loader->load(__DIR__ . '/../project/app/config/config.yml');
        $loader->load($this->containerConfigurator);
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir() . '/pug-symfony-cache';
    }

    /**
     * Override the parent method to force recompiling the container.
     * For performance reasons the container is also not dumped to disk.
     */
    protected function initializeContainer()
    {
        $this->container = $this->buildContainer();
        $this->container->compile();
        $this->container->set('kernel', $this);
    }
}

class TestFormBuilder extends FormBuilder
{
    public function getCompound()
    {
        return true;
    }
}

class Task
{
    protected $name;
    protected $dueDate;

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getDueDate()
    {
        return $this->dueDate;
    }

    public function setDueDate(DateTime $dueDate = null)
    {
        $this->dueDate = $dueDate;
    }
}

if (!class_exists('Symfony\Bundle\FrameworkBundle\Controller\AbstractController')) {
    include __DIR__ . '/AbstractController.php';
}

class TestController extends AbstractController
{
    public function index()
    {
        try {
            return $this->createFormBuilder(new Task())
                ->add('name', 'Symfony\Component\Form\Extension\Core\Type\TextType')
                ->add('dueDate', 'Symfony\Component\Form\Extension\Core\Type\DateType')
                ->add('save', 'Symfony\Component\Form\Extension\Core\Type\SubmitType', ['label' => 'Foo'])
                ->getForm();
        } catch (InvalidArgumentException $e) {
            return $this->createFormBuilder(new Task())
                ->add('name', 'text')
                ->add('dueDate', 'date')
                ->add('save', 'submit', ['label' => 'Foo'])
                ->getForm();
        }
    }
}

class InvalidExceptionOptionsPug extends Pug
{
    public function getOption($name)
    {
        if ($name === 'foobar') {
            throw new InvalidArgumentException('foobar not found');
        }

        return parent::getOption($name);
    }
}

class InvalidExceptionOptionsPugSymfony extends PugSymfonyEngine
{
    public function getEngineClassName()
    {
        return '\Pug\Tests\InvalidExceptionOptionsPug';
    }
}

class PugSymfonyEngineTest extends AbstractTestCase
{
    public function testPreRenderPhp()
    {
        $kernel = new TestKernel(function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'php',
            ]);
        });
        $kernel->boot();
        $pugSymfony = new PugSymfonyEngine($kernel);

        self::assertSame('<p>/foo</p>', $pugSymfony->renderString('p=asset("/foo")'));
        self::assertSame(
            '<html><head><title>My Site</title></head><body><p>/foo</p><footer><p></p>Some footer text</footer></body></html>',
            $pugSymfony->render('asset.pug')
        );
    }

    public function testPreRenderJs()
    {
        $kernel = new TestKernel(function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'js',
            ]);
        });
        $kernel->boot();
        $pugSymfony = new PugSymfonyEngine($kernel);

        self::assertSame('<p>/foo</p>', $pugSymfony->renderString('p=asset("/foo")'));
    }

    public function testPreRenderFile()
    {
        $kernel = new TestKernel(function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'js',
            ]);
        });
        $kernel->boot();
        $pugSymfony = new PugSymfonyEngine($kernel);

        self::assertSame(implode('', [
            '<html>',
            '<head><title>My Site</title></head>',
            '<body><h1>Welcome Bob</h1><p>42</p><footer><p></p>Some footer text</footer></body>',
            '</html>',
        ]), $pugSymfony->render('layout/welcome.pug', [
            'name' => 'Bob',
        ]));
    }

    public function testPreRenderCsrfToken()
    {
        $kernel = new TestKernel(function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'js',
            ]);
        });
        $kernel->boot();
        $pugSymfony = new PugSymfonyEngine($kernel);

        self::assertSame('<p>Hello</p>', $pugSymfony->renderString('p Hello'));

        self::assertRegExp('/<p>[a-zA-Z0-9_-]{10,}<\/p>/', $pugSymfony->renderString('p=csrf_token("authentificate")'));
    }

    public function testGetEngine()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertRegExp('/^\\\\?Jade\\\\Symfony\\\\(Jade|Pug)Engine$/', get_class($pugSymfony->getEngine()));
    }

    public function testFallbackAppDir()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $baseDir = realpath($pugSymfony->getOption('baseDir'));
        $appView = __DIR__ . '/../project/app/Resources/views';
        $srcView = __DIR__ . '/../project/src/TestBundle/Resources/views';

        self::assertTrue($baseDir !== false);
        self::assertSame(realpath($srcView), $baseDir);

        rename($srcView, $srcView . '.save');
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $baseDir = realpath($pugSymfony->getOption('baseDir'));
        rename($srcView . '.save', $srcView);

        self::assertTrue($baseDir !== false);
        self::assertSame(realpath($appView), $baseDir);
    }

    public function testSecurityToken()
    {
        if (version_compare(getenv('SYMFONY_VERSION'), '3.2') < 0) {
            self::markTestSkipped('security.token_storage compatible since 3.3.');

            return;
        }

        $tokenStorage = new TokenStorage();
        $container = self::$kernel->getContainer();
        $reflectionProperty = new ReflectionProperty($container, 'services');
        $reflectionProperty->setAccessible(true);
        $services = $reflectionProperty->getValue($container);
        $services['security.token_storage'] = $tokenStorage;
        $reflectionProperty->setValue($container, $services);
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame('<p>the token</p>', trim($pugSymfony->render('token.pug')));
    }

    /**
     * @throws \ErrorException
     * @throws \ReflectionException
     */
    public function testLogoutHelper()
    {
        $generator = new LogoutUrlGenerator();
        /* @var \Twig_Environment $twig */
        $twig = self::$kernel->getContainer()->get('twig');

        foreach ($twig->getExtensions() as $extension) {
            if ($extension instanceof LogoutUrlExtension) {
                $reflectionClass = new \ReflectionClass('Symfony\Bridge\Twig\Extension\LogoutUrlExtension');
                $reflectionProperty = $reflectionClass->getProperty('generator');
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($extension, $generator);
                $generator = null;
            }
        }

        if ($generator) {
            include_once __DIR__ . '/LogoutUrlHelper.php';
            $logoutUrlHelper = new LogoutUrlHelper($generator);
            self::$kernel->getContainer()->set('templating.helper.logout_url', $logoutUrlHelper);
        }

        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame('<a href="logout-url"></a><a href="logout-path"></a>', trim($pugSymfony->render('logout.pug')));
    }

    /**
     * @throws \ErrorException
     */
    public function testFormHelpers()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $controller = new TestController();
        $controller->setContainer(self::$kernel->getContainer());

        self::assertRegExp('/^' . implode('', [
            '<form name="form" method="get"( action="")?\s*>\s*',
            '<div\s*>\s*<label for="form_name" class="required"\s*>Name<\/label>\s*',
            '<input type="text" id="form_name" name="form\[name\]" required="required"\s*\/>\s*<\/div>\s*',
            '<div\s*>\s*<label class="required"\s*>Due date<\/label>\s*<div id="form_dueDate"\s*>\s*(',
            '<select id="form_dueDate_day" name="form\[dueDate\]\[day\]"\s*>\s*(<option value="\d+"\s*>\d+<\/option>\s*)+<\/select>\s*|',
            '<select id="form_dueDate_month" name="form\[dueDate\]\[month\]"\s*>\s*(<option value="\d+"\s*>[^<]+<\/option>\s*)+<\/select>\s*|',
            '<select id="form_dueDate_year" name="form\[dueDate\]\[year\]"\s*>\s*(<option value="\d+"\s*>\d+<\/option>\s*)+<\/select>\s*){3}',
            '<\/div>\s*<\/div>\s*<div\s*>\s*<button type="submit" id="form_save" name="form\[save\]"\s*>Submit me<\/button>\s*<\/div>\s*',
            '<input type="hidden" id="form__token" name="form\[_token\]" value="[^"]+"\s*\/>\s*<\/form>',
        ]) . '$/', trim($pugSymfony->renderString(implode("\n", [
            '!=form_start(form, {method: "GET"})',
            '!=form_errors(form)',
            '!=form_row(form.name)',
            '!=form_widget(form.name, {attr: {class: "foo"}})',
            '!=form_row(form.dueDate)',
            '!=form_row(form.save, {label: "Submit me"})',
            '!=form_end(form)',
        ]), [
            'form' => $controller->index()->createView(),
        ])));
    }

    public function testCustomHelper()
    {
        self::clearCache();
        $helper = new CustomHelper();
        $kernel = new TestKernel(function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'php',
            ]);
        });
        $kernel->boot();
        $pugSymfony = new PugSymfonyEngine($kernel, $helper);

        self::assertTrue(isset($pugSymfony['custom']));
        self::assertSame($helper, $pugSymfony['custom']);

        self::assertSame('<u>bar</u>', trim($pugSymfony->render('custom-helper.pug')));

        unset($pugSymfony['custom']);
        self::assertFalse(isset($pugSymfony['custom']));

        self::assertSame('<s>Noop</s>', trim($pugSymfony->render('custom-helper.pug')));

        $pugSymfony['custom'] = $helper;
        self::assertTrue(isset($pugSymfony['custom']));
        self::assertSame($helper, $pugSymfony['custom']);

        self::assertSame('<u>bar</u>', trim($pugSymfony->render('custom-helper.pug')));
    }

    public function testOptions()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        $message = method_exists($pugSymfony->getEngine(), 'hasOption') && $pugSymfony->getOption('foo') === null
            ? 'foo is not a valid option name.'
            : null;
        if ($message === null) {
            try {
                $pugSymfony->getOption('foo');
            } catch (InvalidArgumentException $e) {
                $message = $e->getMessage();
            }
        }

        self::assertSame('foo is not a valid option name.', $message);

        $pugSymfony->setCustomOptions(['foo' => 'bar']);
        self::assertSame('bar', $pugSymfony->getOption('foo'));
    }

    public function testBundleView()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame('<p>Hello</p>', trim($pugSymfony->render('TestBundle::bundle.pug', ['text' => 'Hello'])));
        self::assertSame('<section>World</section>', trim($pugSymfony->render('TestBundle:directory:file.pug')));
    }

    public function testAssetHelperPhp()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOption('expressionLanguage', 'php');

        self::assertSame(
            '<div style="' .
                'background-position: 50% -402px; ' .
                'background-image: url(\'/assets/img/patterns/5.png\');' .
                '" class="foo"></div>' . "\n" .
            '<div style="' .
                'background-position:50% -402px;' .
                'background-image:url(\'/assets/img/patterns/5.png\')' .
                '" class="foo"></div>',
            preg_replace(
                '/<div( class="[^"]+")(.+?)></',
                '<div$2$1><',
                str_replace(['\'assets/', "\r"], ['\'/assets/', ''], trim($pugSymfony->render('style-php.pug')))
            )
        );
    }

    public function testAssetHelperJs()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOption('expressionLanguage', 'js');

        self::assertSame(
            '<div style="' .
                'background-position: 50% -402px; ' .
                'background-image: url(\'/assets/img/patterns/5.png\');' .
                '" class="foo"></div>' . "\n" .
            '<div style="' .
                'background-position:50% -402px;' .
                'background-image:url(\'/assets/img/patterns/5.png\')' .
                '" class="foo"></div>',
            preg_replace(
                '/<div( class="[^"]+")(.+?)></',
                '<div$2$1><',
                str_replace(['\'assets/', "\r"], ['\'/assets/', ''], trim($pugSymfony->render('style-js.pug')))
            )
        );
    }

    public function testFilter()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertFalse($pugSymfony->hasFilter('upper'));

        $pugSymfony->filter('upper', '\\Pug\\Tests\\Upper');
        self::assertTrue($pugSymfony->hasFilter('upper'));
        $filter = $pugSymfony->getFilter('upper');
        if (!is_string($filter)) {
            $filter = get_class($filter);
        }
        self::assertSame('Pug\\Tests\\Upper', ltrim($filter, '\\'));
        self::assertSame('FOO', trim($pugSymfony->render('filter.pug')));
    }

    public function testExists()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertTrue($pugSymfony->exists('logout.pug'));
        self::assertFalse($pugSymfony->exists('login.pug'));
    }

    public function testSupports()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertTrue($pugSymfony->supports('foo-bar.pug'));
        self::assertTrue($pugSymfony->supports('foo-bar.jade'));
        self::assertFalse($pugSymfony->supports('foo-bar.twig'));
        self::assertFalse($pugSymfony->supports('foo-bar'));
    }

    public function testCustomOptions()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOptions([
            'prettyprint' => '    ',
            'cache'       => null,
        ]);

        $pugSymfony->setOption('indentSize', 3);
        $pugSymfony->setOption('prettyprint', '   ');

        self::assertSame(3, $pugSymfony->getOption('indentSize'));
        self::assertSame(
            "<div>\n   <p></p>\n</div>",
            str_replace("\r", '', trim($pugSymfony->render('p.pug')))
        );

        $pugSymfony->setOptions(['indentSize' => 5, 'prettyprint' => '     ']);

        self::assertSame(5, $pugSymfony->getOption('indentSize'));
        self::assertSame(5, $pugSymfony->getEngine()->getOption('indentSize'));
        self::assertSame(
            "<div>\n     <p></p>\n</div>",
            str_replace("\r", '', trim($pugSymfony->render('p.pug')))
        );
    }

    /**
     * @expectedException        \ErrorException
     * @expectedExceptionMessage The "this" key is forbidden.
     */
    public function testForbidThis()
    {
        (new PugSymfonyEngine(self::$kernel))->render('p.pug', ['this' => 42]);
    }

    /**
     * @expectedException        \ErrorException
     * @expectedExceptionMessage The "view" key is forbidden.
     */
    public function testForbidView()
    {
        (new PugSymfonyEngine(self::$kernel))->render('p.pug', ['view' => 42]);
    }

    public function testIssue11BackgroundImage()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOption('expressionLanguage', 'js');
        $html = trim($pugSymfony->render('background-image.pug', ['image' => 'foo']));
        $html = preg_replace('/<div( class="[^"]+")([^>]+)>/', '<div$2$1>', $html);

        self::assertSame('<div style="background-image: url(foo);" class="slide"></div>', $html);
    }

    /**
     * @group install
     */
    public function testInstall()
    {
        include_once __DIR__ . '/CaptureIO.php';
        $io = new CaptureIO();
        $composer = new Composer();
        $installedFile = __DIR__ . '/../../installed';
        $fs = new Filesystem();
        touch($installedFile);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), __DIR__ . '/../project'));

        $fs->remove($installedFile);
        $io->setInteractive(true);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), __DIR__ . '/../project'));
        self::assertFileExists($installedFile);

        $fs->remove($installedFile);
        $io->setPermissive(true);
        $io->reset();
        $dir = sys_get_temp_dir() . '/pug-temp';
        $fs->remove($dir);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'Not inside a composer vendor directory, setup skipped.',
        ], $io->getLastOutput());

        $io->reset();
        $fs->mkdir($dir);
        $fs->touch($dir . '/composer.json');

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'framework entry not found in config.yml.',
            'Sorry, AppKernel.php has a format we can\'t handle automatically.',
        ], $io->getLastOutput());
        clearstatcache();
        self::assertFileNotExists($installedFile);

        foreach (['/app/config/config.yml', '/app/AppKernel.php'] as $file) {
            $fs->copy(__DIR__ . '/../project' . $file, $dir . $file);
        }
        $io->reset();

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'templating.engine.pug setting in config.yml already exists.',
            'Pug engine already exist in framework.templating.engines in config.yml.',
            'The bundle already exists in AppKernel.php',
        ], $io->getLastOutput());
        clearstatcache();
        self::assertFileExists($installedFile);

        $fs->remove($installedFile);
        file_put_contents($dir . '/app/config/config.yml', str_replace(
            ['pug', 'services:'],
            ['x', 'x:'],
            file_get_contents($dir . '/app/config/config.yml')
        ));
        file_put_contents($dir . '/app/AppKernel.php', str_replace(
            'Pug',
            'X',
            file_get_contents($dir . '/app/AppKernel.php')
        ));
        $io->reset();

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'Engine service added in config.yml',
            'Engine added to framework.templating.engines in config.yml',
            'Bundle added to AppKernel.php',
        ], $io->getLastOutput());
        self::assertContains(
            'Pug\PugSymfonyBundle\PugSymfonyBundle()',
            file_get_contents($dir . '/app/AppKernel.php')
        );
        self::assertContains(
            'templating.engine.pug',
            file_get_contents($dir . '/app/config/config.yml')
        );
        clearstatcache();
        self::assertFileExists($installedFile);
    }

    /**
     * @group install
     */
    public function testInstallPartialStates()
    {
        include_once __DIR__ . '/CaptureIO.php';
        $io = new CaptureIO();
        $composer = new Composer();
        $installedFile = __DIR__ . '/../../installed';
        $io->setPermissive(true);
        $io->setInteractive(true);
        $io->reset();
        $dir = sys_get_temp_dir() . '/pug-temp';
        $fs = new Filesystem();
        $fs->mkdir($dir);
        $fs->touch($dir . '/composer.json');
        $fs->remove($installedFile);
        clearstatcache();

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        $fs->remove($installedFile);

        file_put_contents($dir . '/app/config/config.yml', implode("\n", [
            'foo:',
            '  bar: biz',
            'framework:',
            '  bar1: biz',
            '  templating:',
            '    bar2: biz',
            '  templating:',
            '    bar2: biz',
            '    engines: ["twig","php"]',
            '    bar3: biz',
            '  engines: ["twig","php"]',
            '  bar4: biz',
            'bar: biz',
        ]));
        PugSymfonyEngine::install(new Event('install', $composer, $io), $dir);
        self::assertSame(implode("\n", [
            'foo:',
            '  bar: biz',
            'services:',
            '    templating.engine.pug:',
            '        public: true',
            '        class: Pug\PugSymfonyEngine',
            '        arguments: ["@kernel"]',
            '',
            'framework:',
            '  bar1: biz',
            '  templating:',
            '    bar2: biz',
            '  templating:',
            '    bar2: biz',
            '    engines: ["pug","twig","php"]',
            '    bar3: biz',
            '  engines: ["twig","php"]',
            '  bar4: biz',
            'bar: biz',
        ]), file_get_contents($dir . '/app/config/config.yml'));
        self::assertFileExists($installedFile);
        $fs->remove($installedFile);

        file_put_contents($dir . '/app/config/config.yml', implode("\n", [
            'foo:',
            '  bar: biz',
            'framework:',
            '  bar1: biz',
            '  templating:',
            '    bar2: biz',
            '  templating:',
            '    bar2: biz',
            '    bar3: biz',
            'bar:',
            '  engines: ["twig","php"]',
            '  bar4: biz',
        ]));
        PugSymfonyEngine::install(new Event('install', $composer, $io), $dir);
        self::assertSame(implode("\n", [
            'foo:',
            '  bar: biz',
            'services:',
            '    templating.engine.pug:',
            '        public: true',
            '        class: Pug\PugSymfonyEngine',
            '        arguments: ["@kernel"]',
            '',
            'framework:',
            '  bar1: biz',
            '  templating:',
            '    bar2: biz',
            '  templating:',
            '    bar2: biz',
            '    bar3: biz',
            'bar:',
            '  engines: ["twig","php"]',
            '  bar4: biz',
        ]), file_get_contents($dir . '/app/config/config.yml'));
        self::assertFileNotExists($installedFile);
    }

    /**
     * @group install
     */
    public function testInstallSymfony4()
    {
        include_once __DIR__ . '/CaptureIO.php';
        $io = new CaptureIO();
        $composer = new Composer();
        $installedFile = __DIR__ . '/../../installed';
        $fs = new Filesystem();
        $fs->touch($installedFile);
        $version = static::isAtLeastSymfony5() ? 5 : 4;

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), __DIR__ . '/../project-s' . $version));

        $fs->remove($installedFile);
        $io->setInteractive(true);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), __DIR__ . '/../project-s' . $version));
        self::assertFileExists($installedFile);

        $fs->remove($installedFile);
        $io->setPermissive(true);
        $io->reset();
        $dir = sys_get_temp_dir() . '/pug-temp';
        $fs->remove($dir);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'Not inside a composer vendor directory, setup skipped.',
        ], $io->getLastOutput());

        $io->reset();
        $fs->mkdir($dir);
        $fs->touch($dir . '/composer.json');

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'framework entry not found in config.yml.',
            'Sorry, AppKernel.php has a format we can\'t handle automatically.',
        ], $io->getLastOutput());
        clearstatcache();
        self::assertFileNotExists($installedFile);

        foreach (['/config/services.yaml', '/config/packages/framework.yaml', '/config/bundles.php'] as $file) {
            $fs->copy(__DIR__ . '/../project-s' . $version . $file, $dir . $file);
        }
        $io->reset();

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'templating.engine.pug setting in config/packages/framework.yaml already exists.',
            'templating.engine.pug setting in config/services.yaml already exists.',
            'The bundle already exists in config/bundles.php',
        ], $io->getLastOutput());
        clearstatcache();
        self::assertFileExists($installedFile);

        $fs->remove($installedFile);
        file_put_contents($dir . '/config/services.yaml', str_replace(
            'pug',
            'x',
            file_get_contents($dir . '/config/services.yaml')
        ));
        file_put_contents($dir . '/config/packages/framework.yaml', str_replace(
            ['pug', 'templating'],
            ['X', 'foo'],
            file_get_contents($dir . '/config/packages/framework.yaml')
        ));
        file_put_contents($dir . '/config/bundles.php', str_replace(
            'Pug',
            'X',
            file_get_contents($dir . '/config/bundles.php')
        ));
        $io->reset();

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'Engine service added in config/packages/framework.yaml',
            'Engine service added in config/services.yaml',
            'Bundle added to config/bundles.php',
        ], $io->getLastOutput());
        self::assertContains(
            'Pug\PugSymfonyBundle\PugSymfonyBundle',
            file_get_contents($dir . '/config/bundles.php')
        );
        self::assertContains(
            'templating.engine.pug',
            file_get_contents($dir . '/config/services.yaml')
        );
        self::assertContains(
            "'pug'",
            file_get_contents($dir . '/config/packages/framework.yaml')
        );
        clearstatcache();
        self::assertFileExists($installedFile);

        $fs->remove($installedFile);
        file_put_contents($dir . '/config/packages/framework.yaml', str_replace(
            'pug',
            'X',
            file_get_contents($dir . '/config/packages/framework.yaml')
        ));
        $io->reset();

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertContains('Engine service added in config/packages/framework.yaml', $io->getLastOutput());
        clearstatcache();
        self::assertFileExists($installedFile);

        $fs->remove($installedFile);
        file_put_contents($dir . '/config/packages/framework.yaml', preg_replace(
            '/^(\s+)engines\s*:\s*\[[^\]]+]/m',
            "\$1engines:\n\$1    - twig",
            file_get_contents($dir . '/config/packages/framework.yaml')
        ));
        $io->reset();

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertContains('Engine service added in config/packages/framework.yaml', $io->getLastOutput());
        clearstatcache();
        self::assertFileExists($installedFile);

        $fs->remove($installedFile);
        file_put_contents($dir . '/config/services.yaml', str_replace(
            ['services', 'pug'],
            ['x', 'x'],
            file_get_contents($dir . '/config/services.yaml')
        ));
        file_put_contents($dir . '/config/packages/framework.yaml', str_replace(
            ['pug', 'twig', 'framework'],
            ['x', 'x', 'x'],
            file_get_contents($dir . '/config/packages/framework.yaml')
        ));
        file_put_contents($dir . '/config/bundles.php', preg_replace(
            '/\[\s*\n\s*/',
            '[',
            file_get_contents($dir . '/config/bundles.php')
        ));
        $io->reset();

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'framework entry not found in config/packages/framework.yaml.',
            'services entry not found in config/services.yaml.',
            'Sorry, config/bundles.php has a format we can\'t handle automatically.',
        ], $io->getLastOutput());
        $fs->remove($installedFile);
    }

    public function testOptionDefaultingOnException()
    {
        $engine = new InvalidExceptionOptionsPugSymfony(self::$kernel);

        self::assertSame('my default', $engine->getOptionDefault('foobar', 'my default'));
    }

    public function testMixedLoader()
    {
        $loader = new MixedLoader(new ArrayLoader([
            'fozz' => 'fozz template',
            'bazz' => 'bazz template',
        ]));

        $loader->setTemplate('foo', 'bar');
        $loader->setTemplateSource('bar', 'biz');

        self::assertTrue($loader->exists('foo'));
        self::assertTrue($loader->isFresh('foo', 1));
        self::assertTrue($loader->exists('bar'));
        self::assertTrue($loader->isFresh('bar', 1));
        self::assertFalse($loader->exists('biz'));

        self::assertSame('fozz template', $loader->getSourceContext('fozz')->getCode());
        self::assertSame('bazz:bazz template', $loader->getCacheKey('bazz'));
    }

    public function testCssWithCustomAssetsHelper()
    {
        if (!class_exists('Symfony\\Bundle\\FrameworkBundle\\Templating\\Helper\\AssetsHelper')) {
            include_once __DIR__ . '/AssetsHelper.php';
        }

        if (!class_exists('Symfony\\Bundle\\FrameworkBundle\\Templating\\Helper\\FakeAssetsHelper')) {
            include_once __DIR__ . '/FakeAssetsHelper.php';
        }

        $helper = new FakeAssetsHelper(new Packages());
        $css = new Css($helper);

        self::assertSame("url('fake:foo')", $css->getUrl('foo'));
    }
}

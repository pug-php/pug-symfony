<?php

namespace Pug\Tests;

use Composer\Composer;
use Composer\Script\Event;
use Pug\Filter\AbstractFilter;
use Pug\PugSymfonyEngine;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Templating\Helper\LogoutUrlHelper as BaseLogoutUrlHelper;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage as BaseTokenStorage;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator as BaseLogoutUrlGenerator;

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

class LogoutUrlHelper extends BaseLogoutUrlHelper
{
    public function __construct()
    {
        parent::__construct(new LogoutUrlGenerator());
    }
}

class TestKernel extends \AppKernel
{
    /**
     * @var \Closure
     */
    private $containerConfigurator;

    public function __construct(\Closure $containerConfigurator, $environment = 'test', $debug = false)
    {
        $this->containerConfigurator = $containerConfigurator;

        parent::__construct($environment, $debug);

        $this->rootDir = realpath(__DIR__ . '/../project/app');
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        parent::registerContainerConfiguration($loader);
        $loader->load(__DIR__ . '/../project/app/config/config.yml');
        $loader->load($this->containerConfigurator);
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

    public function setDueDate(\DateTime $dueDate = null)
    {
        $this->dueDate = $dueDate;
    }
}

class TestController extends Controller
{
    public function index()
    {
        try {
            return $this->createFormBuilder(new Task())
                ->add('name', 'Symfony\Component\Form\Extension\Core\Type\TextType')
                ->add('dueDate', 'Symfony\Component\Form\Extension\Core\Type\DateType')
                ->add('save', 'Symfony\Component\Form\Extension\Core\Type\SubmitType', ['label' => 'Foo'])
                ->getForm();
        } catch (\InvalidArgumentException $e) {
            return $this->createFormBuilder(new Task())
                ->add('name', 'text')
                ->add('dueDate', 'date')
                ->add('save', 'submit', ['label' => 'Foo'])
                ->getForm();
        }
    }
}

class PugSymfonyEngineTest extends KernelTestCase
{
    private static function clearCache()
    {
        foreach (['app', 'var'] as $directory) {
            if (is_dir($path = __DIR__ . "/../project/$directory/cache")) {
                (new Filesystem())->remove($path);
            }
        }
    }

    public static function setUpBeforeClass()
    {
        self::clearCache();
    }

    public static function tearDownAfterClass()
    {
        self::clearCache();
    }

    public function setUp()
    {
        self::bootKernel();
    }

    public function testPreRender()
    {
        $kernel = new TestKernel(function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'php',
            ]);
        });
        $kernel->boot();
        $pugSymfony = new PugSymfonyEngine($kernel);

        self::assertSame('<p>/foo</p>', $pugSymfony->renderString('p=asset("foo")'));
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage It seems you did not set the new settings in services.yml, please add "@kernel" to templating.engine.pug service arguments, see https://github.com/pug-php/pug-symfony#readme
     */
    public function testNeedKernel()
    {
        new PugSymfonyEngine('foo');
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
        self::$kernel->getContainer()->set('security.token_storage', $tokenStorage);
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame('<p>the token</p>', trim($pugSymfony->render('token.pug')));
    }

    public function testLogoutHelper()
    {
        self::clearCache();
        $logoutUrlHelper = new LogoutUrlHelper(new LogoutUrlGenerator());
        self::$kernel->getContainer()->set('templating.helper.logout_url', $logoutUrlHelper);
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame('<a href="logout-url"></a><a href="logout-path"></a>', trim($pugSymfony->render('logout.pug')));
    }

    /**
     * @group i
     *
     * @throws \ErrorException
     */
    public function testFormHelpers()
    {
        //self::clearCache();
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $controller = new TestController();
        $controller->setContainer(self::$kernel->getContainer());

        self::assertRegExp('/^' . implode('', [
            '<form name="form" method="get">',
            '<div><label for="form_name" class="required">Name<\/label><input type="text" id="form_name" name="form\[name\]" required="required" \/><\/div>',
            '<div><label class="required">Due date<\/label><div id="form_dueDate"><select id="form_dueDate_day" name="form\[dueDate\]\[day\]">',
            '(<option value="\d+">\d+<\/option>)+<\/select>',
            '<select id="form_dueDate_month" name="form\[dueDate\]\[month\]">(<option value="\d+">[^<]+<\/option>)+<\/select>',
            '<select id="form_dueDate_year" name="form\[dueDate\]\[year\]">(<option value="\d+">\d+<\/option>)+<\/select>',
            '<\/div><\/div><div><button type="submit" id="form_save" name="form\[save\]">Submit me<\/button><\/div>',
            '<input type="hidden" id="form__token" name="form\[_token\]" value="[^"]+" \/><\/form>',
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
            } catch (\InvalidArgumentException $e) {
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
        self::clearCache();
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
        self::clearCache();
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
        touch($installedFile);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), __DIR__ . '/../project'));

        unlink($installedFile);
        $io->setInteractive(true);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), __DIR__ . '/../project'));
        self::assertFileExists($installedFile);

        unlink($installedFile);
        $io->setPermissive(true);
        $io->reset();
        $dir = sys_get_temp_dir() . '/pug-temp';
        $fs = new Filesystem();
        $fs->remove($dir);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'Not inside a composer vendor directory, setup skipped.',
        ], $io->getLastOutput());

        $io->reset();
        $fs->mkdir($dir);
        touch($dir . '/composer.json');

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

        unlink($installedFile);
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
        touch($dir . '/composer.json');
        file_exists($installedFile) && unlink($installedFile);
        clearstatcache();

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        file_exists($installedFile) && unlink($installedFile);

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
        unlink($installedFile);

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
        touch($installedFile);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), __DIR__ . '/../project-s4'));

        file_exists($installedFile) && unlink($installedFile);
        $io->setInteractive(true);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), __DIR__ . '/../project-s4'));
        self::assertFileExists($installedFile);

        unlink($installedFile);
        $io->setPermissive(true);
        $io->reset();
        $dir = sys_get_temp_dir() . '/pug-temp';
        $fs = new Filesystem();
        $fs->remove($dir);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'Not inside a composer vendor directory, setup skipped.',
        ], $io->getLastOutput());

        $io->reset();
        $fs->mkdir($dir);
        touch($dir . '/composer.json');

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'framework entry not found in config.yml.',
            'Sorry, AppKernel.php has a format we can\'t handle automatically.',
        ], $io->getLastOutput());
        clearstatcache();
        self::assertFileNotExists($installedFile);

        foreach (['/config/services.yaml', '/config/packages/framework.yaml', '/config/bundles.php'] as $file) {
            $fs->copy(__DIR__ . '/../project-s4' . $file, $dir . $file);
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

        unlink($installedFile);
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

        unlink($installedFile);
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

        unlink($installedFile);
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
    }
}

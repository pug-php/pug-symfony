<?php

namespace Pug\Tests;

use Composer\Composer;
use Composer\Script\Event;
use Jade\Compiler;
use Jade\Filter\AbstractFilter;
use Jade\Nodes\Filter;
use Jade\Symfony\JadeEngine as Jade;
use Pug\PugSymfonyEngine;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Templating\Helper\LogoutUrlHelper as BaseLogoutUrlHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage as BaseTokenStorage;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator as BaseLogoutUrlGenerator;

class TokenStorage extends BaseTokenStorage
{
    public function __construct()
    {
    }

    public function getToken()
    {
        return 'token';
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
    public function __invoke(Filter $node, Compiler $compiler)
    {
        return strtoupper($this->getNodeString($node, $compiler));
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

class PugSymfonyEngineTest extends KernelTestCase
{
    private static function clearCache()
    {
        if (is_dir(__DIR__ . '/../project/app/cache')) {
            (new Filesystem())->remove(__DIR__ . '/../project/app/cache');
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
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $code = $pugSymfony->preRender('p=asset("foo")');

        self::assertSame('p=$view[\'assets\']->getUrl("foo")', $code);
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

        self::assertInstanceOf(Jade::class, $pugSymfony->getEngine());
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
        $tokenStorage = new TokenStorage();
        self::$kernel->getContainer()->set('security.token_storage', $tokenStorage);
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame('<p>token</p>', trim($pugSymfony->render('token.pug')));
    }

    public function testLogoutHelper()
    {
        $logoutUrlHelper = new LogoutUrlHelper(new LogoutUrlGenerator());
        self::$kernel->getContainer()->set('templating.helper.logout_url', $logoutUrlHelper);
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame('<a href="logout-url"></a><a href="logout-path"></a>', trim($pugSymfony->render('logout.pug')));
    }

    public function testCustomHelper()
    {
        $helper = new CustomHelper();
        $pugSymfony = new PugSymfonyEngine(self::$kernel, $helper);

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

        $message = null;
        try {
            $pugSymfony->getOption('foo');
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
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
            trim($pugSymfony->render('style-php.pug'))
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
            trim($pugSymfony->render('style-js.pug'))
        );
    }

    public function testFilter()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertFalse($pugSymfony->hasFilter('upper'));

        $pugSymfony->filter('upper', Upper::class);
        self::assertTrue($pugSymfony->hasFilter('upper'));
        self::assertSame(Upper::class, $pugSymfony->getFilter('upper'));
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
            'prettyprint' => true,
            'cache'       => null,
        ]);

        $pugSymfony->setOption('indentSize', 3);

        self::assertSame(3, $pugSymfony->getOption('indentSize'));
        self::assertSame("<div>\n   <p></p>\n</div>", trim($pugSymfony->render('p.pug')));

        $pugSymfony->setOptions(['indentSize' => 5]);

        self::assertSame(5, $pugSymfony->getOption('indentSize'));
        self::assertSame(5, $pugSymfony->getEngine()->getOption('indentSize'));
        self::assertSame("<div>\n     <p></p>\n</div>", trim($pugSymfony->render('p.pug')));
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

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io)));

        unlink($installedFile);
        $io->setInteractive(true);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io)));
        self::assertTrue(file_exists($installedFile));

        unlink($installedFile);
        $io->setPermissive(true);
        $io->reset();
        $dir = sys_get_temp_dir() . '/pug-temp';
        $fs = new Filesystem();
        $fs->remove($dir);

        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'framework entry not found in config.yml.',
            'Sorry, AppKernel.php has a format we can\'t handle automatically.',
        ], $io->getLastOutput());
        clearstatcache();
        self::assertFalse(file_exists($installedFile));

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
        self::assertTrue(file_exists($installedFile));

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
        self::assertTrue(file_exists($installedFile));

        $io->reset();
        unlink($installedFile);
        file_put_contents($dir . '/app/config/config.yml', implode("\n", [
            'foo:',
            '  bar: biz',
            'framework:',
            '  bar1: biz',
            '  templating:',
            '    bar2: biz',
            '    engines:',
            '      - pug',
            '      - php',
            '    bar3: biz',
            '  bar4: biz',
            'bar: biz',
        ]));
        self::assertTrue(PugSymfonyEngine::install(new Event('install', $composer, $io), $dir));
        self::assertSame([
            'Engine service added in config.yml',
            'Automatic engine adding is only possible if framework.templating.engines is a ' .
            'one-line setting in config.yml.',
            'The bundle already exists in AppKernel.php',
        ], $io->getLastOutput());
        clearstatcache();
        self::assertFalse(file_exists($installedFile));

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
        self::assertTrue(file_exists($installedFile));
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
        self::assertFalse(file_exists($installedFile));
    }
}

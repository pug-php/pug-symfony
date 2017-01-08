<?php

namespace Pug\Tests;

use Jade\Compiler;
use Jade\Filter\AbstractFilter;
use Jade\Nodes\Filter;
use Jade\Symfony\JadeEngine as Jade;
use Pug\PugSymfonyEngine;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Templating\Helper\LogoutUrlHelper as BaseLogoutUrlHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
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

    /**
     * @group asset
     */
    public function testAssetHelperPhp()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOption('expressionLanguage', 'php');

        self::assertSame(
            '<div style="'.
                'background-position: 50% -402px; '.
                'background-image: url(\'/assets/img/patterns/5.png\');'.
                '" class="foo"></div>' . "\n" .
            '<div style="'.
                'background-position:50% -402px;'.
                'background-image:url(\'/assets/img/patterns/5.png\')'.
                '" class="foo"></div>',
            trim($pugSymfony->render('style-php.pug'))
        );
    }

    /**
     * @group asset
     */
    public function testAssetHelperJs()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOption('expressionLanguage', 'js');

        self::assertSame(
            '<div style="'.
                'background-position: 50% -402px; '.
                'background-image: url(\'/assets/img/patterns/5.png\');'.
                '" class="foo"></div>' . "\n" .
            '<div style="'.
                'background-position:50% -402px;'.
                'background-image:url(\'/assets/img/patterns/5.png\')'.
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
}

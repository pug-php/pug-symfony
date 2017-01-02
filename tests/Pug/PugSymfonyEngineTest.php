<?php

namespace Pug\Tests;

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
        $fs = new Filesystem();
        $fs->remove(__DIR__ . '/../project/app/cache');
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
        $template = $this->getMockForAbstractClass('Pug\\PugSymfonyEngine', [], '', false);
        $code = $template->preRender('p=asset("foo")');

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
}

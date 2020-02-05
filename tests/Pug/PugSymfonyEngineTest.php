<?php

namespace Pug\Tests;

use DateTime;
use ErrorException;
use InvalidArgumentException;
use Pug\Exceptions\ReservedVariable;
use Pug\Filter\AbstractFilter;
use Pug\Pug;
use Pug\PugSymfonyEngine;
use Pug\Symfony\CssExtension;
use Pug\Symfony\MixedLoader;
use Pug\Symfony\Traits\PrivatePropertyAccessor;
use Pug\Twig\Environment;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use Symfony\Bridge\Twig\Extension\LogoutUrlExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\FakeAssetsHelper;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage as BaseTokenStorage;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator as BaseLogoutUrlGenerator;
use Twig\Error\LoaderError;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

class TokenStorage extends BaseTokenStorage
{
    public function getToken(): string
    {
        return 'the token';
    }
}

class CustomHelper
{
    public function foo(): string
    {
        return 'bar';
    }
}

class Upper extends AbstractFilter
{
    public function parse(string $code): string
    {
        return strtoupper($code);
    }
}

class LogoutUrlGenerator extends BaseLogoutUrlGenerator
{
    public function getLogoutUrl(string $key = null): string
    {
        return 'logout-url';
    }

    public function getLogoutPath(string $key = null): string
    {
        return 'logout-path';
    }
}

require_once __DIR__.'/TestKernel.php';

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
    include __DIR__.'/AbstractController.php';
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

class PugSymfonyEngineTest extends AbstractTestCase
{
    use PrivatePropertyAccessor;

    public function testRequireTwig()
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Twig needs to be configured.');

        $container = self::$kernel->getContainer();
        foreach (['services', 'aliases', 'fileMap', 'methodMap'] as $name) {
            /** @var ReflectionProperty $propertyAccessor */
            $services = static::getPrivateProperty($container, $name, $propertyAccessor);
            unset($services['twig']);
            $propertyAccessor->setValue($container, $services);
        }

        new PugSymfonyEngine(self::$kernel);
    }

    /**
     * @throws ErrorException
     */
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

    /**
     * @throws ErrorException
     */
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

    /**
     * @throws ErrorException
     */
    public function testPreRenderCsrfToken()
    {
        $kernel = new TestKernel(function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'js',
            ]);
        });
        $kernel->boot();
        $pugSymfony = new PugSymfonyEngine($kernel);
        $this->addFormRenderer($kernel->getContainer());

        self::assertSame('<p>Hello</p>', $pugSymfony->renderString('p Hello'));

        self::assertRegExp('/<p>[a-zA-Z0-9_-]{20,}<\/p>/', $pugSymfony->renderString('p=csrf_token("authentificate")'));
    }

    public function testGetEngine()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertInstanceOf(Pug::class, $pugSymfony->getRenderer());
    }

    /**
     * @throws ErrorException
     * @throws ReflectionException
     */
    public function testSecurityToken()
    {
        $tokenStorage = new TokenStorage();
        $container = self::$kernel->getContainer();
        $reflectionProperty = new ReflectionProperty($container, 'services');
        $reflectionProperty->setAccessible(true);
        $services = $reflectionProperty->getValue($container);
        $services['security.token_storage'] = $tokenStorage;
        $reflectionProperty->setValue($container, $services);
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $this->addFormRenderer($container);

        self::assertSame('<p>the token</p>', trim($pugSymfony->render('token.pug')));
    }

    /**
     * @throws ErrorException
     * @throws ReflectionException
     */
    public function testLogoutHelper()
    {
        $generator = new LogoutUrlGenerator();
        /* @var Environment $twig */
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
            include_once __DIR__.'/LogoutUrlHelper.php';
            $logoutUrlHelper = new LogoutUrlHelper($generator);
            self::$kernel->getContainer()->set('templating.helper.logout_url', $logoutUrlHelper);
        }

        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame('<a href="logout-url"></a><a href="logout-path"></a>', trim($pugSymfony->render('logout.pug')));
    }

    /**
     * @throws ErrorException
     */
    public function testFormHelpers()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $container = self::$kernel->getContainer();
        $this->addFormRenderer($container);
        $controller = new TestController();
        $controller->setContainer($container);

        self::assertRegExp('/^'.implode('', [
            '<form name="form" method="get">',
            '<input type="text" id="form_name" name="form\[name\]" required="required" class="foo"\s*\/>',
            '<div\s*><label class="required">Due date<\/label><div id="form_dueDate">(',
            '<select id="form_dueDate_day" name="form\[dueDate\]\[day\]"\s*>\s*(<option value="\d+"\s*>\d+<\/option>\s*)+<\/select>\s*|',
            '<select id="form_dueDate_month" name="form\[dueDate\]\[month\]"\s*>\s*(<option value="\d+"\s*>[^<]+<\/option>\s*)+<\/select>\s*|',
            '<select id="form_dueDate_year" name="form\[dueDate\]\[year\]"\s*>\s*(<option value="\d+"\s*>\d+<\/option>\s*)+<\/select>\s*){3}',
            '<\/div>\s*<\/div>\s*<div\s*>\s*<button type="submit" id="form_save" name="form\[save\]"\s*>Submit me<\/button>\s*<\/div>\s*',
            '<input type="hidden" id="form__token" name="form\[_token\]" value="[^"]+"\s*\/>\s*<\/form>',
        ]).'$/', trim($pugSymfony->render('form', [
            'form' => $controller->index()->createView(),
        ])));
    }

    /**
     * @throws ErrorException
     */
    public function testRenderViaTwig()
    {
        $container = self::$kernel->getContainer();
        $controller = new TestController();
        $controller->setContainer($container);
        /** @var Environment $twig */
        $twig = $container->get('twig');

        self::assertInstanceOf(Environment::class, $twig);
        self::assertInstanceOf(PugSymfonyEngine::class, $twig->getEngine());
        self::assertInstanceOf(Pug::class, $twig->getRenderer());
        self::assertSame(implode("\n", [
            '<p>inc-twig</p>',
            '<p>inc-pug</p>',
            '<div>',
            '  <p></p>',
            '</div>',
        ]), str_replace("\r", '', trim($twig->render('inc-twig.pug', [
            'form' => $controller->index()->createView(),
        ]))));
    }

    /**
     * @throws ErrorException
     */
    public function testServicesSharing()
    {
        /** @var Environment $twig */
        $twig = self::$kernel->getContainer()->get('twig');
        $twig->addGlobal('t', self::$kernel->getContainer()->get('translator'));
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame('<p>Hello Bob</p>', trim($pugSymfony->renderString('p=t.trans("Hello %name%", {"%name%": "Bob"})')));
    }

    /**
     * @throws ErrorException
     */
    public function testTwigGlobals()
    {
        $container = self::$kernel->getContainer();
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        /** @var Environment $twig */
        $twig = $container->get('twig');
        $twig->addGlobal('answer', 42);

        self::assertSame('<p>42</p>', trim($pugSymfony->renderString('p=answer')));
    }

    public function testOptions()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOptions(['foo' => 'bar']);

        self::assertSame('bar', $pugSymfony->getOptionDefault('foo'));
    }

    /**
     * @throws ErrorException
     */
    public function testBundleView()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame('<p>Hello</p>', trim($pugSymfony->render('TestBundle::bundle.pug', ['text' => 'Hello'])));
        self::assertSame('<section>World</section>', trim($pugSymfony->render('TestBundle:directory:file.pug')));
    }

    /**
     * @throws ErrorException
     */
    public function testAssetHelperPhp()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOption('expressionLanguage', 'php');

        self::assertSame(
            '<div style="'.
                'background-position: 50% -402px; '.
                'background-image: url(\'/assets/img/patterns/5.png\');'.
                '" class="foo"></div>'."\n".
            '<div style="'.
                'background-position:50% -402px;'.
                'background-image:url(\'/assets/img/patterns/5.png\')'.
                '" class="foo"></div>',
            preg_replace(
                '/<div( class="[^"]+")(.+?)></',
                '<div$2$1><',
                str_replace(['\'assets/', "\r"], ['\'/assets/', ''], trim($pugSymfony->render('style-php.pug')))
            )
        );
    }

    /**
     * @throws ErrorException
     */
    public function testAssetHelperJs()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOption('expressionLanguage', 'js');

        self::assertSame(
            '<div style="'.
                'background-position: 50% -402px; '.
                'background-image: url(\'/assets/img/patterns/5.png\');'.
                '" class="foo"></div>'."\n".
            '<div style="'.
                'background-position:50% -402px;'.
                'background-image:url(\'/assets/img/patterns/5.png\')'.
                '" class="foo"></div>',
            preg_replace(
                '/<div( class="[^"]+")(.+?)></',
                '<div$2$1><',
                str_replace(['\'assets/', "\r"], ['\'/assets/', ''], trim($pugSymfony->render('style-js.pug')))
            )
        );
    }

    /**
     * @throws ErrorException
     */
    public function testFilter()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertFalse($pugSymfony->hasFilter('upper'));

        $pugSymfony->filter('upper', Upper::class);
        self::assertTrue($pugSymfony->hasFilter('upper'));
        $filter = $pugSymfony->getFilter('upper');

        if (!is_string($filter)) {
            $filter = get_class($filter);
        }

        self::assertSame(Upper::class, ltrim($filter, '\\'));
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

    /**
     * @throws ErrorException
     */
    public function testCustomOptions()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOptions([
            'prettyprint' => '    ',
            'cache'       => null,
        ]);

        $pugSymfony->setOption('indentSize', 3);
        $pugSymfony->setOption('prettyprint', '   ');

        self::assertSame(3, $pugSymfony->getOptionDefault('indentSize'));
        self::assertSame(
            "<div>\n   <p></p>\n</div>",
            str_replace("\r", '', trim($pugSymfony->render('p.pug')))
        );

        $pugSymfony->setOptions(['indentSize' => 5, 'prettyprint' => '     ']);

        self::assertSame(5, $pugSymfony->getOptionDefault('indentSize'));
        self::assertSame(5, $pugSymfony->getRenderer()->getOption('indentSize'));
        self::assertSame(
            "<div>\n     <p></p>\n</div>",
            str_replace("\r", '', trim($pugSymfony->render('p.pug')))
        );
    }

    /**
     * @throws ErrorException
     * @throws ReflectionException
     */
    public function testCustomBaseDir()
    {
        $container = self::$kernel->getContainer();
        $property = new ReflectionProperty($container, 'parameters');
        $property->setAccessible(true);
        $value = $property->getValue($container);
        $value['pug']['prettyprint'] = false;
        $value['pug']['baseDir'] = __DIR__.'/../project-s5/templates-bis';
        $property->setValue($container, $value);
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame(
            '<section><p></p></section>',
            trim($pugSymfony->render('p.pug'))
        );
    }

    /**
     * @throws ErrorException
     * @throws ReflectionException
     */
    public function testCustomPaths()
    {
        $container = self::$kernel->getContainer();
        $property = new ReflectionProperty($container, 'parameters');
        $property->setAccessible(true);
        $value = $property->getValue($container);
        $value['pug']['prettyprint'] = false;
        $value['pug']['paths'] = [__DIR__.'/../project-s5/templates-bis'];
        $property->setValue($container, $value);
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame(
            '<p>alt</p>',
            trim($pugSymfony->render('alt.pug'))
        );
    }

    /**
     * @throws ErrorException
     * @throws ReflectionException
     */
    public function testMissingDir()
    {
        $kernel = new TestKernel();
        $kernel->boot();
        $kernel->setProjectDirectory(__DIR__.'/../project');
        $pugSymfony = new PugSymfonyEngine($kernel);

        self::assertSame(
            '<h1>Page</h1>',
            trim($pugSymfony->render('page.pug'))
        );
    }

    public function testForbidThis()
    {
        self::expectException(ReservedVariable::class);
        self::expectExceptionMessage('"this" is a reserved variable name, you can\'t overwrite it.');

        (new PugSymfonyEngine(self::$kernel))->render('p.pug', ['this' => 42]);
    }

    public function testForbidBlocks()
    {
        self::expectException(ReservedVariable::class);
        self::expectExceptionMessage('"blocks" is a reserved variable name, you can\'t overwrite it.');

        (new PugSymfonyEngine(self::$kernel))->render('p.pug', ['blocks' => 42]);
    }

    public function testIssue11BackgroundImage()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->setOption('expressionLanguage', 'js');
        $html = trim($pugSymfony->render('background-image.pug', ['image' => 'foo']));
        $html = preg_replace('/<div( class="[^"]+")([^>]+)>/', '<div$2$1>', $html);

        self::assertSame('<div style="background-image: url(foo);" class="slide"></div>', $html);
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

        self::assertSame('biz', $loader->getSourceContext('bar')->getCode());
        self::assertSame('bar', $loader->getCacheKey('bar'));
        self::assertSame('fozz template', $loader->getSourceContext('fozz')->getCode());
        self::assertSame('bazz:bazz template', $loader->getCacheKey('bazz'));
    }

    public function testCssWithCustomAssetsHelper()
    {
        if (!class_exists('Symfony\\Bundle\\FrameworkBundle\\Templating\\Helper\\AssetsHelper')) {
            include_once __DIR__.'/AssetsHelper.php';
        }

        if (!class_exists('Symfony\\Bundle\\FrameworkBundle\\Templating\\Helper\\FakeAssetsHelper')) {
            include_once __DIR__.'/FakeAssetsHelper.php';
        }

        $helper = new FakeAssetsHelper(new Packages());
        $css = new CssExtension($helper);

        self::assertSame("url('fake:foo')", $css->getUrl('foo'));
    }

    public function testCompileException()
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unable to compile void function.');

        new PugSymfonyEngine(self::$kernel);
        /** @var Environment $twig */
        $twig = self::$kernel->getContainer()->get('twig');
        $twig->compileCode(new TwigFunction('void'), '{# comment #}');
    }

    public function testLoadTemplate()
    {
        new PugSymfonyEngine(self::$kernel);
        /** @var Environment $twig */
        $twig = self::$kernel->getContainer()->get('twig');

        try {
            $twig->loadTemplate('a', 'b', 1);
        } catch (LoaderError $e) {
            // noop
        }

        $classNames = self::getPrivateProperty($twig, 'classNames');

        self::assertSame('a___1', $classNames['b']);
    }

    public function testDefaultOption()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);

        self::assertSame(42, $pugSymfony->getOptionDefault('does-not-exist', 42));
    }

    public function testGetSharedVariables()
    {
        $pugSymfony = new PugSymfonyEngine(self::$kernel);
        $pugSymfony->share('foo', 'bar');

        self::assertSame('bar', $pugSymfony->getSharedVariables()['foo']);
    }
}

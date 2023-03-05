<?php

namespace Pug\Tests;

use App\Service\PugInterceptor;
use DateTime;
use ErrorException;
use Phug\CompilerException;
use Phug\Util\SourceLocation;
use Pug\Exceptions\ReservedVariable;
use Pug\Filter\AbstractFilter;
use Pug\Pug;
use Pug\PugSymfonyEngine;
use Pug\Symfony\MixedLoader;
use Pug\Symfony\Traits\HelpersHandler;
use Pug\Symfony\Traits\PrivatePropertyAccessor;
use Pug\Twig\Environment;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use Symfony\Bridge\Twig\Extension\LogoutUrlExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage as BaseTokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator as BaseLogoutUrlGenerator;
use Symfony\Component\Translation\Translator;
use Twig\Error\LoaderError;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

class TokenStorage extends BaseTokenStorage
{
    public function getToken(): ?TokenInterface
    {
        return new NullToken();
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

class TestController
{
    public function index()
    {
        $csrfGenerator = new UriSafeTokenGenerator();
        $csrfManager = new CsrfTokenManager($csrfGenerator, new class() implements TokenStorageInterface {
            public function getToken(string $tokenId): string
            {
                return "token:$tokenId";
            }

            public function setToken(string $tokenId, #[\SensitiveParameter] string $token)
            {
                // noop
            }

            public function removeToken(string $tokenId): ?string
            {
                return "token:$tokenId";
            }

            public function hasToken(string $tokenId): bool
            {
                return true;
            }
        });
        $extensions = [new CsrfExtension($csrfManager)];
        $factory = new FormFactory(new FormRegistry($extensions, new ResolvedFormTypeFactory()));

        return $factory->createBuilder(FormType::class, new Task())
            ->add('name', TextType::class)
            ->add('dueDate', DateType::class)
            ->add('save', SubmitType::class, ['label' => 'Foo'])
            ->getForm();
    }
}

class PugSymfonyEngineTest extends AbstractTestCase
{
    use PrivatePropertyAccessor;

    public function testRequireTwig()
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Twig needs to be configured.');

        $object = new class() {
            use HelpersHandler;

            public function wrongEnhance(): void
            {
                $this->enhanceTwig(new \stdClass());
            }
        };

        $object->wrongEnhance();
    }

    /**
     * @throws ErrorException
     */
    public function testPreRenderPhp()
    {
        $kernel = new TestKernel(static function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'php',
            ]);
        });
        $kernel->boot();
        $pugSymfony = $this->getPugSymfonyEngine();
        $pugSymfony->setOption('prettyprint', false);

        self::assertSame('<p>/foo</p>', trim($pugSymfony->renderString('p=asset("/foo")')));
        self::assertSame(
            '<html><head><title>My Site</title></head><body><p>/foo</p><footer><p></p>Some footer text</footer></body></html>',
            $pugSymfony->render('asset.pug'),
        );
    }

    public function testMixin()
    {
        $pugSymfony = $this->getPugSymfonyEngine();
        $pugSymfony->setOption('expressionLanguage', 'js');
        $pugSymfony->setOption('prettyprint', false);

        self::assertSame(
            '<ul><li class="pet">cat</li><li class="pet">dog</li><li class="pet">pig</li></ul>',
            $pugSymfony->render('mixin.pug'),
        );
    }

    /**
     * @throws ErrorException
     */
    public function testPreRenderJs()
    {
        $kernel = new TestKernel(static function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'js',
            ]);
        });
        $kernel->boot();
        $pugSymfony = $this->getPugSymfonyEngine();

        self::assertSame('<p>/foo</p>', trim($pugSymfony->renderString('p=asset("/foo")')));
    }

    public function testPreRenderFile()
    {
        $kernel = new TestKernel(static function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'js',
            ]);
        });
        $kernel->boot();
        $pugSymfony = $this->getPugSymfonyEngine();
        $pugSymfony->setOption('prettyprint', false);

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
        $kernel = new TestKernel(static function (Container $container) {
            $container->setParameter('pug', [
                'expressionLanguage' => 'js',
            ]);
        });
        $kernel->boot();
        $pugSymfony = $this->getPugSymfonyEngine($kernel);
        $this->addFormRenderer();

        self::assertSame('<p>Hello</p>', $pugSymfony->renderString('p Hello'));

        self::assertRegExp('/<p>[a-zA-Z0-9_-]{20,}<\/p>/', $pugSymfony->renderString('p=csrf_token("authenticate")'));
    }

    public function testGetEngine()
    {
        $pugSymfony = $this->getPugSymfonyEngine();

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
        $services = $reflectionProperty->getValue($container);
        $services['security.token_storage'] = $tokenStorage;
        $reflectionProperty->setValue($container, $services);
        $pugSymfony = $this->getPugSymfonyEngine();
        $this->addFormRenderer();

        self::assertSame('<p>the token</p>', trim($pugSymfony->render('token.pug')));
    }

    /**
     * @throws ErrorException
     * @throws ReflectionException
     */
    public function testLogoutHelper()
    {
        $generator = new LogoutUrlGenerator();
        $twig = $this->getTwigEnvironment();

        foreach ($twig->getExtensions() as $extension) {
            if ($extension instanceof LogoutUrlExtension) {
                $reflectionClass = new \ReflectionClass('Symfony\Bridge\Twig\Extension\LogoutUrlExtension');
                $reflectionProperty = $reflectionClass->getProperty('generator');
                $reflectionProperty->setValue($extension, $generator);
                $generator = null;
            }
        }

        if ($generator) {
            include_once __DIR__.'/LogoutUrlHelper.php';
            $logoutUrlHelper = new LogoutUrlHelper($generator);
            self::$kernel->getContainer()->set('templating.helper.logout_url', $logoutUrlHelper);
        }

        $pugSymfony = $this->getPugSymfonyEngine();

        self::assertSame('<a href="logout-url"></a><a href="logout-path"></a>', trim($pugSymfony->render('logout.pug')));
    }

    /**
     * @throws ErrorException
     */
    public function testFormHelpers()
    {
        $pugSymfony = $this->getPugSymfonyEngine();
        $this->addFormRenderer();
        $controller = new TestController();

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
        $twig = $this->getTwigEnvironment();
        $this->getPugSymfonyEngine();

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
        $twig = $this->getTwigEnvironment();
        $twig->addGlobal('t', new Translator('en_US'));
        $pugSymfony = $this->getPugSymfonyEngine();

        self::assertSame('<p>Hello Bob</p>', trim($pugSymfony->renderString('p=t.trans("Hello %name%", {"%name%": "Bob"})')));
    }

    /**
     * @throws ErrorException
     */
    public function testTwigGlobals()
    {
        $twig = $this->getTwigEnvironment();
        $twig->addGlobal('answer', 42);
        $pugSymfony = $this->getPugSymfonyEngine();

        self::assertSame('<p>42</p>', trim($pugSymfony->renderString('p=answer')));
    }

    public function testOptions()
    {
        $pugSymfony = $this->getPugSymfonyEngine();
        $pugSymfony->setOptions(['foo' => 'bar']);

        self::assertSame('bar', $pugSymfony->getOptionDefault('foo'));
    }

    /**
     * @throws ErrorException
     */
    public function testBundleView()
    {
        $pugSymfony = $this->getPugSymfonyEngine();

        self::assertSame('<p>Hello</p>', trim($pugSymfony->render('TestBundle::bundle.pug', ['text' => 'Hello'])));
        self::assertSame('<section>World</section>', trim($pugSymfony->render('TestBundle:directory:file.pug')));
    }

    /**
     * @throws ErrorException
     */
    public function testAssetHelperPhp()
    {
        $pugSymfony = $this->getPugSymfonyEngine();
        $pugSymfony->setOption('expressionLanguage', 'php');

        self::assertSame(
            '<div style="'.
                'background-position: 50% -402px; '.
                'background-image: url(\'assets/img/patterns/5.png\');'.
                '" class="foo"></div>'."\n".
            '<div style="'.
                'background-position:50% -402px;'.
                'background-image:url(\'assets/img/patterns/5.png\')'.
                '" class="foo"></div>',
            preg_replace(
                '/<div( class="[^"]+")(.+?)></',
                '<div$2$1><',
                strtr(trim($pugSymfony->render('style-php.pug')), [
                    "\r"     => '',
                    '&#039;' => "'",
                ]),
            ),
        );
    }

    /**
     * @throws ErrorException
     */
    public function testAssetHelperJs()
    {
        $pugSymfony = $this->getPugSymfonyEngine();
        $pugSymfony->setOption('expressionLanguage', 'js');

        self::assertSame(
            '<div style="'.
                'background-position: 50% -402px; '.
                'background-image: url(\'assets/img/patterns/5.png\');'.
                '" class="foo"></div>'."\n".
            '<div style="'.
                'background-position:50% -402px;'.
                'background-image:url(\'assets/img/patterns/5.png\')'.
                '" class="foo"></div>',
            preg_replace(
                '/<div( class="[^"]+")(.+?)></',
                '<div$2$1><',
                strtr(trim($pugSymfony->render('style-js.pug')), [
                    "\r"     => '',
                    '&#039;' => "'",
                ]),
            ),
        );
    }

    /**
     * @throws ErrorException
     */
    public function testFilter()
    {
        $pugSymfony = $this->getPugSymfonyEngine();

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
        $pugSymfony = $this->getPugSymfonyEngine();

        self::assertTrue($pugSymfony->exists('logout.pug'));
        self::assertFalse($pugSymfony->exists('login.pug'));
        self::assertTrue($pugSymfony->exists('bundle.pug'));
    }

    public function testSupports()
    {
        $pugSymfony = $this->getPugSymfonyEngine();

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
        $pugSymfony = $this->getPugSymfonyEngine();
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
        $value = $property->getValue($container);
        $value['pug']['prettyprint'] = false;
        $value['pug']['baseDir'] = __DIR__.'/../project-s5/templates-bis';
        $property->setValue($container, $value);
        $pugSymfony = $this->getPugSymfonyEngine();

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
        $value = $property->getValue($container);
        $value['pug']['prettyprint'] = false;
        $value['pug']['paths'] = [__DIR__.'/../project-s5/templates-bis'];
        $property->setValue($container, $value);
        $pugSymfony = $this->getPugSymfonyEngine();

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
        self::expectExceptionObject(new CompilerException(
            new SourceLocation('page.pug', 1, 0),
            'Source file page.pug not found',
        ));

        $kernel = new TestKernel();
        $kernel->boot();
        $kernel->setProjectDirectory(__DIR__.'/../project');

        $this->getPugSymfonyEngine()->render('page.pug');
    }

    public function testForbidThis()
    {
        self::expectException(ReservedVariable::class);
        self::expectExceptionMessage('"this" is a reserved variable name, you can\'t overwrite it.');

        $this->getPugSymfonyEngine()->render('p.pug', ['this' => 42]);
    }

    public function testForbidBlocks()
    {
        self::expectException(ReservedVariable::class);
        self::expectExceptionMessage('"blocks" is a reserved variable name, you can\'t overwrite it.');

        $this->getPugSymfonyEngine()->render('p.pug', ['blocks' => 42]);
    }

    public function testIssue11BackgroundImage()
    {
        $pugSymfony = $this->getPugSymfonyEngine();
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

    public function testCompileException()
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unable to compile void function.');

        $this->getPugSymfonyEngine();
        $twig = $this->getTwigEnvironment();
        $twig->compileCode(new TwigFunction('void'), '{# comment #}');
    }

    public function testLoadTemplate()
    {
        $this->getPugSymfonyEngine();
        $twig = $this->getTwigEnvironment();

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
        $pugSymfony = $this->getPugSymfonyEngine();

        self::assertSame(42, $pugSymfony->getOptionDefault('does-not-exist', 42));

        $pugSymfony->getRenderer();

        self::assertSame(42, $pugSymfony->getOptionDefault('does-not-exist', 42));
    }

    public function testGetSharedVariables()
    {
        $pugSymfony = $this->getPugSymfonyEngine();
        $pugSymfony->share('foo', 'bar');

        self::assertSame('bar', $pugSymfony->getSharedVariables()['foo']);
    }

    /**
     * @throws ErrorException
     * @throws ReflectionException
     */
    public function testRenderInterceptor()
    {
        $container = self::$kernel->getContainer();
        $property = new ReflectionProperty($container, 'parameters');
        $value = $property->getValue($container);
        $value['pug']['interceptors'] = [PugInterceptor::class];
        $property->setValue($container, $value);
        $twig = $this->getTwigEnvironment();
        $pugSymfony = $this->getPugSymfonyEngine();

        self::assertSame(Environment::class, trim($twig->render('new-var.pug')));

        $pugSymfony->setOption('special-thing', true);

        self::assertSame('<div><p></p></div>', preg_replace('/\s/', '', $twig->render('new-var.pug')));
    }
}

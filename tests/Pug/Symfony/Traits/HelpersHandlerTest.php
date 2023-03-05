<?php

declare(strict_types=1);

namespace Pug\Tests\Symfony\Traits;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Pug\Symfony\Traits\HelpersHandler;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Twig\TwigFunction;

final class HelpersHandlerTest extends TestCase
{
    public function testHelpersHandlerTestUseContext(): void
    {
        $inspector = new class() {
            use HelpersHandler;

            public function __construct()
            {
                $this->container = new class() implements ContainerInterface {
                    private array $services;

                    public function __construct()
                    {
                        $this->services = [
                            'request_stack' => new RequestStack(),
                            'router.request_context' => RequestContext::fromUri('https://phug-lang.com/bar'),
                        ];
                    }

                    public function get(string $id)
                    {
                        return $this->services[$id];
                    }

                    public function has(string $id): bool
                    {
                        return isset($this->services[$id]);
                    }
                };
            }

            public function getUrl(): string
            {
                return $this->getHttpFoundationExtension()->generateAbsoluteUrl('/foo');
            }
        };

        self::assertSame('https://phug-lang.com/foo', $inspector->getUrl());
    }

    public function testHelpersHandlerTestUseSkipDynamicTwigFunctionNames(): void
    {
        $inspector = new class() {
            use HelpersHandler;

            public function getFunctions(): array
            {
                $this->copyTwigFunction(new TwigFunction('foo_bar'));
                $this->copyTwigFunction(new TwigFunction('render_*'));
                $this->copyTwigFunction(new TwigFunction('biz'));

                return array_keys($this->twigHelpers);
            }
        };

        self::assertSame(['foo_bar', 'biz'], $inspector->getFunctions());
    }
}

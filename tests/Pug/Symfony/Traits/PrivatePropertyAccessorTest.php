<?php

declare(strict_types=1);

namespace Pug\Tests\Symfony\Traits;

use PHPUnit\Framework\TestCase;
use Pug\Symfony\Traits\PrivatePropertyAccessor;

final class PrivatePropertyAccessorTest extends TestCase
{
    public function testPrivatePropertyAccessor(): void
    {
        $object = new class() {
            private string $foo = 'bar';
        };

        $inspector = new class($object) {
            use PrivatePropertyAccessor;

            public function __construct(private $object)
            {
            }

            public function inspect(): array
            {
                return [
                    $this->getPrivateProperty($this->object, 'foo'),
                    $this->getPrivateProperty($this->object, 'biz'),
                ];
            }
        };

        self::assertSame(['bar', null], $inspector->inspect());
    }
}

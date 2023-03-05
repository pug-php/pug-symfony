<?php

namespace Pug\Tests\Exceptions;

use Pug\Exceptions\ReservedVariable;
use Pug\Tests\AbstractTestCase;

class ReservedVariableTest extends AbstractTestCase
{
    public function testConstruct(): void
    {
        $exception = new ReservedVariable('foobar');

        self::assertSame("\"foobar\" is a reserved variable name, you can't overwrite it.", $exception->getMessage());
    }
}

<?php

namespace Pug\Tests\Exceptions;

use Jade\Exceptions\ReservedVariable;
use Pug\Tests\AbstractTestCase;

class ReservedVariableTest extends AbstractTestCase
{
    public function testConstruct()
    {
        $exception = new ReservedVariable('foobar');

        self::assertSame("foobar is a reserved variable name, you can't overwrite it.", $exception->getMessage());
    }
}

<?php

namespace Jade\Symfony\Traits;

use ReflectionProperty;

trait PrivatePropertyAccessor
{
    public static function getPrivateProperty($object, $property, &$propertyAccessor = null)
    {
        $propertyAccessor = new ReflectionProperty($object, $property);
        $propertyAccessor->setAccessible(true);

        return $propertyAccessor->getValue($object);
    }
}

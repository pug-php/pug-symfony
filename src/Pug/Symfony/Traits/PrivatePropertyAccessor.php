<?php

namespace Pug\Symfony\Traits;

use ReflectionException;
use ReflectionProperty;

trait PrivatePropertyAccessor
{
    /**
     * Expose a private/protected property.
     *
     * @param object             $object
     * @param string             $property
     * @param ReflectionProperty $propertyAccessor
     *
     * @throws ReflectionException
     *
     * @return mixed
     */
    public static function getPrivateProperty(object $object, string $property, &$propertyAccessor = null)
    {
        $propertyAccessor = new ReflectionProperty($object, $property);
        $propertyAccessor->setAccessible(true);

        return $propertyAccessor->getValue($object);
    }
}

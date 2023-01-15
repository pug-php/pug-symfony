<?php

declare(strict_types=1);

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
        try {
            $propertyAccessor = new ReflectionProperty($object, $property);

            return $propertyAccessor->getValue($object);
        } catch (ReflectionException) {
            return null;
        }
    }
}

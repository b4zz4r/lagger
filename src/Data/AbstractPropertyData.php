<?php

namespace B4zz4r\LaravelSwagger\Data;

use B4zz4r\LaravelSwagger\Concerns\PropertyDataInterface;
use ReflectionProperty;

abstract class AbstractPropertyData implements PropertyDataInterface
{
    public function __construct(public ReflectionProperty $reflectionProperty)
    {
    }

    abstract public function toArray(): array;
}

<?php

namespace B4zz4r\Lagger\Data;

use B4zz4r\Lagger\Concerns\PropertyDataInterface;
use ReflectionProperty;

abstract class AbstractPropertyData implements PropertyDataInterface
{
    public function __construct(public ReflectionProperty $reflectionProperty)
    {
    }

    abstract public function toArray(): array;
}

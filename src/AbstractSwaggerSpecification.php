<?php

namespace B4zz4r\LaravelSwagger;

use B4zz4r\LaravelSwagger\Concerns\SpecificationInterface;

abstract class AbstractSwaggerSpecification implements SpecificationInterface
{
    public function __construct(public array $additional = [], private bool $isNullable = false, private bool $isArray = false)
    {
    }

    public function isArray(): bool
    {
        return $this->isArray;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }
}

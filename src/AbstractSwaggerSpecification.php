<?php

namespace B4zz4r\LaravelSwagger;

use B4zz4r\LaravelSwagger\Concerns\SpecificationInterface;

abstract class AbstractSwaggerSpecification implements SpecificationInterface
{
    /**
     * Property for OA `description` property
     *
     * @var string
     */
    protected string $specificationDescription = '';

    public function __construct(
        private readonly bool  $isNullable = false,
        private readonly bool  $isArray = false,
        private readonly array $with = [],
    ) {}

    public function isArray(): bool
    {
        return $this->isArray;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function getDescription(): string
    {
        return $this->specificationDescription;
    }

    public function getOtherSpecifications(): array
    {
        return $this->with;
    }

    /**
     * @return array<\ReflectionProperty>
     */
    public function getProperties(): array
    {
        $reflection = new \ReflectionClass(static::class);

        return $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
    }
}

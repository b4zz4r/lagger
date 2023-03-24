<?php

namespace B4zz4r\LaravelSwagger\Concerns;

interface SpecificationInterface
{
    public function isArray(): bool;

    public function isNullable(): bool;

    public function getDescription(): string;

    public function getProperties(): array;

    /**
     * @return array<SpecificationInterface>
     */
    public function getOtherSpecifications(): array;
}

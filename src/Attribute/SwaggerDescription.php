<?php

namespace B4zz4r\LaravelSwagger\Attribute;

use Attribute;
use B4zz4r\LaravelSwagger\Interfaces\DescriptionInterface;

#[Attribute(Attribute::TARGET_METHOD)]
class SwaggerDescription implements DescriptionInterface
{
    public function __construct(public string $description)
    {
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}

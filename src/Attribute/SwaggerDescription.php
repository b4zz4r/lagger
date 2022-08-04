<?php

namespace B4zz4r\LaravelSwagger\Attribute;

use Attribute;
use B4zz4r\LaravelSwagger\Attribute\DescriptionInterface;

#[Attribute(Attribute::TARGET_METHOD)]
class SwaggerDescription implements DescriptionInterface
{
    public function __construct(public string $description,) 
    {
        $this->getDescription($description);
    }

    public function getDescription($description = "DEFAULT VALUE")
    {
        return $description;
    }
}
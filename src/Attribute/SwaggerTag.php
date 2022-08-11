<?php

namespace B4zz4r\LaravelSwagger\Attribute;

use B4zz4r\LaravelSwagger\Interfaces\TagInterface;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SwaggerTag implements TagInterface
{
    public function __construct(public string $tags) 
    {   

    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
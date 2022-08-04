<?php

namespace B4zz4r\LaravelSwagger\Attribute;

use B4zz4r\LaravelSwagger\Attribute\TagInterface;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SwaggerTag implements TagInterface
{
    public function __construct(public array $tags) 
    {   
        $this->getTags($this->tags);
    }

    public function getTags($tags): array
    {
        return $tags;
    }
}
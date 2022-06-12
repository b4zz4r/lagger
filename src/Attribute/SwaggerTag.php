<?php

namespace B4zz4r\Lagger\Attribute;

use B4zz4r\Lagger\Concerns\TagInterface;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SwaggerTag implements TagInterface
{
    public function __construct(public array $tags)
    {
        //
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}

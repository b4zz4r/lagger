<?php

namespace B4zz4r\Lagger\Attribute;

use Attribute;
use B4zz4r\Lagger\Concerns\DescriptionInterface;

#[Attribute(Attribute::TARGET_METHOD)]
class LaggerDescription implements DescriptionInterface
{
    public function __construct(public string $description)
    {
        //
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}

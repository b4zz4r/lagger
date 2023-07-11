<?php

namespace B4zz4r\Lagger\Attribute;

use Attribute;
use B4zz4r\Lagger\Concerns\DescriptionInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
class LaggerProperty implements PropertyInterface
{
    public function __construct(
        readonly public string $type
    ) {
        //
    }
}

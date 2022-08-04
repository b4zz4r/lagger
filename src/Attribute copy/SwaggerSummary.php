<?php

namespace App\Attributes;

use Attribute;

#[Attribute]
class SwaggerTag
{
    public function __construct(
        public string $myArgument2,

    ) {}
}
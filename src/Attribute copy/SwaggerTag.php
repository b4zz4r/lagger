<?php

namespace package\swagger\attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SwaggerTag
{
    public function __construct(
        public string $myArgument,

    ) {}

    // public function getMethod() 
    // {
    //     return $this->myArgument;
    // }
}
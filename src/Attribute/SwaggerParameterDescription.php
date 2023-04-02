<?php

namespace B4zz4r\LaravelSwagger\Attribute;

use Attribute;
use B4zz4r\LaravelSwagger\Concerns\DescriptionInterface;
use B4zz4r\LaravelSwagger\Concerns\ParameterInterface;
use B4zz4r\LaravelSwagger\Concerns\SummaryInterface;

#[Attribute(Attribute::TARGET_METHOD)]
class SwaggerParameterDescription
{
    public function __construct(public array $parameters)
    {
        //
    }
}

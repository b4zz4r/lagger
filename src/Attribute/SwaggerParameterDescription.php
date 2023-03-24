<?php

namespace B4zz4r\LaravelSwagger\Attribute;

use Attribute;
use B4zz4r\LaravelSwagger\Concerns\DescriptionInterface;
use B4zz4r\LaravelSwagger\Concerns\ParameterInterface;
use B4zz4r\LaravelSwagger\Concerns\SummaryInterface;

#[Attribute(Attribute::TARGET_METHOD)]
class SwaggerParameterDescription implements DescriptionInterface, ParameterInterface
{
    public function __construct(public string $parameter, public string $description)
    {
        //
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getParameter(): string
    {
        return $this->parameter;
    }
}

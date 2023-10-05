<?php

namespace B4zz4r\Lagger\Attribute;

use Attribute;
use B4zz4r\Lagger\Concerns\ParametersInterface;

#[Attribute(Attribute::TARGET_METHOD)]
class LaggerParameters implements ParametersInterface
{
    public function __construct(public array $parameters)
    {
        //
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}

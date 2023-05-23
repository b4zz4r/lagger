<?php

namespace B4zz4r\Lagger\Attribute;

use Attribute;
use B4zz4r\Lagger\Concerns\DescriptionInterface;
use B4zz4r\Lagger\Concerns\ParameterInterface;
use B4zz4r\Lagger\Concerns\SummaryInterface;

#[Attribute(Attribute::TARGET_METHOD)]
class LaggerParameterDescription
{
    public function __construct(public array $parameters)
    {
        //
    }
}

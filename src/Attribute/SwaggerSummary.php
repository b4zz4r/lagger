<?php

namespace B4zz4r\LaravelSwagger\Attribute;

use Attribute;
use B4zz4r\LaravelSwagger\Interfaces\SummaryInterface;

#[Attribute(Attribute::TARGET_METHOD)]
class SwaggerSummary implements SummaryInterface
{
    public function __construct(public string $summary)
    {
    }

    public function getSummary(): string
    {
        return $this->summary;
    }
}
